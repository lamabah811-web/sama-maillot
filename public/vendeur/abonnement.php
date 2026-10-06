<?php
/**
 * SAMA MAILLOT — espace vendeur : abonnement et renouvellement par Wave (§18.4).
 *
 * 1. Le vendeur choisit 1, 3 ou 6 mois ; il paie par le lien Wave Business de SAMA MAILLOT (montant
 *    pré-rempli selon la durée) ou en envoyant le montant au numéro Wave de la plateforme.
 * 2. Il paie par Wave, puis saisit l'identifiant de transaction et joint la capture d'écran.
 * 3. Le paiement passe « en vérification » (boutique visible) jusqu'à la décision de l'admin.
 */
require __DIR__ . '/../../includes/config.php';

[$utilisateur, $boutique] = exiger_vendeur();
$etat = calculer_etat_abonnement($boutique);
$prix = prix_abonnement();
$numero_wave = parametre('numero_wave_plateforme');
$wave_configure = $numero_wave !== '' || lien_paiement_wave($prix) !== null;
$en_verification = paiement_en_verification((int) $boutique['id']);
$erreurs = [];
$valeurs = ['mois' => (int) ($_POST['mois'] ?? 1), 'reference' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valeurs['reference'] = champ_post('reference');
    if (!verifier_csrf()) {
        $erreurs['general'] = 'Votre session a expiré. Merci de réessayer.';
    } elseif (!$wave_configure) {
        $erreurs['general'] = 'Le numéro Wave de SAMA MAILLOT n\'est pas encore configuré. Contactez le support.';
    } else {
        $erreurs = soumettre_paiement_abonnement($utilisateur, $boutique, $valeurs['mois'], $valeurs['reference'], $_FILES['capture'] ?? null);
        if ($erreurs === []) {
            message_flash('succes', 'Paiement envoyé ! L\'administration le vérifie sous 24 h ; votre boutique reste visible pendant ce temps.');
            rediriger('vendeur/abonnement.php');
        }
    }
}

$requete = bdd()->prepare('SELECT * FROM paiements_abonnement WHERE boutique_id = :id ORDER BY cree_le DESC, id DESC LIMIT 20');
$requete->execute(['id' => $boutique['id']]);
$historique = $requete->fetchAll();

$titre_page = 'Mon abonnement';
$sans_indexation = true;
$page_active = 'vendeur';

require __DIR__ . '/../../includes/entete.php';
?>

<section class="sm-page-catalogue">
    <div class="container">
        <?= menu_vendeur('abonnement') ?>
        <h1 class="sm-titre-section mb-4">Mon abonnement</h1>

        <div class="row g-4">
            <div class="col-12 col-lg-5">
                <div class="sm-tuile mb-3">
                    <h2 class="h6 fw-bold">État actuel</h2>
                    <p class="mb-1"><?= badge_statut([
                        'essai' => ['Essai gratuit', 'succes'], 'actif' => ['Abonnement actif', 'succes'], 'verification' => ['Paiement en vérification', 'attention'],
                        'grace' => ['Période de grâce', 'attention'], 'expire' => ['Abonnement expiré', 'erreur'], 'aucun' => ['Aucun abonnement', 'erreur'],
                        'suspendue' => ['Boutique suspendue', 'erreur'],
                    ], $etat['etat']) ?></p>
                    <?php if ($etat['fin'] !== null): ?>
                        <p class="small mb-0">Fin de la période : <strong><?= e(date('d/m/Y à H\hi', strtotime($etat['fin']))) ?></strong></p>
                    <?php endif; ?>
                    <p class="small text-body-secondary mt-2 mb-0">
                        Après la fin : <?= e(parametre('abonnement_grace_jours', '3')) ?> jours de grâce (maillots visibles, ajout bloqué), puis la boutique est masquée.
                        Vous gardez toujours l'accès à vos commandes en cours.
                    </p>
                </div>

                <div class="sm-tuile">
                    <h2 class="h6 fw-bold">Historique des paiements</h2>
                    <?php if ($historique === []): ?>
                        <p class="small text-body-secondary mb-0">Aucun paiement pour le moment.</p>
                    <?php else: ?>
                        <ul class="sm-recap-articles mb-0">
                            <?php foreach ($historique as $p): ?>
                                <li>
                                    <span>
                                        <?= e($p['mois']) ?> mois · <?= e($p['reference_wave']) ?>
                                        <span class="small text-body-secondary d-block"><?= e(date_commande($p['cree_le'])) ?><?= $p['motif_refus'] !== null ? ' · ' . e($p['motif_refus']) : '' ?></span>
                                    </span>
                                    <span class="text-end">
                                        <strong class="d-block"><?= e(format_fcfa((int) $p['montant'])) ?></strong>
                                        <?= badge_statut(STATUTS_PAIEMENT_ABONNEMENT, $p['statut']) ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-12 col-lg-7">
                <div class="sm-tuile">
                    <h2 class="h5 fw-bold"><i class="bi bi-arrow-repeat" aria-hidden="true"></i> Renouveler par Wave</h2>

                    <?php if ($etat['etat'] === 'suspendue'): ?>
                        <p class="mb-0">Votre boutique est suspendue : le renouvellement n'est pas possible pour le moment.</p>
                    <?php elseif ($en_verification !== null): ?>
                        <div class="alert alert-info mb-0" role="status">
                            <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                            Votre paiement de <?= e(format_fcfa((int) $en_verification['montant'])) ?> (référence <?= e($en_verification['reference_wave']) ?>)
                            est en cours de vérification. Vous serez prévenu dès la décision.
                        </div>
                    <?php elseif (!$wave_configure): ?>
                        <div class="alert alert-warning mb-0">Le numéro Wave de SAMA MAILLOT n'est pas encore configuré. Contactez le support.</div>
                    <?php else: ?>
                        <?= alerte_formulaire($erreurs) ?>
                        <form method="post" enctype="multipart/form-data" novalidate>
                            <?= champ_csrf() ?>

                            <fieldset class="mb-3">
                                <legend class="form-label fw-semibold fs-6">1. Durée</legend>
                                <div class="sm-choix-durees">
                                    <?php foreach (MOIS_ABONNEMENT as $mois): ?>
                                        <input class="btn-check" type="radio" name="mois" id="mois-<?= $mois ?>" value="<?= $mois ?>"<?= $valeurs['mois'] === $mois ? ' checked' : '' ?>
                                               data-lien-wave="<?= e((string) lien_paiement_wave($prix * $mois)) ?>" data-montant="<?= e(format_fcfa($prix * $mois)) ?>">
                                        <label class="sm-choix-duree" for="mois-<?= $mois ?>">
                                            <strong><?= $mois ?> mois</strong>
                                            <span><?= e(format_fcfa($prix * $mois)) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <?= message_erreur($erreurs, 'mois') ?>
                            </fieldset>

                            <div class="sm-encadre-wave mb-3">
                                <p class="fw-semibold mb-2">2. Payez par Wave</p>
                                <?php if (($lien_wave = lien_paiement_wave($prix * $valeurs['mois'])) !== null): ?>
                                    <a class="btn sm-bouton-wave w-100 mb-2" href="<?= e($lien_wave) ?>" target="_blank" rel="noopener" data-bouton-wave>
                                        <i class="bi bi-phone" aria-hidden="true"></i> Payer <span data-montant-wave><?= e(format_fcfa($prix * $valeurs['mois'])) ?></span> avec Wave
                                    </a>
                                    <p class="small text-body-secondary mb-0">
                                        Le montant est déjà rempli. Après le paiement, notez l'identifiant de la transaction affiché par Wave.
                                        <?php if ($numero_wave !== ''): ?>Ou envoyez-le au <strong><?= e(formater_telephone($numero_wave)) ?></strong>.<?php endif; ?>
                                    </p>
                                <?php else: ?>
                                    <p class="sm-encadre-wave__numero mb-1"><i class="bi bi-phone" aria-hidden="true"></i> <?= e(formater_telephone($numero_wave)) ?></p>
                                    <p class="small text-body-secondary mb-0">Envoyez exactement le montant de la durée choisie, puis notez l'identifiant de la transaction affiché par Wave.</p>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold" for="reference">3. Identifiant de la transaction Wave</label>
                                <input class="form-control<?= classe_erreur($erreurs, 'reference') ?>" type="text" id="reference" name="reference" maxlength="40"
                                       value="<?= e($valeurs['reference']) ?>" autocomplete="off" placeholder="Ex. : T_ABCD1234EFGH" <?= aria_erreur($erreurs, 'reference') ?>>
                                <?= message_erreur($erreurs, 'reference') ?>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold" for="capture">4. Capture d'écran du paiement</label>
                                <input class="form-control<?= classe_erreur($erreurs, 'capture') ?>" type="file" id="capture" name="capture"
                                       accept="image/jpeg,image/png,image/webp" <?= aria_erreur($erreurs, 'capture') ?>>
                                <?= message_erreur($erreurs, 'capture') ?>
                                <div class="form-text">Elle n'est visible que par l'administration de SAMA MAILLOT.</div>
                            </div>

                            <button class="btn btn-or btn-lg w-100" type="submit"><i class="bi bi-send" aria-hidden="true"></i> Envoyer mon paiement</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
