<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\SalaryTrend;
use App\Service\SalaryValidator;
use Cake\TestSuite\TestCase;

class SalaryTrendTest extends TestCase
{
    public function testGoldenSeries(): void
    {
        $entries = [];
        foreach ([1000, 1100, 1050, 1200, 1250, 1300] as $i => $euros) {
            $entries[] = ['month' => sprintf('2026-%02d-01', $i + 1), 'net_paid' => $euros * 100];
        }
        $r = SalaryTrend::compute($entries, 3, 3);

        $this->assertSame(6, $r['n']);
        $this->assertEqualsWithDelta(6000.0, $r['slope'], 0.001);
        $this->assertEqualsWithDelta(94000.0, $r['intercept'], 0.001);
        $this->assertEqualsWithDelta(136000.0, $r['forecast'][0]['value'], 0.001);
        $this->assertSame('2026-07-01', $r['forecast'][0]['month']);
        $this->assertEqualsWithDelta(105000.0, $r['rows'][2]['moving_average'], 0.001);
        $this->assertEqualsWithDelta(125000.0, $r['rows'][5]['moving_average'], 0.001);
        $this->assertEqualsWithDelta(115000.0, $r['rows'][5]['running_average'], 0.001);
        $this->assertEqualsWithDelta(105000.0, $r['rows'][1]['moving_average'], 0.001);
        $this->assertFalse($r['unreliable']);
    }

    public function testMissingMonthKeepsCalendarIndex(): void
    {
        $r = SalaryTrend::compute([
            ['month' => '2026-01-01', 'net_paid' => 100000],
            ['month' => '2026-03-01', 'net_paid' => 120000],
        ]);
        $this->assertSame([1, 3], array_column($r['rows'], 'x'));
        $this->assertEqualsWithDelta(10000.0, $r['slope'], 0.001);
        $this->assertTrue($r['unreliable']);
    }

    public function testValidator(): void
    {
        $this->assertSame([], SalaryValidator::warnings(['net_paid' => 150000]));
        $this->assertSame([], SalaryValidator::warnings(
            ['net_paid' => 149000, 'net_social' => 150000, 'panier' => 1000, 'income_tax_withheld' => 2000],
        ));
        $this->assertCount(1, SalaryValidator::warnings(
            ['net_paid' => 149000, 'net_social' => 150000, 'panier' => 0, 'income_tax_withheld' => 2000],
        ));
    }
}
