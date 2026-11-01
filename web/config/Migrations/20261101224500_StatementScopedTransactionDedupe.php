<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class StatementScopedTransactionDedupe extends BaseMigration
{
    public function up(): void
    {
        $this->table('transactions')
            ->removeIndex(['user_id', 'dedupe_key'])
            ->addIndex(['user_id', 'statement_id', 'dedupe_key'], ['unique' => true])
            ->update();
    }

    public function down(): void
    {
        $this->table('transactions')
            ->removeIndex(['user_id', 'statement_id', 'dedupe_key'])
            ->addIndex(['user_id', 'dedupe_key'], ['unique' => true])
            ->update();
    }
}
