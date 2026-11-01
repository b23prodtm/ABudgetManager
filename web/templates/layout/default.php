<?php
/**
 * @var \Cake\View\View $this
 * @var int|null $currentUserId
 * @var string|null $currentUserEmail
 */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?= $this->Html->charset() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mon Budget — <?= h($this->fetch('title')) ?></title>
    <?= $this->Html->meta('icon') ?>
    <?= $this->Html->css(['normalize.min', 'milligram.min', 'app']) ?>
    <?= $this->fetch('meta') ?>
    <?= $this->fetch('css') ?>
</head>
<body>
    <nav class="topbar">
        <?= $this->Html->link('Mon Budget', '/', ['class' => 'brand']) ?>
        <?php if (!empty($currentUserId)) : ?>
            <?= $this->Html->link('Accueil', ['controller' => 'Dashboard', 'action' => 'index']) ?>
            <?= $this->Html->link('Nouveau mois', ['controller' => 'Imports', 'action' => 'add']) ?>
            <?= $this->Html->link('Relevés', ['controller' => 'Statements', 'action' => 'index']) ?>
            <?= $this->Html->link('Salaire', ['controller' => 'Salary', 'action' => 'index']) ?>
            <?= $this->Html->link('Règles', ['controller' => 'Rules', 'action' => 'index']) ?>
            <span class="spacer"></span>
            <span class="who"><?= h($currentUserEmail) ?></span>
            <?= $this->Form->postLink('Déconnexion', ['controller' => 'Users', 'action' => 'logout'], ['class' => 'button button-outline button-small']) ?>
        <?php else : ?>
            <span class="spacer"></span>
            <?= $this->Html->link('Connexion', ['controller' => 'Users', 'action' => 'login']) ?>
            <?= $this->Html->link('Créer un compte', ['controller' => 'Users', 'action' => 'signup']) ?>
        <?php endif; ?>
    </nav>
    <main class="container">
        <?= $this->Flash->render() ?>
        <?= $this->fetch('content') ?>
    </main>
    <?= $this->fetch('script') ?>
</body>
</html>
