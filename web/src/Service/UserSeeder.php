<?php
declare(strict_types=1);

namespace App\Service;

use Cake\ORM\Locator\LocatorAwareTrait;

/**
 * Seeds categories, ordered rules and settings for a new user (spec §2.4, §2.5, §6).
 */
class UserSeeder
{
    use LocatorAwareTrait;

    public const CATEGORIES = [
        'expense' => [
            'Courses', 'Restauration & distributeurs', 'Abonnements & achats en ligne', 'Transport',
            'Crédits & location', 'Logement & énergie', 'Assurances & épargne', 'Télécom', 'Impôts',
            'Transferts (Wise)', 'Frais bancaires', 'Retraits espèces', 'Achats / Divers', 'Achats / Loisirs',
        ],
        'internal' => ['Virements internes'],
        'income' => ['Salaire'],
    ];

    /** Ordered: later wins. */
    public const RULES = [
        ['PAYPAL', 'Abonnements & achats en ligne'], ['ITUNES', 'Abonnements & achats en ligne'],
        ['GOOGLE', 'Abonnements & achats en ligne'], ['KLARNA', 'Abonnements & achats en ligne'],
        ['WISE', 'Transferts (Wise)'], ['VIR SEPA RECU', 'Salaire'],
        ['VIR SEPA INSTANT EMIS', 'Virements internes'], ['VIR SEPA INSTANT RECU', 'Virements internes'],
        ['AUCHAN', 'Courses'], ['LIDL', 'Courses'], ['CARREFOUR', 'Courses'], ['LECLERC', 'Courses'],
        ['GRAND FRAIS', 'Courses'], ['MONOPRIX', 'Courses'],
        ['CERISE & POTIRO', 'Restauration & distributeurs'], ['MAXICOFFEE', 'Restauration & distributeurs'],
        ['VENDING', 'Restauration & distributeurs'], ['3B LYON', 'Restauration & distributeurs'],
        ['LA MIRA', 'Restauration & distributeurs'],
        ['FULLI', 'Transport'], ['AUTOROUTES DU SUD', 'Transport'], ['MOBILIZE', 'Transport'],
        ['IZIVIA', 'Transport'], ['CRV*TOTALENERGI', 'Transport'], ['CAISSE AUTO', 'Transport'],
        ['TOTAL VOREPPE', 'Transport'], ['PLUG INN', 'Transport'],
        ['TOTALENERGIES ELECTRICITE', 'Logement & énergie'], ['PRLV SEPA TOTALENERGIES', 'Logement & énergie'],
        ['SDC ATRIA', 'Logement & énergie'], ['REGIE D EAU', 'Logement & énergie'],
        ['MACIF', 'Assurances & épargne'], ['CREDIT AGRICOLE', 'Assurances & épargne'],
        ['FREE TELECOM', 'Télécom'], ['FREE MOBILE', 'Télécom'],
        ['FRANFINANCE', 'Crédits & location'], ['PERSONAL FINANCE', 'Crédits & location'],
        ['DIAC LOCATION', 'Crédits & location'], ['COFIDIS', 'Crédits & location'],
        ['D.G.F.I.P.', 'Impôts'], ['IMPOT TF', 'Impôts'],
        ['COMMISSIONS', 'Frais bancaires'], ['RETRAIT DAB', 'Retraits espèces'],
        ['VINTED', 'Achats / Divers'], ['NOUVELLE LU', 'Achats / Divers'], ['KINEPOLIS', 'Achats / Loisirs'],
    ];

    public const SETTINGS = [
        'overdraft_default_cents' => '10000',
        'debt_ratio_threshold' => '0.33',
        'income_source' => 'average',
        'income_average_months' => '6',
        'moving_average_window' => '3',
        'forecast_horizon' => '3',
        'drift_tolerance' => '0.02',
    ];

    public function seed(int $userId): void
    {
        $categories = $this->fetchTable('Categories');
        $ids = [];
        $pos = 0;
        foreach (self::CATEGORIES as $kind => $names) {
            foreach ($names as $name) {
                $c = $categories->save($categories->newEntity(
                    ['user_id' => $userId, 'name' => $name, 'kind' => $kind, 'position' => ++$pos],
                    ['validate' => false],
                ));
                $ids[$name] = $c->id;
            }
        }
        $rules = $this->fetchTable('Rules');
        foreach (self::RULES as $i => [$keyword, $category]) {
            $rules->saveOrFail($rules->newEntity(
                ['user_id' => $userId, 'keyword' => $keyword, 'category_id' => $ids[$category], 'position' => $i + 1],
                ['validate' => false],
            ));
        }
        $settings = $this->fetchTable('Settings');
        foreach (self::SETTINGS as $key => $value) {
            $settings->saveOrFail($settings->newEntity(['user_id' => $userId, 'key' => $key, 'value' => $value]));
        }
    }
}
