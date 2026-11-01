<?php $this->assign('title', 'Connexion'); ?>
<div class="narrow">
    <h2>Connexion</h2>
    <?= $this->Form->create(null) ?>
    <?= $this->Form->control('email', ['type' => 'email', 'label' => 'E-mail', 'required' => true, 'autofocus' => true]) ?>
    <?= $this->Form->control('password', ['type' => 'password', 'label' => 'Mot de passe', 'required' => true]) ?>
    <?= $this->Form->button('Se connecter') ?>
    <?= $this->Form->end() ?>
    <p>Pas encore de compte ? <?= $this->Html->link('Créer un compte', ['action' => 'signup']) ?></p>
</div>
