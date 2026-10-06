<?php
/**
 * SAMA MAILLOT — mot de passe oublié, étape 1 : demande (§8.5).
 *
 * - SMS désactivés (SMS_ACTIF=0, réglage actuel) : toujours un lien par e-mail (1 heure, usage
 *   unique), même si la personne a saisi son numéro de téléphone.
 * - SMS actifs et téléphone vérifié → code SMS à 6 chiffres (10 min, 3 essais, 3 demandes par heure).
 *
 * La réponse est la même que le compte existe ou non : on ne révèle jamais
 * quelles adresses ou quels numéros sont inscrits.
 */
require __DIR__ . '/../../includes/config.php';

rediriger_si_connecte();

$erreurs = [];
$identifiant = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifiant = champ_post('identifiant');
    $email = normaliser_email($identifiant);
    $telephone = $email === null ? normaliser_telephone($identifiant) : null;

    if (!verifier_csrf()) {
        $erreurs['general'] = 'Votre session a expiré. Merci de réessayer.';
    } elseif ($email === null && $telephone === null) {
        $erreurs['identifiant'] = 'Saisissez une adresse e-mail ou un numéro sénégalais valide.';
    } else {
        $comptes = array_values(array_filter(trouver_comptes_par_identifiant($identifiant), 'compte_utilisable'));
        $destinationSms = null;   // numéro à qui le code a été envoyé (null = aucun SMS)
        $limiteAtteinte = false;

        if ($comptes !== [] && !SMS_ACTIF) {
            // SMS désactivés : un lien par e-mail à chaque compte correspondant.
            foreach ($comptes as $compte) {
                $lien = creer_lien_reinitialisation((int) $compte['id'], (string) $compte['email']);
                if ($lien !== '') {
                    envoyer_email(
                        (string) $compte['email'],
                        'Réinitialisation de votre mot de passe',
                        'Bonjour ' . $compte['prenom'] . ', pour choisir un nouveau mot de passe, ouvrez ce lien '
                        . '(valable 1 heure, utilisable une seule fois) : ' . $lien
                        . ' — Si vous n\'êtes pas à l\'origine de cette demande, ignorez ce message.',
                        $lien
                    );
                }
            }
        } elseif ($comptes !== []) {
            $compte = $comptes[0];

            // Téléphone vérifié → code SMS. Depuis un téléphone partagé par 2 comptes,
            // le code ne vise aucun compte en particulier (utilisateur_id NULL) : le choix
            // du compte se fait après la saisie du code.
            if ($compte['telephone_verifie_le'] !== null) {
                $destinationSms = (string) $compte['telephone'];
                $cible = count($comptes) === 1 ? (int) $compte['id'] : null;
                $code = creer_code_sms('reinitialisation_sms', $destinationSms, $cible);

                if ($code === null) {
                    $limiteAtteinte = true;
                } else {
                    envoyer_sms($destinationSms, "SAMA MAILLOT : votre code pour changer de mot de passe est $code. "
                        . 'Il expire dans 10 minutes. Ne le communiquez à personne.');
                }
            } else {
                // Pas de téléphone vérifié → lien par e-mail.
                $lien = creer_lien_reinitialisation((int) $compte['id'], (string) $compte['email']);
                if ($lien !== '') {
                    envoyer_email(
                        (string) $compte['email'],
                        'Réinitialisation de votre mot de passe',
                        'Bonjour ' . $compte['prenom'] . ', pour choisir un nouveau mot de passe, ouvrez ce lien '
                        . '(valable 1 heure, utilisable une seule fois) : ' . $lien
                        . ' — Si vous n\'êtes pas à l\'origine de cette demande, ignorez ce message.',
                        $lien
                    );
                }
            }
        }

        if ($limiteAtteinte) {
            $erreurs['general'] = 'Vous avez demandé trop de codes. Réessayez dans une heure.';
        } elseif ($destinationSms === null && !SMS_ACTIF) {
            message_flash('succes', 'Si un compte correspond, vous venez de recevoir un lien par e-mail pour choisir un nouveau mot de passe.');
            rediriger('connexion/');
        } else {
            // Mémorisé pour l'étape 2 (saisie du code). Si aucun SMS n'est parti, le code
            // saisi sera simplement refusé : la page ne révèle rien.
            $_SESSION['reinitialisation'] = [
                'telephone' => $destinationSms ?? $telephone,
                'expire' => time() + 15 * 60,
            ];

            message_flash('succes', 'Si un compte correspond, vous venez de recevoir un code par SMS '
                . 'ou un lien par e-mail.');
            rediriger('mot-de-passe/code.php');
        }
    }
}

$titre_page = 'Mot de passe oublié';
$sans_indexation = true;
$page_active = 'mot-de-passe-oublie';

require __DIR__ . '/../../includes/entete.php';
?>

<section class="sm-page-formulaire">
    <div class="container">
        <div class="sm-carte-formulaire">
            <span class="sm-medaillon mb-3" aria-hidden="true"><i class="bi bi-key"></i></span>
            <h1 class="mb-2">Mot de passe oublié</h1>
            <p class="text-body-secondary mb-4">
                Saisissez l'adresse e-mail ou le téléphone de votre compte.
                <?= SMS_ACTIF ? 'Vous recevrez un code par SMS (si votre téléphone est vérifié) ou un lien par e-mail.'
                    : 'Vous recevrez un lien par e-mail pour choisir un nouveau mot de passe.' ?>
            </p>

            <?= alerte_formulaire($erreurs) ?>

            <form method="post" novalidate>
                <?= champ_csrf() ?>

                <div class="mb-3">
                    <label class="form-label" for="identifiant">E-mail ou téléphone</label>
                    <input class="form-control<?= classe_erreur($erreurs, 'identifiant') ?>" type="text" id="identifiant" name="identifiant"
                           value="<?= e($identifiant) ?>" autocomplete="username" maxlength="190" required autofocus
                           <?= aria_erreur($erreurs, 'identifiant') ?>>
                    <?= message_erreur($erreurs, 'identifiant') ?>
                </div>

                <button class="btn btn-or w-100" type="submit">
                    <i class="bi bi-send" aria-hidden="true"></i> <?= SMS_ACTIF ? 'Recevoir un code ou un lien' : 'Recevoir le lien par e-mail' ?>
                </button>
            </form>

            <p class="text-center mt-4 mb-0">
                <a href="<?= e(url_page('connexion')) ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Retour à la connexion</a>
            </p>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
