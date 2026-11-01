<?php
declare(strict_types=1);

namespace App\Service;

class StatementTotals
{
    /**
     * @param iterable<array{debit_cents: int, credit_cents: int}> $lines
     * @return array{debits: int, credits: int, closing: int}
     */
    public static function compute(int $openingCents, iterable $lines): array
    {
        $debits = 0;
        $credits = 0;
        foreach ($lines as $line) {
            $debits += (int)$line['debit_cents'];
            $credits += (int)$line['credit_cents'];
        }

        return ['debits' => $debits, 'credits' => $credits, 'closing' => $openingCents - $debits + $credits];
    }

    /**
     * green: closing >= 0, orange: inside authorised overdraft, red: beyond.
     */
    public static function balanceLevel(int $closingCents, int $overdraftLimitCents): string
    {
        if ($closingCents >= 0) {
            return 'green';
        }

        return $closingCents >= -$overdraftLimitCents ? 'orange' : 'red';
    }

    /**
     * Compare declared control values with computed ones.
     *
     * @param array<string, int|null> $declared keys: debits, credits, closing
     * @param array{debits: int, credits: int, closing: int} $computed
     * @return array<int, string> French messages, empty when everything matches
     */
    public static function controlMismatches(array $declared, array $computed): array
    {
        $labels = ['debits' => 'Total des débits', 'credits' => 'Total des crédits', 'closing' => 'Solde final'];
        $messages = [];
        foreach ($labels as $key => $label) {
            $d = $declared[$key] ?? null;
            if ($d === null || $d === $computed[$key]) {
                continue;
            }
            $diff = $d - $computed[$key];
            $word = $key === 'closing'
                ? ($diff > 0 ? 'en trop dans le solde déclaré' : 'en moins dans le solde déclaré')
                : ($diff > 0 ? 'manquants' : 'en trop');
            $messages[] = sprintf(
                '%s : %s déclarés, %s calculés → %s %s',
                $label,
                Money::format($d),
                Money::format($computed[$key]),
                Money::format(abs($diff)),
                $word,
            );
        }

        return $messages;
    }
}
