<?php
/**
 * SAMA MAILLOT — connexion (§8.4).
 *
 * Identifiant : adresse e-mail OU numéro de téléphone vérifié, avec mot de passe.
 * - Message d'erreur générique : on n'indique jamais si le compte existe.
 * - 5 échecs en 15 minutes bloquent la connexion pendant 15 minutes.
 * - Un même téléphone peut servir à un compte client ET à un compte vendeur (§8.3) :
 *   si le mot de passe convient aux deux, on demande lequel ouvrir.
 */
require __DIR__ . '/../../includes/config.php';

rediriger_si_connecte();

$erreurs = [];
$identifiant = '';

// Étape « choix du compte » : valable 5 minutes après la vérification du mot de passe.
$choix = $_SESSION['choix_compte'] ?? null;
if (!is_array($choix) || ($choix['expire'] ?? 0) < time()) {
    $choix = null;
    unset($_SESSION['choix_compte']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifier_csrf()) {
        $erreurs['general'] = 'Votre session a expiré. Merci de réessayer.';
    } elseif (champ_post('action') === 'choisir' && $choix !== null) {
        // 2e étape : l'utilisateur choisit entre son compte client et son compte vendeur.
        $idChoisi = (int) champ_post('compte_id');

        if (in_array($idChoisi, $choix['ids'], true)) {
            $compte = trouver_utilisateur($idChoisi);
            unset($_SESSION['choix_compte']);

            if ($compte !== null && compte_utilisable($compte)) {
                connecter_utilisateur($compte, (bool) $choix['souvenir']);
                rediriger(chemin_apres_connexion($compte));
            }
        }

        $erreurs['general'] = 'Ce compte n\'est pas disponible. Reconnectez-vous.';
        $choix = null;
    } else {
        // 1re étape : identifiant + mot de passe.
        $identifiant = champ_post('identifiant');
        $motDePasse = (string) ($_POST['mot_de_passe'] ?? '');
        $resterConnecte = case_cochee('rester_connecte');
        $cle = identifiant_normalise($identifiant);

        if ($identifiant === '' || $motDePasse === '') {
            $erreurs['general'] = 'Saisissez votre e-mail (ou téléphone) et votre mot de passe.';
        } elseif (connexion_bloquee($cle)) {
            $erreurs['general'] = 'Trop de tentatives de connexion. Pour votre sécurité, réessayez dans 15 minutes.';
        } else {
            $comptes = trouver_comptes_par_identifiant($identifiant);
            $valides = [];

            foreach ($comptes as $compte) {
                if ($compte['mot_de_passe_hache'] !== null && password_verify($motDePasse, $compte['mot_de_passe_hache'])) {
                    $valides[] = $compte;
                }
            }

            // Compte inexistant : on calcule quand même un hachage pour que la réponse
            // prenne le même temps (sinon le délai révélerait si le compte existe).
            if ($comptes === []) {
                password_verify($motDePasse, '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG');
            }

            if ($valides === []) {
                enregistrer_tentative_connexion($cle, false);
                $erreurs['general'] = 'Identifiants incorrects.';
            } else {
                enregistrer_tentative_connexion($cle, true);

                // Compte désactivé ou suspendu : on le dit seulement après un mot de passe correct.
                $utilisables = array_values(array_filter($valides, 'compte_utilisable'));

                if ($utilisables === []) {
                    $erreurs['general'] = 'Ce compte est désactivé ou suspendu. Contactez le support SAMA MAILLOT.';
                } elseif (count($utilisables) === 1) {
                    connecter_utilisateur($utilisables[0], $resterConnecte);
                    rediriger(chemin_apres_connexion($utilisables[0]));
                } else {
                    $_SESSION['choix_compte'] = [
                        'ids' => array_map(static fn (array $c): int => (int) $c['id'], $utilisables),
                        'souvenir' => $resterConnecte,
                        'expire' => time() + 300,
                    ];
                    rediriger('connexion/');
                }
            }
        }
    }
}

$titre_page = 'Connexion';
$description_page = 'Connectez-vous à votre compte SAMA MAILLOT.';
$page_active = 'connexion';

require __DIR__ . '/../../includes/entete.php';
?>

<section class="sm-page-formulaire">
    <div class="container">
        <div class="sm-carte-formulaire">

            <?php if ($choix !== null): ?>
                <!-- Étape 2 : choisir le compte (même téléphone pour un compte client et un compte vendeur) -->
                <h1 class="mb-2">Quel compte ouvrir ?</h1>
                <p class="text-body-secondary mb-4">Ce numéro est lié à deux comptes.</p>

                <?= alerte_formulaire($erreurs) ?>

                <?php foreach ($choix['ids'] as $idCompte): ?>
                    <?php $compte = trouver_utilisateur($idCompte); ?>
                    <?php if ($compte === null) { continue; } ?>
                    <form method="post" class="mb-2">
                        <?= champ_csrf() ?>
                        <input type="hidden" name="action" value="choisir">
                        <input type="hidden" name="compte_id" value="<?= e($compte['id']) ?>">
                        <button class="btn btn-contour w-100 justify-content-start" type="submit">
                            <i class="bi <?= $compte['role'] === 'vendeur' ? 'bi-shop' : 'bi-bag' ?>" aria-hidden="true"></i>
                            <?= $compte['role'] === 'vendeur' ? 'Mon espace vendeur' : 'Mon compte client' ?>
                            <span class="text-body-secondary fw-normal ms-auto small"><?= e($compte['email']) ?></span>
                        </button>
                    </form>
                <?php endforeach; ?>

            <?php else: ?>
                <h1 class="mb-1">Connexion</h1>
                <p class="text-body-secondary mb-4">
                    Pas encore de compte ? <a href="<?= e(url_page('inscription')) ?>">Créer un compte</a>
                </p>

                <?= alerte_formulaire($erreurs) ?>

                <form method="post" novalidate>
                    <?= champ_csrf() ?>
                    <input type="hidden" name="action" value="connexion">

                    <div class="mb-3">
                        <label class="form-label" for="identifiant">E-mail ou téléphone</label>
                        <input class="form-control" type="text" id="identifiant" name="identifiant"
                               value="<?= e($identifiant) ?>" autocomplete="username" maxlength="190" required
                               aria-describedby="aide-identifiant">
                        <div class="form-text" id="aide-identifiant"><?= SMS_ACTIF ? 'Le téléphone doit avoir été vérifié par SMS.' : 'Utilisez de préférence votre adresse e-mail.' ?></div>
                    </div>

                    <?= champ_mot_de_passe('mot_de_passe', 'Mot de passe', $erreurs, 'current-password') ?>

                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" id="rester_connecte" name="rester_connecte" value="1">
                            <label class="form-check-label" for="rester_connecte">Rester connecté (30 jours)</label>
                        </div>
                        <a class="d-inline-flex align-items-center sm-lien-tactile" href="<?= e(url_page('mot-de-passe-oublie')) ?>">Mot de passe oublié ?</a>
                    </div>

                    <button class="btn btn-or w-100" type="submit">
                        <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> Se connecter
                    </button>
                </form>

                <div class="sm-separateur" aria-hidden="true">ou</div>

                <p class="text-center mb-0">
                    Vous vendez des maillots ?
                    <a href="<?= e(url_page('inscription-vendeur')) ?>">Ouvrir ma boutique</a>
                </p>
            <?php endif; ?>

        </div>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
