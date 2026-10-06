<?php
/**
 * SAMA MAILLOT — détail d'une commande, côté client (§12, §14).
 *
 * - Code de livraison (paiement en ligne uniquement, lot 8) visible ICI seulement, jamais dans
 *   l'espace vendeur (§14.1). En paiement à la livraison, il n'y a pas de code.
 * - Actions : annuler (avant expédition ; motif obligatoire si déjà confirmée),
 *   confirmer la réception (expédiée ou livrée), contacter le vendeur sur WhatsApp.
 * - Contrôle d'accès : une commande d'un autre client renvoie une erreur 404.
 */
require __DIR__ . '/../../includes/config.php';

$utilisateur = exiger_connexion();
traiter_commandes_echues();

$commande = commande_du_client((int) ($_GET['id'] ?? $_POST['id'] ?? 0), (int) $utilisateur['id']);
if ($commande === null) {
    afficher_erreur(404);
}
$id = (int) $commande['id'];
$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifier_csrf()) {
        $erreurs['general'] = 'Votre session a expiré. Merci de réessayer.';
    } else {
        try {
            if (champ_post('action') === 'annuler') {
                changer_statut_commande($id, 'annulee', 'client', (int) $utilisateur['id'], champ_post('motif'));
                message_flash('succes', 'Votre commande ' . $commande['numero'] . ' est annulée.');
                rediriger('compte/commande.php?id=' . $id);
            }
            if (champ_post('action') === 'recue') {
                changer_statut_commande($id, 'recue', 'client', (int) $utilisateur['id'], 'Réception confirmée par le client');
                message_flash('succes', 'Merci ! Vous avez confirmé la réception de votre commande.');
                rediriger('compte/commande.php?id=' . $id);
            }
        } catch (ErreurCommande $exception) {
            $erreurs['general'] = $exception->getMessage();
            $commande = commande_du_client($id, (int) $utilisateur['id']);
        }
    }
}

$lignes = lignes_de_la_commande($id);
$historique = historique_de_la_commande($id);
$statut = $commande['statut'];
$code = in_array($statut, ['attente_confirmation', 'confirmee', 'expediee'], true) ? code_livraison_client($commande) : null;
$peut_annuler = transition_autorisee($statut, 'annulee', 'client');
$motif_obligatoire = !in_array($statut, ['attente_confirmation', 'attente_paiement'], true);
$peut_confirmer_reception = $statut !== 'en_litige' && transition_autorisee($statut, 'recue', 'client');
$litige = litige_de_la_commande($id);
$peut_reclamer = $litige === null && refus_reclamation($commande) === null;
$avis = avis_de_la_commande($id);

// Paiement en ligne : reprise du paiement tant qu'il est en cours, et suivi du remboursement.
$transaction = null;
if ($commande['transaction_id'] !== null) {
    $requete = bdd()->prepare('SELECT * FROM transactions_paiement WHERE id = :id');
    $requete->execute(['id' => $commande['transaction_id']]);
    $transaction = $requete->fetch() ?: null;
}
$peut_payer = $statut === 'attente_paiement' && $transaction !== null && $transaction['statut'] === 'initie'
    && strtotime($transaction['expire_le']) > time() && $transaction['url_paiement'] !== null;
$requete = bdd()->prepare('SELECT * FROM remboursements WHERE commande_id = :id');
$requete->execute(['id' => $id]);
$remboursement = $requete->fetch() ?: null;
$peut_noter = refus_avis($commande, $avis) === null;

$titre_page = 'Commande ' . $commande['numero'];
$sans_indexation = true;
$page_active = 'mes-commandes';

require __DIR__ . '/../../includes/entete.php';
?>

<section class="py-4 py-md-5">
    <div class="container">
        <nav aria-label="Fil d'Ariane">
            <ol class="breadcrumb sm-fil-ariane">
                <li class="breadcrumb-item"><a href="<?= e(url_page('compte')) ?>">Mon compte</a></li>
                <li class="breadcrumb-item"><a href="<?= e(url_page('mes-commandes')) ?>">Mes commandes</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= e($commande['numero']) ?></li>
            </ol>
        </nav>

        <div class="d-flex flex-wrap align-items-center gap-2 mb-4">
            <h1 class="sm-titre-section mb-0 me-2"><?= e($commande['numero']) ?></h1>
            <?= badge_statut_commande($statut) ?>
            <?php if ($commande['origine'] === 'whatsapp'): ?>
                <span class="badge text-bg-light border"><i class="bi bi-whatsapp" aria-hidden="true"></i> Via WhatsApp</span>
            <?php endif; ?>
        </div>

        <?= alerte_formulaire($erreurs) ?>

        <?php if (($_SESSION['ouvrir_whatsapp'] ?? 0) === $id): ?>
            <?php unset($_SESSION['ouvrir_whatsapp']); ?>
            <!-- Commande via WhatsApp (§17) : la discussion s'ouvre automatiquement, le bouton reste en secours -->
            <div class="sm-encadre-whatsapp mb-4">
                <p class="mb-2"><strong>Dernière étape :</strong> envoyez le message pré-rempli au vendeur sur WhatsApp.</p>
                <a class="btn btn-or" href="<?= e(url('whatsapp.php?commande=' . $id)) ?>" data-ouverture-auto rel="noopener nofollow">
                    <i class="bi bi-whatsapp" aria-hidden="true"></i> Ouvrir WhatsApp
                </a>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-12 col-lg-7">
                <?php if ($code !== null): ?>
                    <!-- Code de livraison (§14.1) -->
                    <div class="sm-code-livraison mb-3">
                        <span class="sm-code-livraison__titre"><i class="bi bi-shield-lock" aria-hidden="true"></i> Votre code de livraison</span>
                        <span class="sm-code-livraison__code" aria-label="Code <?= e(implode(' ', str_split($code))) ?>"><?= e($code) ?></span>
                        <span class="small">Donnez-le uniquement <strong>à la réception de votre maillot</strong>, jamais avant.</span>
                    </div>
                <?php endif; ?>

                <div class="sm-tuile mb-3">
                    <h2 class="h6 fw-bold">Articles</h2>
                    <ul class="sm-recap-articles">
                        <?php foreach ($lignes as $ligne): ?>
                            <li>
                                <span>
                                    <?= e($ligne['quantite']) ?> × <?= e($ligne['titre']) ?>
                                    <span class="small text-body-secondary d-block">
                                        Taille <?= e($ligne['taille']) ?><?= libelle_flocage($ligne) !== '' ? ' · Flocage ' . e(libelle_flocage($ligne)) : '' ?>
                                        · <?= e(format_fcfa((int) $ligne['prix_unitaire'])) ?> l'unité
                                    </span>
                                </span>
                                <strong><?= e(format_fcfa((int) $ligne['total_ligne'])) ?></strong>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <dl class="sm-recap__lignes mb-0">
                        <dt>Articles</dt>
                        <dd><?= e(format_fcfa((int) $commande['sous_total'])) ?></dd>
                        <dt>Livraison</dt>
                        <dd><?= (int) $commande['frais_a_convenir'] === 1 ? 'À convenir avec le vendeur' : ((int) $commande['frais_livraison'] === 0 ? 'Gratuite' : e(format_fcfa((int) $commande['frais_livraison']))) ?></dd>
                        <dt class="sm-recap__total">Total</dt>
                        <dd class="sm-recap__total"><?= e(format_fcfa((int) $commande['total'])) ?></dd>
                    </dl>
                    <?php if ($commande['mode_paiement'] === 'en_ligne'): ?>
                        <p class="small mt-2 mb-0">
                            <i class="bi bi-shield-check text-success" aria-hidden="true"></i>
                            <?php if ($commande['payee_le'] !== null): ?>
                                Payé en ligne le <?= e(date_commande($commande['payee_le'])) ?><?= $transaction !== null && $transaction['moyen'] !== null ? ' (' . e(MOYENS_PAIEMENT[$transaction['moyen']] ?? $transaction['moyen']) . ')' : '' ?>.
                                SAMA MAILLOT garde l'argent jusqu'à la réception.
                            <?php else: ?>
                                Paiement en ligne <?= $statut === 'attente_paiement' ? 'en attente' : 'non effectué' ?>.
                            <?php endif; ?>
                        </p>
                    <?php else: ?>
                        <p class="small text-body-secondary mt-2 mb-0">Paiement à la livraison, au vendeur (espèces ou Wave).</p>
                    <?php endif; ?>
                    <?php if ($remboursement !== null): ?>
                        <p class="small mt-2 mb-0 <?= $remboursement['statut'] === 'rembourse' ? 'text-success' : 'sm-texte-attention' ?>">
                            <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
                            <?= $remboursement['statut'] === 'rembourse'
                                ? 'Remboursé : ' . e(format_fcfa((int) $remboursement['montant'])) . ' le ' . e(date_commande($remboursement['rembourse_le'])) . ' (réf. ' . e($remboursement['reference']) . ').'
                                : 'Remboursement de ' . e(format_fcfa((int) $remboursement['montant'])) . ' en cours : SAMA MAILLOT vous rembourse sur le moyen utilisé pour payer.' ?>
                        </p>
                    <?php endif; ?>
                </div>

                <!-- Suivi (§12.4 : chaque changement est historisé) -->
                <div class="sm-tuile">
                    <h2 class="h6 fw-bold">Suivi</h2>
                    <ol class="sm-suivi">
                        <?php foreach ($historique as $etape): ?>
                            <li>
                                <strong><?= e(STATUTS_COMMANDE[$etape['nouveau_statut']][0] ?? $etape['nouveau_statut']) ?></strong>
                                <span class="small text-body-secondary"><?= e(date_commande($etape['cree_le'])) ?></span>
                                <?php if ($etape['motif'] !== null): ?>
                                    <span class="small d-block"><?= e($etape['motif']) ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            </div>

            <div class="col-12 col-lg-5">
                <div class="sm-tuile mb-3">
                    <h2 class="h6 fw-bold"><i class="bi bi-shop" aria-hidden="true"></i> Vendeur</h2>
                    <p class="mb-2"><a href="<?= e(url_boutique($commande['boutique_slug'])) ?>"><?= e($commande['boutique_nom']) ?></a></p>
                    <a class="btn btn-contour w-100" href="<?= e(url('whatsapp.php?commande=' . $id)) ?>" target="_blank" rel="noopener nofollow">
                        <i class="bi bi-whatsapp" aria-hidden="true"></i> Contacter le vendeur
                    </a>
                    <?php if (($lien_signaler = lien_signaler_vendeur($utilisateur, (int) $commande['boutique_id'])) !== null): ?>
                        <a class="d-inline-block small text-body-secondary mt-2" href="<?= e($lien_signaler) ?>">
                            <i class="bi bi-flag" aria-hidden="true"></i> Signaler un problème avec ce vendeur
                        </a>
                    <?php endif; ?>
                </div>

                <div class="sm-tuile mb-3">
                    <h2 class="h6 fw-bold"><i class="bi bi-truck" aria-hidden="true"></i> Livraison</h2>
                    <?php if ($commande['mode_livraison'] === 'retrait'): ?>
                        <p class="mb-0">Retrait sur place : <?= e($commande['adresse_retrait']) ?></p>
                    <?php else: ?>
                        <p class="mb-0">
                            <?= e($commande['adresse_quartier']) ?>, <?= e($commande['adresse_ville']) ?> (<?= e($commande['zone_nom']) ?>)
                            <?= $commande['adresse_repere'] !== '' ? '<br><span class="small text-body-secondary">' . e($commande['adresse_repere']) . '</span>' : '' ?>
                            <br><span class="small"><?= e(formater_telephone($commande['telephone_client'])) ?> · délai indicatif : <?= e($commande['delai_livraison']) ?></span>
                        </p>
                    <?php endif; ?>
                </div>

                <?php if ($peut_payer): ?>
                    <div class="sm-tuile mb-3">
                        <h2 class="h6 fw-bold"><i class="bi bi-credit-card" aria-hidden="true"></i> Paiement en attente</h2>
                        <p class="small text-body-secondary">Votre maillot est réservé jusqu'au <?= e(date_commande($transaction['expire_le'])) ?>. Passé ce délai, la commande sera annulée.</p>
                        <a class="btn btn-or w-100" href="<?= e($transaction['url_paiement']) ?>">Payer <?= e(format_fcfa((int) $transaction['montant'])) ?></a>
                    </div>
                <?php endif; ?>

                <?php if ($litige !== null): ?>
                    <!-- Réclamation (§16) -->
                    <div class="sm-tuile mb-3">
                        <h2 class="h6 fw-bold"><i class="bi bi-shield-exclamation" aria-hidden="true"></i> Réclamation</h2>
                        <p class="mb-2"><?= e(MOTIFS_LITIGE[$litige['motif']] ?? $litige['motif']) ?> · <?= badge_statut_litige($litige) ?></p>
                        <a class="btn btn-contour w-100" href="<?= e(url('compte/litige.php?id=' . (int) $litige['id'])) ?>">
                            <i class="bi bi-chat-dots" aria-hidden="true"></i> Voir la réclamation
                        </a>
                    </div>
                <?php endif; ?>

                <?php if ($peut_noter || $avis !== null): ?>
                    <!-- Avis sur le vendeur (§20) -->
                    <div class="sm-tuile mb-3">
                        <h2 class="h6 fw-bold"><i class="bi bi-star" aria-hidden="true"></i> Votre avis sur le vendeur</h2>
                        <?php if ($avis !== null): ?>
                            <p class="mb-2"><?= etoiles((int) $avis['note']) ?></p>
                        <?php else: ?>
                            <p class="small text-body-secondary">Votre note aide les autres clients à choisir.</p>
                        <?php endif; ?>
                        <?php if ($peut_noter): ?>
                            <a class="btn <?= $avis === null ? 'btn-or' : 'btn-contour' ?> w-100" href="<?= e(url('compte/avis.php?commande=' . $id)) ?>">
                                <?= $avis === null ? 'Noter le vendeur' : 'Modifier mon avis' ?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($peut_confirmer_reception): ?>
                    <form method="post" class="sm-tuile mb-3">
                        <?= champ_csrf() ?>
                        <input type="hidden" name="id" value="<?= e($id) ?>">
                        <input type="hidden" name="action" value="recue">
                        <h2 class="h6 fw-bold">Vous avez reçu votre maillot ?</h2>
                        <p class="small text-body-secondary">Confirmez-le pour clôturer la commande.</p>
                        <button class="btn btn-or w-100" type="submit"><i class="bi bi-check2-circle" aria-hidden="true"></i> J'ai bien reçu ma commande</button>
                    </form>
                <?php endif; ?>

                <?php if ($peut_reclamer): ?>
                    <div class="sm-tuile mb-3">
                        <h2 class="h6 fw-bold">Un problème avec la commande ?</h2>
                        <p class="small text-body-secondary">Maillot non reçu, différent de l'annonce, mauvaise taille… Ouvrez une réclamation : SAMA MAILLOT fait la médiation avec le vendeur.</p>
                        <a class="btn btn-contour sm-bouton-danger w-100" href="<?= e(url('compte/reclamation.php?commande=' . $id)) ?>">
                            <i class="bi bi-shield-exclamation" aria-hidden="true"></i> Ouvrir une réclamation
                        </a>
                    </div>
                <?php endif; ?>

                <?php if ($peut_annuler): ?>
                    <form method="post" class="sm-tuile">
                        <?= champ_csrf() ?>
                        <input type="hidden" name="id" value="<?= e($id) ?>">
                        <input type="hidden" name="action" value="annuler">
                        <h2 class="h6 fw-bold">Annuler la commande</h2>
                        <label class="form-label small" for="motif">
                            Motif <?= $motif_obligatoire ? '(obligatoire, le vendeur a déjà confirmé)' : '(facultatif)' ?>
                        </label>
                        <input class="form-control mb-2" type="text" id="motif" name="motif" maxlength="255"
                               <?= $motif_obligatoire ? 'required' : '' ?> placeholder="Ex. : je me suis trompé de taille">
                        <button class="btn btn-contour sm-bouton-danger w-100" type="submit">Annuler ma commande</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
