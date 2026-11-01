<?php
/**
 * @var \Cake\View\View $this
 * @var iterable<object> $statements
 */
$this->assign('title', 'Relevés');
?>
<h2>Mes relevés</h2>
<p><?= $this->Html->link('Nouveau mois', ['controller' => 'Imports', 'action' => 'add'], ['class' => 'button']) ?></p>
<div class="scroll">
<table>
    <thead><tr><th>Relevé</th><th>Période</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($statements as $s) : ?>
        <tr>
            <td><?= $this->Html->link($s->label, ['controller' => 'Dashboard', 'action' => 'index', '?' => ['statement' => $s->id]]) ?></td>
            <td><?= h($s->period_start?->format('d/m/Y')) ?> – <?= h($s->period_end?->format('d/m/Y')) ?></td>
            <td>
                <?= $this->Html->link('CSV', ['action' => 'export', $s->id]) ?>
                <?= $this->Form->postLink('Supprimer', ['action' => 'delete', $s->id], ['confirm' => 'Supprimer ce relevé et ses lignes ?']) ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
