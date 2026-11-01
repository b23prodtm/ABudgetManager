<?php
declare(strict_types=1);

namespace App\Service;

use DateTimeImmutable;

/**
 * Salary statistics: running/moving averages, least-squares trend, forecast.
 * x is the calendar month index (1 = first month of the range).
 */
class SalaryTrend
{
    /**
     * @param array<int, array{month: string, net_paid: int}> $entries
     * @return array<string, mixed>
     */
    public static function compute(array $entries, int $window = 3, int $horizon = 3): array
    {
        usort($entries, fn($a, $b) => strcmp($a['month'], $b['month']));
        $n = count($entries);
        $result = ['n' => $n, 'rows' => [], 'forecast' => [], 'slope' => null, 'intercept' => null,
            'r2' => null, 'rmse' => null, 'mean' => null, 'unreliable' => $n < 6];
        if ($n === 0) {
            return $result;
        }
        $first = self::monthIndex($entries[0]['month']);
        $xs = [];
        $ys = [];
        foreach ($entries as $e) {
            $xs[] = self::monthIndex($e['month']) - $first + 1;
            $ys[] = (float)$e['net_paid'];
        }
        $mean = array_sum($ys) / $n;
        $result['mean'] = $mean;

        $slope = null;
        $intercept = null;
        if ($n >= 2) {
            $mx = array_sum($xs) / $n;
            $sxy = 0.0;
            $sxx = 0.0;
            foreach ($xs as $i => $x) {
                $sxy += ($x - $mx) * ($ys[$i] - $mean);
                $sxx += ($x - $mx) ** 2;
            }
            if ($sxx > 0) {
                $slope = $sxy / $sxx;
                $intercept = $mean - $slope * $mx;
            }
        }
        $result['slope'] = $slope;
        $result['intercept'] = $intercept;

        $sum = 0.0;
        $sse = 0.0;
        $sst = 0.0;
        foreach ($entries as $i => $e) {
            $sum += $ys[$i];
            $slice = array_slice($ys, max(0, $i - $window + 1), min($window, $i + 1));
            $trend = $slope === null ? null : $slope * $xs[$i] + $intercept;
            if ($trend !== null) {
                $sse += ($ys[$i] - $trend) ** 2;
            }
            $sst += ($ys[$i] - $mean) ** 2;
            $result['rows'][] = [
                'month' => $e['month'],
                'x' => $xs[$i],
                'net_paid' => (int)$e['net_paid'],
                'sx' => (int)round($sum),
                'running_average' => $sum / ($i + 1),
                'moving_average' => array_sum($slice) / count($slice),
                'trend' => $trend,
                'deviation' => $ys[$i] - $mean,
                'mom' => $i > 0 && $ys[$i - 1] != 0 ? ($ys[$i] - $ys[$i - 1]) / $ys[$i - 1] : null,
            ];
        }
        if ($slope !== null) {
            $result['r2'] = $sst > 0 ? 1 - $sse / $sst : 1.0;
            $result['rmse'] = sqrt($sse / $n);
            $last = new DateTimeImmutable($entries[$n - 1]['month']);
            for ($k = 1; $k <= $horizon; $k++) {
                $x = $xs[$n - 1] + $k;
                $result['forecast'][] = [
                    'month' => $last->modify("+$k month")->format('Y-m-01'),
                    'x' => $x,
                    'value' => $slope * $x + $intercept,
                ];
            }
        }

        return $result;
    }

    private static function monthIndex(string $month): int
    {
        $d = new DateTimeImmutable($month);

        return (int)$d->format('Y') * 12 + (int)$d->format('n');
    }
}
