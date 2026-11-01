<?php
/**
 * @var string $defaultMonth
 * @var string $overdraft
 */
$this->assign('title', 'Nouveau mois');
?>
<h2>Nouveau mois</h2>
<p>Déposez vos deux fichiers : vous pourrez corriger les données extraites avant de valider.
    Les fichiers ne sont pas conservés : seules les données validées sont enregistrées.</p>
<?= $this->Form->create(null, ['type' => 'file']) ?>
<div class="row">
    <div class="column"><?= $this->Form->control('month', ['type' => 'month', 'label' => 'Mois concerné', 'value' => $defaultMonth, 'required' => true]) ?></div>
    <div class="column"><?= $this->Form->control('opening_balance', ['label' => 'Solde d’ouverture (€)', 'placeholder' => '0,00']) ?></div>
    <div class="column"><?= $this->Form->control('overdraft', ['label' => 'Découvert autorisé (€)', 'value' => $overdraft]) ?></div>
</div>
<div class="row">
    <div class="column"><?= $this->Form->control('statement', ['type' => 'file', 'label' => 'Relevé bancaire (CSV, TXT ou PDF)', 'accept' => '.csv,.txt,.pdf']) ?></div>
    <div class="column"><?= $this->Form->control('payslip', ['type' => 'file', 'label' => 'Bulletin de salaire (PDF)', 'accept' => '.pdf']) ?></div>
</div>
<?= $this->Form->control('statement_text', ['type' => 'textarea', 'rows' => 4, 'label' => 'ou collez le relevé (date ; libellé ; débit ; crédit)']) ?>
<details>
    <summary>Valeurs de contrôle imprimées sur le relevé (facultatif)</summary>
    <div class="row">
        <div class="column"><?= $this->Form->control('declared_debits', ['label' => 'Total des débits (€)']) ?></div>
        <div class="column"><?= $this->Form->control('declared_credits', ['label' => 'Total des crédits (€)']) ?></div>
        <div class="column"><?= $this->Form->control('declared_closing', ['label' => 'Solde final (€)']) ?></div>
    </div>
</details>
<?= $this->Form->button('Analyser les fichiers') ?>
<?= $this->Form->end() ?>
