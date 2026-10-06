<?php
/**
 * SAMA MAILLOT — espace vendeur : « Mes maillots » (§9.4).
 *
 * Liste des maillots de la boutique, filtre par statut, et actions rapides :
 *   masquer (publié → masqué), réafficher (masqué → publié), supprimer (suppression logique).
 * Le vendeur ne voit et ne modifie que SES maillots (§24, IDOR).
 */
require __DIR__ . '/../../includes/config.php';

[$utilisateur, $boutique] = exiger_vendeur();
$abonnement = etat_abonnement($boutique);
$boutiqueId = (int) $boutique['id'];

// ---- Actions rapides (formulaires POST avec jeton CSRF) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifier_csrf()) {
        message_flash('erreur', 'Votre session a expiré. Merci de réessayer.');
        rediriger('vendeur/maillots.php');
    }

    $maillot = maillot_du_vendeur((int) champ_post('id'), $boutiqueId);
    $action = champ_post('action');

    if ($maillot === null) {
        message_flash('erreur', 'Ce maillot est introuvable.');
    } elseif ($maillot['statut'] === 'masque_admin') {
        message_flash('erreur', 'Ce maillot a été masqué par l\'administration : vous ne pouvez pas changer sa visibilité.');
    } elseif ($action === 'masquer' && $maillot['statut'] === 'publie') {
        bdd()->prepare("UPDATE maillots SET statut = 'masque_vendeur' WHERE id = :id AND boutique_id = :b")
            ->execute(['id' => $maillot['id'], 'b' => $boutiqueId]);
        message_flash('succes', 'Maillot masqué : il n\'apparaît plus dans le catalogue.');
    } elseif ($action === 'reafficher' && $maillot['statut'] === 'masque_vendeur') {
        if (!$abonnement['peut_publier']) {
            message_flash('erreur', 'Votre abonnement ne permet pas de republier ce maillot.');
        } else {
            bdd()->prepare("UPDATE maillots SET statut = 'publie', publie_le = COALESCE(publie_le, NOW()) WHERE id = :id AND boutique_id = :b")
                ->execute(['id' => $maillot['id'], 'b' => $boutiqueId]);
            message_flash('succes', 'Maillot de nouveau visible dans le catalogue.');
        }
    } elseif ($action === 'supprimer' && case_cochee('confirmation')) {
        // Suppression logique : la ligne est conservée pour l'historique des commandes (§23.2)
        bdd()->prepare("UPDATE maillots SET statut = 'supprime', supprime_le = NOW() WHERE id = :id AND boutique_id = :b")
            ->execute(['id' => $maillot['id'], 'b' => $boutiqueId]);
        message_flash('succes', 'Maillot supprimé.');
    } else {
        message_flash('erreur', 'Action impossible pour ce maillot.');
    }

    rediriger('vendeur/maillots.php' . (champ_post('filtre') !== '' && isset(STATUTS_MAILLOT[champ_post('filtre')]) ? '?statut=' . champ_post('filtre') : ''));
}

$filtre = is_string($_GET['statut'] ?? null) && isset(STATUTS_MAILLOT[$_GET['statut']]) ? $_GET['statut'] : '';
$maillots = maillots_de_la_boutique($boutiqueId, $filtre);
$stats = statistiques_boutique($boutiqueId);

$titre_page = 'Mes maillots';
$sans_indexation = true;
$page_active = 'vendeur';

require __DIR__ . '/../../includes/entete.php';
?>

<section class="sm-page-catalogue">
    <div class="container">
        <?= menu_vendeur('maillots') ?>

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h1 class="sm-titre-section mb-0">Mes maillots</h1>
            <?php if ($abonnement['peut_ajouter']): ?>
                <a class="btn btn-or" href="<?= e(url('vendeur/maillot.php')) ?>"><i class="bi bi-plus-lg" aria-hidden="true"></i> Ajouter un maillot</a>
            <?php else: ?>
                <span class="small text-body-secondary"><i class="bi bi-lock" aria-hidden="true"></i> Ajout indisponible (<?= e(mb_strtolower($abonnement['libelle'])) ?>)</span>
            <?php endif; ?>
        </div>

        <!-- Filtre par statut -->
        <ul class="sm-onglets mb-4" aria-label="Filtrer par statut">
            <li><a class="sm-onglet" href="<?= e(url('vendeur/maillots.php')) ?>"<?= $filtre === '' ? ' aria-current="page"' : '' ?>>Tous</a></li>
            <li><a class="sm-onglet" href="<?= e(url('vendeur/maillots.php?statut=publie')) ?>"<?= $filtre === 'publie' ? ' aria-current="page"' : '' ?>>Publiés (<?= e($stats['publies']) ?>)</a></li>
            <li><a class="sm-onglet" href="<?= e(url('vendeur/maillots.php?statut=brouillon')) ?>"<?= $filtre === 'brouillon' ? ' aria-current="page"' : '' ?>>Brouillons (<?= e($stats['brouillons']) ?>)</a></li>
            <li><a class="sm-onglet" href="<?= e(url('vendeur/maillots.php?statut=masque_vendeur')) ?>"<?= $filtre === 'masque_vendeur' ? ' aria-current="page"' : '' ?>>Masqués</a></li>
        </ul>

        <?php if ($maillots === []): ?>
            <div class="sm-etat-vide">
                <div class="sm-etat-vide__icone" aria-hidden="true"><i class="bi bi-box-seam"></i></div>
                <h2 class="h3"><?= $filtre === '' ? 'Aucun maillot pour le moment' : 'Aucun maillot dans cette liste' ?></h2>
                <p class="mb-4">Ajoutez votre premier maillot : photos, tailles, prix… il sera visible dans le catalogue dès sa publication.</p>
                <?php if ($abonnement['peut_ajouter']): ?>
                    <a class="btn btn-or" href="<?= e(url('vendeur/maillot.php')) ?>"><i class="bi bi-plus-lg" aria-hidden="true"></i> Ajouter un maillot</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <ul class="sm-liste-vendeur">
                <?php foreach ($maillots as $maillot): ?>
                    <li class="sm-ligne-vendeur">
                        <span class="sm-ligne-vendeur__photo">
                            <?php if (!empty($maillot['miniature'])): ?>
                                <img src="<?= e(url_image_maillot($maillot['miniature'])) ?>" alt="" width="400" height="400" loading="lazy">
                            <?php else: ?>
                                <i class="bi bi-image" aria-hidden="true"></i>
                            <?php endif; ?>
                        </span>

                        <div class="sm-ligne-vendeur__infos">
                            <a class="sm-ligne-vendeur__titre" href="<?= e(url('vendeur/maillot.php?id=' . (int) $maillot['id'])) ?>"><?= e($maillot['titre']) ?></a>
                            <span class="small text-body-secondary">
                                <?= e($maillot['equipe_nom']) ?> · <?= e(LIBELLES_AUTHENTICITE[$maillot['authenticite']]) ?>
                            </span>
                            <span class="d-flex flex-wrap align-items-center gap-2 mt-1">
                                <?= badge_statut_maillot($maillot['statut']) ?>
                                <strong><?= e(format_fcfa((int) $maillot['prix'])) ?></strong>
                                <span class="small <?= (int) $maillot['stock_total'] === 0 ? 'text-danger fw-semibold' : 'text-body-secondary' ?>">
                                    <?= (int) $maillot['stock_total'] === 0 ? 'Rupture de stock' : e($maillot['stock_total']) . ' en stock' ?>
                                </span>
                            </span>
                        </div>

                        <div class="sm-ligne-vendeur__actions">
                            <a class="btn btn-contour btn-sm" href="<?= e(url('vendeur/maillot.php?id=' . (int) $maillot['id'])) ?>">
                                <i class="bi bi-pencil" aria-hidden="true"></i> Modifier
                            </a>
                            <?php if (in_array($maillot['statut'], ['publie', 'masque_vendeur'], true)): ?>
                                <form method="post">
                                    <?= champ_csrf() ?>
                                    <input type="hidden" name="id" value="<?= e($maillot['id']) ?>">
                                    <input type="hidden" name="filtre" value="<?= e($filtre) ?>">
                                    <?php if ($maillot['statut'] === 'publie'): ?>
                                        <button class="btn btn-contour btn-sm" type="submit" name="action" value="masquer">
                                            <i class="bi bi-eye-slash" aria-hidden="true"></i> Masquer
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-contour btn-sm" type="submit" name="action" value="reafficher">
                                            <i class="bi bi-eye" aria-hidden="true"></i> Réafficher
                                        </button>
                                    <?php endif; ?>
                                </form>
                            <?php endif; ?>
                            <?php if ($maillot['statut'] !== 'masque_admin'): ?>
                                <button class="btn btn-contour btn-sm sm-bouton-danger" type="button" data-bs-toggle="collapse"
                                        data-bs-target="#supprimer-<?= e($maillot['id']) ?>" aria-expanded="false" aria-controls="supprimer-<?= e($maillot['id']) ?>">
                                    <i class="bi bi-trash" aria-hidden="true"></i> Supprimer
                                </button>
                            <?php endif; ?>
                        </div>

                        <!-- Confirmation de suppression (sans fenêtre JavaScript) -->
                        <form method="post" class="collapse sm-ligne-vendeur__confirmation" id="supprimer-<?= e($maillot['id']) ?>">
                            <?= champ_csrf() ?>
                            <input type="hidden" name="id" value="<?= e($maillot['id']) ?>">
                            <input type="hidden" name="filtre" value="<?= e($filtre) ?>">
                            <input type="hidden" name="action" value="supprimer">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="confirmation" value="1" id="confirmer-<?= e($maillot['id']) ?>" required>
                                <label class="form-check-label" for="confirmer-<?= e($maillot['id']) ?>">
                                    Je confirme la suppression de « <?= e($maillot['titre']) ?> »
                                </label>
                            </div>
                            <button class="btn btn-sm sm-bouton-danger-plein" type="submit"><i class="bi bi-trash" aria-hidden="true"></i> Supprimer définitivement</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
