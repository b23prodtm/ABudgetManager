<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Money helpers: amounts are integer cents, never floats.
 */
final class Money
{
    /**
     * Parse a human amount ("3 057,42", "1.234,56", "-12.5", "12,50 €") into cents.
     * Returns null when the text is empty or not a number.
     */
    public static function parse(?string $text): ?int
    {
        if ($text === null) {
            return null;
        }
        $t = str_replace(["\u{00A0}", "\u{202F}", ' ', '€', 'EUR'], '', trim($text));
        $t = str_replace('−', '-', $t);
        if ($t === '') {
            return null;
        }
        if (str_contains($t, ',')) {
            $t = str_replace('.', '', $t);
            $t = str_replace(',', '.', $t);
        }
        if (!preg_match('/^([+-]?)(\d*)(?:\.(\d*))?$/', $t, $m) || ($m[2] === '' && ($m[3] ?? '') === '')) {
            return null;
        }
        $frac = str_pad(substr($m[3] ?? '', 0, 3), 3, '0');
        $cents = (int)$m[2] * 100 + (int)substr($frac, 0, 2);
        if ((int)$frac[2] >= 5) {
            $cents++;
        }

        return $m[1] === '-' ? -$cents : $cents;
    }

    /**
     * Format cents as French currency, e.g. 305742 => "3 057,42 €".
     */
    public static function format(?int $cents): string
    {
        $cents ??= 0;
        $sign = $cents < 0 ? '-' : '';
        $abs = abs($cents);

        return $sign . number_format(intdiv($abs, 100), 0, '', "\u{202F}") . ',' . sprintf('%02d', $abs % 100) . "\u{00A0}€";
    }
}
