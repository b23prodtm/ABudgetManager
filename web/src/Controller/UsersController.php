<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\UserSeeder;
use Cake\Http\Response;

class UsersController extends AppController
{
    protected function publicActions(): array
    {
        return ['login', 'signup'];
    }

    public function login(): ?Response
    {
        if ($this->request->is('post')) {
            $email = mb_strtolower(trim((string)$this->request->getData('email')));
            $user = $this->fetchTable('Users')->find()->where(['email' => $email])->first();
            if ($user && password_verify((string)$this->request->getData('password'), $user->password)) {
                return $this->startSession($user);
            }
            $this->Flash->error('E-mail ou mot de passe incorrect.');
        }

        return null;
    }

    public function signup(): ?Response
    {
        $users = $this->fetchTable('Users');
        $user = $users->newEmptyEntity();
        if ($this->request->is('post')) {
            $data = $this->request->getData();
            $data['email'] = mb_strtolower(trim((string)($data['email'] ?? '')));
            $user = $users->newEntity($data);
            if (($data['password'] ?? '') !== ($data['password_confirm'] ?? null)) {
                $user->setError('password_confirm', 'Les mots de passe ne correspondent pas');
            }
            if (!$user->hasErrors() && $users->save($user)) {
                (new UserSeeder())->seed((int)$user->id);

                return $this->startSession($user);
            }
            $this->Flash->error('Inscription impossible : vérifiez le formulaire.');
        }
        $this->set(compact('user'));

        return null;
    }

    public function logout(): Response
    {
        $this->request->allowMethod(['post']);
        $this->request->getSession()->destroy();

        return $this->redirect(['controller' => 'Home', 'action' => 'index']);
    }

    private function startSession(object $user): Response
    {
        $session = $this->request->getSession();
        $session->renew();
        $session->write('Auth.id', $user->id);
        $session->write('Auth.email', $user->email);

        return $this->redirect(['controller' => 'Imports', 'action' => 'add']);
    }
}
