<?php
/**
 * SAMA MAILLOT — changement de mot de passe d'un utilisateur connecté (§8.7, §8.5).
 *
 * Le mot de passe actuel est demandé. Après le changement, toutes les AUTRES sessions
 * sont fermées ; la session en cours reste ouverte.
 */
require __DIR__ . '/../../includes/config.php';

$utilisateur = exiger_connexion();
$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actuel = (string) ($_POST['actuel'] ?? '');
    $nouveau = (string) ($_POST['mot_de_passe'] ?? '');
    $confirmation = (string) ($_POST['confirmation'] ?? '');

    if (!verifier_csrf()) {
        $erreurs['general'] = 'Votre session a expiré. Merci de réessayer.';
    } elseif ($utilisateur['mot_de_passe_hache'] === null || !password_verify($actuel, $utilisateur['mot_de_passe_hache'])) {
        $erreurs['actuel'] = 'Le mot de passe actuel est incorrect.';
    } elseif ($message = valider_mot_de_passe($nouveau)) {
        $erreurs['mot_de_passe'] = $message;
    } elseif ($nouveau !== $confirmation) {
        $erreurs['confirmation'] = 'Les deux mots de passe ne sont pas identiques.';
    } elseif (password_verify($nouveau, $utilisateur['mot_de_passe_hache'])) {
        $erreurs['mot_de_passe'] = 'Le nouveau mot de passe doit être différent de l\'actuel.';
    } else {
        $id = (int) $utilisateur['id'];

        bdd()->prepare('UPDATE utilisateurs SET mot_de_passe_hache = :hache WHERE id = :id')
            ->execute(['hache' => password_hash($nouveau, PASSWORD_DEFAULT), 'id' => $id]);

        // Ferme toutes les sessions (version + 1), puis rouvre celle-ci avec la nouvelle version.
        fermer_toutes_les_sessions($id);
        connecter_utilisateur(trouver_utilisateur($id));
        journaliser_action('mot_de_passe_modifie', $id, 'utilisateur', $id);

        message_flash('succes', 'Mot de passe modifié. Vos autres appareils ont été déconnectés.');
        rediriger(chemin_espace($utilisateur));
    }
}

$titre_page = 'Changer de mot de passe';
$sans_indexation = true;
$page_active = 'compte';

require __DIR__ . '/../../includes/entete.php';
?>

<section class="sm-page-formulaire">
    <div class="container">
        <div class="sm-carte-formulaire">
            <h1 class="mb-2">Changer de mot de passe</h1>
            <p class="text-body-secondary mb-4">Vos autres appareils seront déconnectés.</p>

            <?= alerte_formulaire($erreurs) ?>

            <form method="post" novalidate>
                <?= champ_csrf() ?>
                <?= champ_mot_de_passe('actuel', 'Mot de passe actuel', $erreurs, 'current-password') ?>
                <?= champ_mot_de_passe('mot_de_passe', 'Nouveau mot de passe', $erreurs, 'new-password',
                    '8 caractères minimum, avec au moins une lettre et un chiffre.') ?>
                <?= champ_mot_de_passe('confirmation', 'Confirmer le nouveau mot de passe', $erreurs, 'new-password') ?>

                <button class="btn btn-or w-100" type="submit">
                    <i class="bi bi-check2-circle" aria-hidden="true"></i> Enregistrer
                </button>
            </form>

            <p class="text-center mt-4 mb-0">
                <a href="<?= e(url(chemin_espace($utilisateur))) ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Retour à mon espace</a>
            </p>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
