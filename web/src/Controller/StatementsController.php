<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\Money;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;

class StatementsController extends AppController
{
    public function index(): void
    {
        $this->set('statements', $this->fetchTable('Statements')->find()
            ->where(['user_id' => $this->userId()])->orderBy(['period_end' => 'DESC'])->all());
    }

    public function delete(int $id): Response
    {
        $this->request->allowMethod(['post']);
        $table = $this->fetchTable('Statements');
        $statement = $table->find()->where(['id' => $id, 'user_id' => $this->userId()])->first() ?? throw new NotFoundException();
        $table->delete($statement);
        $this->Flash->success('Relevé supprimé.');

        return $this->redirect(['action' => 'index']);
    }

    public function export(int $id): Response
    {
        $statement = $this->fetchTable('Statements')->find()->where(['id' => $id, 'user_id' => $this->userId()])->first()
            ?? throw new NotFoundException();
        $out = fopen('php://temp', 'w+');
        fputcsv($out, ['Date', 'Libellé', 'Débit', 'Crédit', 'Catégorie'], ';', '"', '');
        $txs = $this->fetchTable('Transactions')->find()->contain(['Categories'])
            ->where(['Transactions.statement_id' => $statement->id, 'Transactions.user_id' => $this->userId()])
            ->orderBy(['booked_on' => 'ASC', 'line_no' => 'ASC']);
        foreach ($txs as $t) {
            fputcsv($out, [$t->booked_on->format('d/m/Y'), $t->label, Money::toInput($t->debit_cents), Money::toInput($t->credit_cents),
                $t->category->name ?? 'À classer'], ';', '"', '');
        }
        rewind($out);

        return $this->response->withType('csv')->withDownload('releve-' . $statement->id . '.csv')->withStringBody((string)stream_get_contents($out));
    }
}
