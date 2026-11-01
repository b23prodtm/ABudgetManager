<?php
declare(strict_types=1);

namespace App\Controller;

use DateTimeImmutable;
use App\Service\DashboardBuilder;
use App\Service\Money;
use App\Service\SalaryTrend;
use App\Service\SalaryValidator;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;

class SalaryController extends AppController
{
    /**
     * Salary trend page: all / last 12 months / calendar year.
     */
    public function index(): void
    {
        $userId = (int)$this->userId();
        $range = (string)($this->request->getQuery('range') ?: 'all');
        $all = $this->fetchTable('SalaryEntries')->find()->where(['user_id' => $userId])->orderBy(['month' => 'ASC'])->all()->toList();
        $years = array_values(array_unique(array_map(fn($e) => $e->month->format('Y'), $all)));
        if ($range === '12') {
            $currentMonth = new DateTimeImmutable('first day of this month');
            $cutoff = $currentMonth->modify('-11 months')->format('Y-m-d');
            $currentMonth = $currentMonth->format('Y-m-d');
            $entries = array_values(array_filter(
                $all,
                fn($e) => $e->month->format('Y-m-d') >= $cutoff && $e->month->format('Y-m-d') <= $currentMonth,
            ));
        } elseif (preg_match('/^\d{4}$/', $range)) {
            $entries = array_values(array_filter($all, fn($e) => $e->month->format('Y') === $range));
        } else {
            $range = 'all';
            $entries = $all;
        }
        $settings = (new DashboardBuilder())->settings($userId);
        $trend = SalaryTrend::compute(
            array_map(fn($e) => ['month' => $e->month->format('Y-m-01'), 'net_paid' => $e->net_paid], $entries),
            (int)($settings['moving_average_window'] ?? 3),
            (int)($settings['forecast_horizon'] ?? 3),
        );
        $warnings = [];
        foreach ($entries as $e) {
            $warnings[$e->id] = SalaryValidator::warnings($e->toArray());
        }
        $ytd = array_sum(array_map(
            fn($e) => $e->net_paid,
            array_filter($all, fn($e) => $e->month->format('Y') === date('Y')),
        ));
        $this->set(compact('entries', 'trend', 'range', 'years', 'warnings', 'ytd'));
    }

    public function edit(?int $id = null): ?Response
    {
        $userId = (int)$this->userId();
        $table = $this->fetchTable('SalaryEntries');
        $entry = $id === null ? $table->newEmptyEntity()
            : ($table->find()->where(['id' => $id, 'user_id' => $userId])->first() ?? throw new NotFoundException());
        if ($this->request->is(['post', 'put'])) {
            $in = $this->request->getData();
            $month = (string)($in['month'] ?? '');
            $net = Money::parse((string)($in['net_paid'] ?? ''));
            if (!preg_match('/^\d{4}-\d{2}$/', $month) || $net === null) {
                $this->Flash->error('Le mois et le net payé sont obligatoires.');
            } else {
                if ($id === null) {
                    $entry = $table->find()->where(['user_id' => $userId, 'month' => $month . '-01'])->first() ?? $entry;
                }
                $data = ['user_id' => $userId, 'month' => $month . '-01', 'notes' => trim((string)($in['notes'] ?? '')) ?: null];
                foreach (ImportsController::SALARY_CENTS as $k) {
                    $data[$k] = Money::parse((string)($in[$k] ?? ''));
                }
                foreach (ImportsController::SALARY_DECIMALS as $k) {
                    $v = str_replace(',', '.', trim((string)($in[$k] ?? '')));
                    $data[$k] = is_numeric($v) ? $v : null;
                }
                $table->patchEntity($entry, $data);
                $table->saveOrFail($entry);
                $this->Flash->success('Bulletin enregistré.');

                return $this->redirect(['action' => 'index']);
            }
        }
        $this->set(compact('entry'));

        return null;
    }

    public function delete(int $id): Response
    {
        $this->request->allowMethod(['post']);
        $table = $this->fetchTable('SalaryEntries');
        $entry = $table->find()->where(['id' => $id, 'user_id' => $this->userId()])->first() ?? throw new NotFoundException();
        $table->delete($entry);
        $this->Flash->success('Bulletin supprimé.');

        return $this->redirect(['action' => 'index']);
    }

    public function export(): Response
    {
        $columns = ['month', ...ImportsController::SALARY_CENTS, ...ImportsController::SALARY_DECIMALS, 'notes'];
        $out = fopen('php://temp', 'w+');
        fputcsv($out, $columns, ';', '"', '');
        foreach ($this->fetchTable('SalaryEntries')->find()->where(['user_id' => $this->userId()])->orderBy(['month' => 'ASC']) as $e) {
            $row = [];
            foreach ($columns as $c) {
                $v = $e->get($c);
                $row[] = $c === 'month' ? $v->format('Y-m') : (in_array($c, ImportsController::SALARY_CENTS, true) ? Money::toInput($v) : $v);
            }
            fputcsv($out, $row, ';', '"', '');
        }
        rewind($out);

        return $this->response->withType('csv')->withDownload('salaires.csv')->withStringBody((string)stream_get_contents($out));
    }
}
