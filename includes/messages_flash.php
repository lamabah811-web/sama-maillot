<?php
/**
 * SAMA MAILLOT — affichage des messages « flash » (succès, erreur, mode développement),
 * commun au site et à l'espace administrateur.
 */
?>
    <?php $messages_flash = lire_messages_flash(); ?>
    <?php if ($messages_flash !== []): ?>
        <div class="container pt-3">
            <?php foreach ($messages_flash as $message): ?>
                <?php if ($message['type'] === 'dev'): ?>
                    <!-- Visible uniquement en local : SMS / e-mail simulés (jamais en production) -->
                    <div class="alert sm-alerte-dev" role="status">
                        <strong><i class="bi bi-wrench-adjustable" aria-hidden="true"></i> Mode développement</strong><br>
                        <?= e($message['texte']) ?>
                        <?php if (!empty($message['lien'])): ?>
                            <br><a href="<?= e($message['lien']) ?>">Ouvrir le lien de l'e-mail</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="alert <?= $message['type'] === 'succes' ? 'alert-success' : 'alert-danger' ?>"
                         role="<?= $message['type'] === 'succes' ? 'status' : 'alert' ?>">
                        <?= e($message['texte']) ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
