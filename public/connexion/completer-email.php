<?php
/**
 * SAMA MAILLOT — Facebook n'a pas transmis d'e-mail (compte créé avec un téléphone, ou permission refusée).
 *
 * - Permission refusée : bouton « Autoriser l'accès à mon e-mail » (nouvelle demande, auth_type=rerequest).
 * - Sinon, ou au choix : la personne saisit son e-mail et reçoit un code à 6 chiffres
 *   (15 minutes, 5 essais, 3 codes par heure et par adresse, 5 envois par connexion Facebook).
 *   Rien n'est créé avant la validation du code.
 * Les données Facebook (identifiant, prénom, nom) restent en session 15 minutes maximum.
 */
require __DIR__ . '/../../includes/config.php';

rediriger_si_connecte();

$identite = identite_en_attente('email');
if ($identite === null) {
    message_flash('erreur', 'Votre demande a expiré. Merci de recommencer la connexion avec Facebook.');
    rediriger('connexion/');
}

$erreurs = [];
$emailSaisi = (string) ($identite['email_en_attente'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = champ_post('action');

    if (!verifier_csrf()) {
        $erreurs['general'] = 'Votre session a expiré. Merci de réessayer.';
    } elseif ($action === 'envoyer') {
        $emailSaisi = champ_post('email');
        $email = normaliser_email($emailSaisi);

        if ($email === null) {
            $erreurs['email'] = 'Saisissez une adresse e-mail valide (ex. : awa.diop@gmail.com).';
        } elseif ((int) ($_SESSION['identite_sociale']['envois'] ?? 0) >= 5) {
            // Au plus 5 envois par connexion Facebook (sinon : envoi de codes à de nombreuses adresses).
            $erreurs['email'] = 'Trop de codes envoyés. Recommencez la connexion avec Facebook plus tard.';
        } else {
            $_SESSION['identite_sociale']['envois'] = (int) ($_SESSION['identite_sociale']['envois'] ?? 0) + 1;
            $code = creer_code_email_social($email);
            if ($code === null) {
                $erreurs['email'] = 'Trop de codes envoyés à cette adresse. Réessayez dans une heure.';
            } else {
                envoyer_email($email, 'Votre code SAMA MAILLOT',
                    'Votre code pour terminer la connexion avec Facebook est ' . $code
                    . '. Il expire dans 15 minutes. Ne le communiquez à personne. '
                    . 'Si vous n\'êtes pas à l\'origine de cette demande, ignorez ce message.');
                $_SESSION['identite_sociale']['email_en_attente'] = $email;
                message_flash('succes', 'Code envoyé à ' . $email . '.');
                rediriger('connexion/completer-email.php');
            }
        }
    } elseif ($action === 'verifier' && $emailSaisi !== '') {
        $resultat = verifier_code_email_social($emailSaisi, champ_post('code'));

        if (!$resultat['ok']) {
            $erreurs['code'] = $resultat['message'];
        } else {
            // E-mail prouvé : on reprend les règles comme si Facebook l'avait fourni (cas B ou C).
            $identite['email'] = $emailSaisi;
            $identite['email_par_code'] = true;
            try {
                rediriger(orienter_selon_email($identite));
            } catch (ErreurConnexionSociale $erreur) {
                oublier_identite();
                message_flash('erreur', $erreur->getMessage());
                rediriger('connexion/');
            }
        }
    } elseif ($action === 'changer') {
        $_SESSION['identite_sociale']['email_en_attente'] = null;
        rediriger('connexion/completer-email.php');
    }
}

$titre_page = 'Votre adresse e-mail';
$sans_indexation = true;

require __DIR__ . '/../../includes/entete.php';
?>

<section class="sm-page-formulaire">
    <div class="container">
        <div class="sm-carte-formulaire">
            <span class="sm-medaillon mb-3" aria-hidden="true"><i class="bi bi-envelope-at"></i></span>
            <h1 class="mb-2">Il nous manque votre e-mail</h1>
            <p class="text-body-secondary mb-4">
                Facebook ne nous a pas transmis votre adresse e-mail. Elle sert à vous envoyer le suivi
                de vos commandes et à sécuriser votre compte.
            </p>

            <?= alerte_formulaire($erreurs) ?>

            <?php if ($identite['email_refuse']): ?>
                <a class="sm-bouton-social sm-bouton-social--facebook mb-3" href="<?= e(url('connexion/facebook.php?redemander=1')) ?>" data-bouton-social>
                    <?php require __DIR__ . '/../../includes/logo_facebook.php'; ?>
                    <span class="sm-bouton-social__texte">Autoriser l'accès à mon e-mail</span>
                    <span class="spinner-border spinner-border-sm sm-bouton-social__attente" aria-hidden="true"></span>
                </a>
                <div class="sm-separateur" aria-hidden="true">ou</div>
            <?php endif; ?>

            <?php if ($emailSaisi === ''): ?>
                <form method="post" novalidate>
                    <?= champ_csrf() ?>
                    <input type="hidden" name="action" value="envoyer">
                    <div class="mb-3">
                        <label class="form-label" for="email">Votre adresse e-mail</label>
                        <input class="form-control<?= classe_erreur($erreurs, 'email') ?>" type="email" id="email" name="email"
                               value="<?= e($emailSaisi) ?>" autocomplete="email" inputmode="email" maxlength="190" required
                               <?= aria_erreur($erreurs, 'email') ?>>
                        <?= message_erreur($erreurs, 'email') ?>
                    </div>
                    <button class="btn btn-or w-100" type="submit">
                        <i class="bi bi-send" aria-hidden="true"></i> Recevoir un code
                    </button>
                </form>
            <?php else: ?>
                <form method="post" novalidate>
                    <?= champ_csrf() ?>
                    <input type="hidden" name="action" value="verifier">
                    <div class="mb-3">
                        <label class="form-label" for="code">Code reçu à <?= e($emailSaisi) ?></label>
                        <input class="form-control<?= classe_erreur($erreurs, 'code') ?>" type="text" id="code" name="code"
                               inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="\d{6}" required autofocus
                               <?= aria_erreur($erreurs, 'code') ?>>
                        <?= message_erreur($erreurs, 'code') ?>
                    </div>
                    <button class="btn btn-or w-100" type="submit">Valider le code</button>
                </form>
                <form method="post" class="text-center mt-3">
                    <?= champ_csrf() ?>
                    <input type="hidden" name="action" value="changer">
                    <button class="btn btn-link sm-lien-tactile" type="submit">Changer d'adresse ou renvoyer un code</button>
                </form>
            <?php endif; ?>

            <p class="text-center mt-4 mb-0">
                <a href="<?= e(url_page('connexion')) ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Annuler</a>
            </p>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
