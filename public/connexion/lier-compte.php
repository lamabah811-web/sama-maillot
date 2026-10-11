<?php
/**
 * SAMA MAILLOT — cas C : un compte existe déjà avec l'e-mail donné par Google ou Facebook.
 *
 * JAMAIS de liaison automatique (sinon, quiconque contrôle un compte Google ou Facebook à cette
 * adresse prendrait le compte). Le titulaire prouve qu'il possède le compte SAMA MAILLOT :
 *   - compte avec mot de passe : saisie du mot de passe (mêmes limites que la connexion) ;
 *   - compte sans mot de passe : code SMS (si SMS actifs et téléphone vérifié) ou lien par e-mail
 *     (usage unique, 10 minutes, à ouvrir dans ce navigateur) ;
 *   - e-mail saisi après Facebook et déjà prouvé par notre code : simple confirmation.
 * L'identité vérifiée attend en session 10 minutes maximum.
 */
require __DIR__ . '/../../includes/config.php';

rediriger_si_connecte();

$identite = identite_en_attente('lier');
$compte = $identite !== null ? trouver_utilisateur((int) $identite['compte_id']) : null;

if ($identite === null || $compte === null) {
    oublier_identite();
    message_flash('erreur', isset($_GET['lien'])
        ? 'Ouvrez ce lien dans le navigateur où vous avez commencé la connexion, dans les 10 minutes. Sinon, recommencez.'
        : 'Votre demande a expiré. Merci de recommencer la connexion.');
    rediriger('connexion/');
}

if ($refus = refus_connexion_sociale($compte)) {
    oublier_identite();
    message_flash('erreur', $refus);
    rediriger('connexion/');
}

$nomFournisseur = nom_fournisseur($identite['fournisseur']);
$aMotDePasse = $compte['mot_de_passe_hache'] !== null;
$parSms = !$aMotDePasse && SMS_ACTIF && $compte['telephone'] !== null && $compte['telephone_verifie_le'] !== null;
$erreurs = [];
$lienEnvoye = false;

// Lien reçu par e-mail (compte sans mot de passe)
if (isset($_GET['lien'])) {
    $idLien = (int) $_GET['lien'];
    $jeton = is_string($_GET['jeton'] ?? null) ? $_GET['jeton'] : '';

    if (!$aMotDePasse && utiliser_lien_liaison($idLien, $jeton) === (int) $compte['id']) {
        try {
            $destination = finaliser_liaison($identite, $compte);
            message_flash('succes', 'Votre compte ' . $nomFournisseur . ' est lié. Vous êtes connecté.');
            rediriger($destination);
        } catch (ErreurConnexionSociale $erreur) {
            $erreurs['general'] = $erreur->getMessage();
        }
    } else {
        $erreurs['general'] = 'Ce lien est expiré ou a déjà été utilisé. Demandez-en un nouveau.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = champ_post('action');

    if (!verifier_csrf()) {
        $erreurs['general'] = 'Votre session a expiré. Merci de réessayer.';
    } elseif ($action === 'annuler') {
        oublier_identite();
        rediriger('connexion/');
    } elseif ($action === 'mot_de_passe' && $aMotDePasse) {
        $cle = identifiant_normalise((string) $compte['email']);

        if (connexion_bloquee($cle)) {
            $erreurs['general'] = 'Trop de tentatives de connexion. Pour votre sécurité, réessayez dans 15 minutes.';
        } elseif (password_verify((string) ($_POST['mot_de_passe'] ?? ''), (string) $compte['mot_de_passe_hache'])) {
            enregistrer_tentative_connexion($cle, true);
            try {
                $destination = finaliser_liaison($identite, $compte);
                message_flash('succes', 'Votre compte ' . $nomFournisseur . ' est lié. La prochaine fois, un clic suffira.');
                rediriger($destination);
            } catch (ErreurConnexionSociale $erreur) {
                $erreurs['general'] = $erreur->getMessage();
            }
        } else {
            enregistrer_tentative_connexion($cle, false);
            $erreurs['mot_de_passe'] = 'Mot de passe incorrect.';
        }
    } elseif ($action === 'confirmer' && !$aMotDePasse && $identite['email_par_code']) {
        // L'e-mail vient d'être prouvé par le code reçu : il suffit de confirmer.
        try {
            rediriger(finaliser_liaison($identite, $compte));
        } catch (ErreurConnexionSociale $erreur) {
            $erreurs['general'] = $erreur->getMessage();
        }
    } elseif ($action === 'envoyer_sms' && $parSms) {
        $code = creer_code_sms('liaison_sociale_sms', (string) $compte['telephone'], (int) $compte['id']);
        if ($code === null) {
            $erreurs['general'] = 'Vous avez demandé trop de codes. Réessayez dans une heure.';
        } else {
            envoyer_sms((string) $compte['telephone'], "SAMA MAILLOT : votre code pour lier votre compte $nomFournisseur est $code. "
                . 'Il expire dans 10 minutes. Ne le communiquez à personne.');
            message_flash('succes', 'Code envoyé par SMS au ' . masquer_telephone((string) $compte['telephone']) . '.');
            rediriger('connexion/lier-compte.php');
        }
    } elseif ($action === 'code_sms' && $parSms) {
        $resultat = verifier_code_sms('liaison_sociale_sms', (string) $compte['telephone'], champ_post('code'));
        if ($resultat['ok'] && $resultat['utilisateur_id'] === (int) $compte['id']) {
            try {
                rediriger(finaliser_liaison($identite, $compte));
            } catch (ErreurConnexionSociale $erreur) {
                $erreurs['general'] = $erreur->getMessage();
            }
        } else {
            $erreurs['code'] = $resultat['message'];
        }
    } elseif ($action === 'envoyer_lien' && !$aMotDePasse) {
        $lien = creer_lien_liaison((int) $compte['id'], (string) $compte['email']);
        if ($lien === '') {
            $erreurs['general'] = 'Vous avez demandé trop de liens. Réessayez dans une heure.';
        } else {
            envoyer_email((string) $compte['email'], 'Lier votre compte ' . $nomFournisseur,
                'Bonjour ' . $compte['prenom'] . ', pour lier votre compte ' . $nomFournisseur . ' à SAMA MAILLOT, ouvrez ce lien '
                . 'dans le navigateur où vous avez commencé la connexion (valable 10 minutes, une seule fois) : ' . $lien
                . ' — Si vous n\'êtes pas à l\'origine de cette demande, ignorez ce message : rien ne sera lié.', $lien);
            $lienEnvoye = true;
        }
    }
}

$titre_page = 'Lier votre compte';
$sans_indexation = true;

require __DIR__ . '/../../includes/entete.php';
?>

<section class="sm-page-formulaire">
    <div class="container">
        <div class="sm-carte-formulaire">
            <span class="sm-medaillon mb-3" aria-hidden="true"><i class="bi bi-link-45deg"></i></span>
            <h1 class="mb-2">Lier votre compte <?= e($nomFournisseur) ?></h1>
            <p class="text-body-secondary mb-4">
                Un compte SAMA MAILLOT existe déjà avec l'adresse <strong><?= e($compte['email']) ?></strong>.
                <?php if ($aMotDePasse): ?>
                    Entrez votre mot de passe pour lier votre compte <?= e($nomFournisseur) ?>.
                <?php elseif ($identite['email_par_code']): ?>
                    Vous venez de prouver que cette adresse est la vôtre : confirmez la liaison.
                <?php else: ?>
                    Pour votre sécurité, confirmez que ce compte est bien le vôtre.
                <?php endif; ?>
            </p>

            <?= alerte_formulaire($erreurs) ?>

            <?php if ($aMotDePasse): ?>
                <form method="post" novalidate>
                    <?= champ_csrf() ?>
                    <input type="hidden" name="action" value="mot_de_passe">
                    <input type="hidden" name="identifiant" value="<?= e($compte['email']) ?>" autocomplete="username">
                    <?= champ_mot_de_passe('mot_de_passe', 'Mot de passe SAMA MAILLOT', $erreurs, 'current-password') ?>
                    <button class="btn btn-or w-100" type="submit">
                        <i class="bi bi-link-45deg" aria-hidden="true"></i> Lier et me connecter
                    </button>
                </form>
                <p class="text-center mt-3 mb-0">
                    <a class="sm-lien-tactile" href="<?= e(url_page('mot-de-passe-oublie')) ?>">Mot de passe oublié ?</a>
                </p>

            <?php elseif ($identite['email_par_code']): ?>
                <form method="post">
                    <?= champ_csrf() ?>
                    <input type="hidden" name="action" value="confirmer">
                    <button class="btn btn-or w-100" type="submit">
                        <i class="bi bi-link-45deg" aria-hidden="true"></i> Lier et me connecter
                    </button>
                </form>

            <?php elseif ($parSms): ?>
                <form method="post" class="mb-3">
                    <?= champ_csrf() ?>
                    <input type="hidden" name="action" value="envoyer_sms">
                    <button class="btn btn-contour w-100" type="submit">
                        <i class="bi bi-phone" aria-hidden="true"></i> Recevoir un code au <?= e(masquer_telephone((string) $compte['telephone'])) ?>
                    </button>
                </form>
                <form method="post" novalidate>
                    <?= champ_csrf() ?>
                    <input type="hidden" name="action" value="code_sms">
                    <div class="mb-3">
                        <label class="form-label" for="code">Code reçu par SMS</label>
                        <input class="form-control<?= classe_erreur($erreurs, 'code') ?>" type="text" id="code" name="code"
                               inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="\d{6}" required
                               <?= aria_erreur($erreurs, 'code') ?>>
                        <?= message_erreur($erreurs, 'code') ?>
                    </div>
                    <button class="btn btn-or w-100" type="submit">Valider le code</button>
                </form>

            <?php else: ?>
                <?php if ($lienEnvoye): ?>
                    <p class="mb-3" role="status">
                        <i class="bi bi-envelope-check text-success" aria-hidden="true"></i>
                        Lien envoyé à <?= e($compte['email']) ?>. Ouvrez-le dans ce navigateur dans les 10 minutes.
                    </p>
                <?php endif; ?>
                <form method="post">
                    <?= champ_csrf() ?>
                    <input type="hidden" name="action" value="envoyer_lien">
                    <button class="btn btn-or w-100" type="submit">
                        <i class="bi bi-envelope" aria-hidden="true"></i> <?= $lienEnvoye ? 'Renvoyer le lien' : 'Recevoir un lien par e-mail' ?>
                    </button>
                </form>
            <?php endif; ?>

            <form method="post" class="text-center mt-4">
                <?= champ_csrf() ?>
                <input type="hidden" name="action" value="annuler">
                <button class="btn btn-link sm-lien-tactile" type="submit">Annuler</button>
            </form>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
