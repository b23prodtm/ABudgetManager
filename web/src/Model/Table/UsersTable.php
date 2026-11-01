<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class UsersTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('users');
        $this->addBehavior('Timestamp');
    }

    public function validationDefault(Validator $validator): Validator
    {
        return $validator
            ->email('email', false, 'Adresse e-mail invalide')
            ->requirePresence('email', 'create')
            ->notEmptyString('email', 'Adresse e-mail requise')
            ->requirePresence('password', 'create')
            ->minLength('password', 8, 'Mot de passe : 8 caractères minimum');
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(['email'], 'Cette adresse est déjà utilisée'));

        return $rules;
    }
}
