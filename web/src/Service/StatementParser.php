<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Parses pasted text or CSV (tab or semicolon separated):
 * date, label, debit, credit  — or date, label, signed amount.
 */
class StatementParser
{
    /**
     * @return array{lines: array<int, array{booked_on: string, label: string, debit_cents: int, credit_cents: int}>, errors: array<int, string>}
     */
    public function parse(string $text): array
    {
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text;
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
        }
        $lines = [];
        $errors = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $n => $raw) {
            if (trim($raw) === '') {
                continue;
            }
            $delimiter = str_contains($raw, "\t") ? "\t" : ';';
            $cells = array_map('trim', str_getcsv($raw, $delimiter, '"', ''));
            $date = self::parseDate($cells[0] ?? '');
            if ($date === null) {
                if ($lines !== [] || $n > 0) {
                    $errors[] = sprintf('Ligne %d ignorée : date illisible', $n + 1);
                }
                continue;
            }
            $label = (string)($cells[1] ?? '');
            $count = count($cells);
            if ($count >= 4) {
                $debit = Money::parse($cells[2]);
                $credit = Money::parse($cells[3]);
                if ((trim($cells[2]) !== '' && $debit === null) || (trim($cells[3]) !== '' && $credit === null)) {
                    $errors[] = sprintf('Ligne %d ignorée : montant illisible', $n + 1);
                    continue;
                }
                $debit = $debit === null ? 0 : abs($debit);
                $credit = $credit === null ? 0 : abs($credit);
            } elseif ($count === 3 && ($amount = Money::parse($cells[2])) !== null) {
                $debit = $amount < 0 ? -$amount : 0;
                $credit = $amount > 0 ? $amount : 0;
            } else {
                $errors[] = sprintf('Ligne %d ignorée : montant illisible', $n + 1);
                continue;
            }
            $lines[] = [
                'booked_on' => $date,
                'label' => $label,
                'debit_cents' => $debit,
                'credit_cents' => $credit,
            ];
        }

        return ['lines' => $lines, 'errors' => $errors];
    }

    public static function parseDate(string $text): ?string
    {
        $text = trim($text);
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $text, $m)) {
            [$d, $mo, $y] = [(int)$m[1], (int)$m[2], (int)$m[3]];
        } elseif (preg_match('#^(\d{4})-(\d{2})-(\d{2})$#', $text, $m)) {
            [$y, $mo, $d] = [(int)$m[1], (int)$m[2], (int)$m[3]];
        } else {
            return null;
        }

        return checkdate($mo, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : null;
    }
}
