<?php
/**
 * SAMA MAILLOT — fil de discussion d'un litige, commun au client, au vendeur et à l'administration.
 *
 * Variables attendues : $litige (ligne litiges), $messages (messages_du_litige), $role_fil
 * ('client' | 'vendeur' | 'admin'), $erreurs, $valeur_message.
 */

$libelles_role = ['client' => 'Client', 'vendeur' => 'Vendeur', 'admin' => 'SAMA MAILLOT'];
?>
<div class="sm-tuile mb-3">
    <h2 class="h6 fw-bold mb-3"><i class="bi bi-chat-dots" aria-hidden="true"></i> Échanges</h2>
    <ol class="sm-fil-litige">
        <?php foreach ($messages as $message): ?>
            <li class="sm-fil-litige__message sm-fil-litige__message--<?= e($message['auteur_role']) ?><?= $message['auteur_role'] === $role_fil ? ' sm-fil-litige__message--moi' : '' ?>">
                <div class="sm-fil-litige__entete">
                    <strong><?= e($libelles_role[$message['auteur_role']] ?? $message['auteur_role']) ?></strong>
                    <?php if ($message['auteur_role'] !== 'admin' && $message['prenom'] !== null): ?>
                        <span class="text-body-secondary">· <?= e($message['prenom']) ?></span>
                    <?php endif; ?>
                    <span class="small text-body-secondary ms-auto"><?= e(date_relative($message['cree_le'])) ?></span>
                </div>
                <p class="mb-0 sm-texte-multiligne"><?= e($message['message']) ?></p>
                <?php if ($message['photos'] !== []): ?>
                    <div class="sm-fil-litige__photos">
                        <?php foreach ($message['photos'] as $photoId): ?>
                            <a href="<?= e(url('litige/photo.php?id=' . $photoId)) ?>" target="_blank" rel="noopener">
                                <img src="<?= e(url('litige/photo.php?id=' . $photoId)) ?>" alt="Photo jointe" loading="lazy" width="96" height="96">
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>

    <?php if ($litige['statut'] === 'ouvert'): ?>
        <form method="post" enctype="multipart/form-data" class="mt-3" novalidate>
            <?= champ_csrf() ?>
            <input type="hidden" name="id" value="<?= e($litige['id']) ?>">
            <input type="hidden" name="action" value="message">
            <label class="form-label fw-semibold" for="message">Votre message</label>
            <textarea class="form-control<?= classe_erreur($erreurs, 'message') ?>" id="message" name="message" rows="3"
                      maxlength="<?= LITIGE_MESSAGE_MAX ?>" required <?= aria_erreur($erreurs, 'message') ?>><?= e($valeur_message) ?></textarea>
            <?= message_erreur($erreurs, 'message') ?>
            <label class="form-label small mt-2" for="photos">Photos (facultatif, 3 au maximum)</label>
            <input class="form-control form-control-sm<?= classe_erreur($erreurs, 'photos') ?>" type="file" id="photos" name="photos[]" multiple
                   accept="image/jpeg,image/png,image/webp,image/avif" <?= aria_erreur($erreurs, 'photos') ?>>
            <?= message_erreur($erreurs, 'photos') ?>
            <button class="btn btn-or mt-3" type="submit"><i class="bi bi-send" aria-hidden="true"></i> Envoyer</button>
        </form>
    <?php else: ?>
        <p class="small text-body-secondary mt-3 mb-0"><i class="bi bi-lock" aria-hidden="true"></i> Réclamation close le <?= e(date_commande($litige['clos_le'])) ?>.</p>
    <?php endif; ?>
</div>
