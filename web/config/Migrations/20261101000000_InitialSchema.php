<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class InitialSchema extends BaseMigration
{
    public function change(): void
    {
        $this->table('users')
            ->addColumn('email', 'string', ['limit' => 255])
            ->addColumn('password', 'string', ['limit' => 255])
            ->addColumn('created', 'datetime', ['null' => true])
            ->addColumn('modified', 'datetime', ['null' => true])
            ->addIndex(['email'], ['unique' => true])
            ->create();

        $this->table('settings')
            ->addColumn('user_id', 'integer')
            ->addColumn('key', 'string', ['limit' => 100])
            ->addColumn('value', 'string', ['limit' => 255])
            ->addColumn('created', 'datetime', ['null' => true])
            ->addColumn('modified', 'datetime', ['null' => true])
            ->addIndex(['user_id', 'key'], ['unique' => true])
            ->create();

        $this->table('categories')
            ->addColumn('user_id', 'integer')
            ->addColumn('name', 'string', ['limit' => 100])
            ->addColumn('kind', 'string', ['limit' => 20, 'default' => 'expense'])
            ->addColumn('position', 'integer', ['default' => 0])
            ->addColumn('color', 'string', ['limit' => 9, 'null' => true])
            ->addColumn('created', 'datetime', ['null' => true])
            ->addColumn('modified', 'datetime', ['null' => true])
            ->addIndex(['user_id'])
            ->create();

        $this->table('rules')
            ->addColumn('user_id', 'integer')
            ->addColumn('keyword', 'string', ['limit' => 255])
            ->addColumn('category_id', 'integer')
            ->addColumn('position', 'integer')
            ->addColumn('created', 'datetime', ['null' => true])
            ->addColumn('modified', 'datetime', ['null' => true])
            ->addIndex(['user_id', 'position'], ['unique' => true])
            ->create();

        $this->table('statements')
            ->addColumn('user_id', 'integer')
            ->addColumn('label', 'string', ['limit' => 255])
            ->addColumn('period_start', 'date', ['null' => true])
            ->addColumn('period_end', 'date', ['null' => true])
            ->addColumn('opening_balance_cents', 'integer', ['default' => 0])
            ->addColumn('overdraft_limit_cents', 'integer', ['default' => 10000])
            ->addColumn('declared_total_debits_cents', 'integer', ['null' => true])
            ->addColumn('declared_total_credits_cents', 'integer', ['null' => true])
            ->addColumn('declared_closing_balance_cents', 'integer', ['null' => true])
            ->addColumn('created', 'datetime', ['null' => true])
            ->addColumn('modified', 'datetime', ['null' => true])
            ->addIndex(['user_id'])
            ->create();

        $this->table('transactions')
            ->addColumn('user_id', 'integer')
            ->addColumn('statement_id', 'integer')
            ->addColumn('booked_on', 'date')
            ->addColumn('label', 'string', ['limit' => 500])
            ->addColumn('debit_cents', 'integer', ['default' => 0])
            ->addColumn('credit_cents', 'integer', ['default' => 0])
            ->addColumn('category_id', 'integer', ['null' => true])
            ->addColumn('rule_id', 'integer', ['null' => true])
            ->addColumn('category_locked', 'boolean', ['default' => false])
            ->addColumn('dedupe_key', 'string', ['limit' => 40])
            ->addColumn('line_no', 'integer', ['default' => 0])
            ->addColumn('created', 'datetime', ['null' => true])
            ->addColumn('modified', 'datetime', ['null' => true])
            ->addIndex(['user_id', 'dedupe_key'], ['unique' => true])
            ->addIndex(['statement_id'])
            ->create();

        $this->table('salary_entries')
            ->addColumn('user_id', 'integer')
            ->addColumn('month', 'date')
            ->addColumn('net_paid', 'integer')
            ->addColumn('net_taxable', 'integer', ['null' => true])
            ->addColumn('gross', 'integer', ['null' => true])
            ->addColumn('net_social', 'integer', ['null' => true])
            ->addColumn('panier', 'integer', ['null' => true])
            ->addColumn('income_tax_withheld', 'integer', ['null' => true])
            ->addColumn('base_salary', 'integer', ['null' => true])
            ->addColumn('overtime25_amount', 'integer', ['null' => true])
            ->addColumn('overtime25_hours', 'decimal', ['precision' => 8, 'scale' => 2, 'null' => true])
            ->addColumn('overtime_nonmajorated_amount', 'integer', ['null' => true])
            ->addColumn('bonus_performance', 'integer', ['null' => true])
            ->addColumn('bonus_tutor', 'integer', ['null' => true])
            ->addColumn('bonus_inter_site', 'integer', ['null' => true])
            ->addColumn('bonus_replacement', 'integer', ['null' => true])
            ->addColumn('paid_leave_indemnity', 'integer', ['null' => true])
            ->addColumn('paid_leave_deduction', 'integer', ['null' => true])
            ->addColumn('hours_worked', 'decimal', ['precision' => 8, 'scale' => 2, 'null' => true])
            ->addColumn('notes', 'text', ['null' => true])
            ->addColumn('created', 'datetime', ['null' => true])
            ->addColumn('modified', 'datetime', ['null' => true])
            ->addIndex(['user_id', 'month'], ['unique' => true])
            ->create();

        // Drafts produced by the upload step, waiting for user validation.
        // Only extracted data is kept: uploaded files are never stored.
        $this->table('imports')
            ->addColumn('user_id', 'integer')
            ->addColumn('month', 'date')
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'draft'])
            ->addColumn('payload', 'text')
            ->addColumn('created', 'datetime', ['null' => true])
            ->addColumn('modified', 'datetime', ['null' => true])
            ->addIndex(['user_id'])
            ->create();
    }
}
