<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class RulesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('rules');
        $this->addBehavior('Timestamp');
        $this->belongsTo('Categories');
    }
}
