<?php
/**
 * SAMA MAILLOT — cas B : première connexion avec Google ou Facebook, aucun compte avec cet e-mail.
 *
 * L'identité vérifiée attend en session (15 minutes). Le compte CLIENT n'est créé qu'après
 * l'acceptation des CGU et de la politique de confidentialité : compte + liaison dans une
 * transaction, e-mail marqué vérifié, pas de mot de passe (il pourra en définir un plus tard).
 */
require __DIR__ . '/../../includes/config.php';

rediriger_si_connecte();

$identite = identite_en_attente('bienvenue');
if ($identite === null) {
    message_flash('erreur', 'Votre demande a expiré. Merci de recommencer la connexion.');
    rediriger('connexion/');
}

$nomFournisseur = nom_fournisseur($identite['fournisseur']);
$erreurs = [];
$valeurs = ['prenom' => nom_propose($identite['prenom']), 'nom' => nom_propose($identite['nom'])];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valeurs = ['prenom' => champ_post('prenom'), 'nom' => champ_post('nom')];

    if (!verifier_csrf()) {
        $erreurs['general'] = 'Votre session a expiré. Merci de réessayer.';
    } else {
        if ($message = valider_nom_personne($valeurs['prenom'], 'prénom')) {
            $erreurs['prenom'] = $message;
        }
        if ($message = valider_nom_personne($valeurs['nom'], 'nom')) {
            $erreurs['nom'] = $message;
        }
        if (!case_cochee('cgu')) {
            $erreurs['cgu'] = 'Vous devez accepter les conditions générales d\'utilisation et la politique de confidentialité.';
        }
    }

    if ($erreurs === []) {
        try {
            // Un compte a pu être créé entre-temps avec cette adresse : on passe alors à la liaison (cas C).
            if (trouver_comptes_par_identifiant((string) $identite['email']) !== []) {
                rediriger(orienter_selon_email($identite));
            }

            $compte = creer_compte_social($identite, $valeurs['prenom'], $valeurs['nom']);
            $destination = ouvrir_session_sociale($compte, $identite['fournisseur']);
            message_flash('succes', 'Bienvenue ' . $compte['prenom'] . ' ! Votre compte est créé.');
            rediriger($destination);
        } catch (ErreurConnexionSociale $erreur) {
            $erreurs['general'] = $erreur->getMessage();
        }
    }
}

$titre_page = 'Bienvenue';
$sans_indexation = true;

require __DIR__ . '/../../includes/entete.php';
?>

<section class="sm-page-formulaire">
    <div class="container">
        <div class="sm-carte-formulaire">
            <span class="sm-medaillon mb-3" aria-hidden="true"><i class="bi bi-stars"></i></span>
            <h1 class="mb-2">Bienvenue sur SAMA MAILLOT</h1>
            <p class="text-body-secondary mb-4">
                Dernière étape pour créer votre compte avec <?= e($nomFournisseur) ?> :
                vérifiez votre nom et acceptez nos conditions.
            </p>

            <?= alerte_formulaire($erreurs) ?>

            <form method="post" novalidate>
                <?= champ_csrf() ?>

                <div class="mb-3">
                    <label class="form-label" for="email">Adresse e-mail</label>
                    <input class="form-control" type="email" id="email" value="<?= e($identite['email']) ?>" readonly
                           aria-describedby="aide-email">
                    <div class="form-text" id="aide-email">Adresse transmise par <?= e($nomFournisseur) ?>.</div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-sm-6">
                        <label class="form-label" for="prenom">Prénom</label>
                        <input class="form-control<?= classe_erreur($erreurs, 'prenom') ?>" type="text" id="prenom" name="prenom"
                               value="<?= e($valeurs['prenom']) ?>" autocomplete="given-name" maxlength="50" required
                               <?= aria_erreur($erreurs, 'prenom') ?>>
                        <?= message_erreur($erreurs, 'prenom') ?>
                    </div>
                    <div class="col-12 col-sm-6">
                        <label class="form-label" for="nom">Nom</label>
                        <input class="form-control<?= classe_erreur($erreurs, 'nom') ?>" type="text" id="nom" name="nom"
                               value="<?= e($valeurs['nom']) ?>" autocomplete="family-name" maxlength="50" required
                               <?= aria_erreur($erreurs, 'nom') ?>>
                        <?= message_erreur($erreurs, 'nom') ?>
                    </div>
                </div>

                <div class="form-check mb-4">
                    <input class="form-check-input<?= classe_erreur($erreurs, 'cgu') ?>" type="checkbox" id="cgu" name="cgu" value="1"
                           required <?= aria_erreur($erreurs, 'cgu') ?>>
                    <label class="form-check-label" for="cgu">
                        J'accepte les <a href="<?= e(url_page('cgu')) ?>" target="_blank" rel="noopener">conditions générales d'utilisation</a>
                        et la <a href="<?= e(url_page('confidentialite')) ?>" target="_blank" rel="noopener">politique de confidentialité</a>.
                    </label>
                </div>
                <?= message_erreur($erreurs, 'cgu') ?>

                <button class="btn btn-or w-100 mt-2" type="submit">
                    <i class="bi bi-person-check" aria-hidden="true"></i> Créer mon compte
                </button>
            </form>

            <p class="text-center mt-4 mb-0">
                <a href="<?= e(url_page('connexion')) ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Annuler</a>
            </p>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
