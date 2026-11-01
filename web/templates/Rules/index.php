<?php
/**
 * @var \Cake\View\View $this
 * @var iterable<object> $rules
 * @var array<int, string> $categories
 * @var string $test
 * @var array<string, mixed>|null $result
 * @var string $keyword
 */
$this->assign('title', 'Règles');
?>
<h2>Règles de catégorisation</h2>
<p>Un mot-clé correspond s’il est contenu dans le libellé (sans tenir compte des majuscules ni des accents ;
    <code>*</code> = n’importe quels caractères, <code>?</code> = un caractère). En cas de plusieurs règles, la dernière de la liste l’emporte.</p>

<div class="row">
    <div class="column">
        <h4>Ajouter une règle</h4>
        <?= $this->Form->create(null, ['url' => ['action' => 'add']]) ?>
        <?= $this->Form->control('keyword', ['label' => 'Mot-clé', 'value' => $keyword, 'required' => true]) ?>
        <?= $this->Form->control('category_id', ['label' => 'Catégorie', 'options' => $categories]) ?>
        <?= $this->Form->button('Ajouter') ?>
        <?= $this->Form->end() ?>
    </div>
    <div class="column">
        <h4>Tester un libellé</h4>
        <form method="get">
            <label for="test">Libellé</label>
            <input type="text" id="test" name="test" value="<?= h($test) ?>">
            <button type="submit">Tester</button>
        </form>
        <?php if ($result !== null) : ?>
            <?php if (!$result['winner']) : ?>
                <p>Aucune règle ne correspond : <strong>À classer</strong></p>
            <?php else : ?>
                <p>Règles correspondantes : <?= h(implode(', ', array_map(fn($m) => '#' . $m['position'] . ' ' . $m['keyword'], $result['matches']))) ?><br>
                    Gagnante : <strong>#<?= (int)$result['winner']['position'] ?> <?= h($result['winner']['keyword']) ?></strong> → <?= h($categories[$result['winner']['category_id']] ?? '?') ?></p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?= $this->Form->postLink('Réappliquer les règles à tous les relevés', ['action' => 'reapply'], ['class' => 'button button-outline']) ?>
<div class="scroll">
<table>
    <thead><tr><th>#</th><th>Mot-clé</th><th>Catégorie</th><th>Ordre</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rules as $r) : ?>
        <tr>
            <td><?= (int)$r->position ?></td>
            <td><?= h($r->keyword) ?></td>
            <td><?= h($r->category->name ?? '') ?></td>
            <td>
                <?= $this->Form->postLink('↑', ['action' => 'move', $r->id, 'up']) ?>
                <?= $this->Form->postLink('↓', ['action' => 'move', $r->id, 'down']) ?>
            </td>
            <td><?= $this->Form->postLink('Supprimer', ['action' => 'delete', $r->id], ['confirm' => 'Supprimer cette règle ?']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
