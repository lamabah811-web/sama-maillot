<?php
/**
 * SAMA MAILLOT — espace vendeur : détail d'une commande et actions (§12.4, §14).
 *
 * Actions selon le statut (matrice §12.4) :
 *   À confirmer : confirmer / refuser (motif obligatoire)
 *   Confirmée   : marquer expédiée / annuler (motif) / client injoignable
 *   Expédiée    : paiement à la livraison → « Marquer comme livrée » (pas de code) ;
 *                 paiement en ligne (lot 8) → code de livraison (5 essais) ou livrée sans code ;
 *                 client injoignable ou refus
 * Le code de livraison n'est JAMAIS affiché ici (§14.1). Commande d'une autre boutique → 404.
 */
require __DIR__ . '/../../includes/config.php';

[$utilisateur, $boutique] = exiger_vendeur();
traiter_commandes_echues();

$commande = commande_de_la_boutique((int) ($_GET['id'] ?? $_POST['id'] ?? 0), (int) $boutique['id']);
if ($commande === null) {
    afficher_erreur(404);
}
$id = (int) $commande['id'];
$vendeurId = (int) $utilisateur['id'];
$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifier_csrf()) {
        $erreurs['general'] = 'Votre session a expiré. Merci de réessayer.';
    } else {
        $action = champ_post('action');
        $motif = champ_post('motif');

        try {
            $message = match ($action) {
                'confirmer' => [changer_statut_commande($id, 'confirmee', 'vendeur', $vendeurId), 'Commande confirmée. Le client est prévenu.'][1],
                'refuser', 'annuler' => [changer_statut_commande($id, 'annulee', 'vendeur', $vendeurId, $motif), 'Commande annulée. Le stock a été remis en vente.'][1],
                'expedier' => [changer_statut_commande($id, 'expediee', 'vendeur', $vendeurId), 'Commande marquée expédiée.'][1],
                'livree' => [changer_statut_commande($id, 'livree', 'vendeur', $vendeurId, 'Remis au client (paiement à la livraison)', ['avec_code' => false]),
                    'Commande marquée livrée. Le client peut confirmer la réception ; sinon elle se termine automatiquement après 48 h.'][1],
                'sans_code' => [changer_statut_commande($id, 'livree', 'vendeur', $vendeurId, 'Livraison déclarée sans code', ['avec_code' => false]),
                    'Livraison déclarée sans code. Le client a 48 h pour signaler un problème.'][1],
                'injoignable' => [changer_statut_commande($id, 'annulee', 'vendeur', $vendeurId,
                    'Client injoignable ou refus à la livraison', ['refus_livraison' => true]), 'Commande annulée (client injoignable ou refus). Le stock a été remis en vente.'][1],
                default => null,
            };

            if ($action === 'code') {
                $resultat = verifier_code_livraison($commande, champ_post('code'), $vendeurId);
                if (!$resultat['ok']) {
                    $erreurs['code'] = $resultat['message'];
                } else {
                    $message = $resultat['message'];
                }
            }

            if ($message !== null) {
                message_flash('succes', $message);
                rediriger('vendeur/commande.php?id=' . $id);
            }
        } catch (ErreurCommande $exception) {
            $erreurs['general'] = $exception->getMessage();
        }
        $commande = commande_de_la_boutique($id, (int) $boutique['id']);
    }
}

$lignes = lignes_de_la_commande($id);
$historique = historique_de_la_commande($id);
$statut = $commande['statut'];
$code_bloque = (int) $commande['code_essais'] >= CODE_LIVRAISON_ESSAIS_MAX;
$avec_code = !empty($commande['code_livraison_chiffre']);   // paiement en ligne uniquement (lot 8)

$titre_page = 'Commande ' . $commande['numero'];
$sans_indexation = true;
$page_active = 'vendeur';

require __DIR__ . '/../../includes/entete.php';
?>

<section class="py-4 py-md-5">
    <div class="container">
        <?= menu_vendeur('commandes') ?>

        <div class="d-flex flex-wrap align-items-center gap-2 mb-4">
            <a class="btn btn-sm btn-contour" href="<?= e(url('vendeur/commandes.php')) ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Commandes</a>
            <h1 class="sm-titre-section mb-0 mx-2"><?= e($commande['numero']) ?></h1>
            <?= badge_statut_commande($statut) ?>
            <?php if ($restant = delai_confirmation_restant($commande)): ?>
                <span class="small sm-delai"><i class="bi bi-hourglass-split" aria-hidden="true"></i> <?= e($restant) ?> pour confirmer, sinon annulation automatique</span>
            <?php endif; ?>
        </div>

        <?= alerte_formulaire(array_intersect_key($erreurs, ['general' => 1])) ?>

        <?php if (($litige = litige_de_la_commande($id)) !== null): ?>
            <div class="alert <?= $litige['statut'] === 'ouvert' ? 'alert-warning' : 'alert-secondary' ?> d-flex flex-wrap align-items-center gap-2" role="status">
                <i class="bi bi-shield-exclamation" aria-hidden="true"></i>
                Réclamation du client : <?= e(MOTIFS_LITIGE[$litige['motif']] ?? $litige['motif']) ?> · <?= badge_statut_litige($litige) ?>
                <a class="btn btn-sm btn-or ms-auto" href="<?= e(url('vendeur/litige.php?id=' . (int) $litige['id'])) ?>">
                    <?= $litige['statut'] === 'ouvert' ? 'Répondre' : 'Voir' ?>
                </a>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-12 col-lg-7">
                <div class="sm-tuile mb-3">
                    <h2 class="h6 fw-bold">Articles à préparer</h2>
                    <ul class="sm-recap-articles">
                        <?php foreach ($lignes as $ligne): ?>
                            <li>
                                <span>
                                    <?= e($ligne['quantite']) ?> × <?= e($ligne['titre']) ?>
                                    <span class="small text-body-secondary d-block">
                                        Taille <strong><?= e($ligne['taille']) ?></strong>
                                        <?php if (libelle_flocage($ligne) !== ''): ?>
                                            · Flocage <strong><?= e(libelle_flocage($ligne)) ?></strong>
                                        <?php endif; ?>
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
                        <dd><?= (int) $commande['frais_a_convenir'] === 1 ? 'À convenir avec le client' : e(format_fcfa((int) $commande['frais_livraison'])) ?></dd>
                        <?php if ($commande['mode_paiement'] === 'en_ligne'): ?>
                            <dt>Payé en ligne par le client</dt>
                            <dd><?= e(format_fcfa((int) $commande['total'])) ?></dd>
                            <?php if ((int) $commande['frais_transaction'] > 0): ?>
                                <dt>Frais de transaction</dt>
                                <dd>− <?= e(format_fcfa((int) $commande['frais_transaction'])) ?></dd>
                            <?php endif; ?>
                            <dt class="sm-recap__total">Vous recevrez</dt>
                            <dd class="sm-recap__total"><?= e(format_fcfa((int) $commande['montant_vendeur'])) ?></dd>
                        <?php else: ?>
                            <dt class="sm-recap__total">À encaisser à la livraison</dt>
                            <dd class="sm-recap__total"><?= e(format_fcfa((int) $commande['total'])) ?></dd>
                        <?php endif; ?>
                    </dl>
                    <?php if ($commande['mode_paiement'] === 'en_ligne'): ?>
                        <p class="small mt-2 mb-0">
                            <i class="bi bi-shield-check text-success" aria-hidden="true"></i>
                            <strong>Ne demandez aucun paiement au client.</strong> L'argent est dans votre
                            <a href="<?= e(url('vendeur/portefeuille.php')) ?>">portefeuille</a> (« en attente ») ; il devient disponible
                            à la réception, puis vous est versé par Wave. À la remise, demandez le <strong>code de livraison</strong> au client.
                        </p>
                    <?php endif; ?>
                    <?php if ((int) $commande['frais_a_convenir'] === 1): ?>
                        <p class="small sm-texte-attention mt-2 mb-0">
                            <i class="bi bi-info-circle" aria-hidden="true"></i>
                            Vous n'avez pas de tarif pour la zone « <?= e($commande['zone_nom']) ?> » : convenez des frais de livraison
                            avec le client avant de confirmer. Pensez à <a href="<?= e(url('vendeur/livraison.php')) ?>">fixer vos tarifs</a>.
                        </p>
                    <?php endif; ?>
                </div>

                <div class="sm-tuile">
                    <h2 class="h6 fw-bold">Historique</h2>
                    <ol class="sm-suivi">
                        <?php foreach ($historique as $etape): ?>
                            <li>
                                <strong><?= e(STATUTS_COMMANDE[$etape['nouveau_statut']][0] ?? $etape['nouveau_statut']) ?></strong>
                                <span class="small text-body-secondary"><?= e(date_commande($etape['cree_le'])) ?>
                                    · <?= e(['client' => 'client', 'vendeur' => 'vous', 'admin' => 'administration', 'systeme' => 'automatique'][$etape['auteur_role']]) ?></span>
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
                    <h2 class="h6 fw-bold"><i class="bi bi-person" aria-hidden="true"></i> Client</h2>
                    <p class="mb-1"><?= e($commande['client_nom']) ?></p>
                    <p class="mb-2">
                        <a href="tel:<?= e($commande['telephone_client']) ?>"><?= e(formater_telephone($commande['telephone_client'])) ?></a>
                    </p>
                    <?php if ($commande['mode_livraison'] === 'retrait'): ?>
                        <p class="small mb-0"><strong>Retrait sur place</strong> à votre adresse de retrait.</p>
                    <?php else: ?>
                        <p class="small mb-0">
                            <strong><?= e($commande['adresse_quartier']) ?>, <?= e($commande['adresse_ville']) ?></strong> (<?= e($commande['zone_nom']) ?>)
                            <?= $commande['adresse_repere'] !== '' ? '<br>' . e($commande['adresse_repere']) : '' ?>
                        </p>
                    <?php endif; ?>
                </div>

                <?php if ($statut === 'attente_confirmation'): ?>
                    <div class="sm-tuile mb-3">
                        <h2 class="h6 fw-bold">Confirmer la commande</h2>
                        <p class="small text-body-secondary">Vérifiez que vous avez bien le maillot et pouvez livrer.</p>
                        <form method="post" class="mb-3">
                            <?= champ_csrf() ?>
                            <input type="hidden" name="id" value="<?= e($id) ?>">
                            <input type="hidden" name="action" value="confirmer">
                            <button class="btn btn-or w-100" type="submit"><i class="bi bi-check2-circle" aria-hidden="true"></i> Confirmer</button>
                        </form>
                        <form method="post">
                            <?= champ_csrf() ?>
                            <input type="hidden" name="id" value="<?= e($id) ?>">
                            <input type="hidden" name="action" value="refuser">
                            <label class="form-label small" for="motif-refus">Ou refuser (motif obligatoire)</label>
                            <input class="form-control mb-2" type="text" id="motif-refus" name="motif" maxlength="255" required placeholder="Ex. : taille plus disponible">
                            <button class="btn btn-contour sm-bouton-danger w-100" type="submit">Refuser la commande</button>
                        </form>
                    </div>
                <?php endif; ?>

                <?php if ($statut === 'confirmee'): ?>
                    <div class="sm-tuile mb-3">
                        <h2 class="h6 fw-bold">Expédition</h2>
                        <form method="post" class="mb-3">
                            <?= champ_csrf() ?>
                            <input type="hidden" name="id" value="<?= e($id) ?>">
                            <input type="hidden" name="action" value="expedier">
                            <button class="btn btn-or w-100" type="submit"><i class="bi bi-truck" aria-hidden="true"></i> Marquer comme expédiée</button>
                        </form>
                        <form method="post">
                            <?= champ_csrf() ?>
                            <input type="hidden" name="id" value="<?= e($id) ?>">
                            <input type="hidden" name="action" value="annuler">
                            <label class="form-label small" for="motif-annulation">Annuler (motif obligatoire)</label>
                            <input class="form-control mb-2" type="text" id="motif-annulation" name="motif" maxlength="255" required>
                            <button class="btn btn-contour sm-bouton-danger w-100" type="submit">Annuler la commande</button>
                        </form>
                    </div>
                <?php endif; ?>

                <?php if ($statut === 'expediee'): ?>
                    <div class="sm-tuile mb-3">
                        <h2 class="h6 fw-bold">Remise du maillot</h2>
                        <?php if (!$avec_code): ?>
                            <!-- Paiement à la livraison : pas de code, le vendeur confirme la remise -->
                            <p class="small text-body-secondary">Une fois le maillot remis et payé par le client, marquez la commande comme livrée.</p>
                            <form method="post">
                                <?= champ_csrf() ?>
                                <input type="hidden" name="id" value="<?= e($id) ?>">
                                <input type="hidden" name="action" value="livree">
                                <button class="btn btn-or w-100" type="submit"><i class="bi bi-box-seam" aria-hidden="true"></i> Marquer comme livrée</button>
                            </form>
                        <?php elseif (!$code_bloque): ?>
                            <form method="post" class="mb-3" novalidate>
                                <?= champ_csrf() ?>
                                <input type="hidden" name="id" value="<?= e($id) ?>">
                                <input type="hidden" name="action" value="code">
                                <label class="form-label small" for="code">Code de livraison donné par le client</label>
                                <input class="form-control sm-champ-code<?= classe_erreur($erreurs, 'code') ?>" type="text" id="code" name="code"
                                       inputmode="numeric" pattern="[0-9]{4}" maxlength="4" placeholder="••••" autocomplete="off"
                                       <?= aria_erreur($erreurs, 'code') ?>>
                                <?= message_erreur($erreurs, 'code') ?>
                                <span class="form-text d-block mb-2">Essais restants : <?= e(CODE_LIVRAISON_ESSAIS_MAX - (int) $commande['code_essais']) ?></span>
                                <button class="btn btn-or w-100" type="submit"><i class="bi bi-shield-check" aria-hidden="true"></i> Valider la livraison</button>
                            </form>
                        <?php else: ?>
                            <p class="alert alert-warning small">Code bloqué après 5 essais : l'administration est alertée. Vous pouvez déclarer la livraison sans code.</p>
                        <?php endif; ?>
                        <?php if ($avec_code): ?>
                        <form method="post" class="mb-3">
                            <?= champ_csrf() ?>
                            <input type="hidden" name="id" value="<?= e($id) ?>">
                            <input type="hidden" name="action" value="sans_code">
                            <button class="btn btn-contour w-100" type="submit">Client absent : déclarer livrée sans code</button>
                            <span class="form-text">Le client sera prévenu par e-mail et pourra signaler un problème pendant 48 h.</span>
                        </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (in_array($statut, ['confirmee', 'expediee'], true)): ?>
                    <form method="post" class="sm-tuile">
                        <?= champ_csrf() ?>
                        <input type="hidden" name="id" value="<?= e($id) ?>">
                        <input type="hidden" name="action" value="injoignable">
                        <h2 class="h6 fw-bold">Client injoignable ou refus à la livraison</h2>
                        <p class="small text-body-secondary">La commande sera annulée et le stock remis en vente. Au 3e refus, le paiement à la livraison est bloqué pour ce client.</p>
                        <button class="btn btn-contour sm-bouton-danger w-100" type="submit">Signaler et annuler</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
