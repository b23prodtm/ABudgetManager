<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\DashboardBuilder;
use App\Service\SalaryTrend;

/**
 * User homepage: the budget view of the logged-in user.
 */
class DashboardController extends AppController
{
    public function index(): void
    {
        $userId = (int)$this->userId();
        $statements = $this->fetchTable('Statements')->find()
            ->where(['user_id' => $userId])->orderBy(['period_end' => 'DESC', 'id' => 'DESC'])->all();
        $selected = $statements->first();
        $requested = (int)$this->request->getQuery('statement');
        foreach ($statements as $s) {
            if ($s->id === $requested) {
                $selected = $s;
            }
        }
        $builder = new DashboardBuilder();
        $data = $selected ? $builder->build($userId, $selected) : null;
        $settings = $builder->settings($userId);
        $salaries = $this->fetchTable('SalaryEntries')->find()->where(['user_id' => $userId])
            ->orderBy(['month' => 'ASC'])->all()
            ->map(fn($e) => ['month' => $e->month->format('Y-m-01'), 'net_paid' => $e->net_paid])->toList();
        $trend = SalaryTrend::compute($salaries, (int)($settings['moving_average_window'] ?? 3), (int)($settings['forecast_horizon'] ?? 3));
        $this->set(compact('statements', 'selected', 'data', 'trend'));
    }
}
