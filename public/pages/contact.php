<?php
/**
 * SAMA MAILLOT — page « Contact » (§7.3) : formulaire + numéro WhatsApp du support.
 *
 * - Les messages sont enregistrés (table messages_contact) et traités par l'administration
 *   (lot 5). Une copie part par e-mail au support si le paramètre email_support est rempli.
 * - Le numéro WhatsApp du support (paramètre numero_whatsapp_support) n'est jamais écrit dans
 *   la page : le bouton passe par whatsapp.php?support=1. Sans numéro, le bloc est masqué.
 * - Anti-spam : champ piège invisible, délai minimal de 3 secondes, 3 messages par heure et par IP.
 */
require __DIR__ . '/../../includes/config.php';

const SUJETS_CONTACT = [
    'commande' => 'Une commande',
    'vendeur' => 'Vendre sur SAMA MAILLOT',
    'compte' => 'Mon compte',
    'signalement' => 'Signaler un problème ou un vendeur',
    'partenariat' => 'Partenariat ou presse',
    'autre' => 'Autre question',
];

$utilisateur = utilisateur_connecte();
$whatsapp_support = preg_replace('/\D+/', '', parametre('numero_whatsapp_support')) ?? '';
$email_support = normaliser_email(parametre('email_support'));

$erreurs = [];
$valeurs = [
    'nom' => $utilisateur !== null ? $utilisateur['prenom'] . ' ' . $utilisateur['nom'] : '',
    'email' => $utilisateur['email'] ?? '',
    'telephone' => $utilisateur !== null && $utilisateur['telephone'] !== null ? formater_telephone($utilisateur['telephone']) : '',
    'sujet' => is_string($_GET['sujet'] ?? null) && isset(SUJETS_CONTACT[$_GET['sujet']]) ? $_GET['sujet'] : '',
    'numero_commande' => '',
    'message' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($valeurs) as $champ) {
        $valeurs[$champ] = champ_post($champ);
    }
    $valeurs['message'] = trim((string) ($_POST['message'] ?? ''));   // retours à la ligne conservés

    $debut = (int) ($_SESSION['contact_affiche_le'] ?? 0);
    $piege = trim((string) ($_POST['site_web'] ?? ''));

    if (!verifier_csrf()) {
        $erreurs['general'] = 'Votre session a expiré. Merci de renvoyer le formulaire.';
    } elseif ($piege !== '' || $debut === 0 || time() - $debut < 3) {
        // Robot probable : on ne dit pas pourquoi.
        $erreurs['general'] = 'Votre message n\'a pas pu être envoyé. Réessayez dans quelques secondes.';
    } else {
        $nomLongueur = mb_strlen($valeurs['nom']);
        if ($nomLongueur < 2 || $nomLongueur > 101) {
            $erreurs['nom'] = 'Indiquez votre nom (2 à 100 caractères).';
        }

        $email = normaliser_email($valeurs['email']);
        if ($email === null) {
            $erreurs['email'] = 'Saisissez une adresse e-mail valide pour recevoir notre réponse.';
        }

        $telephone = null;
        if ($valeurs['telephone'] !== '') {
            $telephone = normaliser_telephone($valeurs['telephone']);
            if ($telephone === null) {
                $erreurs['telephone'] = 'Numéro sénégalais de 9 chiffres (ou laissez vide).';
            }
        }

        if (!isset(SUJETS_CONTACT[$valeurs['sujet']])) {
            $erreurs['sujet'] = 'Choisissez le sujet de votre message.';
        }

        $numeroCommande = strtoupper(trim($valeurs['numero_commande']));
        if ($numeroCommande !== '' && preg_match('/^CMD-\d{4}-\d{6}$/', $numeroCommande) !== 1) {
            $erreurs['numero_commande'] = 'Format attendu : CMD-2026-000142 (ou laissez vide).';
        }

        $messageLongueur = mb_strlen($valeurs['message']);
        if ($messageLongueur < 10 || $messageLongueur > 3000) {
            $erreurs['message'] = 'Votre message doit contenir entre 10 et 3 000 caractères.';
        }

        // 3 messages par heure et par adresse IP
        if ($erreurs === []) {
            $requete = bdd()->prepare('SELECT COUNT(*) FROM messages_contact WHERE adresse_ip = :ip AND cree_le > NOW() - INTERVAL 1 HOUR');
            $requete->execute(['ip' => adresse_ip()]);
            if ((int) $requete->fetchColumn() >= 3) {
                $erreurs['general'] = 'Vous avez déjà envoyé plusieurs messages. Merci de patienter une heure avant d\'écrire à nouveau.';
            }
        }

        if ($erreurs === []) {
            bdd()->prepare(
                'INSERT INTO messages_contact (utilisateur_id, nom, email, telephone, sujet, numero_commande, message, adresse_ip)
                 VALUES (:utilisateur, :nom, :email, :telephone, :sujet, :commande, :message, :ip)'
            )->execute([
                'utilisateur' => $utilisateur['id'] ?? null,
                'nom' => $valeurs['nom'],
                'email' => $email,
                'telephone' => $telephone,
                'sujet' => $valeurs['sujet'],
                'commande' => $numeroCommande !== '' ? $numeroCommande : null,
                'message' => $valeurs['message'],
                'ip' => adresse_ip(),
            ]);
            $id = (int) bdd()->lastInsertId();

            if ($email_support !== null) {
                envoyer_email($email_support, 'Contact SAMA MAILLOT n° ' . $id . ' : ' . SUJETS_CONTACT[$valeurs['sujet']],
                    "Message de {$valeurs['nom']} <$email>" . ($telephone !== null ? ' — ' . formater_telephone($telephone) : '')
                    . ($numeroCommande !== '' ? "\nCommande : $numeroCommande" : '') . "\n\n" . $valeurs['message']);
            }

            unset($_SESSION['contact_affiche_le']);
            message_flash('succes', 'Merci ' . $valeurs['nom'] . ' ! Votre message est bien envoyé. Nous vous répondrons par e-mail à ' . $email . '.');
            rediriger('pages/contact.php');
        }
    }
}

// Heure d'affichage du formulaire (anti-robot : envoi trop rapide refusé)
$_SESSION['contact_affiche_le'] = $_SESSION['contact_affiche_le'] ?? time();

$titre_page = 'Contact';
$description_page = 'Contactez l\'équipe SAMA MAILLOT : une question sur une commande, un vendeur, votre compte ou un partenariat.';
$page_active = 'contact';

require __DIR__ . '/../../includes/entete.php';
?>

<section class="py-4 py-md-5">
    <div class="container">
        <span class="sm-sur-titre">Contact</span>
        <h1 class="sm-titre-section mb-1">Écrivez-nous</h1>
        <p class="sm-texte-section mb-4">Une question sur une commande, un vendeur ou votre compte ? Notre équipe vous répond par e-mail.</p>

        <div class="row g-4">
            <div class="col-12 col-lg-7">
                <div class="sm-tuile">
                    <?= alerte_formulaire($erreurs) ?>

                    <form method="post" novalidate>
                        <?= champ_csrf() ?>

                        <!-- Champ piège : invisible pour les personnes, rempli par les robots -->
                        <div class="sm-piege" aria-hidden="true">
                            <label for="site_web">Ne pas remplir</label>
                            <input type="text" id="site_web" name="site_web" tabindex="-1" autocomplete="off">
                        </div>

                        <div class="row g-3">
                            <div class="col-12 col-sm-6">
                                <label class="form-label" for="nom">Nom</label>
                                <input class="form-control<?= classe_erreur($erreurs, 'nom') ?>" type="text" id="nom" name="nom" maxlength="101"
                                       value="<?= e($valeurs['nom']) ?>" autocomplete="name" required <?= aria_erreur($erreurs, 'nom') ?>>
                                <?= message_erreur($erreurs, 'nom') ?>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label" for="email">Adresse e-mail</label>
                                <input class="form-control<?= classe_erreur($erreurs, 'email') ?>" type="email" id="email" name="email" maxlength="190"
                                       value="<?= e($valeurs['email']) ?>" autocomplete="email" inputmode="email" required <?= aria_erreur($erreurs, 'email') ?>>
                                <?= message_erreur($erreurs, 'email') ?>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label" for="telephone">Téléphone <span class="text-body-secondary fw-normal">(facultatif)</span></label>
                                <div class="input-group">
                                    <span class="input-group-text" aria-hidden="true">+221</span>
                                    <input class="form-control<?= classe_erreur($erreurs, 'telephone') ?>" type="tel" id="telephone" name="telephone" maxlength="20"
                                           value="<?= e($valeurs['telephone']) ?>" autocomplete="tel-national" inputmode="numeric" placeholder="77 123 45 67"
                                           <?= aria_erreur($erreurs, 'telephone') ?>>
                                </div>
                                <?= message_erreur($erreurs, 'telephone') ?>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label" for="sujet">Sujet</label>
                                <select class="form-select<?= classe_erreur($erreurs, 'sujet') ?>" id="sujet" name="sujet" required <?= aria_erreur($erreurs, 'sujet') ?>>
                                    <option value="">Choisir…</option>
                                    <?php foreach (SUJETS_CONTACT as $cle => $libelle): ?>
                                        <option value="<?= e($cle) ?>"<?= $valeurs['sujet'] === $cle ? ' selected' : '' ?>><?= e($libelle) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?= message_erreur($erreurs, 'sujet') ?>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="numero_commande">Numéro de commande <span class="text-body-secondary fw-normal">(facultatif)</span></label>
                                <input class="form-control<?= classe_erreur($erreurs, 'numero_commande') ?>" type="text" id="numero_commande" name="numero_commande"
                                       maxlength="20" value="<?= e($valeurs['numero_commande']) ?>" placeholder="CMD-2026-000142" autocomplete="off"
                                       <?= aria_erreur($erreurs, 'numero_commande') ?>>
                                <?= message_erreur($erreurs, 'numero_commande') ?>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="message">Message</label>
                                <textarea class="form-control<?= classe_erreur($erreurs, 'message') ?>" id="message" name="message" rows="6" maxlength="3000"
                                          required <?= aria_erreur($erreurs, 'message') ?>><?= e($valeurs['message']) ?></textarea>
                                <?= message_erreur($erreurs, 'message') ?>
                            </div>
                        </div>

                        <button class="btn btn-or btn-lg w-100 mt-4" type="submit">
                            <i class="bi bi-send" aria-hidden="true"></i> Envoyer mon message
                        </button>
                        <p class="small text-body-secondary mt-2 mb-0">
                            Vos informations servent uniquement à vous répondre.
                            <a href="<?= e(url_page('confidentialite')) ?>">Politique de confidentialité</a>
                        </p>
                    </form>
                </div>
            </div>

            <div class="col-12 col-lg-5">
                <?php if (preg_match('/^2217[05678]\d{7}$/', $whatsapp_support) === 1): ?>
                    <div class="sm-tuile sm-contact-whatsapp mb-3">
                        <span class="sm-icone-teintee mb-3" aria-hidden="true"><i class="bi bi-whatsapp"></i></span>
                        <h2 class="h5 fw-bold">Support sur WhatsApp</h2>
                        <p>Le plus rapide pour une question simple. Écrivez-nous directement.</p>
                        <a class="btn btn-or w-100" href="<?= e(url('whatsapp.php?support=1')) ?>" target="_blank" rel="noopener nofollow">
                            <i class="bi bi-whatsapp" aria-hidden="true"></i> Écrire au support
                        </a>
                    </div>
                <?php endif; ?>

                <div class="sm-tuile mb-3">
                    <h2 class="h6 fw-bold"><i class="bi bi-box-seam" aria-hidden="true"></i> Un souci avec une commande ?</h2>
                    <p class="small mb-2">
                        Le plus simple est de contacter directement le vendeur depuis votre commande :
                        bouton « Contacter le vendeur » dans <a href="<?= e(url_page('mes-commandes')) ?>">Mes commandes</a>.
                    </p>
                    <p class="small mb-0">Indiquez votre numéro de commande dans le formulaire si vous nous écrivez.</p>
                </div>

                <div class="sm-tuile mb-3">
                    <h2 class="h6 fw-bold"><i class="bi bi-shop" aria-hidden="true"></i> Vous voulez vendre ?</h2>
                    <p class="small mb-2">
                        Ouvrez votre boutique en quelques minutes : <?= e(ESSAI_GRATUIT_JOURS) ?> jours d'essai gratuit,
                        puis <?= e(format_fcfa(PRIX_ABONNEMENT_FCFA)) ?> par mois.
                    </p>
                    <a class="btn btn-contour btn-sm" href="<?= e(url_page('devenir-vendeur')) ?>">Ouvrir ma boutique</a>
                </div>

                <div class="sm-tuile">
                    <h2 class="h6 fw-bold"><i class="bi bi-geo-alt" aria-hidden="true"></i> SAMA MAILLOT</h2>
                    <p class="small mb-0">Marketplace de maillots de football, Dakar, Sénégal.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../../includes/pied_de_page.php'; ?>
