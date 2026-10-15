<?php
/**
 * SAMA MAILLOT — tableau de bord du vendeur (§9.1).
 *
 * Toutes les données affichées sont réelles : état de l'abonnement, nombre de maillots
 * publiés, en brouillon, en rupture, zones de livraison, commandes à confirmer / en cours /
 * livrées. Le portefeuille arrive au lot 8.
 */
require __DIR__ . '/../../includes/config.php';

[$utilisateur, $boutique] = exiger_vendeur();
$abonnement = etat_abonnement($boutique);
$stats = statistiques_boutique((int) $boutique['id']);
$zones = zones_desservies((int) $boutique['id']);
traiter_commandes_echues();
$commandes = compter_commandes_boutique((int) $boutique['id']);
$a_confirmer = $commandes['attente_confirmation'];
$en_cours = $commandes['confirmee'] + $commandes['expediee'];
$livrees = $commandes['livree'] + $commandes['recue'];

// Avertissement : signalements actifs de clients (non classés, sur la période) avant suspension.
$signalements_actifs = signaleurs_distincts((int) $boutique['id']);
$seuil_suspension = max(1, (int) parametre('signalements_avant_suspension', '3'));

// Boutique suspendue (§21.2) : le vendeur peut envoyer sa contestation à l'administration.
$erreur_contestation = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && champ_post('action') === 'contester') {
    if (!verifier_csrf()) {
        $erreur_contestation = 'Votre session a expiré. Merci de réessayer.';
    } else {
        $erreur_contestation = envoyer_contestation($utilisateur, $boutique, trim((string) ($_POST['contestation'] ?? '')));
        if ($erreur_contestation === null) {
            message_flash('succes', 'Votre contestation est envoyée. L\'administration va l\'examiner.');
            rediriger('vendeur/');
        }
    }
}

$joursRestants = null;
if ($abonnement['fin'] !== null) {
    $joursRestants = (int) ceil((strtotime($abonnement['fin']) - time()) / 86400);
}

// Étapes de démarrage : ce qui manque encore pour bien vendre
$a_faire = [];
if ($zones === [] && (int) $boutique['retrait_sur_place'] !== 1) {
    $a_faire[] = ['vendeur/livraison.php', 'bi-truck', 'Définissez vos zones et tarifs de livraison'];
}
if ($stats['publies'] === 0) {
    $a_faire[] = ['vendeur/maillot.php', 'bi-plus-lg', 'Publiez votre premier maillot'];
}
if (empty($boutique['logo'])) {
    $a_faire[] = ['vendeur/boutique.php', 'bi-image', 'Ajoutez le logo de votre boutique'];
}
// Numéro Wave des versements : seulement utile (et demandé) quand le paiement en ligne est actif.
if (empty($boutique['numero_wave']) && paiement_en_ligne_actif()) {
    $a_faire[] = ['vendeur/boutique.php', 'bi-wallet2', 'Renseignez votre numéro Wave pour les versements'];
}

$titre_page = 'Espace vendeur';
$sans_indexation = true;
$page_active = 'vendeur';

require __DIR__ . '/../../includes/entete.php';
?>

<section class="sm-page-catalogue">
    <div class="container">
        <?= menu_vendeur('tableau') ?>

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
            <div>
                <span class="sm-sur-titre">Espace vendeur</span>
                <h1 class="sm-titre-section mb-0"><?= e($boutique['nom']) ?></h1>
            </div>
            <a class="btn btn-contour" href="<?= e(url_boutique($boutique['slug'])) ?>">
                Voir ma boutique <i class="bi bi-arrow-right" aria-hidden="true"></i>
            </a>
        </div>

        <?php if ($boutique['statut'] !== 'suspendue' && $signalements_actifs > 0): ?>
            <!-- Avertissement automatique : la boutique a été signalée par des clients -->
            <div class="sm-tuile sm-avertissement mb-4" role="status">
                <h2 class="h6 fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> Avertissement</h2>
                <p class="mb-0">
                    <?= e($signalements_actifs) ?> client<?= $signalements_actifs > 1 ? 's ont' : ' a' ?> signalé votre boutique ces
                    <?= e(parametre('signalements_periode_jours', '90')) ?> derniers jours.
                    À <?= e($seuil_suspension) ?>, votre boutique sera <strong>suspendue automatiquement</strong>.
                    Contactez vos clients et réglez les problèmes en cours.
                </p>
            </div>
        <?php endif; ?>

        <?php if ($boutique['statut'] === 'suspendue'): ?>
            <!-- Boutique suspendue (§21.2) : maillots masqués, commandes en cours toujours à livrer -->
            <div class="sm-tuile sm-suspension mb-4" role="alert">
                <h2 class="h5 fw-bold"><i class="bi bi-exclamation-octagon" aria-hidden="true"></i> Votre boutique est suspendue</h2>
                <p class="mb-2">
                    <?= $boutique['suspension_auto_le'] !== null
                        ? 'Plusieurs clients ont signalé votre boutique.'
                        : 'L\'administration a suspendu votre boutique.' ?>
                    Vos maillots ne sont plus visibles et vous ne recevez plus de nouvelles commandes.
                    <strong>Livrez vos commandes en cours</strong> : elles restent accessibles.
                </p>
                <?php if ($boutique['contestation_le'] !== null): ?>
                    <p class="small mb-0"><i class="bi bi-check2-circle" aria-hidden="true"></i>
                        Contestation envoyée le <?= e(date_commande($boutique['contestation_le'])) ?>. L'administration va l'examiner.</p>
                <?php else: ?>
                    <form method="post" class="mt-3">
                        <?= champ_csrf() ?>
                        <input type="hidden" name="action" value="contester">
                        <label class="form-label fw-semibold" for="contestation">Contester la suspension</label>
                        <textarea class="form-control<?= $erreur_contestation !== null ? ' is-invalid' : '' ?>" id="contestation" name="contestation"
                                  rows="4" maxlength="2000" required placeholder="Expliquez votre situation : commandes livrées, échanges avec les clients…"></textarea>
                        <?php if ($erreur_contestation !== null): ?>
                            <div class="invalid-feedback d-block"><?= e($erreur_contestation) ?></div>
                        <?php endif; ?>
                        <button class="btn btn-or mt-2" type="submit">Envoyer ma contestation</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Chiffres réels de la boutique -->
        <div class="sm-chiffres mb-4">
            <a class="sm-chiffre" href="<?= e(url('vendeur/maillots.php?statut=publie')) ?>">
                <span class="sm-chiffre__valeur"><?= e($stats['publies']) ?></span>
                <span class="sm-chiffre__libelle">maillot<?= $stats['publies'] > 1 ? 's' : '' ?> publié<?= $stats['publies'] > 1 ? 's' : '' ?></span>
            </a>
            <a class="sm-chiffre" href="<?= e(url('vendeur/maillots.php?statut=brouillon')) ?>">
                <span class="sm-chiffre__valeur"><?= e($stats['brouillons']) ?></span>
                <span class="sm-chiffre__libelle">brouillon<?= $stats['brouillons'] > 1 ? 's' : '' ?></span>
            </a>
            <a class="sm-chiffre" href="<?= e(url('vendeur/maillots.php')) ?>">
                <span class="sm-chiffre__valeur<?= $stats['ruptures'] > 0 ? ' text-danger' : '' ?>"><?= e($stats['ruptures']) ?></span>
                <span class="sm-chiffre__libelle">en rupture de stock</span>
            </a>
            <a class="sm-chiffre" href="<?= e(url('vendeur/livraison.php')) ?>">
                <span class="sm-chiffre__valeur"><?= e(count($zones)) ?></span>
                <span class="sm-chiffre__libelle">zone<?= count($zones) > 1 ? 's' : '' ?> de livraison</span>
            </a>
        </div>

        <div class="row g-3">
            <div class="col-12 col-lg-5">
                <div class="sm-tuile">
                    <h2><i class="bi bi-calendar-check" aria-hidden="true"></i> Mon abonnement</h2>
                    <?php if (in_array($abonnement['etat'], ['essai', 'actif'], true)): ?>
                        <p class="mb-2">
                            <span class="sm-statut sm-statut--succes"><?= e($abonnement['libelle']) ?></span>
                            Il vous reste <strong><?= e($joursRestants) ?> jour<?= $joursRestants > 1 ? 's' : '' ?></strong>
                            (jusqu'au <?= e(date('d/m/Y', strtotime($abonnement['fin']))) ?>).
                        </p>
                        <p class="mb-2 small text-body-secondary"><?= e(format_fcfa(prix_abonnement())) ?> par mois, payables par Wave (1, 3 ou 6 mois).</p>
                    <?php elseif ($abonnement['etat'] === 'verification'): ?>
                        <p class="mb-2"><span class="sm-statut sm-statut--attention">Paiement en vérification</span></p>
                        <p class="mb-2 small">Votre boutique reste visible pendant que l'administration vérifie votre paiement Wave (sous 24 h).</p>
                    <?php elseif ($abonnement['etat'] === 'grace'): ?>
                        <p class="mb-2"><span class="sm-statut sm-statut--attention">Période de grâce</span></p>
                        <p class="mb-2 small">Vos maillots restent visibles, mais vous ne pouvez plus en ajouter. Renouvelez avant le masquage de votre boutique.</p>
                    <?php elseif ($abonnement['etat'] === 'suspendue'): ?>
                        <p class="mb-0"><span class="sm-statut sm-statut--erreur">Boutique suspendue</span> Contactez le support SAMA MAILLOT.</p>
                    <?php else: ?>
                        <p class="mb-2"><span class="sm-statut sm-statut--erreur"><?= e($abonnement['libelle']) ?></span></p>
                        <p class="mb-2 small">Votre boutique est masquée. Renouvelez (<?= e(format_fcfa(prix_abonnement())) ?>/mois) pour remettre vos maillots en ligne.</p>
                    <?php endif; ?>
                    <?php if ($abonnement['etat'] !== 'suspendue'): ?>
                        <a class="btn <?= in_array($abonnement['etat'], ['grace', 'expire', 'aucun'], true) ? 'btn-or' : 'btn-contour' ?>" href="<?= e(url('vendeur/abonnement.php')) ?>">
                            <i class="bi bi-arrow-repeat" aria-hidden="true"></i> <?= $abonnement['etat'] === 'verification' ? 'Voir mon paiement' : 'Renouveler' ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-12 col-lg-7">
                <div class="sm-tuile">
                    <h2><i class="bi bi-list-check" aria-hidden="true"></i> À faire</h2>
                    <?php if ($a_faire === []): ?>
                        <p class="mb-0"><i class="bi bi-check-circle-fill text-success" aria-hidden="true"></i> Votre boutique est prête. Bonnes ventes !</p>
                    <?php else: ?>
                        <ul class="sm-liste-a-faire">
                            <?php foreach ($a_faire as [$chemin, $icone, $texte]): ?>
                                <li>
                                    <a href="<?= e(url($chemin)) ?>"><i class="bi <?= e($icone) ?>" aria-hidden="true"></i> <?= e($texte) ?>
                                        <i class="bi bi-chevron-right ms-auto" aria-hidden="true"></i></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-12 col-sm-6">
                <div class="sm-tuile">
                    <h2><i class="bi bi-receipt" aria-hidden="true"></i> Commandes reçues</h2>
                    <?php if ($a_confirmer > 0): ?>
                        <p class="mb-2 fw-semibold sm-texte-attention">
                            <i class="bi bi-bell" aria-hidden="true"></i>
                            <?= e($a_confirmer) ?> commande<?= $a_confirmer > 1 ? 's' : '' ?> à confirmer sous 24 h
                        </p>
                    <?php endif; ?>
                    <p class="mb-2 small text-body-secondary">
                        <?= e($en_cours) ?> en cours · <?= e($livrees) ?> livrée<?= $livrees > 1 ? 's' : '' ?>
                    </p>
                    <a class="btn btn-or" href="<?= e(url('vendeur/commandes.php')) ?>">Voir les commandes <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                </div>
            </div>
            <div class="col-12 col-sm-6">
                <?php if (paiement_en_ligne_actif()): ?>
                    <div class="sm-tuile">
                        <h2><i class="bi bi-wallet2" aria-hidden="true"></i> Mon portefeuille</h2>
                        <p class="mb-2">Vos fonds en attente, disponibles et versés.</p>
                        <a class="btn btn-contour" href="<?= e(url('vendeur/portefeuille.php')) ?>">Voir mon portefeuille <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                    </div>
                <?php else: ?>
                    <div class="sm-tuile">
                        <h2><i class="bi bi-cash-coin" aria-hidden="true"></i> Paiement des clients</h2>
                        <p class="mb-0">Vos clients vous paient <strong>à la livraison</strong>, en espèces ou par Wave,
                            quand ils reçoivent leur maillot. L'argent vous revient directement.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
