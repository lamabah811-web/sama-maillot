<?php
/**
 * SAMA MAILLOT — commande via WhatsApp (§17).
 *
 * 1. Sur la fiche, le client choisit sa taille et clique « Commander via WhatsApp » (POST ici).
 * 2. S'il n'est pas connecté, son choix est gardé en session et il se connecte (ou s'inscrit).
 * 3. Il confirme son adresse : une VRAIE commande est créée en paiement à la livraison
 *    (stock réservé, numéro de commande).
 * 4. WhatsApp s'ouvre avec un message pré-rempli (numéro du vendeur jamais écrit dans la page).
 */
require __DIR__ . '/../../includes/config.php';

$utilisateur = utilisateur_connecte();
$erreurs = [];
$valeurs = [];

// ---- Choix envoyé depuis la fiche maillot ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && champ_post('action') === 'ajouter') {
    if (!verifier_csrf()) {
        message_flash('erreur', 'Votre session a expiré. Merci de réessayer.');
        rediriger('catalogue/');
    }

    [$flocageNom, $flocageNumero, $erreurFlocage] = lire_flocage(champ_post('flocage_nom'), champ_post('flocage_numero'));
    $maillot = maillot_par_id((int) champ_post('maillot_id'));
    if ($maillot === null) {
        message_flash('erreur', 'Ce maillot n\'est plus disponible.');
        rediriger('catalogue/');
    }
    if ($erreurFlocage !== null || !isset(tailles_du_maillot((int) $maillot['id'])[champ_post('taille')])) {
        message_flash('erreur', $erreurFlocage ?? 'Choisissez une taille.');
        rediriger('maillot/' . $maillot['slug'] . '-' . (int) $maillot['id']);
    }

    $_SESSION['commande_whatsapp'] = [
        'maillot_id' => (int) $maillot['id'],
        'taille' => champ_post('taille'),
        'quantite' => max(1, min(QUANTITE_MAX_PAR_LIGNE, (int) champ_post('quantite'))),
        'flocage_nom' => $flocageNom,
        'flocage_numero' => $flocageNumero,
    ];
    rediriger('commande/whatsapp.php');
}

// ---- Connexion obligatoire (le choix reste en session) ----
$utilisateur = exiger_connexion();
$choix = $_SESSION['commande_whatsapp'] ?? null;
$maillot = is_array($choix) ? maillot_par_id((int) $choix['maillot_id']) : null;

if ($maillot === null) {
    unset($_SESSION['commande_whatsapp']);
    message_flash('erreur', 'Choisissez d\'abord votre maillot et sa taille.');
    rediriger('catalogue/');
}
if (!peut_acheter($utilisateur) || boutique_proprietaire((int) $maillot['boutique_id']) === (int) $utilisateur['id']) {
    unset($_SESSION['commande_whatsapp']);
    message_flash('erreur', 'Vous ne pouvez pas commander ce maillot avec ce compte.');
    rediriger('maillot/' . $maillot['slug'] . '-' . (int) $maillot['id']);
}

$avec_flocage = $choix['flocage_nom'] !== '';
$prix_unitaire = (int) $maillot['prix'] + ($avec_flocage ? (int) $maillot['flocage_supplement'] : 0);
$adresses = adresses_du_client((int) $utilisateur['id']);
$retrait = (int) $maillot['boutique_retrait'] === 1;

// ---- Création de la commande ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && champ_post('action') === 'commander') {
    if (!verifier_csrf()) {
        $erreurs['general'] = 'Votre session a expiré. Merci de réessayer.';
    } else {
        [$adresse, $erreurs] = lire_adresse_post($utilisateur);
        $valeurs = $adresse;
        $mode = $retrait && champ_post('mode') === 'retrait' ? 'retrait' : 'livraison';

        if ($erreurs === [] && VERIFICATION_SMS && $utilisateur['telephone_verifie_le'] === null) {
            demarrer_verification_telephone($utilisateur, $adresse['telephone'], 'commande/whatsapp.php');
        }

        if ($erreurs === []) {
            try {
                $creees = creer_commandes($utilisateur, [[
                    'maillot_id' => (int) $maillot['id'], 'taille' => $choix['taille'], 'flocage_nom' => $choix['flocage_nom'],
                    'flocage_numero' => $choix['flocage_numero'], 'quantite' => $choix['quantite'],
                ]], $adresse, [(int) $maillot['boutique_id'] => $mode], 'whatsapp');

                memoriser_adresse((int) $utilisateur['id'], $adresse);
                unset($_SESSION['commande_whatsapp']);
                message_flash('succes', 'Commande ' . $creees[0]['numero'] . ' enregistrée. Finalisez les détails avec le vendeur sur WhatsApp.');
                $_SESSION['ouvrir_whatsapp'] = $creees[0]['id'];
                rediriger('compte/commande.php?id=' . $creees[0]['id']);
            } catch (ErreurCommande $exception) {
                $erreurs['general'] = $exception->getMessage();
            }
        }
    }
}

$titre_page = 'Commander via WhatsApp';
$sans_indexation = true;

require __DIR__ . '/../../includes/entete.php';
?>

<section class="py-4 py-md-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-lg-8">
                <div class="sm-tuile">
                    <span class="sm-sur-titre"><i class="bi bi-whatsapp" aria-hidden="true"></i> Commander via WhatsApp</span>
                    <h1 class="h3 fw-bold mb-3">Votre commande</h1>

                    <div class="sm-recap-maillot mb-4">
                        <div>
                            <strong><?= e($choix['quantite']) ?> × <?= e($maillot['titre']) ?></strong>
                            <span class="d-block small text-body-secondary">
                                Taille <?= e($choix['taille']) ?><?= $avec_flocage ? ' · Flocage ' . e($choix['flocage_nom'] . ' ' . $choix['flocage_numero']) : '' ?>
                                · <?= e($maillot['boutique_nom']) ?>
                            </span>
                        </div>
                        <strong><?= e(format_fcfa($prix_unitaire * $choix['quantite'])) ?></strong>
                    </div>

                    <p class="small text-body-secondary">
                        Une vraie commande est créée en <strong>paiement à la livraison</strong> : le maillot vous est réservé,
                        vous recevez un numéro de commande. WhatsApp s'ouvre ensuite pour discuter avec le vendeur.
                    </p>

                    <?= alerte_formulaire($erreurs) ?>

                    <form method="post" novalidate>
                        <?= champ_csrf() ?>
                        <input type="hidden" name="action" value="commander">
                        <?= champs_adresse($utilisateur, $adresses, $valeurs, $erreurs) ?>

                        <?php if ($retrait): ?>
                            <fieldset class="mt-3">
                                <legend class="form-label fw-semibold fs-6">Mode de remise</legend>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="mode" id="mode-livraison" value="livraison" checked>
                                    <label class="form-check-label" for="mode-livraison">Livraison (frais selon votre zone)</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="mode" id="mode-retrait" value="retrait">
                                    <label class="form-check-label" for="mode-retrait">Retrait sur place, gratuit : <?= e($maillot['boutique_adresse_retrait']) ?></label>
                                </div>
                            </fieldset>
                        <?php endif; ?>

                        <button class="btn btn-or btn-lg w-100 mt-4" type="submit">
                            <i class="bi bi-whatsapp" aria-hidden="true"></i> Créer la commande et ouvrir WhatsApp
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
