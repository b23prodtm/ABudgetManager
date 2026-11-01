<?php
/**
 * @var \Cake\View\View $this
 * @var array<string, mixed> $values
 * @var string $prefix
 */
use App\Service\Money;

$labels = [
    'net_paid' => 'Net payé *', 'net_taxable' => 'Net imposable', 'gross' => 'Brut', 'net_social' => 'Net social',
    'panier' => 'Panier', 'income_tax_withheld' => 'Impôt prélevé à la source', 'base_salary' => 'Salaire de base',
    'overtime25_amount' => 'Heures sup. 25 % (montant)', 'overtime25_hours' => 'Heures sup. 25 % (heures)',
    'overtime_nonmajorated_amount' => 'Heures sup. non majorées', 'bonus_performance' => 'Prime de performance',
    'bonus_tutor' => 'Prime tutorat', 'bonus_inter_site' => 'Prime inter-sites', 'bonus_replacement' => 'Prime de remplacement',
    'paid_leave_indemnity' => 'Indemnité congés payés', 'paid_leave_deduction' => 'Retenue congés payés', 'hours_worked' => 'Heures travaillées',
];
$decimals = ['overtime25_hours', 'hours_worked'];
?>
<div class="row" style="flex-wrap: wrap">
    <?php foreach ($labels as $key => $label) : ?>
        <div class="column column-33">
            <?= $this->Form->control($prefix . $key, [
                'label' => $label,
                'type' => 'text',
                'class' => 'num',
                'value' => in_array($key, $decimals, true) ? (string)($values[$key] ?? '') : Money::toInput(isset($values[$key]) ? (int)$values[$key] : null),
            ]) ?>
        </div>
    <?php endforeach; ?>
</div>
<?= $this->Form->control($prefix . 'notes', ['type' => 'textarea', 'rows' => 2, 'label' => 'Notes', 'value' => (string)($values['notes'] ?? '')]) ?>
