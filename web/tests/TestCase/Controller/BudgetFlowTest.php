<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use DateTimeImmutable;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class BudgetFlowTest extends TestCase
{
    use IntegrationTestTrait;
    use LocatorAwareTrait;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['users', 'settings', 'categories', 'rules', 'statements', 'transactions', 'salary_entries', 'imports'] as $t) {
            $this->getTableLocator()->get('Users')->getConnection()->execute("DELETE FROM $t");
        }
        $this->enableCsrfToken();
        $this->enableSecurityToken();
    }

    private function signup(string $email = 'ada@example.test'): void
    {
        $this->post('/signup', ['email' => $email, 'password' => 'secret-pass-1', 'password_confirm' => 'secret-pass-1']);
        $user = $this->fetchTable('Users')->find()->where(['email' => $email])->firstOrFail();
        $this->session(['Auth' => ['id' => $user->id, 'email' => $email]]);
    }

    /**
     * @return array<int, array<string, string|int>>
     */
    private function initialReviewLines(): array
    {
        $categoryIds = array_flip($this->fetchTable('Categories')->find('list')->toArray());

        return [
            ['booked_on' => '02/05/2026', 'label' => 'CARTE LIDL GRENOBLE', 'debit' => '45,10', 'credit' => '', 'category_id' => $categoryIds['Courses']],
            ['booked_on' => '03/05/2026', 'label' => 'VIR SEPA RECU REGIE D EAU', 'debit' => '', 'credit' => '12,00', 'category_id' => $categoryIds['Logement & énergie']],
            ['booked_on' => '04/05/2026', 'label' => 'MYSTERY SHOP', 'debit' => '20,00', 'credit' => '', 'category_id' => ''],
            ['booked_on' => '05/05/2026', 'label' => 'VIR SEPA RECU ACME SA', 'debit' => '', 'credit' => '1 500,00', 'category_id' => $categoryIds['Salaire']],
        ];
    }

    public function testGuestsAreRedirectedToLogin(): void
    {
        foreach (['/dashboard', '/uploads/new', '/salary', '/rules', '/statements'] as $url) {
            $this->get($url);
            $this->assertRedirectContains('/login');
        }
        $this->get('/');
        $this->assertResponseOk();
        $this->assertResponseContains('Créer un compte');
        $this->get('/health');
        $this->assertResponseOk();
    }

    public function testSignupSeedsDataAndLogin(): void
    {
        $this->signup();
        $this->assertRedirectContains('/uploads/new');
        $this->assertSame(46, $this->fetchTable('Rules')->find()->count());
        $this->assertSame(16, $this->fetchTable('Categories')->find()->count());

        $this->post('/logout');
        $this->post('/login', ['email' => 'ada@example.test', 'password' => 'wrong']);
        $this->assertResponseContains('incorrect');
        $this->post('/login', ['email' => 'ADA@example.test', 'password' => 'secret-pass-1']);
        $this->assertRedirectContains('/uploads/new');
    }

    public function testUploadReviewValidateAndDashboard(): void
    {
        $this->signup();
        $csv = "Date;Libellé;Débit;Crédit\n02/05/2026;CARTE LIDL GRENOBLE;45,10;\n03/05/2026;VIR SEPA RECU REGIE D EAU;;12,00\n04/05/2026;MYSTERY SHOP;20,00;\n05/05/2026;VIR SEPA RECU ACME SA;;1 500,00\n";
        $this->post('/uploads/new', ['month' => '2026-05', 'statement_text' => $csv, 'opening_balance' => '100,00', 'declared_debits' => '70,00']);
        $import = $this->fetchTable('Imports')->find()->firstOrFail();
        $this->assertRedirectContains('/uploads/' . $import->id . '/review');

        $this->get('/uploads/' . $import->id . '/review');
        $this->assertResponseOk();
        $this->assertResponseContains('CARTE LIDL GRENOBLE');
        $this->assertResponseContains('aria-label="Ligne 1 — Date"');
        $this->assertResponseContains('aria-label="Ligne 1 — Retirer"');
        $this->assertResponseContains('1 à classer');
        $this->assertResponseContains('Total des débits');

        $this->post('/uploads/' . $import->id . '/review', [
            'validate' => '1',
            'lines' => $this->initialReviewLines(),
            'salary' => ['net_paid' => '1 500,00', 'net_social' => '1 500,00', 'income_tax_withheld' => '', 'panier' => ''],
        ]);
        $this->assertRedirectContains('/dashboard');

        $txs = $this->fetchTable('Transactions')->find()->orderBy(['line_no' => 'ASC'])->all();
        $this->assertCount(4, $txs);
        $cats = $this->fetchTable('Categories')->find('list')->toArray();
        $this->assertSame('Courses', $cats[$txs->first()->category_id]);
        $this->assertSame('Logement & énergie', $cats[$txs->skip(1)->first()->category_id]);
        $this->assertNull($txs->skip(2)->first()->category_id);
        $this->assertSame('Salaire', $cats[$txs->last()->category_id]);
        $this->assertSame(150000, $this->fetchTable('SalaryEntries')->find()->firstOrFail()->net_paid);

        $this->get('/dashboard');
        $this->assertResponseOk();
        $this->assertResponseContains('Salaire reçu');
        $this->assertResponseContains('correspond au bulletin');
        $this->assertResponseContains('ligne(s) du relevé à classer');

        // overlapping re-import skips known lines
        $this->post('/uploads/new', ['month' => '2026-05', 'statement_text' => $csv]);
        $second = $this->fetchTable('Imports')->find()->where(['status' => 'draft'])->firstOrFail();
        $this->post('/uploads/' . $second->id . '/review', ['validate' => '1', 'lines' => $this->initialReviewLines()]);
        $this->assertCount(4, $this->fetchTable('Transactions')->find()->all());
        $this->assertCount(1, $this->fetchTable('Statements')->find()->all());

        $categoryIds = array_flip($this->fetchTable('Categories')->find('list')->toArray());
        $this->post('/uploads/new', [
            'month' => '2026-05',
            'statement_text' => "02/05/2026;CARTE LIDL GRENOBLE;45,10;\n06/05/2026;CARTE LIDL NOUVELLE;10,00;\n",
        ]);
        $partial = $this->fetchTable('Imports')->find()->where(['status' => 'draft'])->orderBy(['id' => 'DESC'])->firstOrFail();
        $this->post('/uploads/' . $partial->id . '/review', [
            'validate' => '1',
            'lines' => [
                ['booked_on' => '02/05/2026', 'label' => 'CARTE LIDL GRENOBLE', 'debit' => '45,10', 'credit' => '', 'category_id' => $categoryIds['Courses']],
                ['booked_on' => '06/05/2026', 'label' => 'CARTE LIDL NOUVELLE', 'debit' => '10,00', 'credit' => '', 'category_id' => $categoryIds['Courses']],
            ],
        ]);
        $this->assertCount(6, $this->fetchTable('Transactions')->find()->all());
        $this->assertCount(2, $this->fetchTable('Statements')->find()->all());
        $partialStatement = $this->fetchTable('Statements')->find()->orderBy(['id' => 'DESC'])->firstOrFail();
        $this->assertCount(2, $this->fetchTable('Transactions')->find()->where(['statement_id' => $partialStatement->id])->all());
        $userId = (int)$this->fetchTable('Users')->find()->select(['id'])->firstOrFail()->id;
        $summary = (new \App\Service\DashboardBuilder())->build($userId, $partialStatement);
        $this->assertSame(5510, $summary['totals']['debits']);
    }

    public function testIncompleteReviewSubmissionKeepsDraftLines(): void
    {
        $this->signup();
        $csv = "02/05/2026;SHOP ONE;10,00;\n03/05/2026;SHOP TWO;20,00;\n";
        $this->post('/uploads/new', ['month' => '2026-05', 'statement_text' => $csv]);
        $import = $this->fetchTable('Imports')->find()->firstOrFail();
        $original = $import->payload;

        $this->post('/uploads/' . $import->id . '/review', [
            'validate' => '1',
            'lines' => [
                ['booked_on' => '02/05/2026', 'label' => 'SHOP ONE', 'debit' => '10,00', 'credit' => '', 'category_id' => ''],
            ],
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains('Le formulaire est incomplet');
        $import = $this->fetchTable('Imports')->get($import->id);
        $this->assertSame('draft', $import->status);
        $this->assertSame($original, $import->payload);
    }

    public function testDataIsScopedPerUser(): void
    {
        $this->signup('a@example.test');
        $this->post('/uploads/new', ['month' => '2026-05', 'statement_text' => "02/05/2026;LIDL;10,00;\n"]);
        $import = $this->fetchTable('Imports')->find()->firstOrFail();
        $this->post('/logout');
        $this->_session = [];
        $this->signup('b@example.test');
        $this->get('/uploads/' . $import->id . '/review');
        $this->assertResponseCode(404);
    }

    public function testSalaryEditAndTrend(): void
    {
        $this->signup();
        foreach ([1000, 1100, 1050] as $i => $euros) {
            $this->post('/salary/edit', ['month' => sprintf('2026-%02d', $i + 1), 'net_paid' => (string)$euros]);
        }
        $this->get('/salary');
        $this->assertResponseOk();
        $this->assertResponseContains('Tendance peu fiable');
        $this->get('/salary/export');
        $this->assertResponseOk();
    }

    public function testTwelveMonthSalaryRangeUsesCalendarCutoff(): void
    {
        $this->signup();
        $entries = $this->fetchTable('SalaryEntries');
        $userId = (int)$this->fetchTable('Users')->find()->select(['id'])->firstOrFail()->id;
        $currentMonth = new DateTimeImmutable('first day of this month');
        for ($offset = 24; $offset >= 13; $offset--) {
            $month = $currentMonth->modify("-$offset months")->format('Y-m-01');
            $entries->saveOrFail($entries->newEntity(['user_id' => $userId, 'month' => $month, 'net_paid' => 100000]));
        }
        $recent = $currentMonth->modify('-11 months')->format('Y-m-01');
        $entries->saveOrFail($entries->newEntity(['user_id' => $userId, 'month' => $recent, 'net_paid' => 120000]));

        $this->get('/salary?range=12');
        $this->assertResponseOk();
        $this->assertResponseContains((new DateTimeImmutable($recent))->format('m/Y'));
        $this->assertResponseNotContains($currentMonth->modify('-24 months')->format('m/Y'));
    }
}
