<?php
declare(strict_types=1);

namespace App\Service;

use Cake\ORM\Locator\LocatorAwareTrait;

class StatementImporter
{
    use LocatorAwareTrait;

    /**
     * Adds category_id / rule_id / dedupe_key / line_no to parsed lines.
     *
     * @param array<int, array<string, mixed>> $lines
     * @return array<int, array<string, mixed>>
     */
    public function prepare(int $userId, array $lines): array
    {
        $categorizer = $this->categorizer($userId);
        $seen = [];
        foreach ($lines as $i => &$line) {
            $base = implode('|', [$line['booked_on'], $line['label'], $line['debit_cents'], $line['credit_cents']]);
            $seen[$base] = ($seen[$base] ?? -1) + 1;
            $line['dedupe_key'] = sha1($base . '|' . $seen[$base]);
            $line['line_no'] = $i + 1;
            $rule = $categorizer->winner($line['label']);
            $line['category_id'] = $rule['category_id'] ?? null;
            $line['rule_id'] = $rule['id'] ?? null;
            $line['category_locked'] = false;
        }

        return $lines;
    }

    /**
     * @param array<string, mixed> $header statement columns
     * @param array<int, array<string, mixed>> $lines prepared lines
     * @return array{statement: \Cake\Datasource\EntityInterface, inserted: int, skipped: int}
     */
    public function save(int $userId, array $header, array $lines): array
    {
        $statements = $this->fetchTable('Statements');
        $transactions = $this->fetchTable('Transactions');

        return $statements->getConnection()->transactional(function () use ($userId, $header, $lines, $statements, $transactions) {
            $knownRows = $transactions->find()
                ->select(['dedupe_key', 'statement_id'])
                ->where(['user_id' => $userId, 'dedupe_key IN' => array_column($lines, 'dedupe_key') ?: ['']])
                ->all()
                ->toList();
            $known = array_fill_keys(array_map(fn($tx) => $tx->dedupe_key, $knownRows), true);
            $allKnown = $lines !== [];
            foreach ($lines as $line) {
                if (!isset($known[$line['dedupe_key']])) {
                    $allKnown = false;
                    break;
                }
            }
            if ($allKnown) {
                $statement = $statements->find()
                    ->where(['id' => $knownRows[0]->statement_id, 'user_id' => $userId])->firstOrFail();

                return ['statement' => $statement, 'inserted' => 0, 'skipped' => count($lines)];
            }

            $statement = $statements->saveOrFail($statements->newEntity($header + ['user_id' => $userId]));
            foreach ($lines as $line) {
                $transactions->saveOrFail($transactions->newEntity([
                    'user_id' => $userId,
                    'statement_id' => $statement->id,
                ] + array_intersect_key($line, array_flip([
                    'booked_on', 'label', 'debit_cents', 'credit_cents', 'category_id', 'rule_id', 'category_locked', 'dedupe_key', 'line_no',
                ]))));
            }

            return ['statement' => $statement, 'inserted' => count($lines), 'skipped' => 0];
        });
    }

    /**
     * Re-apply rules (idempotent). Locked lines are never touched.
     */
    public function reapply(int $userId, ?int $statementId = null): int
    {
        $transactions = $this->fetchTable('Transactions');
        $categorizer = $this->categorizer($userId);
        $query = $transactions->find()->where(['user_id' => $userId, 'category_locked' => false]);
        if ($statementId !== null) {
            $query->where(['statement_id' => $statementId]);
        }
        $changed = 0;
        foreach ($query->all() as $tx) {
            $rule = $categorizer->winner($tx->label);
            $category = $rule['category_id'] ?? null;
            $ruleId = $rule['id'] ?? null;
            if ($tx->category_id !== $category || $tx->rule_id !== $ruleId) {
                $tx->category_id = $category;
                $tx->rule_id = $ruleId;
                $transactions->saveOrFail($tx);
                $changed++;
            }
        }

        return $changed;
    }

    public function categorizer(int $userId): Categorizer
    {
        return new Categorizer(
            $this->fetchTable('Rules')->find()->where(['user_id' => $userId])->orderBy(['position' => 'ASC'])->all()
                ->map(fn($r) => ['id' => $r->id, 'keyword' => $r->keyword, 'category_id' => $r->category_id, 'position' => $r->position])
                ->toList(),
        );
    }
}
