<?php
/**
 * SAMA MAILLOT — panier (§11).
 *
 * Ouvert à tous (panier invité gardé 7 jours) ; la connexion n'est demandée qu'au moment
 * de commander. Affichage regroupé par boutique : une boutique = une commande, avec ses
 * propres frais de livraison selon la zone choisie. Prix et stock recalculés à chaque affichage.
 *
 * Actions POST : ajouter (depuis la fiche), quantite, supprimer, zone, retirer_problemes.
 */
require __DIR__ . '/../../includes/config.php';

$utilisateur = utilisateur_connecte();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifier_csrf()) {
        message_flash('erreur', 'Votre session a expiré. Merci de réessayer.');
        rediriger('panier/');
    }

    $action = champ_post('action');

    if ($action === 'ajouter') {
        $maillotId = (int) champ_post('maillot_id');
        $erreur = ajouter_au_panier($maillotId, champ_post('taille'), (int) champ_post('quantite'),
            champ_post('flocage_nom'), champ_post('flocage_numero'));

        if ($erreur !== null) {
            $maillot = maillot_par_id($maillotId);
            if ($maillot === null) {
                message_flash('erreur', $erreur);
                rediriger('panier/');
            }
            // Message affiché à côté du bouton (pas en haut de page) et choix du client conservés.
            $_SESSION['achat_refuse'] = [
                'maillot_id' => $maillotId, 'message' => $erreur, 'taille' => champ_post('taille'),
                'quantite' => (int) champ_post('quantite'), 'flocage_nom' => champ_post('flocage_nom'),
                'flocage_numero' => champ_post('flocage_numero'),
            ];
            rediriger('maillot/' . $maillot['slug'] . '-' . (int) $maillot['id'] . '#form-achat');
        }
        message_flash('succes', 'Maillot ajouté à votre panier.');
    } elseif ($action === 'quantite') {
        modifier_quantite_ligne((int) champ_post('ligne_id'), (int) champ_post('quantite'));
    } elseif ($action === 'supprimer') {
        supprimer_ligne_panier((int) champ_post('ligne_id'));
        message_flash('succes', 'Article retiré du panier.');
    } elseif ($action === 'retirer_problemes') {
        foreach (lignes_du_panier() as $ligne) {
            if ($ligne['probleme'] !== null && $ligne['probleme'] !== 'stock') {
                supprimer_ligne_panier((int) $ligne['id']);
            } elseif ($ligne['probleme'] === 'stock') {
                modifier_quantite_ligne((int) $ligne['id'], $ligne['stock']);
            }
        }
        message_flash('succes', 'Votre panier a été mis à jour.');
    } elseif ($action === 'zone') {
        $zone = (int) champ_post('zone_id');
        $_SESSION['zone_livraison'] = in_array($zone, array_map('intval', array_column(zones_actives(), 'id')), true) ? $zone : 0;
    }

    rediriger('panier/');
}

// ---- Affichage : tout est recalculé côté serveur ----
$lignes = lignes_du_panier();
$groupes = panier_par_boutique($lignes);
$zones = zones_actives();
$zone_id = zone_choisie();

$prix_changes = array_values(array_filter($lignes, static fn (array $l): bool => $l['prix_change'] && $l['probleme'] === null));
$problemes = array_values(array_filter($lignes, static fn (array $l): bool => $l['probleme'] !== null));
marquer_prix_vus($lignes);   // les nouveaux prix sont maintenant affichés : on ne les signalera plus

// Frais par boutique pour la zone choisie (estimation ; recalculés à la validation)
$total_articles = 0;
$total_frais = 0;
$frais_connus = $zone_id !== null;
foreach ($groupes as $boutiqueId => &$groupe) {
    $total_articles += $groupe['sous_total'];
    $groupe['tarif'] = $zone_id !== null ? tarif_boutique_zone($boutiqueId, $zone_id) : null;
    if ($groupe['tarif'] !== null) {
        $total_frais += $groupe['tarif']['tarif'];
    } elseif ($zone_id !== null && !$groupe['boutique']['retrait_sur_place']) {
        $frais_connus = false;
    }
}
unset($groupe);

$titre_page = 'Mon panier';
$sans_indexation = true;
$page_active = 'panier';

require __DIR__ . '/../../includes/entete.php';
?>

<section class="py-4 py-md-5">
    <div class="container">
        <span class="sm-sur-titre">Panier</span>
        <h1 class="sm-titre-section mb-4">
            Mon panier
            <?php if ($nombre_articles_panier > 0): ?>
                <span class="text-body-secondary fw-normal fs-5">(<?= e($libelle_panier) ?>)</span>
            <?php endif; ?>
        </h1>

        <?php if ($lignes === []): ?>
            <div class="sm-etat-vide">
                <div class="sm-etat-vide__icone" aria-hidden="true"><i class="bi bi-bag"></i></div>
                <h2 class="h4">Votre panier est vide</h2>
                <p>Parcourez le catalogue et ajoutez le maillot de votre équipe.</p>
                <a class="btn btn-or" href="<?= e(url_page('catalogue')) ?>">Voir le catalogue</a>
            </div>
        <?php else: ?>

            <?php if (!peut_acheter($utilisateur)): ?>
                <div class="alert alert-info" role="note">Un compte administrateur ne peut pas passer de commande.</div>
            <?php endif; ?>

            <?php if ($prix_changes !== []): ?>
                <div class="alert alert-warning" role="alert">
                    <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                    Le prix de <?= count($prix_changes) > 1 ? count($prix_changes) . ' articles a' : '1 article a' ?> changé depuis votre ajout.
                    Les prix affichés ci-dessous sont les prix actuels.
                </div>
            <?php endif; ?>

            <?php if ($problemes !== []): ?>
                <div class="alert alert-danger d-flex flex-wrap align-items-center justify-content-between gap-2" role="alert">
                    <span>
                        <i class="bi bi-exclamation-octagon" aria-hidden="true"></i>
                        Certains articles ne peuvent pas être commandés en l'état (voir ci-dessous).
                    </span>
                    <form method="post">
                        <?= champ_csrf() ?>
                        <input type="hidden" name="action" value="retirer_problemes">
                        <button class="btn btn-sm btn-contour" type="submit">Corriger mon panier</button>
                    </form>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Articles, regroupés par boutique -->
                <div class="col-12 col-lg-8">
                    <?php foreach ($groupes as $groupe): ?>
                        <section class="sm-tuile sm-panier-boutique mb-3" aria-labelledby="boutique-<?= e($groupe['boutique']['id']) ?>">
                            <header class="sm-panier-boutique__entete">
                                <h2 class="h6 mb-0" id="boutique-<?= e($groupe['boutique']['id']) ?>">
                                    <i class="bi bi-shop" aria-hidden="true"></i>
                                    <a href="<?= e(url_boutique($groupe['boutique']['slug'])) ?>"><?= e($groupe['boutique']['nom']) ?></a>
                                </h2>
                                <span class="small text-body-secondary">1 commande · livraison par le vendeur</span>
                            </header>

                            <ul class="sm-panier-lignes">
                                <?php foreach ($groupe['lignes'] as $ligne): ?>
                                    <li class="sm-panier-ligne<?= $ligne['probleme'] !== null ? ' sm-panier-ligne--probleme' : '' ?>">
                                        <a class="sm-panier-ligne__image" href="<?= e(url_maillot(['id' => $ligne['maillot_id'], 'slug' => $ligne['slug']])) ?>" tabindex="-1" aria-hidden="true">
                                            <?php if ($ligne['miniature']): ?>
                                                <img src="<?= e(url_image_maillot($ligne['miniature'])) ?>" alt="" width="400" height="400" loading="lazy">
                                            <?php else: ?>
                                                <i class="bi bi-image"></i>
                                            <?php endif; ?>
                                        </a>
                                        <div class="sm-panier-ligne__infos">
                                            <a class="sm-panier-ligne__titre" href="<?= e(url_maillot(['id' => $ligne['maillot_id'], 'slug' => $ligne['slug']])) ?>"><?= e($ligne['titre']) ?></a>
                                            <span class="small text-body-secondary">
                                                Taille <?= e($ligne['taille']) ?>
                                                <?php if ($ligne['flocage_nom'] !== ''): ?>
                                                    · Flocage <?= e($ligne['flocage_nom'] . ' ' . $ligne['flocage_numero']) ?>
                                                <?php endif; ?>
                                            </span>
                                            <span class="small"><?= e(format_fcfa($ligne['prix_unitaire'])) ?> l'unité</span>
                                            <?php if ($ligne['probleme'] !== null): ?>
                                                <span class="sm-panier-ligne__alerte"><i class="bi bi-exclamation-circle" aria-hidden="true"></i> <?= e(message_probleme_ligne($ligne)) ?></span>
                                            <?php elseif ($ligne['prix_change']): ?>
                                                <span class="sm-panier-ligne__alerte sm-panier-ligne__alerte--info">
                                                    <i class="bi bi-info-circle" aria-hidden="true"></i> Prix modifié (avant : <?= e(format_fcfa((int) $ligne['prix_vu'])) ?>)
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="sm-panier-ligne__actions">
                                            <form method="post" class="sm-panier-quantite">
                                                <?= champ_csrf() ?>
                                                <input type="hidden" name="action" value="quantite">
                                                <input type="hidden" name="ligne_id" value="<?= e($ligne['id']) ?>">
                                                <label class="visually-hidden" for="quantite-<?= e($ligne['id']) ?>">Quantité</label>
                                                <select class="form-select form-select-sm" id="quantite-<?= e($ligne['id']) ?>" name="quantite" data-envoi-auto>
                                                    <?php $quantite_max = max((int) $ligne['quantite'], min(QUANTITE_MAX_PAR_LIGNE, $ligne['stock'])); ?>
                                                    <?php for ($q = 1; $q <= max(1, $quantite_max); $q++): ?>
                                                        <option value="<?= $q ?>"<?= $q === (int) $ligne['quantite'] ? ' selected' : '' ?>><?= $q ?></option>
                                                    <?php endfor; ?>
                                                </select>
                                                <noscript><button class="btn btn-sm btn-contour" type="submit">OK</button></noscript>
                                            </form>
                                            <strong class="sm-panier-ligne__prix"><?= e(format_fcfa($ligne['total_ligne'])) ?></strong>
                                            <form method="post">
                                                <?= champ_csrf() ?>
                                                <input type="hidden" name="action" value="supprimer">
                                                <input type="hidden" name="ligne_id" value="<?= e($ligne['id']) ?>">
                                                <button class="btn btn-link btn-sm sm-lien-supprimer" type="submit" aria-label="Retirer <?= e($ligne['titre']) ?> du panier">
                                                    <i class="bi bi-trash3" aria-hidden="true"></i> Retirer
                                                </button>
                                            </form>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>

                            <footer class="sm-panier-boutique__pied">
                                <span>Sous-total</span>
                                <strong><?= e(format_fcfa($groupe['sous_total'])) ?></strong>
                                <span>Livraison<?= $zone_id !== null && $groupe['tarif'] !== null ? ' (' . e($groupe['tarif']['delai']) . ')' : '' ?></span>
                                <strong>
                                    <?php if ($zone_id === null): ?>
                                        <span class="fw-normal text-body-secondary small">Choisissez votre zone</span>
                                    <?php elseif ($groupe['tarif'] !== null): ?>
                                        <?= $groupe['tarif']['tarif'] === 0 ? 'Gratuite' : e(format_fcfa($groupe['tarif']['tarif'])) ?>
                                    <?php elseif ($groupe['boutique']['retrait_sur_place']): ?>
                                        <span class="fw-normal small">Retrait sur place uniquement (gratuit)</span>
                                    <?php else: ?>
                                        <span class="fw-normal small">À convenir avec le vendeur</span>
                                    <?php endif; ?>
                                </strong>
                            </footer>
                        </section>
                    <?php endforeach; ?>
                </div>

                <!-- Récapitulatif -->
                <div class="col-12 col-lg-4">
                    <aside class="sm-tuile sm-recap" aria-label="Récapitulatif">
                        <h2 class="h5 fw-bold">Récapitulatif</h2>

                        <form method="post" class="mb-3">
                            <?= champ_csrf() ?>
                            <input type="hidden" name="action" value="zone">
                            <label class="form-label small fw-semibold" for="zone_id">Zone de livraison</label>
                            <select class="form-select" id="zone_id" name="zone_id" data-envoi-auto>
                                <option value="">Choisir…</option>
                                <?php foreach ($zones as $zone): ?>
                                    <option value="<?= e($zone['id']) ?>"<?= (int) $zone['id'] === $zone_id ? ' selected' : '' ?>><?= e($zone['nom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <noscript><button class="btn btn-sm btn-contour mt-2" type="submit">Calculer</button></noscript>
                        </form>

                        <dl class="sm-recap__lignes">
                            <dt>Articles</dt>
                            <dd><?= e(format_fcfa($total_articles)) ?></dd>
                            <dt>Livraison (<?= count($groupes) ?> boutique<?= count($groupes) > 1 ? 's' : '' ?>)</dt>
                            <dd><?= $zone_id === null ? '—' : e(format_fcfa($total_frais)) ?></dd>
                            <dt class="sm-recap__total">Total</dt>
                            <dd class="sm-recap__total"><?= e(format_fcfa($total_articles + ($zone_id === null ? 0 : $total_frais))) ?></dd>
                        </dl>
                        <?php if (count($groupes) > 1): ?>
                            <p class="small text-body-secondary">
                                <i class="bi bi-info-circle" aria-hidden="true"></i>
                                <?= count($groupes) ?> boutiques = <?= count($groupes) ?> commandes, validées en une seule fois.
                            </p>
                        <?php endif; ?>

                        <?php if (peut_acheter($utilisateur)): ?>
                            <?php if ($problemes === []): ?>
                                <a class="btn btn-or btn-lg w-100" href="<?= e(url('panier/commander.php')) ?>">
                                    <i class="bi bi-lock" aria-hidden="true"></i> Commander
                                </a>
                                <?php if ($utilisateur === null): ?>
                                    <p class="small text-body-secondary text-center mt-2 mb-0">Vous vous connecterez à l'étape suivante.</p>
                                <?php endif; ?>
                            <?php else: ?>
                                <button class="btn btn-or btn-lg w-100" type="button" disabled>Commander</button>
                                <p class="small text-danger mt-2 mb-0">Corrigez d'abord les articles signalés.</p>
                            <?php endif; ?>
                        <?php endif; ?>

                        <a class="btn btn-link w-100 mt-2" href="<?= e(url_page('catalogue')) ?>">Continuer mes achats</a>
                    </aside>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
