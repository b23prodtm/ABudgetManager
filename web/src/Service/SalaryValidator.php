<?php
declare(strict_types=1);

namespace App\Service;

class SalaryValidator
{
    private const TOLERANCE = 1; // 0.01 €

    /**
     * Payslip consistency checks (warnings, never blockers).
     *
     * @param array<string, mixed> $e
     * @return array<int, string>
     */
    public static function warnings(array $e): array
    {
        $w = [];
        $has = fn(string ...$keys) => !array_filter($keys, fn($k) => ($e[$k] ?? null) === null || ($e[$k] ?? '') === '');
        $v = fn(string $k) => (int)($e[$k] ?? 0);

        if ($has('net_paid', 'net_social', 'income_tax_withheld')) {
            $expected = $v('net_social') + $v('panier') - $v('income_tax_withheld');
            if (abs($v('net_paid') - $expected) > self::TOLERANCE) {
                $w[] = sprintf('Net payé %s ≠ net social + panier − impôt (%s)', Money::format($v('net_paid')), Money::format($expected));
            }
        }
        if ($has('gross', 'base_salary')) {
            $expected = $v('base_salary') + $v('overtime25_amount') + $v('overtime_nonmajorated_amount')
                + $v('bonus_performance') + $v('bonus_tutor') + $v('bonus_inter_site') + $v('bonus_replacement')
                + ($v('paid_leave_indemnity') - $v('paid_leave_deduction'));
            if (abs($v('gross') - $expected) > self::TOLERANCE) {
                $w[] = sprintf('Brut %s ≠ somme des éléments (%s)', Money::format($v('gross')), Money::format($expected));
            }
        }

        return $w;
    }
}
