<?php
/**
 * SAMA MAILLOT — vérification du téléphone par code SMS (§8.2, §8.3, §12.1).
 *
 * Code à 6 chiffres, valable 10 minutes, 3 essais, 3 demandes maximum par heure.
 * - Vendeur : une fois le numéro vérifié, l'essai gratuit de 14 jours démarre — seulement si
 *   ce numéro n'en a jamais bénéficié (table essais_gratuits, conservée même après suppression).
 * - Client : vérification avant la première commande, puis retour à la commande.
 * Inactive tant que VERIFICATION_SMS vaut 0 (.env).
 */
require __DIR__ . '/../../includes/config.php';

$utilisateur = exiger_connexion();
$est_vendeur = $utilisateur['role'] === 'vendeur';

// Page à rouvrir après la vérification (chemin interne simple uniquement)
$retour = (string) ($_SESSION['retour_apres_verification'] ?? '');
if ($est_vendeur || preg_match('#^[a-z0-9/_.\-]+(\?[a-z0-9=&_-]*)?$#i', $retour) !== 1) {
    $retour = $est_vendeur ? 'vendeur/' : 'compte/';
}

// Déjà vérifié, pas de numéro, ou vérification SMS désactivée (config.php) : rien à faire ici.
if (!VERIFICATION_SMS || $utilisateur['telephone_verifie_le'] !== null || $utilisateur['telephone'] === null) {
    unset($_SESSION['retour_apres_verification']);
    rediriger($retour);
}

$telephone = (string) $utilisateur['telephone'];
$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifier_csrf()) {
        $erreurs['general'] = 'Votre session a expiré. Merci de réessayer.';
    } elseif (champ_post('action') === 'renvoyer') {
        // Nouveau code (limité à 3 demandes par heure et par numéro)
        $code = creer_code_sms('verification_telephone', $telephone, (int) $utilisateur['id']);

        if ($code === null) {
            $erreurs['general'] = 'Vous avez demandé trop de codes. Réessayez dans une heure.';
        } else {
            envoyer_sms($telephone, "SAMA MAILLOT : votre code de vérification est $code. Il expire dans 10 minutes.");
            message_flash('succes', 'Un nouveau code a été envoyé au ' . formater_telephone($telephone) . '.');
            rediriger('inscription/verifier-telephone.php');
        }
    } else {
        $resultat = verifier_code_sms('verification_telephone', $telephone, champ_post('code'));

        if (!$resultat['ok']) {
            $erreurs['code'] = $resultat['message'];
        } else {
            $pdo = bdd();
            $pdo->beginTransaction();

            $pdo->prepare('UPDATE utilisateurs SET telephone_verifie_le = NOW() WHERE id = :id')
                ->execute(['id' => $utilisateur['id']]);
            $messageEssai = $est_vendeur ? demarrer_essai_gratuit((int) $utilisateur['id'], $telephone) : '';

            $pdo->commit();

            journaliser_action('telephone_verifie', (int) $utilisateur['id'], 'utilisateur', (int) $utilisateur['id']);

            unset($_SESSION['retour_apres_verification']);
            message_flash('succes', trim('Téléphone vérifié ! ' . $messageEssai));
            rediriger($retour);
        }
    }
}

$titre_page = 'Vérifier mon téléphone';
$sans_indexation = true;
$page_active = 'verifier-telephone';

require __DIR__ . '/../../includes/entete.php';
?>

<section class="sm-page-formulaire">
    <div class="container">
        <div class="sm-carte-formulaire">
            <span class="sm-medaillon mb-3" aria-hidden="true"><i class="bi bi-phone"></i></span>
            <h1 class="mb-2">Vérifier mon téléphone</h1>
            <p class="text-body-secondary mb-4">
                Saisissez le code à 6 chiffres reçu par SMS au
                <strong class="text-nowrap"><?= e(formater_telephone($telephone)) ?></strong>.
                Il est valable 10 minutes.
            </p>

            <?= rappel_sms_simule($telephone) ?>
            <?= alerte_formulaire($erreurs) ?>

            <form method="post" novalidate>
                <?= champ_csrf() ?>
                <input type="hidden" name="action" value="verifier">

                <div class="mb-3">
                    <label class="form-label" for="code">Code reçu par SMS</label>
                    <input class="form-control sm-champ-code<?= classe_erreur($erreurs, 'code') ?>" type="text" id="code" name="code"
                           inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6"
                           placeholder="••••••" required autofocus <?= aria_erreur($erreurs, 'code') ?>>
                    <?= message_erreur($erreurs, 'code') ?>
                </div>

                <button class="btn btn-or w-100" type="submit">
                    <i class="bi bi-check2-circle" aria-hidden="true"></i> Vérifier
                </button>
            </form>

            <form method="post" class="mt-3 text-center">
                <?= champ_csrf() ?>
                <input type="hidden" name="action" value="renvoyer">
                <button class="btn btn-link" type="submit">Je n'ai pas reçu de code : renvoyer</button>
            </form>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
