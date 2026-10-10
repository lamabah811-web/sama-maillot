<?php
/**
 * SAMA MAILLOT — suivi d'une commande passée SANS COMPTE (lien secret).
 *
 *   commande/suivi.php?c=<id>&j=<jeton de 64 caractères>
 *
 * Le lien est donné après la commande (et envoyé par e-mail si le client en a donné un). Sans le
 * bon jeton : erreur 404. Mêmes actions que pour un client connecté : annuler avant l'expédition,
 * confirmer la réception, contacter le vendeur, payer (paiement en ligne en attente).
 * Avis et réclamations demandent un compte : le client crée son mot de passe avec
 * « Mot de passe oublié » (son e-mail), puis retrouve ses commandes dans son compte.
 */
require __DIR__ . '/../../includes/config.php';

// Cette page contient un lien personnel : jamais indexée ni gardée en cache partagé.
header('Cache-Control: private, no-store');

$jeton = is_string($_GET['j'] ?? $_POST['j'] ?? null) ? ($_GET['j'] ?? $_POST['j']) : '';
$commande = commande_par_suivi((int) ($_GET['c'] ?? $_POST['c'] ?? 0), $jeton);
if ($commande === null) {
    afficher_erreur(404);
}
traiter_commandes_echues();
$commande = commande_par_suivi((int) $commande['id'], $jeton);
$id = (int) $commande['id'];
$clientId = (int) $commande['client_id'];
$lien_suivi = 'commande/suivi.php?c=' . $id . '&j=' . $jeton;
$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifier_csrf()) {
        $erreurs['general'] = 'Votre session a expiré. Merci de réessayer.';
    } else {
        try {
            if (champ_post('action') === 'annuler') {
                changer_statut_commande($id, 'annulee', 'client', $clientId, champ_post('motif'));
                message_flash('succes', 'Votre commande ' . $commande['numero'] . ' est annulée.');
                rediriger($lien_suivi);
            }
            if (champ_post('action') === 'recue') {
                changer_statut_commande($id, 'recue', 'client', $clientId, 'Réception confirmée par le client');
                message_flash('succes', 'Merci ! Vous avez confirmé la réception de votre commande.');
                rediriger($lien_suivi);
            }
        } catch (ErreurCommande $exception) {
            $erreurs['general'] = $exception->getMessage();
            $commande = commande_par_suivi($id, $jeton);
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
$autres_commandes = commandes_du_meme_panier($commande, $jeton);

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

$titre_page = 'Suivi de la commande ' . $commande['numero'];
$sans_indexation = true;

require __DIR__ . '/../../includes/entete.php';
?>

<section class="py-4 py-md-5">
    <div class="container">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <h1 class="sm-titre-section mb-0 me-2"><?= e($commande['numero']) ?></h1>
            <?= badge_statut_commande($statut) ?>
        </div>
        <p class="text-body-secondary mb-4">
            Commande de <strong><?= e($commande['client_nom']) ?></strong> · <?= e(date_commande($commande['cree_le'])) ?>
        </p>

        <div class="sm-encadre-info small mb-4">
            <i class="bi bi-bookmark-star" aria-hidden="true"></i>
            <strong>Gardez ce lien</strong> (ajoutez cette page à vos favoris) : il vous permet de suivre votre commande sans compte.
            <?php if (!empty($commande['email_client'])): ?>
                Il vous a aussi été envoyé à <strong><?= e($commande['email_client']) ?></strong>.
            <?php endif; ?>
        </div>

        <?= alerte_formulaire($erreurs) ?>

        <div class="row g-4">
            <div class="col-12 col-lg-7">
                <?php if ($code !== null): ?>
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
                    <p class="small text-body-secondary mt-2 mb-0">
                        <?= $commande['mode_paiement'] === 'en_ligne'
                            ? ($commande['payee_le'] !== null ? 'Payé en ligne : SAMA MAILLOT garde l\'argent jusqu\'à la réception.' : 'Paiement en ligne en attente.')
                            : 'Paiement à la livraison, au vendeur (espèces ou Wave).' ?>
                    </p>
                    <?php if ($remboursement !== null): ?>
                        <p class="small mt-2 mb-0">
                            <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
                            <?= $remboursement['statut'] === 'rembourse'
                                ? 'Remboursé : ' . e(format_fcfa((int) $remboursement['montant'])) . ' (réf. ' . e($remboursement['reference']) . ').'
                                : 'Remboursement de ' . e(format_fcfa((int) $remboursement['montant'])) . ' en cours.' ?>
                        </p>
                    <?php endif; ?>
                </div>

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
                <?php if ($peut_payer): ?>
                    <div class="sm-tuile mb-3">
                        <h2 class="h6 fw-bold"><i class="bi bi-credit-card" aria-hidden="true"></i> Paiement en attente</h2>
                        <a class="btn btn-or w-100" href="<?= e($transaction['url_paiement']) ?>">Payer <?= e(format_fcfa((int) $transaction['montant'])) ?></a>
                    </div>
                <?php endif; ?>

                <div class="sm-tuile mb-3">
                    <h2 class="h6 fw-bold"><i class="bi bi-shop" aria-hidden="true"></i> Vendeur</h2>
                    <p class="mb-2"><a href="<?= e(url_boutique($commande['boutique_slug'])) ?>"><?= e($commande['boutique_nom']) ?></a></p>
                    <a class="btn btn-contour w-100" href="<?= e(url('whatsapp.php?suivi=' . $id . '&j=' . $jeton)) ?>" target="_blank" rel="noopener nofollow">
                        <i class="bi bi-whatsapp" aria-hidden="true"></i> Contacter le vendeur
                    </a>
                </div>

                <div class="sm-tuile mb-3">
                    <h2 class="h6 fw-bold"><i class="bi bi-truck" aria-hidden="true"></i> Livraison</h2>
                    <?php if ($commande['mode_livraison'] === 'retrait'): ?>
                        <p class="mb-0">Retrait sur place : <?= e($commande['adresse_retrait']) ?></p>
                    <?php else: ?>
                        <p class="mb-0">
                            <?= e($commande['adresse_quartier']) ?>, <?= e($commande['adresse_ville']) ?> (<?= e($commande['zone_nom']) ?>)
                            <br><span class="small"><?= e(formater_telephone($commande['telephone_client'])) ?></span>
                        </p>
                    <?php endif; ?>
                </div>

                <?php if ($peut_confirmer_reception): ?>
                    <form method="post" class="sm-tuile mb-3">
                        <?= champ_csrf() ?>
                        <input type="hidden" name="c" value="<?= e($id) ?>">
                        <input type="hidden" name="j" value="<?= e($jeton) ?>">
                        <input type="hidden" name="action" value="recue">
                        <h2 class="h6 fw-bold">Vous avez reçu votre maillot ?</h2>
                        <button class="btn btn-or w-100" type="submit"><i class="bi bi-check2-circle" aria-hidden="true"></i> J'ai bien reçu ma commande</button>
                    </form>
                <?php endif; ?>

                <?php if ($peut_annuler): ?>
                    <form method="post" class="sm-tuile mb-3">
                        <?= champ_csrf() ?>
                        <input type="hidden" name="c" value="<?= e($id) ?>">
                        <input type="hidden" name="j" value="<?= e($jeton) ?>">
                        <input type="hidden" name="action" value="annuler">
                        <h2 class="h6 fw-bold">Annuler la commande</h2>
                        <label class="form-label small" for="motif">
                            Motif <?= $motif_obligatoire ? '(obligatoire, le vendeur a déjà confirmé)' : '(facultatif)' ?>
                        </label>
                        <input class="form-control mb-2" type="text" id="motif" name="motif" maxlength="255" <?= $motif_obligatoire ? 'required' : '' ?>>
                        <button class="btn btn-contour sm-bouton-danger w-100" type="submit">Annuler ma commande</button>
                    </form>
                <?php endif; ?>

                <?php if ($autres_commandes !== []): ?>
                    <div class="sm-tuile mb-3">
                        <h2 class="h6 fw-bold">Vos autres commandes de ce panier</h2>
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($autres_commandes as $autre): ?>
                                <li class="mb-1">
                                    <a href="<?= e(url('commande/suivi.php?c=' . (int) $autre['id'] . '&j=' . $jeton)) ?>"><?= e($autre['numero']) ?></a>
                                    · <?= e($autre['boutique_nom']) ?> · <?= badge_statut_commande($autre['statut']) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="sm-tuile">
                    <h2 class="h6 fw-bold"><i class="bi bi-person-plus" aria-hidden="true"></i> Noter le vendeur, faire une réclamation</h2>
                    <p class="small text-body-secondary mb-2">
                        Ces services demandent un compte.
                        <?php if (!empty($commande['email_client'])): ?>
                            Créez votre mot de passe avec votre e-mail : vous retrouverez cette commande dans votre compte.
                        <?php else: ?>
                            Contactez le vendeur sur WhatsApp en cas de problème, ou écrivez au support.
                        <?php endif; ?>
                    </p>
                    <?php if (!empty($commande['email_client'])): ?>
                        <a class="btn btn-contour w-100" href="<?= e(url_page('mot-de-passe-oublie')) ?>">Créer mon mot de passe</a>
                    <?php else: ?>
                        <a class="btn btn-contour w-100" href="<?= e(url_page('contact')) ?>">Contacter le support</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
