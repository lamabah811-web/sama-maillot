<?php
/**
 * SAMA MAILLOT — messages « flash » (succès, erreur, mode développement), commun au site et à
 * l'espace administrateur.
 *
 * Affichés en petites notifications flottantes en haut à droite, par-dessus la page (le contenu
 * n'est pas décalé). Disparition automatique (sama.js, « notifications flottantes ») : succès après
 * 4 s, erreur après 8 s ; croix pour fermer. Les messages de développement (local) restent affichés.
 */
?>
    <?php $messages_flash = lire_messages_flash(); ?>
    <?php if ($messages_flash !== []): ?>
        <div class="sm-toasts" aria-live="polite">
            <?php foreach ($messages_flash as $message): ?>
                <?php if ($message['type'] === 'dev'): ?>
                    <!-- Visible uniquement en local : SMS / e-mail simulés (jamais en production) -->
                    <div class="sm-toast sm-toast--dev" role="status" data-toast>
                        <i class="bi bi-wrench-adjustable sm-toast__icone" aria-hidden="true"></i>
                        <div class="sm-toast__texte">
                            <strong>Mode développement</strong><br>
                            <?= e($message['texte']) ?>
                            <?php if (!empty($message['lien'])): ?>
                                <br><a href="<?= e($message['lien']) ?>">Ouvrir le lien de l'e-mail</a>
                            <?php endif; ?>
                        </div>
                        <button class="sm-toast__fermer" type="button" data-toast-fermer aria-label="Fermer le message">
                            <i class="bi bi-x-lg" aria-hidden="true"></i>
                        </button>
                    </div>
                <?php else: ?>
                    <?php $succes = $message['type'] === 'succes'; ?>
                    <div class="sm-toast <?= $succes ? 'sm-toast--succes' : 'sm-toast--erreur' ?>"
                         role="<?= $succes ? 'status' : 'alert' ?>" data-toast data-toast-duree="<?= $succes ? 4000 : 8000 ?>">
                        <i class="bi <?= $succes ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill' ?> sm-toast__icone" aria-hidden="true"></i>
                        <div class="sm-toast__texte"><?= e($message['texte']) ?></div>
                        <button class="sm-toast__fermer" type="button" data-toast-fermer aria-label="Fermer le message">
                            <i class="bi bi-x-lg" aria-hidden="true"></i>
                        </button>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
