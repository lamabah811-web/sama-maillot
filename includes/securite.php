<?php
/**
 * SAMA MAILLOT — sécurité : session, en-têtes HTTP, protection CSRF (§24).
 */

declare(strict_types=1);

/** Vrai si la page est servie en HTTPS (directement ou derrière le proxy d'Infomaniak). */
function est_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

/* -------------------------------------------------------------------------
   Session
   ------------------------------------------------------------------------- */

/** Durée d'inactivité avant déconnexion automatique : 2 heures (§8.4). */
const SESSION_INACTIVITE_MAX = 2 * 60 * 60;

/**
 * Démarre la session avec des cookies protégés :
 *   - HttpOnly : illisible par JavaScript (limite le vol de session par XSS) ;
 *   - Secure   : envoyé seulement en HTTPS (activé automatiquement en HTTPS) ;
 *   - SameSite=Lax : non envoyé par les formulaires d'autres sites (limite le CSRF).
 */
function demarrer_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE || PHP_SAPI === 'cli') {
        return;
    }

    ini_set('session.use_strict_mode', '1');   // refuse les identifiants inventés
    ini_set('session.use_only_cookies', '1');  // jamais d'identifiant dans l'URL

    session_name('sama_session');
    session_set_cookie_params([
        'lifetime' => 0,          // cookie supprimé à la fermeture du navigateur
        'path' => '/',
        'secure' => est_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();

    // Déconnexion automatique après 2 heures sans activité.
    $maintenant = time();
    $derniereActivite = (int) ($_SESSION['derniere_activite'] ?? $maintenant);

    if ($maintenant - $derniereActivite > SESSION_INACTIVITE_MAX) {
        $_SESSION = [];
        session_regenerate_id(true);
    }

    $_SESSION['derniere_activite'] = $maintenant;
}

/* -------------------------------------------------------------------------
   En-têtes HTTP de sécurité
   ------------------------------------------------------------------------- */

/**
 * Contenu du script de thème (public/js/theme-init.js), recopié dans le <head>
 * par entete.php pour appliquer le thème AVANT l'affichage (pas de flash blanc).
 */
function script_theme(): string
{
    static $contenu = null;

    if ($contenu === null) {
        $contenu = (string) @file_get_contents(DOSSIER_PUBLIC . '/js/theme-init.js');
    }

    return $contenu;
}

/**
 * Envoie les en-têtes de sécurité à chaque page.
 *
 * Content-Security-Policy : n'autorise que nos fichiers, le CDN jsDelivr (Bootstrap et
 * ses icônes) et UN seul script inline, reconnu par son empreinte SHA-256 : celui du thème.
 * L'empreinte est recalculée à partir du fichier : rien à mettre à jour à la main.
 */
function envoyer_entetes_securite(): void
{
    if (headers_sent()) {
        return;
    }

    $cdn = 'https://cdn.jsdelivr.net';
    $empreinte = "'sha256-" . base64_encode(hash('sha256', script_theme(), true)) . "'";

    // Ne pas annoncer la version de PHP (aide un attaquant à cibler une faille connue).
    // En production, mettre aussi expose_php = Off dans la configuration PHP.
    header_remove('X-Powered-By');
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Content-Security-Policy: ' . implode('; ', [
        "default-src 'self'",
        "script-src 'self' $cdn $empreinte",
        "style-src 'self' $cdn",
        "font-src 'self' $cdn",
        "img-src 'self' data:",
        "connect-src 'self'",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'" . DOMAINES_PAIEMENT_CSP . DOMAINES_CONNEXION_SOCIALE_CSP,   // + paiement, + Google / Facebook (« Lier » est un formulaire)
        "frame-ancestors 'none'",
    ]));

    // Local et recette : jamais indexés par les moteurs de recherche (§30, §33.1).
    if (APP_ENV !== 'production') {
        header('X-Robots-Tag: noindex, nofollow');
    }

    // HSTS : uniquement en HTTPS (il serait ignoré, voire gênant, en HTTP local).
    if (est_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

/* -------------------------------------------------------------------------
   Protection CSRF
   Chaque formulaire POST contient un jeton secret lié à la session. Un autre
   site ne connaît pas ce jeton : il ne peut donc pas soumettre le formulaire
   à la place de l'utilisateur.
   ------------------------------------------------------------------------- */

/** Jeton CSRF de la session (créé au premier appel). */
function jeton_csrf(): string
{
    if (empty($_SESSION['jeton_csrf']) || !is_string($_SESSION['jeton_csrf'])) {
        $_SESSION['jeton_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['jeton_csrf'];
}

/** Champ caché à placer dans chaque formulaire POST : <?= champ_csrf() ?> */
function champ_csrf(): string
{
    return '<input type="hidden" name="jeton_csrf" value="' . e(jeton_csrf()) . '">';
}

/**
 * Vérifie le jeton envoyé avec le formulaire. À appeler au début de chaque traitement POST :
 *     if (!verifier_csrf()) { afficher_erreur(403); }
 */
function verifier_csrf(): bool
{
    $jetonRecu = $_POST['jeton_csrf'] ?? '';
    $jetonAttendu = $_SESSION['jeton_csrf'] ?? '';

    return is_string($jetonRecu) && is_string($jetonAttendu)
        && $jetonAttendu !== '' && hash_equals($jetonAttendu, $jetonRecu);
}

/* -------------------------------------------------------------------------
   Chiffrement de petites données sensibles (code de livraison, §14.1)
   AES-256-GCM : le texte est illisible sans la clé CLE_CHIFFREMENT du fichier .env,
   et toute modification en base est détectée (étiquette d'authentification).
   ------------------------------------------------------------------------- */

/** Clé de 32 octets lue dans .env (CLE_CHIFFREMENT, en base64). */
function cle_chiffrement(): string
{
    $cle = base64_decode((string) env('CLE_CHIFFREMENT', ''), true);

    if ($cle === false || strlen($cle) !== 32) {
        throw new RuntimeException('CLE_CHIFFREMENT absente ou invalide dans .env (32 octets en base64).');
    }

    return $cle;
}

/** Chiffre un texte court. Résultat stockable en base : base64(iv . étiquette . texte chiffré). */
function chiffrer(string $texte): string
{
    $iv = random_bytes(12);
    $etiquette = '';
    $chiffre = openssl_encrypt($texte, 'aes-256-gcm', cle_chiffrement(), OPENSSL_RAW_DATA, $iv, $etiquette);

    if ($chiffre === false) {
        throw new RuntimeException('Chiffrement impossible.');
    }

    return base64_encode($iv . $etiquette . $chiffre);
}

/** Déchiffre un texte produit par chiffrer() ; null s'il est illisible ou modifié. */
function dechiffrer(string $donnees): ?string
{
    $brut = base64_decode($donnees, true);

    if ($brut === false || strlen($brut) < 29) {
        return null;
    }

    $texte = openssl_decrypt(substr($brut, 28), 'aes-256-gcm', cle_chiffrement(), OPENSSL_RAW_DATA,
        substr($brut, 0, 12), substr($brut, 12, 16));

    return $texte === false ? null : $texte;
}
