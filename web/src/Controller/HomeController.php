<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;

class HomeController extends AppController
{
    protected function publicActions(): array
    {
        return ['index', 'health'];
    }

    /**
     * Welcome page for guests; logged-in users go to their homepage.
     */
    public function index(): ?Response
    {
        if ($this->userId() !== null) {
            return $this->redirect(['controller' => 'Dashboard', 'action' => 'index']);
        }
        $this->viewBuilder()->setLayout('default');

        return null;
    }

    public function health(): Response
    {
        return $this->response->withType('application/json')->withStringBody('{"status":"ok"}');
    }
}
