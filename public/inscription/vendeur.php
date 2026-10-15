<?php
/**
 * SAMA MAILLOT — ouverture d'une boutique (§8.2).
 *
 * Deux cas :
 *   - VISITEUR : prénom, nom, e-mail, téléphone, mot de passe, boutique, ville, CGU et
 *     politique vendeur → création du compte vendeur et de la boutique (transaction SQL).
 *   - CLIENT CONNECTÉ (« Devenir vendeur ») : son compte devient un compte vendeur. Même
 *     adresse e-mail, même mot de passe, favoris conservés. Un vendeur peut toujours acheter
 *     (§5.1), sauf dans sa propre boutique. Seuls le téléphone, la boutique et la ville sont
 *     demandés. Téléphone déjà vérifié et inchangé → l'essai gratuit démarre tout de suite.
 *
 * Sinon un code SMS est envoyé : l'essai gratuit de 14 jours démarre à la vérification.
 * Vérification SMS désactivée (VERIFICATION_SMS=0) : l'essai démarre dès la création.
 */
require __DIR__ . '/../../includes/config.php';

// Un vendeur ou un admin déjà connecté va dans son espace.
$client_connecte = utilisateur_connecte();
if ($client_connecte !== null && $client_connecte['role'] !== 'client') {
    rediriger(chemin_espace($client_connecte));
}
$mode_client = $client_connecte !== null;

// Villes proposées (suggestions) : la saisie reste libre.
$villes = ['Dakar', 'Pikine', 'Guédiawaye', 'Rufisque', 'Keur Massar', 'Thiès', 'Mbour', 'Saly',
    'Saint-Louis', 'Touba', 'Kaolack', 'Ziguinchor', 'Diourbel', 'Louga', 'Tambacounda', 'Kolda', 'Fatick'];

$erreurs = [];
$valeurs = $mode_client
    ? ['telephone' => '', 'nom_boutique' => '', 'ville' => '']
    : ['prenom' => '', 'nom' => '', 'email' => '', 'telephone' => '', 'nom_boutique' => '', 'ville' => ''];

// Client connecté : son téléphone est proposé d'office.
if ($mode_client && $client_connecte['telephone'] !== null) {
    $valeurs['telephone'] = formater_telephone($client_connecte['telephone']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifier_csrf()) {
        $erreurs['general'] = 'Votre session a expiré. Merci de renvoyer le formulaire.';
    } else {
        foreach (array_keys($valeurs) as $champ) {
            $valeurs[$champ] = champ_post($champ);
        }

        // 1. Contrôle de chaque champ
        if (!$mode_client) {
            $motDePasse = (string) ($_POST['mot_de_passe'] ?? '');
            $confirmation = (string) ($_POST['confirmation'] ?? '');

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
        }

        $telephone = normaliser_telephone($valeurs['telephone']);
        if ($telephone === null) {
            $erreurs['telephone'] = 'Saisissez un numéro sénégalais de 9 chiffres commençant par 70, 75, 76, 77 ou 78.';
        }

        $longueurBoutique = mb_strlen($valeurs['nom_boutique']);
        $slug = creer_slug($valeurs['nom_boutique']);
        if ($longueurBoutique < 3 || $longueurBoutique > 60) {
            $erreurs['nom_boutique'] = 'Le nom de la boutique doit contenir entre 3 et 60 caractères.';
        } elseif (strlen($slug) < 3) {
            $erreurs['nom_boutique'] = 'Le nom de la boutique doit contenir au moins 3 lettres ou chiffres.';
        }

        if (mb_strlen($valeurs['ville']) < 2 || mb_strlen($valeurs['ville']) > 60) {
            $erreurs['ville'] = 'Indiquez la ville de votre boutique.';
        }

        if (!case_cochee('cgu')) {
            $erreurs['cgu'] = 'Vous devez accepter les conditions générales d\'utilisation.';
        }
        if (!case_cochee('politique_vendeur')) {
            $erreurs['politique_vendeur'] = 'Vous devez accepter la politique vendeur.';
        }

        // 2. Unicité : e-mail (visiteur), numéro (un seul compte vendeur par numéro, §8.3), boutique
        if ($erreurs === []) {
            if (!$mode_client) {
                $requete = bdd()->prepare('SELECT COUNT(*) FROM utilisateurs WHERE email = :email');
                $requete->execute(['email' => $email]);
                if ((int) $requete->fetchColumn() > 0) {
                    $erreurs['email'] = 'Un compte existe déjà avec cette adresse e-mail. '
                        . 'Connectez-vous, puis choisissez « Devenir vendeur » dans votre menu.';
                }
            }

            $requete = bdd()->prepare("SELECT COUNT(*) FROM utilisateurs WHERE telephone = :telephone AND role = 'vendeur'");
            $requete->execute(['telephone' => $telephone]);
            if ((int) $requete->fetchColumn() > 0) {
                $erreurs['telephone'] = 'Ce numéro est déjà associé à un compte vendeur.';
            }

            $requete = bdd()->prepare('SELECT COUNT(*) FROM boutiques WHERE nom = :nom OR slug = :slug');
            $requete->execute(['nom' => $valeurs['nom_boutique'], 'slug' => $slug]);
            if ((int) $requete->fetchColumn() > 0) {
                $erreurs['nom_boutique'] = 'Ce nom de boutique est déjà pris. Choisissez-en un autre.';
            }
        }

        // 3. Compte vendeur + boutique, tout ou rien (transaction)
        if ($erreurs === []) {
            $pdo = bdd();
            $messageEssai = null;   // rempli si l'essai démarre sans nouveau code SMS
            $pdo->beginTransaction();

            try {
                if ($mode_client) {
                    $id = (int) $client_connecte['id'];

                    // Téléphone déjà vérifié et inchangé : la vérification reste valable.
                    $telephoneDejaVerifie = $client_connecte['telephone'] === $telephone
                        && $client_connecte['telephone_verifie_le'] !== null;

                    $miseAJour = $pdo->prepare(
                        "UPDATE utilisateurs
                         SET role = 'vendeur', telephone = :telephone,
                             telephone_verifie_le = CASE WHEN :garder = 1 THEN telephone_verifie_le ELSE NULL END,
                             cgu_acceptees_le = NOW()
                         WHERE id = :id AND role = 'client'"
                    );
                    $miseAJour->execute(['telephone' => $telephone, 'garder' => $telephoneDejaVerifie ? 1 : 0, 'id' => $id]);
                    if ($miseAJour->rowCount() !== 1) {
                        throw new RuntimeException('Le compte client n\'a pas pu être transformé en compte vendeur.');
                    }
                } else {
                    $telephoneDejaVerifie = false;

                    $pdo->prepare(
                        "INSERT INTO utilisateurs (role, prenom, nom, email, telephone, mot_de_passe_hache, cgu_acceptees_le)
                         VALUES ('vendeur', :prenom, :nom, :email, :telephone, :hache, NOW())"
                    )->execute([
                        'prenom' => $valeurs['prenom'],
                        'nom' => $valeurs['nom'],
                        'email' => $email,
                        'telephone' => $telephone,
                        'hache' => password_hash($motDePasse, PASSWORD_DEFAULT),
                    ]);
                    $id = (int) $pdo->lastInsertId();
                }

                $pdo->prepare(
                    'INSERT INTO boutiques (utilisateur_id, nom, slug, ville, numero_whatsapp)
                     VALUES (:utilisateur_id, :nom, :slug, :ville, :whatsapp)'
                )->execute([
                    'utilisateur_id' => $id,
                    'nom' => $valeurs['nom_boutique'],
                    'slug' => $slug,
                    'ville' => $valeurs['ville'],
                    'whatsapp' => $telephone,
                ]);

                // Essai immédiat : numéro déjà vérifié, ou vérification SMS désactivée (config.php).
                if ($telephoneDejaVerifie || !VERIFICATION_SMS) {
                    $messageEssai = demarrer_essai_gratuit($id, $telephone);
                }

                $pdo->commit();
            } catch (PDOException $exception) {
                $pdo->rollBack();

                // Doublon créé entre-temps par une autre inscription simultanée.
                if ($exception->getCode() !== '23000') {
                    throw $exception;
                }
                $erreurs['general'] = 'Cette adresse e-mail, ce numéro ou ce nom de boutique vient d\'être utilisé. Vérifiez vos informations.';
            }
        }

        // 4. Connexion (nouvel identifiant de session : les droits ont changé), puis vérification
        if ($erreurs === []) {
            journaliser_action($mode_client ? 'client_devenu_vendeur' : 'inscription_vendeur', $id, 'utilisateur', $id);
            connecter_utilisateur(trouver_utilisateur($id));

            if ($messageEssai !== null) {
                message_flash('succes', 'Votre boutique « ' . $valeurs['nom_boutique'] . ' » est ouverte ! ' . $messageEssai);
                rediriger('vendeur/');
            }

            $code = creer_code_sms('verification_telephone', $telephone, $id);
            if ($code !== null) {
                envoyer_sms($telephone, "SAMA MAILLOT : votre code de vérification est $code. Il expire dans 10 minutes.");
            }
            message_flash('succes', 'Votre boutique « ' . $valeurs['nom_boutique'] . ' » est créée. Il reste à vérifier votre téléphone.');
            rediriger('inscription/verifier-telephone.php');
        }
    }
}

$titre_page = $mode_client ? 'Devenir vendeur' : 'Ouvrir ma boutique';
$description_page = 'Vendez vos maillots de football sur SAMA MAILLOT : '
    . ESSAI_GRATUIT_JOURS . ' jours d\'essai gratuit, puis ' . format_fcfa(PRIX_ABONNEMENT_FCFA) . ' par mois.';
$page_active = $mode_client ? 'devenir-vendeur' : 'inscription-vendeur';

require __DIR__ . '/../../includes/entete.php';
?>

<section class="sm-page-formulaire">
    <div class="container">
        <div class="sm-carte-formulaire sm-carte-formulaire--large">
            <span class="sm-sur-titre">Espace vendeur</span>
            <h1 class="mb-1">Ouvrir ma boutique</h1>
            <p class="text-body-secondary mb-4">
                <?= e(ESSAI_GRATUIT_JOURS) ?> jours d'essai gratuit, puis <?= e(format_fcfa(PRIX_ABONNEMENT_FCFA)) ?> par mois.
                <?php if (!$mode_client): ?>
                    Déjà vendeur ? <a href="<?= e(url_page('connexion')) ?>">Se connecter</a>
                <?php endif; ?>
            </p>

            <?php if ($mode_client): ?>
                <div class="alert alert-info" role="note">
                    <i class="bi bi-info-circle" aria-hidden="true"></i>
                    Votre compte <strong><?= e($client_connecte['email']) ?></strong> devient un compte vendeur :
                    même adresse e-mail, même mot de passe, favoris conservés.
                    Vous pourrez <strong>toujours acheter</strong> des maillots dans les autres boutiques.
                </div>
            <?php endif; ?>

            <?= alerte_formulaire($erreurs) ?>

            <form method="post" novalidate>
                <?= champ_csrf() ?>

                <?php if (!$mode_client): ?>
                    <h2 class="h6 text-uppercase text-body-secondary mb-3">Vos informations</h2>

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
                <?php endif; ?>

                <div class="mb-3">
                    <label class="form-label" for="telephone">Téléphone</label>
                    <div class="input-group">
                        <span class="input-group-text" aria-hidden="true">+221</span>
                        <input class="form-control<?= classe_erreur($erreurs, 'telephone') ?>" type="tel" id="telephone" name="telephone"
                               value="<?= e($valeurs['telephone']) ?>" autocomplete="tel-national" inputmode="numeric"
                               placeholder="77 123 45 67" maxlength="20" required
                               aria-describedby="aide-telephone<?= isset($erreurs['telephone']) ? ' erreur-telephone' : '' ?>"
                               <?= isset($erreurs['telephone']) ? 'aria-invalid="true"' : '' ?>>
                    </div>
                    <div class="form-text" id="aide-telephone">
                        <?php if (!VERIFICATION_SMS): ?>
                            Numéro de contact de votre boutique (WhatsApp).
                        <?php elseif ($mode_client && $client_connecte['telephone_verifie_le'] !== null): ?>
                            Numéro déjà vérifié : gardez-le pour démarrer tout de suite, sans nouveau code.
                        <?php else: ?>
                            Un code de vérification vous sera envoyé par SMS.
                        <?php endif; ?>
                    </div>
                    <?= message_erreur($erreurs, 'telephone') ?>
                </div>

                <?php if (!$mode_client): ?>
                    <?= champ_mot_de_passe('mot_de_passe', 'Mot de passe', $erreurs, 'new-password',
                        '8 caractères minimum, avec au moins une lettre et un chiffre.') ?>
                    <?= champ_mot_de_passe('confirmation', 'Confirmer le mot de passe', $erreurs, 'new-password') ?>
                <?php endif; ?>

                <h2 class="h6 text-uppercase text-body-secondary mt-4 mb-3">Votre boutique</h2>

                <div class="mb-3">
                    <label class="form-label" for="nom_boutique">Nom de la boutique</label>
                    <input class="form-control<?= classe_erreur($erreurs, 'nom_boutique') ?>" type="text" id="nom_boutique" name="nom_boutique"
                           value="<?= e($valeurs['nom_boutique']) ?>" autocomplete="organization" maxlength="60" required
                           aria-describedby="aide-boutique<?= isset($erreurs['nom_boutique']) ? ' erreur-nom_boutique' : '' ?>"
                           <?= isset($erreurs['nom_boutique']) ? 'aria-invalid="true"' : '' ?>>
                    <div class="form-text" id="aide-boutique">Il doit être unique. Exemple : Dakar Foot Store.</div>
                    <?= message_erreur($erreurs, 'nom_boutique') ?>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="ville">Ville</label>
                    <input class="form-control<?= classe_erreur($erreurs, 'ville') ?>" type="text" id="ville" name="ville"
                           value="<?= e($valeurs['ville']) ?>" list="liste-villes" autocomplete="address-level2" maxlength="60" required
                           <?= aria_erreur($erreurs, 'ville') ?>>
                    <datalist id="liste-villes">
                        <?php foreach ($villes as $ville): ?>
                            <option value="<?= e($ville) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                    <?= message_erreur($erreurs, 'ville') ?>
                </div>

                <div class="form-check mt-4">
                    <input class="form-check-input<?= classe_erreur($erreurs, 'cgu') ?>" type="checkbox" id="cgu" name="cgu" value="1"
                           required <?= aria_erreur($erreurs, 'cgu') ?>>
                    <label class="form-check-label" for="cgu">
                        J'accepte les <a href="<?= e(url_page('cgu')) ?>" target="_blank" rel="noopener">conditions générales d'utilisation</a>.
                    </label>
                </div>
                <?= message_erreur($erreurs, 'cgu') ?>

                <div class="form-check mb-4">
                    <input class="form-check-input<?= classe_erreur($erreurs, 'politique_vendeur') ?>" type="checkbox" id="politique_vendeur"
                           name="politique_vendeur" value="1" required <?= aria_erreur($erreurs, 'politique_vendeur') ?>>
                    <label class="form-check-label" for="politique_vendeur">
                        J'accepte la <a href="<?= e(url_page('politique-vendeur')) ?>" target="_blank" rel="noopener">politique vendeur</a>
                        (authenticité déclarée, répliques signalées comme telles)<?php if (paiement_en_ligne_actif()): ?> et le
                        <a href="<?= e(url_page('cgv')) ?>#paiement" target="_blank" rel="noopener">mandat d'encaissement</a> des paiements en ligne<?php endif; ?>.
                    </label>
                </div>
                <?= message_erreur($erreurs, 'politique_vendeur') ?>

                <button class="btn btn-or w-100 mt-2" type="submit">
                    <i class="bi bi-shop" aria-hidden="true"></i> <?= $mode_client ? 'Ouvrir ma boutique' : 'Créer ma boutique' ?>
                </button>
            </form>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
