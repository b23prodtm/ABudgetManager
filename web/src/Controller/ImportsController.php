<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\DashboardBuilder;
use App\Service\ExtractionClient;
use App\Service\Money;
use App\Service\SalaryValidator;
use App\Service\StatementImporter;
use App\Service\StatementParser;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Psr\Http\Message\UploadedFileInterface;

/**
 * "New month" flow: upload bank statement + payslip, review the extracted data, validate.
 */
class ImportsController extends AppController
{
    /** Payslip fields stored in cents. */
    public const SALARY_CENTS = [
        'net_paid', 'net_taxable', 'gross', 'net_social', 'panier', 'income_tax_withheld', 'base_salary',
        'overtime25_amount', 'overtime_nonmajorated_amount', 'bonus_performance', 'bonus_tutor',
        'bonus_inter_site', 'bonus_replacement', 'paid_leave_indemnity', 'paid_leave_deduction',
    ];

    /** Payslip fields stored as decimals. */
    public const SALARY_DECIMALS = ['overtime25_hours', 'hours_worked'];

    private const MAX_BYTES = 10 * 1024 * 1024;

    /**
     * Month homepage: pick the month, upload the two files.
     */
    public function add(): ?Response
    {
        $userId = (int)$this->userId();
        $settings = (new DashboardBuilder())->settings($userId);
        if ($this->request->is('post')) {
            $month = $this->request->getData('month');
            if (!is_string($month) || !preg_match('/^\d{4}-\d{2}$/', $month)) {
                $this->Flash->error('Choisissez le mois concerné.');
            } else {
                $payload = $this->extract($month . '-01', $settings);
                if ($payload !== null) {
                    $import = $this->fetchTable('Imports')->saveOrFail($this->fetchTable('Imports')->newEntity([
                        'user_id' => $userId,
                        'month' => $month . '-01',
                        'status' => 'draft',
                        'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    ]));

                    return $this->redirect(['action' => 'review', $import->id]);
                }
            }
        }
        $this->set('defaultMonth', date('Y-m'));
        $this->set('overdraft', Money::toInput((int)($settings['overdraft_default_cents'] ?? 10000)));

        return null;
    }

    /**
     * Review the extracted data, edit it, then validate.
     */
    public function review(int $id): ?Response
    {
        $userId = (int)$this->userId();
        $import = $this->fetchTable('Imports')->find()->where(['id' => $id, 'user_id' => $userId, 'status' => 'draft'])->first();
        if (!$import) {
            throw new NotFoundException();
        }
        $payload = json_decode($import->payload, true, 512, JSON_THROW_ON_ERROR);
        $categories = $this->fetchTable('Categories')->find()->where(['user_id' => $userId])->orderBy(['position' => 'ASC'])->all();

        if ($this->request->is('post')) {
            $data = $this->request->getData();
            if (isset($data['discard'])) {
                $this->fetchTable('Imports')->delete($import);
                $this->Flash->success('Import annulé.');

                return $this->redirect(['action' => 'add']);
            }
            $payload = $this->applyEdits($payload, $data, $categories->extract('id')->toList());
            $import->payload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $this->fetchTable('Imports')->saveOrFail($import);
            if (isset($data['validate']) && $payload['salary']['net_paid'] === null && $payload['salary_present']) {
                $this->Flash->error('Le net payé est obligatoire pour enregistrer le bulletin.');
            } elseif (isset($data['validate'])) {
                return $this->validateImport($import, $payload);
            } else {
                $this->Flash->success('Modifications enregistrées. Vérifiez puis validez.');
            }
        }
        $lines = (new StatementImporter())->prepare($userId, $payload['lines']);
        foreach ($lines as $i => &$l) {
            if (array_key_exists('chosen_category_id', $payload['lines'][$i])) {
                $l['category_id'] = $payload['lines'][$i]['chosen_category_id'];
            }
        }
        unset($l);
        $this->set([
            'import' => $import,
            'payload' => $payload,
            'lines' => $lines,
            'categories' => $categories,
            'warnings' => $payload['salary_present'] ? SalaryValidator::warnings($payload['salary']) : [],
            'unclassified' => count(array_filter($lines, fn($l) => $l['category_id'] === null)),
        ]);

        return null;
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>|null
     */
    private function extract(string $month, array $settings): ?array
    {
        $files = $this->request->getUploadedFiles();
        $statementFile = $this->usable($files['statement'] ?? null);
        $payslipFile = $this->usable($files['payslip'] ?? null);
        $text = trim((string)$this->request->getData('statement_text'));
        if ($statementFile === null && $text === '' && $payslipFile === null) {
            $this->Flash->error('Ajoutez au moins le relevé bancaire ou le bulletin de salaire.');

            return null;
        }
        $client = new ExtractionClient();
        $parsed = ['lines' => [], 'errors' => []];
        if ($statementFile !== null) {
            $parsed = $client->statement($statementFile);
        } elseif ($text !== '') {
            $parsed = (new StatementParser())->parse($text);
        }
        $salary = array_fill_keys([...self::SALARY_CENTS, ...self::SALARY_DECIMALS], null) + ['notes' => null];
        $messages = $parsed['errors'];
        if ($payslipFile !== null) {
            $res = $client->payslip($payslipFile);
            foreach ($res['fields'] as $k => $v) {
                if (array_key_exists($k, $salary) && is_numeric($v)) {
                    $salary[$k] = in_array($k, self::SALARY_DECIMALS, true) ? (string)$v : (int)$v;
                }
            }
            $messages = array_merge($messages, $res['errors']);
        }
        $dates = array_column($parsed['lines'], 'booked_on');
        $overdraft = Money::parse((string)$this->request->getData('overdraft'));

        return [
            'month' => $month,
            'messages' => $messages,
            'statement_present' => $statementFile !== null || $text !== '',
            'salary_present' => $payslipFile !== null,
            'statement' => [
                'label' => 'Relevé ' . date('m/Y', strtotime($month)),
                'period_start' => $dates ? min($dates) : $month,
                'period_end' => $dates ? max($dates) : date('Y-m-t', strtotime($month)),
                'opening_balance_cents' => Money::parse((string)$this->request->getData('opening_balance')) ?? 0,
                'overdraft_limit_cents' => $overdraft ?? (int)($settings['overdraft_default_cents'] ?? 10000),
                'declared_total_debits_cents' => Money::parse((string)$this->request->getData('declared_debits')),
                'declared_total_credits_cents' => Money::parse((string)$this->request->getData('declared_credits')),
                'declared_closing_balance_cents' => Money::parse((string)$this->request->getData('declared_closing')),
            ],
            'lines' => $parsed['lines'],
            'salary' => $salary,
        ];
    }

    private function usable(mixed $file): ?UploadedFileInterface
    {
        if (!$file instanceof UploadedFileInterface || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($file->getError() !== UPLOAD_ERR_OK || $file->getSize() > self::MAX_BYTES) {
            $this->Flash->error('Fichier refusé (erreur ou taille > 10 Mo) : ' . h($file->getClientFilename()));

            return null;
        }

        return $file;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $data
     * @param array<int, int> $categoryIds
     * @return array<string, mixed>
     */
    private function applyEdits(array $payload, array $data, array $categoryIds): array
    {
        $h = (array)($data['statement'] ?? []);
        $s = &$payload['statement'];
        $s['label'] = trim((string)($h['label'] ?? $s['label'])) ?: $s['label'];
        foreach (['period_start', 'period_end'] as $k) {
            $s[$k] = StatementParser::parseDate((string)($h[$k] ?? '')) ?? $s[$k];
        }
        foreach (['opening_balance', 'overdraft_limit', 'declared_total_debits', 'declared_total_credits', 'declared_closing_balance'] as $k) {
            if (array_key_exists($k, $h)) {
                $v = Money::parse((string)$h[$k]);
                $s[$k . '_cents'] = $v ?? ($k === 'opening_balance' ? 0 : ($k === 'overdraft_limit' ? $s[$k . '_cents'] : null));
            }
        }
        unset($s);

        if (isset($data['lines']) && is_array($data['lines'])) {
            $lines = [];
            foreach ($data['lines'] as $row) {
                if (!empty($row['remove'])) {
                    continue;
                }
                $date = StatementParser::parseDate((string)($row['booked_on'] ?? ''));
                $debit = abs(Money::parse((string)($row['debit'] ?? '')) ?? 0);
                $credit = abs(Money::parse((string)($row['credit'] ?? '')) ?? 0);
                $label = trim((string)($row['label'] ?? ''));
                if ($date === null || $label === '') {
                    continue;
                }
                $line = ['booked_on' => $date, 'label' => $label, 'debit_cents' => $debit, 'credit_cents' => $credit];
                $chosen = (string)($row['category_id'] ?? '');
                $line['chosen_category_id'] = in_array((int)$chosen, $categoryIds, true) ? (int)$chosen : null;
                $lines[] = $line;
            }
            $payload['lines'] = $lines;
        }

        if (isset($data['salary']) && is_array($data['salary'])) {
            $in = $data['salary'];
            foreach (self::SALARY_CENTS as $k) {
                $payload['salary'][$k] = Money::parse((string)($in[$k] ?? ''));
            }
            foreach (self::SALARY_DECIMALS as $k) {
                $v = str_replace(',', '.', trim((string)($in[$k] ?? '')));
                $payload['salary'][$k] = is_numeric($v) ? $v : null;
            }
            $payload['salary']['notes'] = trim((string)($in['notes'] ?? '')) ?: null;
            $payload['salary_present'] = $payload['salary_present'] || $payload['salary']['net_paid'] !== null;
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function validateImport(object $import, array $payload): Response
    {
        $userId = (int)$this->userId();
        $statementId = null;
        $messages = [];
        if ($payload['lines']) {
            $importer = new StatementImporter();
            $lines = $importer->prepare($userId, $payload['lines']);
            foreach ($lines as $i => &$l) {
                if (array_key_exists('chosen_category_id', $payload['lines'][$i])
                    && $payload['lines'][$i]['chosen_category_id'] !== $l['category_id']) {
                    $l['category_id'] = $payload['lines'][$i]['chosen_category_id'];
                    $l['rule_id'] = null;
                    $l['category_locked'] = true;
                }
            }
            unset($l);
            $result = $importer->save($userId, $payload['statement'], $lines);
            $statementId = $result['statement']->id;
            if ($result['skipped'] > 0) {
                $messages[] = sprintf('%d ligne(s) déjà connue(s) ignorée(s) (doublons).', $result['skipped']);
            }
            $messages[] = sprintf('%d ligne(s) importée(s).', $result['inserted']);
        }
        if ($payload['salary_present'] && $payload['salary']['net_paid'] !== null) {
            $entries = $this->fetchTable('SalaryEntries');
            $entry = $entries->find()->where(['user_id' => $userId, 'month' => $payload['month']])->first()
                ?? $entries->newEmptyEntity();
            $entries->patchEntity($entry, $payload['salary'] + ['user_id' => $userId, 'month' => $payload['month']]);
            $entries->saveOrFail($entry);
            $messages[] = 'Bulletin du mois enregistré.';
        }
        $import->status = 'done';
        $import->payload = '{}'; // keep nothing beyond the validated data
        $this->fetchTable('Imports')->saveOrFail($import);
        $this->Flash->success(implode(' ', $messages) ?: 'Rien à enregistrer.');

        return $this->redirect(['controller' => 'Dashboard', 'action' => 'index', '?' => $statementId ? ['statement' => $statementId] : []]);
    }
}
