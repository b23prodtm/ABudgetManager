<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\Categorizer;
use App\Service\Money;
use App\Service\StatementParser;
use App\Service\StatementTotals;
use Cake\TestSuite\TestCase;

class StatementServicesTest extends TestCase
{
    public function testMoney(): void
    {
        $this->assertSame(305742, Money::parse('3 057,42'));
        $this->assertSame(123456, Money::parse('1.234,56'));
        $this->assertSame(-1250, Money::parse('-12.5'));
        $this->assertNull(Money::parse(''));
        $this->assertNull(Money::parse('abc'));
        $this->assertSame("3\u{202F}057,42\u{00A0}€", Money::format(305742));
    }

    public function testRulesLaterWins(): void
    {
        $c = new Categorizer([
            ['id' => 1, 'keyword' => 'VIR SEPA RECU', 'category_id' => 10, 'position' => 6],
            ['id' => 2, 'keyword' => 'REGIE D EAU', 'category_id' => 20, 'position' => 31],
            ['id' => 3, 'keyword' => 'CRV*TOTALENERGI', 'category_id' => 30, 'position' => 24],
            ['id' => 4, 'keyword' => 'LIDL', 'category_id' => 40, 'position' => 10],
        ]);
        $this->assertSame(20, $c->winner('VIR SEPA RECU REGIE D EAU')['category_id']);
        $this->assertCount(2, $c->matches('vir  sepa recu régie d eau'));
        $this->assertSame(30, $c->winner('CRV TOTALENERGIES 0123')['category_id']);
        $this->assertSame(40, $c->winner('Carte LÏDL grenoble')['category_id']);
        $this->assertNull($c->winner('UNKNOWN SHOP'));
        $this->assertTrue(Categorizer::keywordMatches('MA?IF', 'PRLV macif'));
    }

    public function testParserBnpStyleAndSignedAmount(): void
    {
        $p = new StatementParser();
        $r = $p->parse("Date;Libellé;Débit;Crédit\n02/05/2026;CARTE LIDL;45,10;\n03/05/2026;VIR SEPA RECU ACME;;2 100,00\n");
        $this->assertSame([], $r['errors']);
        $this->assertSame(
            [['booked_on' => '2026-05-02', 'label' => 'CARTE LIDL', 'debit_cents' => 4510, 'credit_cents' => 0],
             ['booked_on' => '2026-05-03', 'label' => 'VIR SEPA RECU ACME', 'debit_cents' => 0, 'credit_cents' => 210000]],
            $r['lines'],
        );
        $r = $p->parse("2026-05-02\tCARTE LIDL\t-45,10\n2026-05-04\tREMB\t10,00");
        $this->assertSame(4510, $r['lines'][0]['debit_cents']);
        $this->assertSame(1000, $r['lines'][1]['credit_cents']);
    }

    public function testTotalsAndControls(): void
    {
        $t = StatementTotals::compute(100000, [
            ['debit_cents' => 304942, 'credit_cents' => 0],
            ['debit_cents' => 0, 'credit_cents' => 250000],
        ]);
        $this->assertSame(['debits' => 304942, 'credits' => 250000, 'closing' => 45058], $t);
        $this->assertSame('green', StatementTotals::balanceLevel(0, 10000));
        $this->assertSame('orange', StatementTotals::balanceLevel(-10000, 10000));
        $this->assertSame('red', StatementTotals::balanceLevel(-10001, 10000));
        $msgs = StatementTotals::controlMismatches(['debits' => 305742, 'credits' => 250000, 'closing' => null], $t);
        $this->assertCount(1, $msgs);
        $this->assertStringContainsString('8,00', $msgs[0]);
        $this->assertStringContainsString('manquants', $msgs[0]);
    }
}
