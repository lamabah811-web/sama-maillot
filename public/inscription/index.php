<?php
/**
 * SAMA MAILLOT — inscription d'un client (§8.1).
 *
 * Champs : prénom, nom, e-mail, mot de passe + confirmation, acceptation des CGU.
 * Le téléphone n'est PAS demandé ici : il sera vérifié par SMS à la première commande.
 */
require __DIR__ . '/../../includes/config.php';

rediriger_si_connecte();

$erreurs = [];
$valeurs = ['prenom' => '', 'nom' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifier_csrf()) {
        $erreurs['general'] = 'Votre session a expiré. Merci de renvoyer le formulaire.';
    } else {
        $valeurs['prenom'] = champ_post('prenom');
        $valeurs['nom'] = champ_post('nom');
        $valeurs['email'] = champ_post('email');
        $motDePasse = (string) ($_POST['mot_de_passe'] ?? '');
        $confirmation = (string) ($_POST['confirmation'] ?? '');

        // 1. Contrôle de chaque champ
        if ($message = valider_nom_personne($valeurs['prenom'], 'prénom')) {
            $erreurs['prenom'] = $message;
        }
        if ($message = valider_nom_personne($valeurs['nom'], 'nom')) {
            $erreurs['nom'] = $message;
        }

        $email = normaliser_email($valeurs['email']);
        if ($email === null) {
            $erreurs['email'] = 'Saisissez une adresse e-mail valide (ex. : awa.diop@gmail.com).';
        }

        if ($message = valider_mot_de_passe($motDePasse)) {
            $erreurs['mot_de_passe'] = $message;
        } elseif ($motDePasse !== $confirmation) {
            $erreurs['confirmation'] = 'Les deux mots de passe ne sont pas identiques.';
        }

        if (!case_cochee('cgu')) {
            $erreurs['cgu'] = 'Vous devez accepter les conditions générales d\'utilisation.';
        }

        // 2. Adresse e-mail déjà utilisée ?
        if ($erreurs === [] && trouver_comptes_par_identifiant($email) !== []) {
            $erreurs['email'] = 'Un compte existe déjà avec cette adresse e-mail. Connectez-vous ou utilisez « Mot de passe oublié ».';
        }

        // 3. Création du compte
        if ($erreurs === []) {
            try {
                bdd()->prepare(
                    "INSERT INTO utilisateurs (role, prenom, nom, email, mot_de_passe_hache, cgu_acceptees_le)
                     VALUES ('client', :prenom, :nom, :email, :hache, NOW())"
                )->execute([
                    'prenom' => $valeurs['prenom'],
                    'nom' => $valeurs['nom'],
                    'email' => $email,
                    'hache' => password_hash($motDePasse, PASSWORD_DEFAULT),
                ]);
            } catch (PDOException $exception) {
                // Deux inscriptions simultanées avec la même adresse : la base refuse la seconde.
                if ($exception->getCode() !== '23000') {
                    throw $exception;
                }
                $erreurs['email'] = 'Un compte existe déjà avec cette adresse e-mail.';
            }
        }

        if ($erreurs === []) {
            $id = (int) bdd()->lastInsertId();
            journaliser_action('inscription_client', $id, 'utilisateur', $id);

            connecter_utilisateur(trouver_utilisateur($id));
            message_flash('succes', 'Bienvenue ' . $valeurs['prenom'] . ' ! Votre compte est créé.');
            rediriger('');   // accueil
        }
    }
}

$titre_page = 'Créer un compte';
$description_page = 'Créez votre compte client SAMA MAILLOT pour commander vos maillots de football.';
$page_active = 'inscription';

require __DIR__ . '/../../includes/entete.php';
?>

<section class="sm-page-formulaire">
    <div class="container">
        <div class="sm-carte-formulaire">
            <h1 class="mb-1">Créer un compte</h1>
            <p class="text-body-secondary mb-4">
                Déjà inscrit ? <a href="<?= e(url_page('connexion')) ?>">Se connecter</a>
            </p>

            <?= alerte_formulaire($erreurs) ?>

            <?php require __DIR__ . '/../../includes/boutons_connexion_sociale.php'; ?>

            <form method="post" novalidate>
                <?= champ_csrf() ?>

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

                <div class="mb-3">
                    <label class="form-label" for="email">Adresse e-mail</label>
                    <input class="form-control<?= classe_erreur($erreurs, 'email') ?>" type="email" id="email" name="email"
                           value="<?= e($valeurs['email']) ?>" autocomplete="email" inputmode="email" maxlength="190" required
                           <?= aria_erreur($erreurs, 'email') ?>>
                    <?= message_erreur($erreurs, 'email') ?>
                </div>

                <?= champ_mot_de_passe('mot_de_passe', 'Mot de passe', $erreurs, 'new-password',
                    '8 caractères minimum, avec au moins une lettre et un chiffre.') ?>
                <?= champ_mot_de_passe('confirmation', 'Confirmer le mot de passe', $erreurs, 'new-password') ?>

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
                    <i class="bi bi-person-plus" aria-hidden="true"></i> Créer mon compte
                </button>
            </form>

            <div class="sm-separateur" aria-hidden="true">ou</div>

            <p class="text-center mb-0">
                Vous vendez des maillots ?
                <a href="<?= e(url_page('inscription-vendeur')) ?>">Ouvrir ma boutique</a>
            </p>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
