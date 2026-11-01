<?php
/**
 * @var \Cake\View\View $this
 * @var iterable<object> $statements
 * @var object|null $selected
 * @var array<string, mixed>|null $data
 * @var array<string, mixed> $trend
 */
use App\Service\Money;

$this->assign('title', 'Mon budget');
$cls = fn(?int $v) => $v === null ? '' : ($v >= 0 ? 'pos' : 'neg');
?>
<h2>Mon budget</h2>
<?php if (!$selected) : ?>
    <div class="card">
        <p>Aucun relevé pour l’instant.</p>
        <?= $this->Html->link('Importer mon premier mois', ['controller' => 'Imports', 'action' => 'add'], ['class' => 'button']) ?>
    </div>
<?php else : ?>
    <form method="get" class="row">
        <div class="column column-50">
            <select name="statement" onchange="this.form.submit()" aria-label="Relevé">
                <?php foreach ($statements as $st) : ?>
                    <option value="<?= $st->id ?>" <?= $st->id === $selected->id ? 'selected' : '' ?>><?= h($st->label) ?> (<?= h($st->period_start?->format('d/m/Y')) ?> – <?= h($st->period_end?->format('d/m/Y')) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>

    <div class="cards">
        <div class="card"><div class="label">Revenu net mensuel</div>
            <div class="value"><?= $data['netIncome'] === null ? '—' : Money::format($data['netIncome']) ?></div>
            <div class="label">moyenne des derniers bulletins</div></div>
        <div class="card"><div class="label">Dépenses du relevé</div><div class="value"><?= Money::format($data['totalExpenses']) ?></div></div>
        <div class="card"><div class="label">Reste à vivre</div>
            <div class="value <?= $cls($data['remaining']) ?>"><?= $data['remaining'] === null ? '—' : Money::format($data['remaining']) ?></div></div>
        <div class="card"><div class="label">Solde final</div>
            <div class="value <?= ['green' => 'pos', 'orange' => 'warn', 'red' => 'neg'][$data['level']] ?>"><?= Money::format($data['totals']['closing']) ?></div>
            <div class="label">débits <?= Money::format($data['totals']['debits']) ?> · crédits <?= Money::format($data['totals']['credits']) ?></div></div>
    </div>

    <h3>Alertes</h3>
    <?php foreach ($data['alerts'] as [$severity, $text]) : ?>
        <div class="alert <?= h($severity) ?>"><?= ['info' => 'ℹ️', 'warning' => '⚠️', 'danger' => '⛔'][$severity] ?> <?= h($text) ?></div>
    <?php endforeach; ?>

    <h3>Dépenses par catégorie</h3>
    <div class="scroll">
    <table>
        <thead><tr><th>Catégorie</th><th class="num">Dépensé</th><th class="num">%</th><th class="num">Lignes</th><th style="width:30%"></th></tr></thead>
        <tbody>
        <?php foreach ($data['expenseRows'] as $r) : $pct = $data['totalExpenses'] > 0 ? $r['net'] / $data['totalExpenses'] * 100 : 0; ?>
            <tr>
                <td><?= h($r['category']->name) ?></td>
                <td class="num"><?= Money::format($r['net']) ?></td>
                <td class="num"><?= number_format($pct, 1, ',', '') ?> %</td>
                <td class="num"><?= $r['count'] ?></td>
                <td><div class="bar" style="width: <?= max(0, min(100, round($pct))) ?>%; background: <?= h($r['category']->color ?: '#3b6fd8') ?>"></div></td>
            </tr>
        <?php endforeach; ?>
        <tr>
            <td><strong>À classer</strong></td>
            <td class="num"><?= Money::format($data['unclassified']['net']) ?></td><td></td>
            <td class="num"><?= $data['unclassified']['count'] ?></td><td></td>
        </tr>
        <tr><th>Total dépenses</th><th class="num"><?= Money::format($data['totalExpenses']) ?></th><th></th><th></th><th></th></tr>
        </tbody>
    </table>
    </div>
    <p>Hors dépenses : virements internes <strong><?= Money::format($data['internal']) ?></strong> · Salaire reçu <strong><?= Money::format($data['salaryReceived']) ?></strong></p>

    <?php if ($data['unclassified']['count'] > 0) : ?>
        <h3>Lignes à classer</h3>
        <div class="scroll"><table>
            <?php foreach ($data['unclassified']['lines'] as $t) : ?>
                <tr>
                    <td><?= h($t->booked_on->format('d/m/Y')) ?></td><td><?= h($t->label) ?></td>
                    <td class="num"><?= Money::format($t->debit_cents - $t->credit_cents) ?></td>
                    <td><?= $this->Html->link('Créer une règle', ['controller' => 'Rules', 'action' => 'index', '?' => ['keyword' => $t->label]]) ?></td>
                </tr>
            <?php endforeach; ?>
        </table></div>
    <?php endif; ?>
<?php endif; ?>

<h3>Salaire</h3>
<?php if ($trend['n'] === 0) : ?>
    <p>Aucun bulletin enregistré. <?= $this->Html->link('Ajouter un bulletin', ['controller' => 'Salary', 'action' => 'edit']) ?></p>
<?php else : $last = end($trend['rows']); ?>
    <div class="cards">
        <div class="card"><div class="label">Dernier net</div><div class="value"><?= Money::format($last['net_paid']) ?></div></div>
        <div class="card"><div class="label">Tendance</div>
            <div class="value <?= ($trend['slope'] ?? 0) >= 0 ? 'pos' : 'neg' ?>"><?= $trend['slope'] === null ? '—' : Money::format((int)round($trend['slope'])) . '/mois' ?></div></div>
        <div class="card"><div class="label">Prévision mois suivant</div>
            <div class="value"><?= $trend['forecast'] ? Money::format((int)round($trend['forecast'][0]['value'])) : '—' ?></div></div>
    </div>
    <?= $this->Html->link('Voir le détail', ['controller' => 'Salary', 'action' => 'index']) ?>
<?php endif; ?>
