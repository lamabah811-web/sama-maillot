<?php
/**
 * SAMA MAILLOT — mot de passe oublié, étape 2 : code SMS + nouveau mot de passe (§8.5).
 *
 * Après le changement, TOUTES les sessions ouvertes du compte sont fermées
 * (autres téléphones, ordinateurs, cookies « Rester connecté »).
 */
require __DIR__ . '/../../includes/config.php';

rediriger_si_connecte();

// SMS désactivés : la réinitialisation se fait uniquement par lien e-mail.
if (!SMS_ACTIF) {
    rediriger('mot-de-passe/');
}

$demande = $_SESSION['reinitialisation'] ?? null;

if (!is_array($demande) || ($demande['expire'] ?? 0) < time()) {
    unset($_SESSION['reinitialisation']);
    message_flash('erreur', 'Votre demande a expiré. Recommencez.');
    rediriger('mot-de-passe/');
}

$telephone = is_string($demande['telephone'] ?? null) ? $demande['telephone'] : null;
$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $motDePasse = (string) ($_POST['mot_de_passe'] ?? '');
    $confirmation = (string) ($_POST['confirmation'] ?? '');

    if (!verifier_csrf()) {
        $erreurs['general'] = 'Votre session a expiré. Merci de réessayer.';
    } elseif ($message = valider_mot_de_passe($motDePasse)) {
        // Le mot de passe est contrôlé AVANT le code : une faute de frappe ne coûte pas un essai.
        $erreurs['mot_de_passe'] = $message;
    } elseif ($motDePasse !== $confirmation) {
        $erreurs['confirmation'] = 'Les deux mots de passe ne sont pas identiques.';
    } elseif ($telephone === null) {
        // Aucun SMS n'a été envoyé (compte inconnu ou réinitialisation par e-mail).
        $erreurs['code'] = 'Code incorrect ou expiré. Demandez un nouveau code.';
    } else {
        $resultat = verifier_code_sms('reinitialisation_sms', $telephone, champ_post('code'));

        if (!$resultat['ok']) {
            $erreurs['code'] = $resultat['message'];
        } else {
            // Comptes concernés : celui du code, ou tous les comptes liés à ce numéro vérifié
            // (le code prouve que la personne possède ce téléphone).
            if ($resultat['utilisateur_id'] !== null) {
                $ids = [$resultat['utilisateur_id']];
            } else {
                $ids = array_map(
                    static fn (array $c): int => (int) $c['id'],
                    trouver_comptes_par_identifiant($telephone)
                );
            }

            $hache = password_hash($motDePasse, PASSWORD_DEFAULT);

            foreach ($ids as $id) {
                bdd()->prepare('UPDATE utilisateurs SET mot_de_passe_hache = :hache WHERE id = :id')
                    ->execute(['hache' => $hache, 'id' => $id]);
                fermer_toutes_les_sessions($id);
                journaliser_action('mot_de_passe_reinitialise_sms', $id, 'utilisateur', $id);
            }

            unset($_SESSION['reinitialisation']);

            message_flash('succes', count($ids) > 1
                ? 'Mot de passe modifié pour vos deux comptes (client et vendeur). Connectez-vous avec le nouveau mot de passe.'
                : 'Mot de passe modifié. Connectez-vous avec votre nouveau mot de passe.');
            rediriger('connexion/');
        }
    }
}

$titre_page = 'Nouveau mot de passe';
$sans_indexation = true;
$page_active = 'mot-de-passe-oublie';

require __DIR__ . '/../../includes/entete.php';
?>

<section class="sm-page-formulaire">
    <div class="container">
        <div class="sm-carte-formulaire">
            <span class="sm-medaillon mb-3" aria-hidden="true"><i class="bi bi-shield-lock"></i></span>
            <h1 class="mb-2">Nouveau mot de passe</h1>
            <p class="text-body-secondary mb-4">
                Saisissez le code reçu par SMS et choisissez un nouveau mot de passe.
                Si vous avez reçu un <strong>e-mail</strong>, ouvrez simplement le lien qu'il contient.
            </p>

            <?= rappel_sms_simule($telephone) ?>
            <?= alerte_formulaire($erreurs) ?>

            <form method="post" novalidate>
                <?= champ_csrf() ?>

                <div class="mb-3">
                    <label class="form-label" for="code">Code reçu par SMS</label>
                    <input class="form-control sm-champ-code<?= classe_erreur($erreurs, 'code') ?>" type="text" id="code" name="code"
                           inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6"
                           placeholder="••••••" required autofocus <?= aria_erreur($erreurs, 'code') ?>>
                    <?= message_erreur($erreurs, 'code') ?>
                </div>

                <?= champ_mot_de_passe('mot_de_passe', 'Nouveau mot de passe', $erreurs, 'new-password',
                    '8 caractères minimum, avec au moins une lettre et un chiffre.') ?>
                <?= champ_mot_de_passe('confirmation', 'Confirmer le nouveau mot de passe', $erreurs, 'new-password') ?>

                <button class="btn btn-or w-100" type="submit">
                    <i class="bi bi-check2-circle" aria-hidden="true"></i> Changer mon mot de passe
                </button>
            </form>

            <p class="text-center mt-4 mb-0">
                <a href="<?= e(url_page('mot-de-passe-oublie')) ?>">Demander un nouveau code</a>
            </p>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
