<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class TransactionsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('transactions');
        $this->addBehavior('Timestamp');
        $this->belongsTo('Statements');
        $this->belongsTo('Categories');
    }
}
