<?php
declare(strict_types=1);

namespace App\Service;

use Cake\ORM\Locator\LocatorAwareTrait;

/**
 * Computes the numbers shown on the user homepage for one statement.
 */
class DashboardBuilder
{
    use LocatorAwareTrait;

    /**
     * @return array<string, mixed>
     */
    public function build(int $userId, \Cake\Datasource\EntityInterface $statement): array
    {
        $txs = $this->fetchTable('Transactions')->find()
            ->where(['user_id' => $userId, 'statement_id' => $statement->id])
            ->orderBy(['booked_on' => 'ASC', 'line_no' => 'ASC'])->all()->toList();
        $categories = $this->fetchTable('Categories')->find()
            ->where(['user_id' => $userId])->orderBy(['position' => 'ASC'])->all()->indexBy('id')->toArray();

        $totals = StatementTotals::compute((int)$statement->opening_balance_cents, $txs);
        $perCategory = [];
        foreach ($categories as $id => $c) {
            $perCategory[$id] = ['category' => $c, 'net' => 0, 'count' => 0];
        }
        $unclassified = ['net' => 0, 'count' => 0, 'lines' => []];
        foreach ($txs as $t) {
            $net = $t->debit_cents - $t->credit_cents;
            if ($t->category_id === null || !isset($perCategory[$t->category_id])) {
                $unclassified['net'] += $net;
                $unclassified['count']++;
                $unclassified['lines'][] = $t;
                continue;
            }
            $perCategory[$t->category_id]['net'] += $net;
            $perCategory[$t->category_id]['count']++;
        }
        $expenseRows = [];
        $internal = 0;
        $salaryReceived = 0;
        $totalExpenses = $unclassified['net'];
        foreach ($perCategory as $row) {
            match ($row['category']->kind) {
                'expense' => $expenseRows[] = $row,
                'internal' => $internal += $row['net'],
                'income' => $salaryReceived += -$row['net'],
                default => null,
            };
            if ($row['category']->kind === 'expense') {
                $totalExpenses += $row['net'];
            }
        }
        $expenseRows = array_values(array_filter($expenseRows, fn($r) => $r['count'] > 0));
        usort($expenseRows, fn($a, $b) => $b['net'] <=> $a['net']);

        $settings = $this->settings($userId);
        $months = max(1, (int)($settings['income_average_months'] ?? 6));
        $salaries = $this->fetchTable('SalaryEntries')->find()
            ->where(['user_id' => $userId])->orderBy(['month' => 'DESC'])->all()->toList();
        $recent = array_slice($salaries, 0, $months);
        $netIncome = $recent ? (int)round(array_sum(array_map(fn($s) => $s->net_paid, $recent)) / count($recent)) : null;

        $level = StatementTotals::balanceLevel($totals['closing'], (int)$statement->overdraft_limit_cents);
        $controlMessages = StatementTotals::controlMismatches([
            'debits' => $statement->declared_total_debits_cents,
            'credits' => $statement->declared_total_credits_cents,
            'closing' => $statement->declared_closing_balance_cents,
        ], $totals);
        $remaining = $netIncome === null ? null : $netIncome - $totalExpenses;

        $alerts = [];
        $alerts[] = match ($level) {
            'green' => ['info', 'Solde final positif : ' . Money::format($totals['closing'])],
            'orange' => ['warning', 'Solde final ' . Money::format($totals['closing']) . ' : dans le découvert autorisé'],
            default => ['danger', 'Solde final ' . Money::format($totals['closing']) . ' : au-delà du découvert autorisé'],
        };
        foreach ($controlMessages as $m) {
            $alerts[] = ['danger', $m];
        }
        if ($remaining !== null && $remaining < 0) {
            $alerts[] = ['danger', 'Reste à vivre négatif : ' . Money::format($remaining)];
        }
        if ($unclassified['count'] > 0) {
            $alerts[] = ['warning', sprintf('%d ligne(s) du relevé à classer : ajoutez un mot-clé dans Règles', $unclassified['count'])];
        }
        foreach ($expenseRows as $r) {
            if ($r['category']->name === 'Abonnements & achats en ligne') {
                $alerts[] = ['info', sprintf('Abonnements & achats en ligne : %s (%d ligne(s))', Money::format($r['net']), $r['count'])];
            }
        }
        $alerts[] = $this->salaryCrossCheck($txs, $categories, $salaries);

        return compact('txs', 'totals', 'level', 'controlMessages', 'expenseRows', 'unclassified', 'internal') + [
            'salaryReceived' => $salaryReceived,
            'totalExpenses' => $totalExpenses,
            'netIncome' => $netIncome,
            'remaining' => $remaining,
            'alerts' => array_values(array_filter($alerts)),
        ];
    }

    /**
     * @param array<int, mixed> $txs
     * @param array<int, mixed> $categories
     * @param array<int, mixed> $salaries
     * @return array{0: string, 1: string}|null
     */
    private function salaryCrossCheck(array $txs, array $categories, array $salaries): ?array
    {
        foreach ($txs as $t) {
            $c = $categories[$t->category_id] ?? null;
            if ($c === null || $c->kind !== 'income' || $t->credit_cents <= 0) {
                continue;
            }
            foreach ($salaries as $s) {
                if (abs($s->net_paid - $t->credit_cents) <= 1) {
                    return ['info', '✓ Salaire reçu correspond au bulletin de ' . $s->month->i18nFormat('MMMM y', null, 'fr_FR')];
                }
            }

            return ['warning', '⚠ Aucun bulletin ne correspond au salaire reçu (' . Money::format($t->credit_cents) . ')'];
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    public function settings(int $userId): array
    {
        return $this->fetchTable('Settings')->find('list', keyField: 'key', valueField: 'value')
            ->where(['user_id' => $userId])->toArray();
    }
}
