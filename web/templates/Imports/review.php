<?php
/**
 * @var \Cake\View\View $this
 * @var object $import
 * @var array<string, mixed> $payload
 * @var array<int, array<string, mixed>> $lines
 * @var iterable<object> $categories
 * @var array<int, string> $warnings
 * @var int $unclassified
 */
use App\Service\Money;
use App\Service\StatementTotals;

$this->assign('title', 'Vérification');
$s = $payload['statement'];
$totals = StatementTotals::compute((int)$s['opening_balance_cents'], $lines);
$mismatches = StatementTotals::controlMismatches([
    'debits' => $s['declared_total_debits_cents'], 'credits' => $s['declared_total_credits_cents'], 'closing' => $s['declared_closing_balance_cents'],
], $totals);
$options = [];
foreach ($categories as $c) {
    $options[$c->id] = $c->name;
}
?>
<h2>Vérifiez les données extraites — <?= h(date('m/Y', strtotime($payload['month']))) ?></h2>
<?php foreach ($payload['messages'] as $m) : ?>
    <div class="alert warning"><?= h($m) ?></div>
<?php endforeach; ?>
<?php foreach ($mismatches as $m) : ?>
    <div class="alert danger"><?= h($m) ?></div>
<?php endforeach; ?>

<?= $this->Form->create(null) ?>
<h3>Relevé bancaire</h3>
<div class="row">
    <div class="column"><?= $this->Form->control('statement.label', ['label' => 'Libellé', 'value' => $s['label']]) ?></div>
    <div class="column"><?= $this->Form->control('statement.period_start', ['label' => 'Début', 'value' => $s['period_start']]) ?></div>
    <div class="column"><?= $this->Form->control('statement.period_end', ['label' => 'Fin', 'value' => $s['period_end']]) ?></div>
</div>
<div class="row">
    <div class="column"><?= $this->Form->control('statement.opening_balance', ['label' => 'Solde d’ouverture (€)', 'value' => Money::toInput($s['opening_balance_cents'])]) ?></div>
    <div class="column"><?= $this->Form->control('statement.overdraft_limit', ['label' => 'Découvert autorisé (€)', 'value' => Money::toInput($s['overdraft_limit_cents'])]) ?></div>
    <div class="column"><?= $this->Form->control('statement.declared_total_debits', ['label' => 'Débits déclarés (€)', 'value' => Money::toInput($s['declared_total_debits_cents'])]) ?></div>
    <div class="column"><?= $this->Form->control('statement.declared_total_credits', ['label' => 'Crédits déclarés (€)', 'value' => Money::toInput($s['declared_total_credits_cents'])]) ?></div>
    <div class="column"><?= $this->Form->control('statement.declared_closing_balance', ['label' => 'Solde final déclaré (€)', 'value' => Money::toInput($s['declared_closing_balance_cents'])]) ?></div>
</div>
<p>
    <strong><?= count($lines) ?></strong> ligne(s) —
    débits <strong><?= Money::format($totals['debits']) ?></strong>,
    crédits <strong><?= Money::format($totals['credits']) ?></strong>,
    solde final <strong class="<?= ['green' => 'pos', 'orange' => 'warn', 'red' => 'neg'][StatementTotals::balanceLevel($totals['closing'], (int)$s['overdraft_limit_cents'])] ?>"><?= Money::format($totals['closing']) ?></strong> —
    <span class="<?= $unclassified ? 'warn' : 'pos' ?>"><?= $unclassified ?> à classer</span>
</p>
<div class="scroll">
<table>
    <thead><tr><th>Date</th><th>Libellé</th><th class="num">Débit</th><th class="num">Crédit</th><th>Catégorie</th><th>Retirer</th></tr></thead>
    <tbody>
    <?php foreach ($lines as $i => $l) : ?>
        <tr>
            <td><?= $this->Form->text("lines.$i.booked_on", ['value' => $l['booked_on'], 'size' => 10, 'aria-label' => sprintf('Ligne %d — Date', $i + 1)]) ?></td>
            <td><?= $this->Form->text("lines.$i.label", ['value' => $l['label'], 'aria-label' => sprintf('Ligne %d — Libellé', $i + 1)]) ?></td>
            <td><?= $this->Form->text("lines.$i.debit", ['value' => $l['debit_cents'] ? Money::toInput($l['debit_cents']) : '', 'class' => 'num', 'size' => 9, 'aria-label' => sprintf('Ligne %d — Débit', $i + 1)]) ?></td>
            <td><?= $this->Form->text("lines.$i.credit", ['value' => $l['credit_cents'] ? Money::toInput($l['credit_cents']) : '', 'class' => 'num', 'size' => 9, 'aria-label' => sprintf('Ligne %d — Crédit', $i + 1)]) ?></td>
            <td><?= $this->Form->select("lines.$i.category_id", $options, ['empty' => 'À classer', 'value' => $l['category_id'], 'aria-label' => sprintf('Ligne %d — Catégorie', $i + 1)]) ?></td>
            <td><?= $this->Form->checkbox("lines.$i.remove", ['hiddenField' => false, 'aria-label' => sprintf('Ligne %d — Retirer', $i + 1)]) ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$lines) : ?><tr><td colspan="6"><em>Aucune ligne de relevé.</em></td></tr><?php endif; ?>
    </tbody>
</table>
</div>

<h3>Bulletin de salaire</h3>
<?php foreach ($warnings as $w) : ?>
    <div class="alert warning">⚠ <?= h($w) ?></div>
<?php endforeach; ?>
<?= $this->element('salary_fields', ['values' => $payload['salary'], 'prefix' => 'salary.']) ?>

<?= $this->Form->button('Enregistrer les modifications', ['name' => 'save', 'class' => 'button button-outline']) ?>
<?= $this->Form->button('Valider et voir mon budget', ['name' => 'validate', 'value' => '1']) ?>
<?= $this->Form->button('Annuler cet import', ['name' => 'discard', 'value' => '1', 'class' => 'button button-clear']) ?>
<?= $this->Form->end() ?>
