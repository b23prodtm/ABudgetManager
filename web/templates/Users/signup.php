<?php
/** @var \Cake\Datasource\EntityInterface $user */
$this->assign('title', 'Créer un compte');
?>
<div class="narrow">
    <h2>Créer un compte</h2>
    <?= $this->Form->create($user) ?>
    <?= $this->Form->control('email', ['type' => 'email', 'label' => 'E-mail', 'required' => true]) ?>
    <?= $this->Form->control('password', ['type' => 'password', 'label' => 'Mot de passe (8 caractères minimum)', 'required' => true, 'value' => '']) ?>
    <?= $this->Form->control('password_confirm', ['type' => 'password', 'label' => 'Confirmer le mot de passe', 'required' => true, 'value' => '']) ?>
    <?= $this->Form->button('Créer mon compte') ?>
    <?= $this->Form->end() ?>
    <p>Déjà inscrit ? <?= $this->Html->link('Se connecter', ['action' => 'login']) ?></p>
</div>
