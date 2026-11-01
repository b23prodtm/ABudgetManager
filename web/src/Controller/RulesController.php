<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\StatementImporter;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;

class RulesController extends AppController
{
    public function index(): void
    {
        $userId = (int)$this->userId();
        $rules = $this->fetchTable('Rules')->find()->where(['Rules.user_id' => $userId])
            ->contain(['Categories'])->orderBy(['Rules.position' => 'ASC'])->all();
        $categories = $this->fetchTable('Categories')->find('list')->where(['user_id' => $userId])->orderBy(['position' => 'ASC'])->toArray();
        $test = trim((string)$this->request->getQuery('test'));
        $result = null;
        if ($test !== '') {
            $c = (new StatementImporter())->categorizer($userId);
            $result = ['matches' => $c->matches($test), 'winner' => $c->winner($test)];
        }
        $this->set(compact('rules', 'categories', 'test', 'result') + ['keyword' => (string)$this->request->getQuery('keyword')]);
    }

    public function add(): Response
    {
        $this->request->allowMethod(['post']);
        $userId = (int)$this->userId();
        $rules = $this->fetchTable('Rules');
        $keyword = trim((string)$this->request->getData('keyword'));
        $category = $this->fetchTable('Categories')->find()
            ->where(['id' => (int)$this->request->getData('category_id'), 'user_id' => $userId])->first();
        if ($keyword === '' || !$category) {
            $this->Flash->error('Mot-clé et catégorie requis.');
        } else {
            $max = (int)$rules->find()->where(['user_id' => $userId])->all()->max('position')?->position;
            $rules->saveOrFail($rules->newEntity(['user_id' => $userId, 'keyword' => $keyword, 'category_id' => $category->id, 'position' => $max + 1]));
            $n = (new StatementImporter())->reapply($userId);
            $this->Flash->success(sprintf('Règle ajoutée ; %d ligne(s) reclassée(s).', $n));
        }

        return $this->redirect(['action' => 'index']);
    }

    public function delete(int $id): Response
    {
        $this->request->allowMethod(['post']);
        $rules = $this->fetchTable('Rules');
        $rules->delete($this->find($id));
        (new StatementImporter())->reapply((int)$this->userId());
        $this->Flash->success('Règle supprimée.');

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Swap position with the previous/next rule.
     */
    public function move(int $id, string $dir): Response
    {
        $this->request->allowMethod(['post']);
        $rules = $this->fetchTable('Rules');
        $rule = $this->find($id);
        $query = $rules->find()->where(['user_id' => $this->userId()]);
        $other = $dir === 'up'
            ? $query->where(['position <' => $rule->position])->orderBy(['position' => 'DESC'])->first()
            : $query->where(['position >' => $rule->position])->orderBy(['position' => 'ASC'])->first();
        if ($other) {
            $rules->getConnection()->transactional(function () use ($rules, $rule, $other) {
                [$a, $b] = [$rule->position, $other->position];
                // unique (user_id, position): go through a free slot
                $rule->position = -$a;
                $rules->saveOrFail($rule);
                $other->position = $a;
                $rules->saveOrFail($other);
                $rule->position = $b;
                $rules->saveOrFail($rule);
            });
            (new StatementImporter())->reapply((int)$this->userId());
        }

        return $this->redirect(['action' => 'index']);
    }

    public function reapply(): Response
    {
        $this->request->allowMethod(['post']);
        $n = (new StatementImporter())->reapply((int)$this->userId());
        $this->Flash->success(sprintf('Règles réappliquées : %d ligne(s) modifiée(s).', $n));

        return $this->redirect(['action' => 'index']);
    }

    private function find(int $id): object
    {
        return $this->fetchTable('Rules')->find()->where(['id' => $id, 'user_id' => $this->userId()])->first()
            ?? throw new NotFoundException();
    }
}
