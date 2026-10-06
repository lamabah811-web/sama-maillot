<?php
/**
 * SAMA MAILLOT — administration : traiter une réclamation (§16.3).
 * Fil d'échanges avec les deux parties, puis décision motivée : fondée (signalement retenu du
 * vendeur, suspension au seuil) ou non fondée. La commande est alors terminée.
 */
require __DIR__ . '/../../includes/config.php';

$admin = exiger_admin();

$litige = litige_complet((int) ($_GET['id'] ?? $_POST['id'] ?? 0));
if ($litige === null) {
    afficher_erreur(404);
}
$id = (int) $litige['id'];
$erreurs = [];
$valeur_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valeur_message = trim((string) ($_POST['message'] ?? ''));

    if (!verifier_csrf()) {
        $erreurs['general'] = 'Votre session a expiré. Merci de réessayer.';
    } elseif (champ_post('action') === 'decider') {
        $refus = decider_litige($admin, $litige, champ_post('decision'), (string) ($_POST['motif'] ?? ''));
        if ($refus === null) {
            message_flash('succes', 'Décision enregistrée. Le client et le vendeur sont prévenus.');
            rediriger('admin/litige.php?id=' . $id);
        }
        $erreurs['general'] = $refus;
    } else {
        $erreurs = repondre_litige($litige, $admin, 'admin', $valeur_message, fichiers_envoyes('photos'));
        if ($erreurs === []) {
            message_flash('succes', 'Message envoyé au client et au vendeur.');
            rediriger('admin/litige.php?id=' . $id);
        }
    }
}

$messages = messages_du_litige($id);
$role_fil = 'admin';
$seuil = max(1, (int) parametre('signalements_avant_suspension', '3'));

$titre_page = 'Réclamation ' . $litige['numero'];
$admin_actif = 'litiges';

require __DIR__ . '/../../includes/admin_entete.php';
?>

<div class="sm-admin__titre">
    <div>
        <h1>Réclamation <?= e($litige['numero']) ?> <?= badge_statut_litige($litige) ?></h1>
        <p><?= e(MOTIFS_LITIGE[$litige['motif']] ?? $litige['motif']) ?> · ouverte <?= e(date_relative($litige['cree_le'])) ?></p>
    </div>
    <a class="btn btn-sm btn-contour" href="<?= e(url('admin/litiges.php')) ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Réclamations</a>
</div>

<?= alerte_formulaire(array_intersect_key($erreurs, ['general' => 1])) ?>

<div class="row g-4">
    <div class="col-12 col-xl-8">
        <?php require __DIR__ . '/../../includes/fil_litige.php'; ?>
    </div>

    <div class="col-12 col-xl-4">
        <section class="sm-panneau mb-3">
            <header class="sm-panneau__entete"><div><h2>Parties</h2></div></header>
            <dl class="sm-recap__lignes mb-0">
                <dt>Client</dt>
                <dd><a href="<?= e(url('admin/utilisateur.php?id=' . (int) $litige['client_id'])) ?>"><?= e($litige['client_prenom'] . ' ' . $litige['client_nom']) ?></a></dd>
                <dt>Téléphone</dt>
                <dd><?= e(formater_telephone((string) $litige['telephone_client'])) ?></dd>
                <dt>Boutique</dt>
                <dd><a href="<?= e(url('admin/utilisateur.php?id=' . (int) $litige['vendeur_id'])) ?>"><?= e($litige['boutique_nom']) ?></a></dd>
                <dt>Signalements</dt>
                <dd><?= e(signaleurs_distincts((int) $litige['boutique_id'])) ?> / <?= e($seuil) ?></dd>
                <dt>Total</dt>
                <dd><?= e(format_fcfa((int) $litige['total'])) ?></dd>
                <dt>Réponse vendeur</dt>
                <dd><?= (int) $litige['vendeur_a_repondu'] === 1 ? 'Oui' : 'Non (limite ' . e(date_commande($litige['reponse_limite_le'])) . ')' ?></dd>
            </dl>
        </section>

        <?php if ($litige['statut'] === 'ouvert'): ?>
            <form method="post" class="sm-panneau">
                <?= champ_csrf() ?>
                <input type="hidden" name="id" value="<?= e($id) ?>">
                <input type="hidden" name="action" value="decider">
                <header class="sm-panneau__entete"><div><h2>Décision</h2><p>Envoyée au client et au vendeur ; la commande sera terminée.</p></div></header>
                <label class="form-label small" for="motif-decision">Motif (5 à 500 caractères)</label>
                <textarea class="form-control form-control-sm mb-2" id="motif-decision" name="motif" rows="3" maxlength="500" required minlength="5"
                          placeholder="Ex. : photos à l'appui, le maillot reçu ne correspond pas à l'annonce."></textarea>
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-sm btn-contour sm-bouton-danger" type="submit" name="decision" value="fondee">Fondée</button>
                    <button class="btn btn-sm btn-contour" type="submit" name="decision" value="non_fondee">Non fondée</button>
                </div>
                <p class="small text-body-secondary mt-2 mb-0">« Fondée » compte comme un signalement retenu du vendeur (suspension automatique à <?= e($seuil) ?>).</p>
            </form>
        <?php elseif ($litige['statut'] === 'decide'): ?>
            <section class="sm-panneau">
                <header class="sm-panneau__entete"><div><h2>Décision</h2></div></header>
                <p class="mb-0"><?= badge_statut_litige($litige) ?> <?= e($litige['decision_motif']) ?></p>
            </section>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../../includes/admin_pied.php'; ?>
