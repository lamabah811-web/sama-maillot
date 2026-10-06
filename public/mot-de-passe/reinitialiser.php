<?php
/**
 * SAMA MAILLOT — réinitialisation par lien e-mail (§8.5).
 *
 * Lien : mot-de-passe/reinitialiser.php?id=...&jeton=...
 * Valable 1 heure, utilisable une seule fois. Après le changement, toutes les sessions
 * ouvertes du compte sont fermées.
 */
require __DIR__ . '/../../includes/config.php';

rediriger_si_connecte();

// Le lien arrive en GET ; le formulaire renvoie les mêmes valeurs en champs cachés (POST).
$source = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$idLien = (int) ($source['id'] ?? 0);
$jeton = is_string($source['jeton'] ?? null) ? $source['jeton'] : '';

$utilisateurId = verifier_lien_reinitialisation($idLien, $jeton);
$erreurs = [];

if ($utilisateurId !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $motDePasse = (string) ($_POST['mot_de_passe'] ?? '');
    $confirmation = (string) ($_POST['confirmation'] ?? '');

    if (!verifier_csrf()) {
        $erreurs['general'] = 'Votre session a expiré. Merci de réessayer.';
    } elseif ($message = valider_mot_de_passe($motDePasse)) {
        $erreurs['mot_de_passe'] = $message;
    } elseif ($motDePasse !== $confirmation) {
        $erreurs['confirmation'] = 'Les deux mots de passe ne sont pas identiques.';
    } else {
        bdd()->prepare('UPDATE utilisateurs SET mot_de_passe_hache = :hache WHERE id = :id')
            ->execute(['hache' => password_hash($motDePasse, PASSWORD_DEFAULT), 'id' => $utilisateurId]);

        utiliser_lien_reinitialisation($idLien);
        fermer_toutes_les_sessions($utilisateurId);
        journaliser_action('mot_de_passe_reinitialise_email', $utilisateurId, 'utilisateur', $utilisateurId);

        message_flash('succes', 'Mot de passe modifié. Connectez-vous avec votre nouveau mot de passe.');
        rediriger('connexion/');
    }
}

$titre_page = 'Nouveau mot de passe';
$sans_indexation = true;

require __DIR__ . '/../../includes/entete.php';
?>

<section class="sm-page-formulaire">
    <div class="container">
        <div class="sm-carte-formulaire">

            <?php if ($utilisateurId === null): ?>
                <span class="sm-medaillon mb-3" aria-hidden="true"><i class="bi bi-hourglass-bottom"></i></span>
                <h1 class="mb-2">Lien expiré ou déjà utilisé</h1>
                <p class="text-body-secondary mb-4">
                    Pour votre sécurité, un lien de réinitialisation n'est valable qu'une heure et une seule fois.
                </p>
                <a class="btn btn-or w-100" href="<?= e(url_page('mot-de-passe-oublie')) ?>">Faire une nouvelle demande</a>

            <?php else: ?>
                <span class="sm-medaillon mb-3" aria-hidden="true"><i class="bi bi-shield-lock"></i></span>
                <h1 class="mb-2">Nouveau mot de passe</h1>
                <p class="text-body-secondary mb-4">Choisissez votre nouveau mot de passe.</p>

                <?= alerte_formulaire($erreurs) ?>

                <form method="post" novalidate>
                    <?= champ_csrf() ?>
                    <input type="hidden" name="id" value="<?= e($idLien) ?>">
                    <input type="hidden" name="jeton" value="<?= e($jeton) ?>">

                    <?= champ_mot_de_passe('mot_de_passe', 'Nouveau mot de passe', $erreurs, 'new-password',
                        '8 caractères minimum, avec au moins une lettre et un chiffre.') ?>
                    <?= champ_mot_de_passe('confirmation', 'Confirmer le nouveau mot de passe', $erreurs, 'new-password') ?>

                    <button class="btn btn-or w-100" type="submit">
                        <i class="bi bi-check2-circle" aria-hidden="true"></i> Changer mon mot de passe
                    </button>
                </form>
            <?php endif; ?>

        </div>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
