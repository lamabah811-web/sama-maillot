<?php
/**
 * SAMA MAILLOT — configuration centrale.
 *
 * C'est le SEUL fichier à inclure en haut de chaque page publique :
 *     require __DIR__ . '/../includes/config.php';
 *
 * Il :
 *   1. lit le fichier .env (secrets et réglages propres à chaque machine) ;
 *   2. règle le fuseau horaire, l'encodage et l'affichage des erreurs ;
 *   3. charge les autres fichiers du socle (base de données, fonctions, sécurité) ;
 *   4. démarre la session sécurisée et envoie les en-têtes HTTP de sécurité.
 */

declare(strict_types=1);

// Dossier racine du projet (celui qui contient public/, includes/, journaux/...).
define('DOSSIER_RACINE', dirname(__DIR__));
define('DOSSIER_PUBLIC', DOSSIER_RACINE . '/public');
define('DOSSIER_JOURNAUX', DOSSIER_RACINE . '/journaux');

/* -------------------------------------------------------------------------
   1. Lecture du fichier .env
   Format : une variable par ligne, NOM=valeur. Les lignes vides et celles qui
   commencent par # sont ignorées. Les guillemets autour de la valeur sont retirés.
   ------------------------------------------------------------------------- */

/**
 * Lit le fichier .env une seule fois et renvoie toutes ses variables.
 *
 * @return array<string, string>
 */
function charger_env(string $fichier): array
{
    $variables = [];

    if (!is_readable($fichier)) {
        return $variables;
    }

    $lignes = file($fichier, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

    foreach ($lignes as $ligne) {
        $ligne = trim($ligne);

        // Commentaire ou ligne sans « = » : on passe.
        if ($ligne === '' || str_starts_with($ligne, '#') || !str_contains($ligne, '=')) {
            continue;
        }

        [$nom, $valeur] = explode('=', $ligne, 2);
        $nom = trim($nom);
        $valeur = trim($valeur);

        // Retire les guillemets simples ou doubles autour de la valeur.
        if (strlen($valeur) >= 2 && ($valeur[0] === '"' || $valeur[0] === "'") && $valeur[-1] === $valeur[0]) {
            $valeur = substr($valeur, 1, -1);
        }

        $variables[$nom] = $valeur;
    }

    return $variables;
}

$GLOBALS['sm_env'] = charger_env(DOSSIER_RACINE . '/.env');

/**
 * Valeur d'une variable de configuration, ou $defaut si elle est absente.
 * Une variable d'environnement du serveur (§13.4) est prioritaire sur le fichier .env.
 * Exemple : env('DB_HOST', '127.0.0.1')
 */
function env(string $nom, ?string $defaut = null): ?string
{
    $valeurServeur = getenv($nom);

    if ($valeurServeur !== false) {
        return $valeurServeur;
    }

    return $GLOBALS['sm_env'][$nom] ?? $defaut;
}

/* -------------------------------------------------------------------------
   2. Réglages généraux
   ------------------------------------------------------------------------- */

// Environnement : « local » (XAMPP), « recette » ou « production » (Infomaniak).
define('APP_ENV', env('APP_ENV', 'production'));
define('EN_PRODUCTION', APP_ENV !== 'local');

// Adresse du site, sans « / » final. Ex. : http://localhost/sama-maillot
define('APP_URL', rtrim((string) env('APP_URL', 'http://localhost/sama-maillot'), '/'));

define('NOM_SITE', 'SAMA MAILLOT');

// Vérification du téléphone des vendeurs par code SMS (§8.2). Désactivée tant qu'aucun
// fournisseur SMS n'est branché : la boutique et l'essai gratuit démarrent directement.
// Mettre VERIFICATION_SMS=1 dans .env pour la réactiver.
define('VERIFICATION_SMS', env('VERIFICATION_SMS', '0') === '1');

// SMS désactivés (décision du porteur du projet) : toutes les notifications partent par e-mail.
// Mettre SMS_ACTIF=1 dans .env pour les rallumer (fournisseur SMS requis, voir metier/sms.php).
define('SMS_ACTIF', env('SMS_ACTIF', '0') === '1');

// Heure de Dakar (UTC+0, sans heure d'été) pour toutes les dates PHP (§26.1).
date_default_timezone_set('Africa/Dakar');
mb_internal_encoding('UTF-8');

/* -------------------------------------------------------------------------
   3. Erreurs PHP
   - En local : affichées à l'écran pour développer facilement.
   - En recette / production : JAMAIS affichées (§24), seulement écrites dans
     journaux/php-AAAA-MM.log (un fichier par mois = rotation simple).
   ------------------------------------------------------------------------- */
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', DOSSIER_JOURNAUX . '/php-' . date('Y-m') . '.log');

if (EN_PRODUCTION) {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
} else {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
}

/* -------------------------------------------------------------------------
   4. Paramètres du site (§ Annexe A). Ils passeront dans la table
   « parametres » à l'étape des abonnements ; ils sont ici pour qu'aucun
   montant ne soit écrit en dur dans les pages.
   ------------------------------------------------------------------------- */
const ESSAI_GRATUIT_JOURS = 14;
const PRIX_ABONNEMENT_FCFA = 3000;

// Les 3 catégories du catalogue (§7.1). La clé servira dans les adresses.
const CATEGORIES = [
    'equipes-nationales' => [
        'nom' => 'Équipes nationales',
        'icone' => 'bi-flag',
        'texte' => 'Sénégal, Maroc, France, Brésil… les maillots des sélections.',
    ],
    'clubs' => [
        'nom' => 'Clubs',
        'icone' => 'bi-shield',
        'texte' => 'Les clubs d\'Europe, d\'Afrique et d\'ailleurs.',
    ],
    'retro' => [
        'nom' => 'Rétro',
        'icone' => 'bi-clock-history',
        'texte' => 'Les maillots qui ont marqué l\'histoire du football.',
    ],
];

/*
 * Pages du site : clé => [titre, fichier dans public/].
 * Tant que le fichier n'existe pas, url_page() renvoie vers « Bientôt disponible ».
 * Quand une page sera créée, ses liens fonctionneront automatiquement partout.
 */
const PAGES = [
    'catalogue' => ['titre' => 'Catalogue', 'fichier' => 'catalogue/index.php'],
    'recherche' => ['titre' => 'Recherche', 'fichier' => 'catalogue/index.php'],   // la recherche affiche le catalogue filtré
    'panier' => ['titre' => 'Panier', 'fichier' => 'panier/index.php'],
    'compte' => ['titre' => 'Mon compte', 'fichier' => 'compte/index.php'],
    'compte-mot-de-passe' => ['titre' => 'Changer de mot de passe', 'fichier' => 'compte/mot-de-passe.php'],
    'connexion' => ['titre' => 'Connexion', 'fichier' => 'connexion/index.php'],
    'deconnexion' => ['titre' => 'Déconnexion', 'fichier' => 'connexion/deconnexion.php'],
    'inscription' => ['titre' => 'Créer un compte', 'fichier' => 'inscription/index.php'],
    'inscription-vendeur' => ['titre' => 'Ouvrir ma boutique', 'fichier' => 'inscription/vendeur.php'],
    'verifier-telephone' => ['titre' => 'Vérifier mon téléphone', 'fichier' => 'inscription/verifier-telephone.php'],
    'mot-de-passe-oublie' => ['titre' => 'Mot de passe oublié', 'fichier' => 'mot-de-passe/index.php'],
    'vendeur' => ['titre' => 'Espace vendeur', 'fichier' => 'vendeur/index.php'],
    'mes-favoris' => ['titre' => 'Mes favoris', 'fichier' => 'compte/favoris.php'],
    'signaler' => ['titre' => 'Signaler un maillot', 'fichier' => 'signalement/index.php'],
    'mes-commandes' => ['titre' => 'Mes commandes', 'fichier' => 'compte/commandes.php'],
    'notifications' => ['titre' => 'Notifications', 'fichier' => 'compte/notifications.php'],
    'admin' => ['titre' => 'Administration', 'fichier' => 'admin/index.php'],
    'politique-vendeur' => ['titre' => 'Politique vendeur', 'fichier' => 'pages/politique-vendeur.php'],
    'a-propos' => ['titre' => 'À propos', 'fichier' => 'pages/a-propos.php'],
    'comment-ca-marche' => ['titre' => 'Comment ça marche', 'fichier' => 'pages/comment-ca-marche.php'],
    'devenir-vendeur' => ['titre' => 'Devenir vendeur', 'fichier' => 'inscription/vendeur.php'],   // mène directement à l'ouverture de boutique
    'faq' => ['titre' => 'Questions fréquentes', 'fichier' => 'pages/faq.php'],
    'contact' => ['titre' => 'Contact', 'fichier' => 'pages/contact.php'],
    'cgu' => ['titre' => 'Conditions générales d\'utilisation', 'fichier' => 'pages/cgu.php'],
    'cgv' => ['titre' => 'Conditions générales de vente', 'fichier' => 'pages/cgv.php'],
    'confidentialite' => ['titre' => 'Politique de confidentialité', 'fichier' => 'pages/confidentialite.php'],
    'mentions-legales' => ['titre' => 'Mentions légales', 'fichier' => 'pages/mentions-legales.php'],
];

/* -------------------------------------------------------------------------
   5. Chargement du reste du socle, puis session et en-têtes de sécurité
   ------------------------------------------------------------------------- */
require_once __DIR__ . '/base_de_donnees.php';
require_once __DIR__ . '/fonctions.php';
require_once __DIR__ . '/securite.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/formulaires.php';
require_once __DIR__ . '/authentification.php';
require_once __DIR__ . '/metier/codes.php';
require_once __DIR__ . '/metier/sms.php';
require_once __DIR__ . '/metier/email.php';
require_once __DIR__ . '/metier/catalogue.php';
require_once __DIR__ . '/metier/images.php';
require_once __DIR__ . '/metier/vendeur.php';
require_once __DIR__ . '/metier/panier.php';
require_once __DIR__ . '/metier/commandes.php';
require_once __DIR__ . '/metier/invites.php';
require_once __DIR__ . '/metier/notifications.php';
require_once __DIR__ . '/metier/admin.php';
require_once __DIR__ . '/metier/signalements.php';
require_once __DIR__ . '/metier/abonnements.php';
require_once __DIR__ . '/metier/avis.php';
require_once __DIR__ . '/metier/litiges.php';
require_once __DIR__ . '/metier/paiements.php';
require_once __DIR__ . '/metier/portefeuille.php';
require_once __DIR__ . '/metier/legal.php';
require_once __DIR__ . '/metier/systeme.php';
require_once __DIR__ . '/taches.php';

// Toute exception non attrapée : journalisée, puis page 500 propre (jamais de détail technique).
set_exception_handler('gerer_exception');

demarrer_session();
envoyer_entetes_securite();

// Identifie l'utilisateur dès maintenant, avant tout affichage : la reconnexion par
// « Rester connecté » doit pouvoir écrire son cookie. Un visiteur anonyme sans cookie
// ne déclenche aucune requête en base.
utilisateur_connecte();

// Tâches automatiques (annulations, abonnements, file des SMS) : au plus toutes les 10 minutes,
// en plus du cron de production (§27.3). Jamais en ligne de commande (les scripts taches/ s'en chargent).
if (PHP_SAPI !== 'cli') {
    executer_taches_si_besoin();
}
