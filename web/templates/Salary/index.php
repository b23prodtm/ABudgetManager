<?php
/**
 * @var \Cake\View\View $this
 * @var array<int, object> $entries
 * @var array<string, mixed> $trend
 * @var string $range
 * @var array<int, string> $years
 * @var array<int, array<int, string>> $warnings
 * @var int $ytd
 */
use App\Service\Money;

$this->assign('title', 'Salaire');
$rows = $trend['rows'];
$last = $rows ? end($rows) : null;
?>
<h2>Tendance du salaire</h2>
<p>
    <?= $this->Html->link('Tout', ['?' => ['range' => 'all']], ['class' => 'button button-small' . ($range === 'all' ? '' : ' button-outline')]) ?>
    <?= $this->Html->link('12 derniers mois', ['?' => ['range' => '12']], ['class' => 'button button-small' . ($range === '12' ? '' : ' button-outline')]) ?>
    <?php foreach ($years as $y) : ?>
        <?= $this->Html->link($y, ['?' => ['range' => $y]], ['class' => 'button button-small' . ($range === $y ? '' : ' button-outline')]) ?>
    <?php endforeach; ?>
    <?= $this->Html->link('Ajouter un bulletin', ['action' => 'edit'], ['class' => 'button button-small']) ?>
    <?= $this->Html->link('Export CSV', ['action' => 'export'], ['class' => 'button button-small button-clear']) ?>
</p>
<?php if ($trend['unreliable'] && $trend['n'] > 0) : ?>
    <div class="alert warning">Tendance peu fiable (moins de 6 mois de données).</div>
<?php endif; ?>
<?php if ($last) : ?>
<div class="cards">
    <div class="card"><div class="label">Dernier net</div><div class="value"><?= Money::format($last['net_paid']) ?></div></div>
    <div class="card"><div class="label">Moyenne de la période</div><div class="value"><?= Money::format((int)round($trend['mean'])) ?></div></div>
    <div class="card"><div class="label">Tendance / mois</div><div class="value"><?= $trend['slope'] === null ? '—' : Money::format((int)round($trend['slope'])) ?></div></div>
    <div class="card"><div class="label">Prévision mois suivant</div><div class="value"><?= $trend['forecast'] ? Money::format((int)round($trend['forecast'][0]['value'])) : '—' ?></div></div>
    <div class="card"><div class="label">Cumul <?= date('Y') ?></div><div class="value"><?= Money::format($ytd) ?></div></div>
</div>
<?php if ($trend['r2'] !== null) : ?>
    <p>R² = <?= number_format($trend['r2'], 3, ',', '') ?> · RMSE = <?= Money::format((int)round($trend['rmse'])) ?></p>
<?php endif; ?>
<div class="card"><canvas id="salaryChart" height="110"></canvas></div>
<?php endif; ?>

<div class="scroll">
<table>
    <thead><tr><th>Mois</th><th class="num">Net payé</th><th class="num">Moyenne mobile</th><th class="num">Tendance</th><th class="num">Écart</th><th class="num">Var. %</th><th class="num">Brut</th><th>Alertes</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($entries as $i => $e) : $r = $rows[$i]; ?>
        <tr>
            <td><?= h($e->month->format('m/Y')) ?></td>
            <td class="num"><?= Money::format($e->net_paid) ?></td>
            <td class="num"><?= Money::format((int)round($r['moving_average'])) ?></td>
            <td class="num"><?= $r['trend'] === null ? '—' : Money::format((int)round($r['trend'])) ?></td>
            <td class="num"><?= Money::format((int)round($r['deviation'])) ?></td>
            <td class="num"><?= $r['mom'] === null ? '—' : number_format($r['mom'] * 100, 1, ',', '') . ' %' ?></td>
            <td class="num"><?= $e->gross === null ? '—' : Money::format($e->gross) ?></td>
            <td><?php foreach ($warnings[$e->id] as $w) : ?><div class="warn">⚠ <?= h($w) ?></div><?php endforeach; ?></td>
            <td>
                <?= $this->Html->link('Modifier', ['action' => 'edit', $e->id]) ?>
                <?= $this->Form->postLink('Supprimer', ['action' => 'delete', $e->id], ['confirm' => 'Supprimer ce bulletin ?']) ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php if ($last) : ?>
<?php $this->append('script'); ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
(function () {
    var rows = <?= json_encode(array_map(fn($r) => [substr($r['month'], 0, 7), $r['net_paid'] / 100, round($r['moving_average']) / 100, $r['trend'] === null ? null : round($r['trend']) / 100], $rows)) ?>;
    var fc = <?= json_encode(array_map(fn($f) => [substr($f['month'], 0, 7), round($f['value']) / 100], $trend['forecast'])) ?>;
    var labels = rows.map(function (r) { return r[0]; }).concat(fc.map(function (f) { return f[0]; }));
    var pad = function (n) { return new Array(n).fill(null); };
    var lastIdx = rows.length - 1;
    var forecast = pad(rows.length).concat(fc.map(function (f) { return f[1]; }));
    if (fc.length) { forecast[lastIdx] = rows[lastIdx][3]; }
    if (typeof Chart === 'undefined') { return; }
    new Chart(document.getElementById('salaryChart'), {
        type: 'line',
        data: { labels: labels, datasets: [
            { label: 'Net payé', data: rows.map(function (r) { return r[1]; }), borderColor: '#3b6fd8' },
            { label: 'Moyenne mobile', data: rows.map(function (r) { return r[2]; }), borderColor: '#e07b00' },
            { label: 'Tendance', data: rows.map(function (r) { return r[3]; }), borderColor: '#222', pointRadius: 0 },
            { label: 'Projection', data: forecast, borderColor: '#1b8a3a', borderDash: [6, 4] }
        ] }
    });
})();
</script>
<?php $this->end(); ?>
<?php endif; ?>
