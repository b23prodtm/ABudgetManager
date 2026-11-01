<?php $this->assign('title', 'Bienvenue'); ?>
<div class="hero">
    <h1>Mon Budget</h1>
    <p>Déposez votre relevé bancaire et votre bulletin de salaire, vérifiez les données extraites,<br>
        et retrouvez votre budget, votre reste à vivre et la tendance de votre salaire.</p>
    <p>
        <?= $this->Html->link('Se connecter', ['controller' => 'Users', 'action' => 'login'], ['class' => 'button']) ?>
        <?= $this->Html->link('Créer un compte', ['controller' => 'Users', 'action' => 'signup'], ['class' => 'button button-outline']) ?>
    </p>
</div>
