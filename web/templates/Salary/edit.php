<?php
/**
 * @var \Cake\View\View $this
 * @var \Cake\Datasource\EntityInterface $entry
 */
$this->assign('title', 'Bulletin');
?>
<h2><?= $entry->isNew() ? 'Ajouter un bulletin' : 'Modifier le bulletin' ?></h2>
<?= $this->Form->create(null) ?>
<?= $this->Form->control('month', ['type' => 'month', 'label' => 'Mois *', 'required' => true, 'value' => $entry->month ? $entry->month->format('Y-m') : date('Y-m')]) ?>
<?= $this->element('salary_fields', ['values' => $entry->toArray(), 'prefix' => '']) ?>
<?= $this->Form->button('Enregistrer') ?>
<?= $this->Html->link('Annuler', ['action' => 'index'], ['class' => 'button button-clear']) ?>
<?= $this->Form->end() ?>
