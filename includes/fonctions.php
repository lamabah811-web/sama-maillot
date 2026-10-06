<?php
/**
 * SAMA MAILLOT — fonctions utilitaires utilisées dans toutes les pages.
 */

declare(strict_types=1);

/* -------------------------------------------------------------------------
   Affichage
   ------------------------------------------------------------------------- */

/**
 * Échappe une valeur avant de l'afficher dans le HTML (protection XSS, §24).
 * À utiliser pour TOUT ce qui vient de l'utilisateur ou de la base : <?= e($nom) ?>
 */
function e(mixed $valeur): string
{
    return htmlspecialchars((string) $valeur, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Montant en francs CFA, sans décimales : format_fcfa(15000) → « 15 000 FCFA ».
 * Espaces insécables (fine entre les milliers, normale avant FCFA) : le montant
 * n'est jamais coupé en fin de ligne.
 */
function format_fcfa(int $montant): string
{
    return number_format($montant, 0, ',', "\u{202F}") . "\u{00A0}FCFA";
}

/* -------------------------------------------------------------------------
   Adresses (URL)
   ------------------------------------------------------------------------- */

/**
 * Adresse complète d'une page du site, construite à partir de APP_URL.
 * url() → http://sama-maillot.test/ · url('pages/faq.php') → http://sama-maillot.test/pages/faq.php
 */
function url(string $chemin = ''): string
{
    return APP_URL . '/' . ltrim($chemin, '/');
}

/**
 * Adresse complète de la page affichée (sans les paramètres), pour l'aperçu de partage (og:url).
 * Construite à partir d'APP_URL (jamais de l'en-tête Host, modifiable par le visiteur).
 */
function url_actuelle(): string
{
    $racine = parse_url(APP_URL);
    $origine = ($racine['scheme'] ?? 'https') . '://' . ($racine['host'] ?? '') . (isset($racine['port']) ? ':' . $racine['port'] : '');
    $chemin = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

    return $origine . ($chemin !== '' ? $chemin : '/');
}

/**
 * Adresse d'un fichier CSS, JS ou image, avec sa date de modification (?v=...) :
 * le navigateur garde le fichier en cache, et le retélécharge dès qu'il change.
 */
function ressource(string $chemin): string
{
    $fichier = DOSSIER_PUBLIC . '/' . ltrim($chemin, '/');

    // Version minifiée (outils/minifier.php, §29.3) si elle existe ET est à jour : un fichier
    // source modifié sans relancer la minification reste servi tel quel, jamais une version périmée.
    $minifie = preg_replace('/\.(css|js)$/', '.min.$1', $fichier);
    if ($minifie !== $fichier && is_file($minifie) && is_file($fichier) && filemtime($minifie) >= filemtime($fichier)) {
        $chemin = preg_replace('/\.(css|js)$/', '.min.$1', $chemin);
        $fichier = $minifie;
    }

    $version = is_file($fichier) ? (string) filemtime($fichier) : '1';

    return url($chemin) . '?v=' . $version;
}

/** Vrai si la page (clé du tableau PAGES de config.php) est déjà développée. */
function page_existe(string $cle): bool
{
    return isset(PAGES[$cle]) && is_file(DOSSIER_PUBLIC . '/' . PAGES[$cle]['fichier']);
}

/**
 * Adresse d'une page du site. Si elle n'est pas encore créée, renvoie vers
 * la page « Bientôt disponible » (pages/bientot.php?page=...).
 */
function url_page(string $cle): string
{
    if (page_existe($cle)) {
        // « catalogue/index.php » s'affiche « catalogue/ » : adresse plus propre.
        return url(preg_replace('#(^|/)index\.php$#', '$1', PAGES[$cle]['fichier']));
    }

    return url('pages/bientot.php?page=' . rawurlencode($cle));
}

/** Chemin de la page affichée, sans paramètres. Ex. : « /pages/bientot.php ». */
function chemin_actuel(): string
{
    $chemin = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

    return is_string($chemin) && $chemin !== '' ? $chemin : '/';
}

/**
 * Renvoie ' aria-current="page"' si le lien correspond à la page affichée
 * (pour les lecteurs d'écran et le surlignage du menu), sinon une chaîne vide.
 */
function attribut_page_active(string $cle): string
{
    $pageActuelle = $GLOBALS['page_active'] ?? '';

    return $pageActuelle === $cle ? ' aria-current="page"' : '';
}

/* -------------------------------------------------------------------------
   Redirections et messages
   ------------------------------------------------------------------------- */

/**
 * Redirige vers une page du site puis arrête le script.
 * Toujours après un formulaire POST (« redirection après POST », §24).
 * Seuls les chemins internes sont acceptés : on ne renvoie jamais le visiteur
 * vers un autre site à partir d'une valeur reçue.
 */
function rediriger(string $chemin = '', int $code = 303): never
{
    if (preg_match('#^(https?:)?//#i', $chemin) === 1) {
        $chemin = '';
    }

    header('Location: ' . url($chemin), true, $code);
    exit;
}

/**
 * Enregistre un message à afficher UNE seule fois sur la page suivante.
 * $type : 'succes' (vert), 'erreur' (rouge) ou 'dev' (SMS / e-mail simulés en local).
 *     message_flash('succes', 'Votre adresse a été enregistrée.');
 *     rediriger('compte/');
 *
 * @param string|null $lien lien affiché cliquable sous le message (messages 'dev' uniquement)
 */
function message_flash(string $type, string $texte, ?string $lien = null): void
{
    if (!in_array($type, ['succes', 'erreur', 'dev'], true)) {
        $type = 'erreur';
    }

    $_SESSION['messages_flash'][] = ['type' => $type, 'texte' => $texte, 'lien' => $lien];
}

/**
 * Chemin de la page affichée, relatif à l'adresse du site, avec ses paramètres.
 * Ex. : http://localhost/sama-maillot/compte/?onglet=2 → « compte/?onglet=2 »
 */
function chemin_relatif_actuel(): string
{
    $base = rtrim((string) parse_url(APP_URL, PHP_URL_PATH), '/');
    $demande = (string) ($_SERVER['REQUEST_URI'] ?? '/');

    if ($base !== '' && str_starts_with($demande, $base . '/')) {
        $demande = substr($demande, strlen($base));
    }

    return ltrim($demande, '/');
}

/**
 * Récupère puis efface les messages flash (appelée par entete.php).
 *
 * @return list<array{type: string, texte: string, lien: string|null}>
 */
function lire_messages_flash(): array
{
    $messages = $_SESSION['messages_flash'] ?? [];
    unset($_SESSION['messages_flash']);

    return is_array($messages) ? $messages : [];
}

/* -------------------------------------------------------------------------
   Pages d'erreur
   ------------------------------------------------------------------------- */

/**
 * Affiche une page d'erreur complète aux couleurs du site, puis arrête le script.
 * Utilisée par public/erreurs/*.php et par gerer_exception().
 */
function afficher_erreur(int $code): never
{
    $erreurs = [
        403 => ['bi-lock', 'Accès refusé', 'Vous n\'avez pas l\'autorisation d\'ouvrir cette page.'],
        404 => ['bi-compass', 'Cette page n\'existe pas', 'Le lien est peut-être ancien, ou l\'adresse a été mal saisie.'],
        500 => ['bi-exclamation-triangle', 'Une erreur est survenue', 'Ce n\'est pas de votre faute. Réessayez dans un instant.'],
    ];

    if (!isset($erreurs[$code])) {
        $code = 500;
    }

    [$icone, $titre, $texte] = $erreurs[$code];

    if (!headers_sent()) {
        http_response_code($code);
    }

    // Variables lues par entete.php
    $titre_page = $titre;
    $sans_indexation = true;

    require __DIR__ . '/entete.php';
    ?>
    <section class="sm-page-message" aria-labelledby="titre-erreur">
        <div class="container">
            <span class="sm-medaillon" aria-hidden="true"><i class="bi <?= e($icone) ?>"></i></span>
            <p class="sm-page-message__code" aria-hidden="true"><?= e($code) ?></p>
            <h1 id="titre-erreur" class="fw-bold mb-3">
                <span class="visually-hidden">Erreur <?= e($code) ?> : </span><?= e($titre) ?>
            </h1>
            <p class="lead mb-4"><?= e($texte) ?></p>
            <div class="d-flex flex-wrap justify-content-center gap-2">
                <a class="btn btn-or" href="<?= e(url()) ?>">
                    <i class="bi bi-house" aria-hidden="true"></i> Retour à l'accueil
                </a>
                <a class="btn btn-contour" href="<?= e(url_page('catalogue')) ?>">
                    <i class="bi bi-grid" aria-hidden="true"></i> Voir le catalogue
                </a>
            </div>
        </div>
    </section>
    <?php
    require __DIR__ . '/pied_de_page.php';
    exit;
}

/**
 * Gestionnaire des exceptions non attrapées (déclaré dans config.php).
 * Le détail est journalisé ; le visiteur ne voit qu'un message simple (§31).
 */
function gerer_exception(Throwable $exception): void
{
    static $dejaEnCours = false;

    error_log(sprintf(
        '%s : %s dans %s ligne %d',
        $exception::class,
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine()
    ));

    // En local, on montre l'erreur pour corriger plus vite.
    if (!EN_PRODUCTION) {
        if (!headers_sent()) {
            http_response_code(500);
        }
        echo '<pre style="white-space:pre-wrap;padding:1rem">' . e((string) $exception) . '</pre>';
        return;
    }

    // Si l'affichage de la page 500 échoue lui-même, on se rabat sur un texte minimal.
    if ($dejaEnCours) {
        echo '<h1>Une erreur est survenue</h1><p>Réessayez dans un instant.</p>';
        return;
    }

    $dejaEnCours = true;

    // On jette ce qui avait déjà été préparé pour repartir d'une page propre.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    afficher_erreur(500);
}
