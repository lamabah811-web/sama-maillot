<?php
/**
 * SAMA MAILLOT — validation de la commande (§12.1).
 *
 * Étape 1 « adresse » : adresse enregistrée ou nouvelle (zone, ville, quartier, repère, téléphone).
 * Étape 2 « recap »   : une commande par boutique, frais selon la zone (ou retrait sur place),
 *                       paiement en ligne (séquestre) ou à la livraison, puis confirmation.
 *
 * Le serveur recalcule tout à la confirmation. Une « empreinte » des montants affichés est
 * envoyée avec le formulaire : si un prix, un stock ou un frais a changé entre-temps, la
 * commande n'est pas créée et le client revoit le nouveau récapitulatif.
 *
 * Paiement en ligne : commandes « en attente de paiement » (stock réservé), puis page de l'agrégateur.
 */
require __DIR__ . '/../../includes/config.php';

$utilisateur = exiger_connexion();

if (!peut_acheter($utilisateur)) {
    message_flash('erreur', 'Un compte administrateur ne peut pas passer de commande.');
    rediriger('panier/');
}

$lignes = lignes_du_panier();
if ($lignes === []) {
    rediriger('panier/');
}
if (array_filter($lignes, static fn (array $l): bool => $l['probleme'] !== null) !== []) {
    message_flash('erreur', 'Certains articles de votre panier ne peuvent pas être commandés. Corrigez-les d\'abord.');
    rediriger('panier/');
}

$groupes = panier_par_boutique($lignes);
$adresses = adresses_du_client((int) $utilisateur['id']);
$choix = $_SESSION['commande_en_cours'] ?? null;
$etape = ($_GET['etape'] ?? '') === 'recap' && is_array($choix) ? 'recap' : 'adresse';
$erreurs = [];
$valeurs = is_array($choix) ? $choix['adresse'] : [];

/**
 * Frais et mode de livraison de chaque boutique pour l'adresse choisie.
 * Mode par défaut : livraison. Sans tarif pour cette zone, la livraison reste possible avec des
 * frais « à convenir avec le vendeur » (0 FCFA affiché, fixés ensuite entre client et vendeur).
 */
function calculer_livraisons(array $groupes, array $adresse, array $modes): array
{
    $resultat = [];

    foreach ($groupes as $boutiqueId => $groupe) {
        $tarif = tarif_boutique_zone($boutiqueId, (int) $adresse['zone_id']);
        $retrait = $groupe['boutique']['retrait_sur_place'];
        $mode = ($modes[$boutiqueId] ?? 'livraison') === 'retrait' && $retrait ? 'retrait' : 'livraison';

        $resultat[$boutiqueId] = [
            'mode' => $mode,
            'tarif' => $tarif,
            'retrait' => $retrait,
            'possible' => true,
            'a_convenir' => $mode === 'livraison' && $tarif === null,
            'frais' => $mode === 'livraison' && $tarif !== null ? $tarif['tarif'] : 0,
        ];
    }

    return $resultat;
}

/** Empreinte des montants affichés (lignes, quantités, prix, frais, modes). */
function empreinte_commande(array $lignes, array $livraisons, array $adresse): string
{
    $donnees = [array_map(static fn (array $l): array => [$l['id'], $l['quantite'], $l['prix_unitaire']], $lignes),
        array_map(static fn (array $l): array => [$l['mode'], $l['frais']], $livraisons), $adresse['zone_id']];

    return hash('sha256', json_encode($donnees));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifier_csrf()) {
        message_flash('erreur', 'Votre session a expiré. Merci de réessayer.');
        rediriger('panier/commander.php');
    }

    $action = champ_post('action');

    if ($action === 'adresse') {
        // ---- Étape 1 → 2 ----
        [$adresse, $erreurs] = lire_adresse_post($utilisateur);
        $valeurs = $adresse + ['adresse_id' => champ_post('adresse_id')];

        if ($erreurs === []) {
            $_SESSION['commande_en_cours'] = ['adresse' => $adresse, 'modes' => []];
            $_SESSION['zone_livraison'] = $adresse['zone_id'];
            rediriger('panier/commander.php?etape=recap');
        }
    } elseif ($action === 'modes' && is_array($choix)) {
        // ---- Choix livraison / retrait (étape 2) ----
        $modes = [];
        foreach (array_keys($groupes) as $boutiqueId) {
            $modes[$boutiqueId] = ($_POST['mode'][$boutiqueId] ?? '') === 'retrait' ? 'retrait' : 'livraison';
        }
        $_SESSION['commande_en_cours']['modes'] = $modes;
        rediriger('panier/commander.php?etape=recap');
    } elseif ($action === 'confirmer' && is_array($choix)) {
        // ---- Confirmation : tout est recalculé ----
        $livraisons = calculer_livraisons($groupes, $choix['adresse'], $choix['modes']);

        if (!hash_equals(empreinte_commande($lignes, $livraisons, $choix['adresse']), champ_post('empreinte'))) {
            message_flash('erreur', 'Votre panier, un prix ou des frais de livraison ont changé. Vérifiez le nouveau récapitulatif avant de confirmer.');
            rediriger('panier/commander.php?etape=recap');
        }
        $paiement = champ_post('paiement');
        if (!in_array($paiement, ['livraison', 'en_ligne'], true) || ($paiement === 'en_ligne' && !paiement_en_ligne_actif())) {
            message_flash('erreur', 'Choisissez le mode de paiement.');
            rediriger('panier/commander.php?etape=recap');
        }

        // Téléphone vérifié par SMS avant la première commande (§12.1), si la vérification est active.
        if (VERIFICATION_SMS && $utilisateur['telephone_verifie_le'] === null) {
            demarrer_verification_telephone($utilisateur, $choix['adresse']['telephone'], 'panier/commander.php?etape=recap');
        }

        $articles = array_map(static fn (array $l): array => [
            'maillot_id' => (int) $l['maillot_id'], 'taille' => $l['taille'], 'flocage_nom' => $l['flocage_nom'],
            'flocage_numero' => $l['flocage_numero'], 'quantite' => (int) $l['quantite'],
        ], $lignes);
        $modes = array_map(static fn (array $l): string => $l['mode'], $livraisons);

        try {
            $creees = creer_commandes($utilisateur, $articles, $choix['adresse'], $modes, 'site', $paiement);
        } catch (ErreurCommande $exception) {
            message_flash('erreur', $exception->getMessage());
            rediriger($paiement === 'en_ligne' ? 'panier/commander.php?etape=recap' : 'panier/');
        }

        memoriser_adresse((int) $utilisateur['id'], $choix['adresse']);
        foreach ($lignes as $ligne) {
            supprimer_ligne_panier((int) $ligne['id']);
        }
        unset($_SESSION['commande_en_cours']);

        // Paiement en ligne : le stock est réservé, le client part payer sur la page de l'agrégateur.
        if ($paiement === 'en_ligne') {
            aller_vers_paiement(creer_paiement($utilisateur, $creees));
        }

        $numeros = implode(', ', array_column($creees, 'numero'));
        message_flash('succes', count($creees) > 1
            ? 'Merci ! Vos ' . count($creees) . ' commandes sont enregistrées (' . $numeros . '). Chaque vendeur doit maintenant les confirmer.'
            : 'Merci ! Votre commande ' . $numeros . ' est enregistrée. Le vendeur doit maintenant la confirmer.');
        rediriger(count($creees) === 1 ? 'compte/commande.php?id=' . $creees[0]['id'] : 'compte/commandes.php');
    }
}

// ---- Données de l'étape 2 ----
if ($etape === 'recap') {
    $livraisons = calculer_livraisons($groupes, $choix['adresse'], $choix['modes']);
    $total_articles = array_sum(array_column($groupes, 'sous_total'));
    $total_frais = array_sum(array_column($livraisons, 'frais'));
    $tout_possible = !in_array(false, array_column($livraisons, 'possible'), true);
    $frais_a_convenir = in_array(true, array_column($livraisons, 'a_convenir'), true);
    $empreinte = empreinte_commande($lignes, $livraisons, $choix['adresse']);
    $refus_livraison = refus_paiement_livraison($utilisateur, count($groupes));
    // Paiement en ligne : montant connu d'avance (pas de frais « à convenir »).
    $en_ligne_possible = paiement_en_ligne_actif() && !$frais_a_convenir;
    $zone_nom = '';
    foreach (zones_actives() as $zone) {
        if ((int) $zone['id'] === (int) $choix['adresse']['zone_id']) {
            $zone_nom = $zone['nom'];
        }
    }
}

$titre_page = 'Valider ma commande';
$sans_indexation = true;
$page_active = 'panier';

require __DIR__ . '/../../includes/entete.php';
?>

<section class="py-4 py-md-5">
    <div class="container">
        <nav aria-label="Étapes de la commande">
            <ol class="sm-etapes-commande">
                <li><a href="<?= e(url_page('panier')) ?>">Panier</a></li>
                <li<?= $etape === 'adresse' ? ' aria-current="step"' : '' ?>>
                    <?= $etape === 'recap' ? '<a href="' . e(url('panier/commander.php')) . '">Adresse</a>' : 'Adresse' ?>
                </li>
                <li<?= $etape === 'recap' ? ' aria-current="step"' : '' ?>>Confirmation</li>
            </ol>
        </nav>

        <?php if ($etape === 'adresse'): ?>
            <!-- ===== Étape 1 : adresse ===== -->
            <div class="row justify-content-center">
                <div class="col-12 col-lg-8">
                    <div class="sm-tuile">
                        <h1 class="h3 fw-bold mb-1">Où livrer votre commande ?</h1>
                        <p class="text-body-secondary mb-4">Le vendeur vous appellera à ce numéro pour la livraison.</p>
                        <?= alerte_formulaire($erreurs) ?>
                        <form method="post" novalidate>
                            <?= champ_csrf() ?>
                            <input type="hidden" name="action" value="adresse">
                            <?= champs_adresse($utilisateur, $adresses, $valeurs, $erreurs) ?>
                            <button class="btn btn-or btn-lg w-100 mt-4" type="submit">
                                Continuer <i class="bi bi-arrow-right" aria-hidden="true"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- ===== Étape 2 : récapitulatif et confirmation ===== -->
            <h1 class="h3 fw-bold mb-4">Vérifiez et confirmez</h1>

            <div class="row g-4">
                <div class="col-12 col-lg-8">
                    <div class="sm-tuile mb-3">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <h2 class="h6 fw-bold mb-1"><i class="bi bi-geo-alt" aria-hidden="true"></i> Adresse de livraison</h2>
                                <p class="mb-0">
                                    <?= e($choix['adresse']['quartier']) ?>, <?= e($choix['adresse']['ville']) ?> (<?= e($zone_nom) ?>)
                                    <?= $choix['adresse']['repere'] !== '' ? '<br><span class="small text-body-secondary">' . e($choix['adresse']['repere']) . '</span>' : '' ?>
                                    <br><span class="small"><?= e(formater_telephone($choix['adresse']['telephone'])) ?></span>
                                </p>
                            </div>
                            <a class="btn btn-sm btn-contour" href="<?= e(url('panier/commander.php')) ?>">Modifier</a>
                        </div>
                    </div>

                    <form method="post" id="form-modes">
                        <?= champ_csrf() ?>
                        <input type="hidden" name="action" value="modes">
                        <?php $numero_commande = 0; ?>
                        <?php foreach ($groupes as $boutiqueId => $groupe): ?>
                            <?php $livraison = $livraisons[$boutiqueId]; $numero_commande++; ?>
                            <section class="sm-tuile sm-panier-boutique mb-3">
                                <header class="sm-panier-boutique__entete">
                                    <h2 class="h6 mb-0">
                                        <?php if (count($groupes) > 1): ?>Commande <?= $numero_commande ?> · <?php endif; ?>
                                        <i class="bi bi-shop" aria-hidden="true"></i> <?= e($groupe['boutique']['nom']) ?>
                                    </h2>
                                </header>
                                <ul class="sm-recap-articles">
                                    <?php foreach ($groupe['lignes'] as $ligne): ?>
                                        <li>
                                            <span>
                                                <?= e($ligne['quantite']) ?> × <?= e($ligne['titre']) ?>
                                                <span class="small text-body-secondary d-block">
                                                    Taille <?= e($ligne['taille']) ?><?= $ligne['flocage_nom'] !== '' ? ' · Flocage ' . e($ligne['flocage_nom'] . ' ' . $ligne['flocage_numero']) : '' ?>
                                                </span>
                                            </span>
                                            <strong><?= e(format_fcfa($ligne['total_ligne'])) ?></strong>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>

                                <fieldset class="sm-choix-livraison">
                                    <legend class="visually-hidden">Mode de livraison pour <?= e($groupe['boutique']['nom']) ?></legend>
                                    <?php if ($livraison['tarif'] !== null): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="mode[<?= e($boutiqueId) ?>]" id="mode-l-<?= e($boutiqueId) ?>"
                                                   value="livraison"<?= $livraison['mode'] === 'livraison' ? ' checked' : '' ?> data-envoi-auto>
                                            <label class="form-check-label" for="mode-l-<?= e($boutiqueId) ?>">
                                                Livraison (<?= e($livraison['tarif']['delai']) ?>) —
                                                <strong><?= $livraison['tarif']['tarif'] === 0 ? 'gratuite' : e(format_fcfa($livraison['tarif']['tarif'])) ?></strong>
                                            </label>
                                        </div>
                                    <?php else: ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="mode[<?= e($boutiqueId) ?>]" id="mode-l-<?= e($boutiqueId) ?>"
                                                   value="livraison"<?= $livraison['mode'] === 'livraison' ? ' checked' : '' ?> data-envoi-auto>
                                            <label class="form-check-label" for="mode-l-<?= e($boutiqueId) ?>">
                                                Livraison — <strong>frais à convenir avec le vendeur</strong>
                                                <span class="d-block small text-body-secondary">Pas encore de tarif pour la zone « <?= e($zone_nom) ?> » : le vendeur vous indiquera le prix avant de confirmer.</span>
                                            </label>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($livraison['retrait']): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="mode[<?= e($boutiqueId) ?>]" id="mode-r-<?= e($boutiqueId) ?>"
                                                   value="retrait"<?= $livraison['mode'] === 'retrait' ? ' checked' : '' ?> data-envoi-auto>
                                            <label class="form-check-label" for="mode-r-<?= e($boutiqueId) ?>">
                                                Retrait sur place — <strong>gratuit</strong>
                                                <span class="d-block small text-body-secondary"><?= e($groupe['boutique']['adresse_retrait']) ?></span>
                                            </label>
                                        </div>
                                    <?php endif; ?>
                                </fieldset>

                                <footer class="sm-panier-boutique__pied">
                                    <span>Total de cette commande</span>
                                    <strong><?= e(format_fcfa($groupe['sous_total'] + $livraison['frais'])) ?></strong>
                                </footer>
                            </section>
                        <?php endforeach; ?>
                        <noscript><button class="btn btn-contour mb-3" type="submit">Mettre à jour les frais</button></noscript>
                    </form>
                </div>

                <div class="col-12 col-lg-4">
                    <aside class="sm-tuile sm-recap" aria-label="Paiement et confirmation">
                        <h2 class="h5 fw-bold">Paiement</h2>

                        <form method="post">
                            <?= champ_csrf() ?>
                            <input type="hidden" name="action" value="confirmer">
                            <input type="hidden" name="empreinte" value="<?= e($empreinte) ?>">

                            <fieldset class="mb-3">
                                <legend class="visually-hidden">Mode de paiement</legend>
                                <?php if ($en_ligne_possible): ?>
                                    <div class="form-check sm-choix-paiement">
                                        <input class="form-check-input" type="radio" name="paiement" id="paiement-en-ligne" value="en_ligne"
                                               <?= $refus_livraison !== null ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="paiement-en-ligne">
                                            <strong>Payer en ligne</strong> <span class="badge sm-badge-protege"><i class="bi bi-shield-check" aria-hidden="true"></i> Protégé</span>
                                            <?php if (paiement_mode_test()): ?><span class="badge text-bg-warning">TEST</span><?php endif; ?>
                                            <span class="d-block small text-body-secondary">Wave, Orange Money, Free Money ou carte. Remboursé si vous ne recevez pas votre maillot ou s'il n'est pas conforme.</span>
                                        </label>
                                    </div>
                                <?php else: ?>
                                    <div class="form-check sm-choix-paiement sm-choix-paiement--indisponible">
                                        <input class="form-check-input" type="radio" name="paiement" id="paiement-en-ligne" value="en_ligne" disabled>
                                        <label class="form-check-label" for="paiement-en-ligne">
                                            <strong>Payer en ligne</strong>
                                            <?= paiement_en_ligne_actif() ? '' : '<span class="badge text-bg-secondary">Bientôt</span>' ?>
                                            <span class="d-block small text-body-secondary"><?= $frais_a_convenir && paiement_en_ligne_actif()
                                                ? 'Impossible avec des frais de livraison « à convenir » : choisissez le retrait ou le paiement à la livraison.'
                                                : 'Wave, Orange Money, carte : remboursé si vous ne recevez pas votre maillot.' ?></span>
                                        </label>
                                    </div>
                                <?php endif; ?>
                                <div class="form-check sm-choix-paiement">
                                    <input class="form-check-input" type="radio" name="paiement" id="paiement-livraison" value="livraison"
                                           <?= $refus_livraison === null ? 'checked' : 'disabled' ?>>
                                    <label class="form-check-label" for="paiement-livraison">
                                        <strong>Paiement à la livraison</strong>
                                        <span class="d-block small text-body-secondary">En espèces ou par Wave, au vendeur, quand vous recevez votre maillot.</span>
                                    </label>
                                </div>
                            </fieldset>

                            <p class="small text-body-secondary">
                                <i class="bi bi-info-circle" aria-hidden="true"></i>
                                <strong>Payez en ligne pour être remboursé en cas de problème</strong> : SAMA MAILLOT garde l'argent jusqu'à la réception.
                                En paiement à la livraison, vous payez le vendeur directement : nous ne pouvons pas vous rembourser.
                            </p>

                            <dl class="sm-recap__lignes">
                                <dt>Articles</dt>
                                <dd><?= e(format_fcfa($total_articles)) ?></dd>
                                <dt>Livraison</dt>
                                <dd><?= e(format_fcfa($total_frais)) ?><?= $frais_a_convenir ? ' <span class="d-block small fw-normal text-body-secondary">+ frais à convenir</span>' : '' ?></dd>
                                <dt class="sm-recap__total">Total à payer</dt>
                                <dd class="sm-recap__total"><?= e(format_fcfa($total_articles + $total_frais)) ?></dd>
                            </dl>

                            <?php if ($refus_livraison !== null && !$en_ligne_possible): ?>
                                <div class="alert alert-danger small" role="alert"><?= e($refus_livraison) ?></div>
                            <?php elseif (!$tout_possible): ?>
                                <div class="alert alert-danger small" role="alert">
                                    Une boutique ne livre pas votre zone. <a href="<?= e(url('panier/commander.php')) ?>">Changez d'adresse</a>
                                    ou <a href="<?= e(url_page('panier')) ?>">retirez ses articles</a>.
                                </div>
                            <?php else: ?>
                                <button class="btn btn-or btn-lg w-100" type="submit">
                                    <i class="bi bi-check2-circle" aria-hidden="true"></i>
                                    Confirmer <?= count($groupes) > 1 ? 'les ' . count($groupes) . ' commandes' : 'la commande' ?>
                                </button>
                            <?php endif; ?>
                        </form>
                        <p class="small text-body-secondary mt-3 mb-0">
                            Le vendeur vous <strong>appellera</strong> au numéro indiqué pour organiser la livraison.
                        </p>
                    </aside>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
