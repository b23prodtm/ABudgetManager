<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

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
        $this->assertResponseContains('1 à classer');
        $this->assertResponseContains('Total des débits');

        $this->post('/uploads/' . $import->id . '/review', [
            'validate' => '1',
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
        $this->post('/uploads/' . $second->id . '/review', ['validate' => '1']);
        $this->assertCount(4, $this->fetchTable('Transactions')->find()->all());
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
}
