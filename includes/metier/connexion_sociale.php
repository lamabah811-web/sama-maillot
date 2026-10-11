<?php
/**
 * SAMA MAILLOT — connexion et inscription avec Google ou Facebook (une seule source de vérité).
 *
 * Flux « code d'autorisation » entièrement côté serveur (aucun SDK JavaScript) :
 *   1. connexion/google.php ou facebook.php : state (+ nonce et PKCE S256 pour Google) en session,
 *      puis redirection vers le fournisseur ;
 *   2. connexion/google-retour.php ou facebook-retour.php : state vérifié (usage unique, hash_equals),
 *      code échangé côté serveur, identité VÉRIFIÉE (id_token Google signé ; jeton Facebook contrôlé
 *      par /debug_token + appsecret_proof). Rien n'est accepté depuis le navigateur ;
 *   3. règles métier :
 *        A. (fournisseur, identifiant) déjà lié          → connexion ;
 *        B. inconnu, aucun compte avec cet e-mail        → page « Bienvenue » (CGU) puis création ;
 *        C. inconnu, un compte existe avec cet e-mail    → JAMAIS de liaison automatique : mot de passe,
 *                                                          ou code SMS / lien e-mail si pas de mot de passe.
 *
 * Un compte est retrouvé par (fournisseur + identifiant), jamais par son e-mail seul.
 * Aucun jeton d'accès Google ou Facebook n'est conservé (ni en base, ni en session, ni dans les journaux).
 * Les administrateurs ne peuvent pas utiliser ces connexions.
 */

declare(strict_types=1);

use GuzzleHttp\Client as ClientGuzzle;
use GuzzleHttp\ClientInterface;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Facebook;
use League\OAuth2\Client\Provider\Google;

const FOURNISSEURS_SOCIAUX = ['google' => 'Google', 'facebook' => 'Facebook'];
const SOCIAL_TENTATIVES_MAX = 10;              // connexions démarrées par adresse IP...
const SOCIAL_FENETRE_MINUTES = 15;             // ... par tranche de 15 minutes
const SOCIAL_ALLER_RETOUR_SECONDES = 600;      // le visiteur a 10 minutes pour revenir de Google / Facebook
const SOCIAL_LIAISON_SECONDES = 600;           // cas C : identité gardée 10 minutes
const SOCIAL_ATTENTE_SECONDES = 900;           // cas B et e-mail à compléter : 15 minutes
const CODE_EMAIL_SOCIAL_MINUTES = 15;
const CODE_EMAIL_SOCIAL_ESSAIS = 5;
const HTTP_DELAI_SECONDES = 10;

const GOOGLE_EMETTEURS = ['accounts.google.com', 'https://accounts.google.com'];
const GOOGLE_URL_CLES = 'https://www.googleapis.com/oauth2/v3/certs';
const FACEBOOK_URL_GRAPH = 'https://graph.facebook.com/';

// « Lier » (compte/connexions.php) est un formulaire suivi d'une redirection vers le fournisseur :
// ces pages doivent figurer dans la directive form-action de la CSP (securite.php).
const DOMAINES_CONNEXION_SOCIALE_CSP = ' https://accounts.google.com https://www.facebook.com';

/**
 * Erreur à montrer telle quelle au visiteur (message simple, en français, sans détail technique).
 * Le détail technique éventuel est passé à part et seulement journalisé.
 */
final class ErreurConnexionSociale extends RuntimeException
{
    public function __construct(string $messageVisiteur, public readonly string $detail = '')
    {
        parent::__construct($messageVisiteur);
    }
}

// Google ne propose pas PKCE de lui-même dans la bibliothèque : on l'active (méthode S256).
if (class_exists(Google::class) && !class_exists('FournisseurGoogle', false)) {
    final class FournisseurGoogle extends Google
    {
        protected function getPkceMethod(): string
        {
            return AbstractProvider::PKCE_METHOD_S256;
        }
    }
}

/* -------------------------------------------------------------------------
   Configuration
   ------------------------------------------------------------------------- */

function env_vrai(string $nom): bool
{
    return in_array(strtolower(trim((string) env($nom, 'false'))), ['1', 'true', 'oui'], true);
}

/** Vrai si le fournisseur est activé ET complètement configuré (sinon : pas de bouton, pages en 404). */
function fournisseur_actif(string $fournisseur): bool
{
    return match ($fournisseur) {
        'google' => env_vrai('GOOGLE_ACTIF') && trim((string) env('GOOGLE_CLIENT_ID', '')) !== ''
            && trim((string) env('GOOGLE_CLIENT_SECRET', '')) !== '' && class_exists(Google::class),
        'facebook' => env_vrai('FACEBOOK_ACTIF') && preg_match('/^\d{5,25}$/', (string) env('FACEBOOK_APP_ID', '')) === 1
            && trim((string) env('FACEBOOK_APP_SECRET', '')) !== ''
            && preg_match('/^v\d+\.\d+$/', version_api_facebook()) === 1 && class_exists(Facebook::class),
        default => false,
    };
}

/** @return list<string> fournisseurs utilisables, dans l'ordre d'affichage */
function fournisseurs_actifs(): array
{
    return array_values(array_filter(array_keys(FOURNISSEURS_SOCIAUX), 'fournisseur_actif'));
}

/** Pages Google / Facebook : 404 si le fournisseur est inactif ou mal configuré. */
function exiger_fournisseur_actif(string $fournisseur): void
{
    if (!fournisseur_actif($fournisseur)) {
        afficher_erreur(404);
    }
}

function nom_fournisseur(string $fournisseur): string
{
    return FOURNISSEURS_SOCIAUX[$fournisseur] ?? $fournisseur;
}

function version_api_facebook(): string
{
    return trim((string) env('FACEBOOK_VERSION_API', 'v21.0'));
}

/** Adresse de retour déclarée chez le fournisseur (.env), sinon celle du site. */
function url_retour_sociale(string $fournisseur): string
{
    $configuree = trim((string) env(strtoupper($fournisseur) . '_URL_RETOUR', ''));

    return $configuree !== '' ? $configuree : url('connexion/' . $fournisseur . '-retour.php');
}

/**
 * Client HTTP vers Google et Facebook : délai de 10 secondes, certificat TLS toujours vérifié.
 * Les tests le remplacent par un client simulé ($GLOBALS['sm_client_http_oauth']).
 */
function client_http_oauth(): ClientInterface
{
    if (($GLOBALS['sm_client_http_oauth'] ?? null) instanceof ClientInterface) {
        return $GLOBALS['sm_client_http_oauth'];
    }

    static $client = null;

    return $client ??= new ClientGuzzle([
        'timeout' => HTTP_DELAI_SECONDES,
        'connect_timeout' => HTTP_DELAI_SECONDES,
        'verify' => true,
        'allow_redirects' => false,
    ]);
}

function fournisseur_oauth(string $fournisseur): AbstractProvider
{
    $collaborateurs = ['httpClient' => client_http_oauth()];

    return match ($fournisseur) {
        'google' => new FournisseurGoogle([
            'clientId' => (string) env('GOOGLE_CLIENT_ID'),
            'clientSecret' => (string) env('GOOGLE_CLIENT_SECRET'),
            'redirectUri' => url_retour_sociale('google'),
        ], $collaborateurs),
        'facebook' => new Facebook([
            'clientId' => (string) env('FACEBOOK_APP_ID'),
            'clientSecret' => (string) env('FACEBOOK_APP_SECRET'),
            'redirectUri' => url_retour_sociale('facebook'),
            'graphApiVersion' => version_api_facebook(),
        ], $collaborateurs),
        default => throw new InvalidArgumentException('Fournisseur inconnu.'),
    };
}

/* -------------------------------------------------------------------------
   Limitation et redirection après connexion
   ------------------------------------------------------------------------- */

/** Vrai si cette adresse IP a déjà démarré 10 connexions Google / Facebook en 15 minutes. */
function trop_de_connexions_sociales(): bool
{
    $requete = bdd()->prepare(
        'SELECT COUNT(*) FROM tentatives_connexion_sociale
         WHERE adresse_ip = :ip AND cree_le > NOW() - INTERVAL ' . SOCIAL_FENETRE_MINUTES . ' MINUTE'
    );
    $requete->execute(['ip' => adresse_ip()]);

    return (int) $requete->fetchColumn() >= SOCIAL_TENTATIVES_MAX;
}

/**
 * Mémorise la page à ouvrir après la connexion, UNIQUEMENT si c'est un chemin interne :
 * commence par « / » mais pas par « // » ni « /\ » (sinon un lien piégé renverrait vers un autre site).
 */
function memoriser_redirection(mixed $valeur): void
{
    if (is_string($valeur) && strlen($valeur) <= 300
        && preg_match('#^/(?![/\\\\])[a-z0-9/_.\-]*(\?[a-z0-9=&_.%\-]*)?$#i', $valeur) === 1) {
        $_SESSION['retour_apres_connexion'] = ltrim($valeur, '/');
    }
}

/* -------------------------------------------------------------------------
   1. Départ vers Google / Facebook
   ------------------------------------------------------------------------- */

/**
 * Démarre la connexion : state (et pour Google nonce + PKCE) gardés en session 10 minutes,
 * puis redirection vers la page d'autorisation du fournisseur.
 *
 * @param string $mode 'connexion' ou 'liaison' (ajout depuis « Mes connexions », compte connecté)
 * @param bool $redemander Facebook : redemander l'accès à l'e-mail refusé (auth_type=rerequest)
 */
function demarrer_connexion_sociale(string $fournisseur, string $mode = 'connexion', bool $redemander = false): never
{
    $retour = $mode === 'liaison' ? 'compte/connexions.php' : 'connexion/';

    if (trop_de_connexions_sociales()) {
        message_flash('erreur', 'Trop de tentatives de connexion. Pour votre sécurité, réessayez dans 15 minutes.');
        rediriger($retour);
    }

    bdd()->prepare('INSERT INTO tentatives_connexion_sociale (fournisseur, adresse_ip) VALUES (:f, :ip)')
        ->execute(['f' => $fournisseur, 'ip' => adresse_ip()]);

    $state = bin2hex(random_bytes(32));
    $attente = [
        'fournisseur' => $fournisseur,
        'state' => $state,
        'mode' => $mode,
        'utilisateur_id' => $mode === 'liaison' ? (int) (utilisateur_connecte()['id'] ?? 0) : 0,
        'expire' => time() + SOCIAL_ALLER_RETOUR_SECONDES,
    ];

    try {
        $fournisseurOauth = fournisseur_oauth($fournisseur);

        if ($fournisseur === 'google') {
            $attente['nonce'] = bin2hex(random_bytes(32));
            $adresse = $fournisseurOauth->getAuthorizationUrl([
                'state' => $state,
                'nonce' => $attente['nonce'],
                'prompt' => 'select_account',
            ]);
            $attente['verificateur'] = (string) $fournisseurOauth->getPkceCode();   // code_verifier PKCE
        } else {
            $options = ['state' => $state, 'scope' => ['public_profile', 'email']];
            if ($redemander) {
                $options['auth_type'] = 'rerequest';
            }
            $adresse = $fournisseurOauth->getAuthorizationUrl($options);
        }
    } catch (Throwable $exception) {
        journaliser_erreur_sociale($fournisseur, $exception);
        message_flash('erreur', 'La connexion avec ' . nom_fournisseur($fournisseur) . ' est indisponible pour le moment. Réessayez plus tard.');
        rediriger($retour);
    }

    $_SESSION['oauth_en_cours'] = $attente;

    header('Location: ' . $adresse, true, 303);
    exit;
}

/**
 * Page de départ (connexion/google.php, connexion/facebook.php) :
 *   POST action=lier + jeton CSRF  ajout depuis « Mes connexions » (compte connecté) ;
 *   ?redirection=/chemin           page à ouvrir après la connexion (chemin interne uniquement) ;
 *   ?redemander=1                  Facebook : redemander l'accès à l'e-mail.
 */
function page_depart_sociale(string $fournisseur): never
{
    exiger_fournisseur_actif($fournisseur);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $utilisateur = exiger_connexion();
        if (!verifier_csrf() || champ_post('action') !== 'lier') {
            afficher_erreur(403);
        }
        if ($utilisateur['role'] === 'admin') {
            message_flash('erreur', 'Les comptes administrateur se connectent uniquement avec leur mot de passe.');
            rediriger('compte/connexions.php');
        }
        demarrer_connexion_sociale($fournisseur, 'liaison');
    }

    rediriger_si_connecte();
    memoriser_redirection($_GET['redirection'] ?? null);
    demarrer_connexion_sociale($fournisseur, 'connexion', $fournisseur === 'facebook' && ($_GET['redemander'] ?? '') === '1');
}

/* -------------------------------------------------------------------------
   2. Retour du fournisseur
   ------------------------------------------------------------------------- */

/**
 * Page de retour complète : vérifie, identifie, applique les règles, puis redirige.
 * N'affiche JAMAIS d'exception : en cas d'erreur, journalisation + retour avec un message simple.
 */
function terminer_retour_oauth(string $fournisseur): never
{
    $mode = (string) ($_SESSION['oauth_en_cours']['mode'] ?? 'connexion');
    $pageErreur = $mode === 'liaison' ? 'compte/connexions.php' : 'connexion/';

    try {
        $attente = lire_retour_oauth($fournisseur, $_GET);
        $identite = $fournisseur === 'google'
            ? identite_google($attente['code'], (string) $attente['verificateur'], (string) $attente['nonce'])
            : identite_facebook($attente['code']);

        rediriger(traiter_identite_sociale($identite, $attente));
    } catch (ErreurConnexionSociale $erreur) {
        if ($erreur->detail !== '') {
            error_log('Connexion ' . $fournisseur . ' refusée : ' . masquer_secrets($erreur->detail));
        }
        message_flash('erreur', $erreur->getMessage());
    } catch (Throwable $exception) {
        journaliser_erreur_sociale($fournisseur, $exception);
        message_flash('erreur', 'La connexion avec ' . nom_fournisseur($fournisseur) . ' a échoué. Réessayez ou utilisez votre e-mail.');
    }

    rediriger($pageErreur);
}

/**
 * Contrôle le retour : annulation, state (usage unique, comparé avec hash_equals), délai, code.
 *
 * @param array<string, mixed> $parametres paramètres reçus en GET
 * @return array état mémorisé au départ + 'code'
 */
function lire_retour_oauth(string $fournisseur, array $parametres): array
{
    $attente = $_SESSION['oauth_en_cours'] ?? null;
    unset($_SESSION['oauth_en_cours']);   // usage unique : effacé AVANT toute vérification

    $nom = nom_fournisseur($fournisseur);

    if (($parametres['error'] ?? '') === 'access_denied' || ($parametres['error_reason'] ?? '') === 'user_denied') {
        throw new ErreurConnexionSociale('Connexion ' . $nom . ' annulée.');
    }

    $state = $parametres['state'] ?? null;
    if (!is_array($attente) || ($attente['fournisseur'] ?? '') !== $fournisseur || ($attente['expire'] ?? 0) < time()
        || !is_string($state) || !is_string($attente['state'] ?? null) || !hash_equals($attente['state'], $state)) {
        throw new ErreurConnexionSociale('La connexion avec ' . $nom . ' a expiré. Merci de recommencer.', 'state absent, expiré ou différent');
    }

    if (isset($parametres['error'])) {
        throw new ErreurConnexionSociale('La connexion avec ' . $nom . ' a échoué. Réessayez.', 'erreur renvoyée : ' . substr((string) $parametres['error'], 0, 60));
    }

    $code = $parametres['code'] ?? null;
    if (!is_string($code) || $code === '' || strlen($code) > 2048) {
        throw new ErreurConnexionSociale('La connexion avec ' . $nom . ' a échoué. Réessayez.', 'code absent');
    }

    if ($attente['mode'] === 'liaison' && (int) (utilisateur_connecte()['id'] ?? 0) !== (int) $attente['utilisateur_id']) {
        throw new ErreurConnexionSociale('Votre session a changé. Reconnectez-vous puis recommencez.', 'liaison : utilisateur différent');
    }

    return $attente + ['code' => $code];
}

/* -------------------------------------------------------------------------
   Google : jetons et vérification complète de l'id_token
   ------------------------------------------------------------------------- */

/**
 * Échange le code (avec le code_verifier PKCE) et renvoie l'identité tirée de l'id_token vérifié.
 * Le jeton d'accès Google n'est jamais conservé.
 */
function identite_google(string $code, string $verificateur, string $nonce): array
{
    $fournisseurOauth = fournisseur_oauth('google');
    $fournisseurOauth->setPkceCode($verificateur);
    $jetons = $fournisseurOauth->getAccessToken('authorization_code', ['code' => $code]);
    $idToken = (string) ($jetons->getValues()['id_token'] ?? '');
    unset($jetons);

    $revendications = verifier_id_token_google($idToken, $nonce);

    if (($revendications['email_verified'] ?? false) !== true && ($revendications['email_verified'] ?? '') !== 'true') {
        throw new ErreurConnexionSociale('Votre adresse Gmail n\'est pas vérifiée par Google. Vérifiez-la chez Google, ou inscrivez-vous avec votre e-mail.');
    }

    $email = normaliser_email((string) ($revendications['email'] ?? ''));
    if ($email === null) {
        throw new ErreurConnexionSociale('Google n\'a pas transmis d\'adresse e-mail utilisable.', 'e-mail absent ou invalide');
    }

    return [
        'fournisseur' => 'google',
        'identifiant' => (string) $revendications['sub'],
        'email' => $email,
        'email_refuse' => false,
        'prenom' => (string) ($revendications['given_name'] ?? ''),
        'nom' => (string) ($revendications['family_name'] ?? ''),
    ];
}

/**
 * Vérifie un id_token Google : signature RS256 (clés publiques de Google), émetteur, audience,
 * expiration et nonce. Renvoie les revendications, ou lève une ErreurConnexionSociale.
 *
 * @param array|null $cles clés JWK (tests) ; null = clés publiques de Google (cache)
 */
function verifier_id_token_google(string $idToken, string $nonceAttendu, ?array $cles = null, ?int $maintenant = null): array
{
    $refus = static fn (string $detail) => new ErreurConnexionSociale('La connexion avec Google a échoué. Réessayez.', 'id_token : ' . $detail);
    $maintenant ??= time();

    $parties = explode('.', $idToken);
    if (count($parties) !== 3) {
        throw $refus('format');
    }

    $entete = json_decode((string) base64url_decoder($parties[0]), true);
    $charge = json_decode((string) base64url_decoder($parties[1]), true);
    $signature = base64url_decoder($parties[2]);

    if (!is_array($entete) || !is_array($charge) || $signature === false || $signature === '') {
        throw $refus('illisible');
    }
    if (($entete['alg'] ?? '') !== 'RS256' || !is_string($entete['kid'] ?? null)) {
        throw $refus('algorithme');
    }

    $cle = cle_publique_google($entete['kid'], $cles);
    if ($cle === null || openssl_verify($parties[0] . '.' . $parties[1], $signature, $cle, OPENSSL_ALGO_SHA256) !== 1) {
        throw $refus('signature');
    }

    $clientId = (string) env('GOOGLE_CLIENT_ID', '');
    $audience = $charge['aud'] ?? null;
    $audienceOk = is_string($audience) ? hash_equals($clientId, $audience)
        : (is_array($audience) && in_array($clientId, $audience, true) && ($charge['azp'] ?? '') === $clientId);

    if (!in_array($charge['iss'] ?? '', GOOGLE_EMETTEURS, true)) {
        throw $refus('émetteur');
    }
    if ($clientId === '' || !$audienceOk) {
        throw $refus('audience');
    }
    if (!is_int($charge['exp'] ?? null) || $charge['exp'] < $maintenant - 60) {
        throw $refus('expiré');
    }
    if (is_int($charge['iat'] ?? null) && $charge['iat'] > $maintenant + 300) {
        throw $refus('émis dans le futur');
    }
    if ($nonceAttendu === '' || !is_string($charge['nonce'] ?? null) || !hash_equals($nonceAttendu, $charge['nonce'])) {
        throw $refus('nonce');
    }
    if (!is_string($charge['sub'] ?? null) || $charge['sub'] === '' || strlen($charge['sub']) > 255) {
        throw $refus('sub');
    }

    return $charge;
}

/** Clé publique (PEM) de Google pour ce « kid » ; les clés sont relues si le kid est inconnu. */
function cle_publique_google(string $kid, ?array $cles = null): ?string
{
    $trouver = static function (array $liste) use ($kid): ?string {
        foreach ($liste as $jwk) {
            if (is_array($jwk) && ($jwk['kid'] ?? '') === $kid && ($jwk['kty'] ?? '') === 'RSA') {
                return jwk_vers_pem($jwk);
            }
        }

        return null;
    };

    if ($cles !== null) {
        return $trouver($cles);
    }

    return $trouver(cles_publiques_google()) ?? $trouver(cles_publiques_google(true));
}

/**
 * Clés publiques de Google (JWKS), gardées en cache dans stockage/cache/ selon le Cache-Control.
 *
 * @return list<array>
 */
function cles_publiques_google(bool $forcer = false): array
{
    // Les tests utilisent leur propre fichier ($GLOBALS['sm_cache_cles_google']) : le cache réel n'est jamais touché.
    $fichier = $GLOBALS['sm_cache_cles_google'] ?? DOSSIER_RACINE . '/stockage/cache/google-cles.json';

    if (!$forcer && is_file($fichier)) {
        $cache = json_decode((string) file_get_contents($fichier), true);
        if (is_array($cache) && ($cache['expire'] ?? 0) > time() && is_array($cache['keys'] ?? null)) {
            return $cache['keys'];
        }
    }

    $reponse = client_http_oauth()->request('GET', GOOGLE_URL_CLES, ['http_errors' => false]);
    $donnees = json_decode((string) $reponse->getBody(), true);

    if ($reponse->getStatusCode() !== 200 || !is_array($donnees['keys'] ?? null)) {
        throw new RuntimeException('Clés publiques Google illisibles (HTTP ' . $reponse->getStatusCode() . ').');
    }

    $duree = preg_match('/max-age=(\d+)/', $reponse->getHeaderLine('Cache-Control'), $m) === 1 ? (int) $m[1] : 3600;
    if (!is_dir(dirname($fichier))) {
        @mkdir(dirname($fichier), 0750, true);
    }
    @file_put_contents($fichier, json_encode(['expire' => time() + min($duree, 86400), 'keys' => $donnees['keys']]), LOCK_EX);

    return $donnees['keys'];
}

/** Convertit une clé JWK RSA (n, e) en clé publique PEM lisible par OpenSSL. */
function jwk_vers_pem(array $jwk): ?string
{
    $n = base64url_decoder((string) ($jwk['n'] ?? ''));
    $e = base64url_decoder((string) ($jwk['e'] ?? ''));
    if ($n === false || $e === false || $n === '' || $e === '') {
        return null;
    }

    $longueur = static function (int $taille): string {
        if ($taille < 0x80) {
            return chr($taille);
        }
        $octets = ltrim(pack('N', $taille), "\0");

        return chr(0x80 | strlen($octets)) . $octets;
    };
    $entier = static function (string $octets) use ($longueur): string {
        $octets = ltrim($octets, "\0");
        if ($octets === '' || ord($octets[0]) > 0x7f) {
            $octets = "\0" . $octets;
        }

        return "\x02" . $longueur(strlen($octets)) . $octets;
    };
    $sequence = static fn (string $contenu): string => "\x30" . $longueur(strlen($contenu)) . $contenu;

    $clefRsa = $sequence($entier($n) . $entier($e));
    $algorithme = $sequence("\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00");   // rsaEncryption + NULL
    $infos = $sequence($algorithme . "\x03" . $longueur(strlen($clefRsa) + 1) . "\x00" . $clefRsa);

    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($infos), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

function base64url_decoder(string $texte): string|false
{
    if (preg_match('/^[A-Za-z0-9_-]*$/', $texte) !== 1) {
        return false;
    }

    return base64_decode(strtr($texte, '-_', '+/') . str_repeat('=', (4 - strlen($texte) % 4) % 4), true);
}

/* -------------------------------------------------------------------------
   Facebook : jeton contrôlé par /debug_token, profil lu avec appsecret_proof
   ------------------------------------------------------------------------- */

/**
 * Échange le code contre un jeton (côté serveur), le fait valider par Facebook, puis lit le profil.
 * Le jeton n'est jamais conservé : il disparaît à la fin de cette fonction.
 */
function identite_facebook(string $code): array
{
    $jeton = fournisseur_oauth('facebook')->getAccessToken('authorization_code', ['code' => $code])->getToken();

    $idUtilisateur = verifier_jeton_facebook(appel_graph('debug_token', [
        'input_token' => $jeton,
        'access_token' => env('FACEBOOK_APP_ID') . '|' . env('FACEBOOK_APP_SECRET'),   // jeton d'application
    ]));

    $preuve = preuve_secret_facebook($jeton);
    $profil = appel_graph('me', ['fields' => 'id,first_name,last_name,email', 'access_token' => $jeton, 'appsecret_proof' => $preuve]);

    if ((string) ($profil['id'] ?? '') !== $idUtilisateur) {
        throw new ErreurConnexionSociale('La connexion avec Facebook a échoué. Réessayez.', 'profil différent du jeton');
    }

    $email = normaliser_email((string) ($profil['email'] ?? ''));
    $emailRefuse = false;

    if ($email === null) {
        // E-mail absent : permission refusée (on proposera de la redemander) ou compte créé avec un téléphone.
        $permissions = appel_graph('me/permissions', ['access_token' => $jeton, 'appsecret_proof' => $preuve]);
        foreach ($permissions['data'] ?? [] as $permission) {
            if (($permission['permission'] ?? '') === 'email' && ($permission['status'] ?? '') === 'declined') {
                $emailRefuse = true;
            }
        }
    }

    unset($jeton, $preuve);

    return [
        'fournisseur' => 'facebook',
        'identifiant' => $idUtilisateur,
        'email' => $email,
        'email_refuse' => $emailRefuse,
        'prenom' => (string) ($profil['first_name'] ?? ''),
        'nom' => (string) ($profil['last_name'] ?? ''),
    ];
}

/** appsecret_proof : HMAC-SHA256 du jeton d'accès avec le secret de l'application. */
function preuve_secret_facebook(string $jeton): string
{
    return hash_hmac('sha256', $jeton, (string) env('FACEBOOK_APP_SECRET', ''));
}

/**
 * Contrôle la réponse de /debug_token : jeton valide, émis pour NOTRE application, non expiré.
 * Renvoie l'identifiant Facebook de l'utilisateur.
 */
function verifier_jeton_facebook(array $reponse, ?int $maintenant = null): string
{
    $donnees = $reponse['data'] ?? null;
    $maintenant ??= time();
    $refus = static fn (string $detail) => new ErreurConnexionSociale('La connexion avec Facebook a échoué. Réessayez.', 'debug_token : ' . $detail);

    if (!is_array($donnees) || ($donnees['is_valid'] ?? false) !== true) {
        throw $refus('jeton invalide');
    }
    if ((string) ($donnees['app_id'] ?? '') !== (string) env('FACEBOOK_APP_ID', '')) {
        throw $refus('jeton émis pour une autre application');
    }
    $expire = (int) ($donnees['expires_at'] ?? 0);
    if ($expire !== 0 && $expire < $maintenant) {
        throw $refus('jeton expiré');
    }
    $idUtilisateur = (string) ($donnees['user_id'] ?? '');
    if (preg_match('/^\d{1,40}$/', $idUtilisateur) !== 1) {
        throw $refus('utilisateur absent');
    }

    return $idUtilisateur;
}

/**
 * Appel GET à l'API Graph (version épinglée). Les messages d'erreur ne contiennent jamais le jeton.
 *
 * @return array réponse JSON décodée
 */
function appel_graph(string $chemin, array $parametres): array
{
    $reponse = client_http_oauth()->request('GET', FACEBOOK_URL_GRAPH . version_api_facebook() . '/' . $chemin, [
        'query' => $parametres,
        'http_errors' => false,
    ]);
    $donnees = json_decode((string) $reponse->getBody(), true);

    if ($reponse->getStatusCode() !== 200 || !is_array($donnees)) {
        throw new ErreurConnexionSociale('La connexion avec Facebook a échoué. Réessayez.',
            'Graph ' . $chemin . ' : HTTP ' . $reponse->getStatusCode() . ' ' . (is_array($donnees) ? (string) ($donnees['error']['type'] ?? '') : ''));
    }

    return $donnees;
}

/* -------------------------------------------------------------------------
   3. Règles métier (cas A, B, C)
   ------------------------------------------------------------------------- */

/**
 * Applique les règles après une identité vérifiée. Renvoie la page vers laquelle rediriger.
 *
 * @param array $identite fournisseur, identifiant, email (ou null), email_refuse, prenom, nom
 * @param array $attente  état du départ (mode 'connexion' ou 'liaison')
 */
function traiter_identite_sociale(array $identite, array $attente): string
{
    $fournisseur = $identite['fournisseur'];
    $nom = nom_fournisseur($fournisseur);
    $compteLie = compte_par_liaison($fournisseur, $identite['identifiant']);

    // Ajout depuis « Mes connexions » (utilisateur déjà connecté)
    if ($attente['mode'] === 'liaison') {
        $utilisateur = utilisateur_connecte();
        if ($utilisateur === null || $utilisateur['role'] === 'admin') {
            throw new ErreurConnexionSociale('Ce compte ne peut pas être lié.');
        }
        if ($compteLie !== null && (int) $compteLie['id'] !== (int) $utilisateur['id']) {
            throw new ErreurConnexionSociale('Ce compte ' . $nom . ' est déjà lié à un autre compte SAMA MAILLOT.');
        }
        if ($compteLie === null) {
            creer_liaison((int) $utilisateur['id'], $fournisseur, $identite['identifiant'], $identite['email']);
            journaliser_action('liaison_' . $fournisseur, (int) $utilisateur['id'], 'utilisateur', (int) $utilisateur['id']);
        }
        message_flash('succes', 'Votre compte ' . $nom . ' est lié : vous pouvez maintenant l\'utiliser pour vous connecter.');

        return 'compte/connexions.php';
    }

    // Cas A : identité déjà liée → connexion
    if ($compteLie !== null) {
        return ouvrir_session_sociale($compteLie, $fournisseur);
    }

    oublier_identite();

    // Facebook sans e-mail : saisie de l'e-mail + code (ou nouvelle demande d'autorisation)
    if ($identite['email'] === null) {
        memoriser_identite($identite, 'email', SOCIAL_ATTENTE_SECONDES);

        return 'connexion/completer-email.php';
    }

    return orienter_selon_email($identite);
}

/** Cas B (aucun compte avec cet e-mail) ou cas C (un compte existe : liaison à confirmer). */
function orienter_selon_email(array $identite): string
{
    $comptes = trouver_comptes_par_identifiant((string) $identite['email']);

    if ($comptes === []) {
        memoriser_identite($identite, 'bienvenue', SOCIAL_ATTENTE_SECONDES);

        return 'connexion/bienvenue.php';
    }

    $compte = $comptes[0];
    if ($refus = refus_connexion_sociale($compte)) {
        throw new ErreurConnexionSociale($refus);
    }

    memoriser_identite($identite, 'lier', SOCIAL_LIAISON_SECONDES, (int) $compte['id']);

    return 'connexion/lier-compte.php';
}

/** Motif empêchant d'utiliser Google / Facebook avec ce compte, ou null. */
function refus_connexion_sociale(array $compte): ?string
{
    if ($compte['role'] === 'admin') {
        return 'Les comptes administrateur se connectent uniquement avec leur mot de passe.';
    }
    if (!compte_utilisable($compte)) {
        return 'Ce compte est désactivé ou suspendu. Contactez le support SAMA MAILLOT.';
    }

    return null;
}

/**
 * Ouvre la session (identifiant régénéré, panier invité fusionné), journalise,
 * et renvoie la page à ouvrir (page demandée avant la connexion, sinon la page d'arrivée).
 */
function ouvrir_session_sociale(array $compte, string $fournisseur): string
{
    if ($refus = refus_connexion_sociale($compte)) {
        throw new ErreurConnexionSociale($refus);
    }

    oublier_identite();
    connecter_utilisateur($compte);   // session_regenerate_id(true) + fusion du panier invité

    bdd()->prepare('UPDATE connexions_sociales SET derniere_connexion_le = NOW() WHERE utilisateur_id = :id AND fournisseur = :f')
        ->execute(['id' => $compte['id'], 'f' => $fournisseur]);
    journaliser_action('connexion_via_' . $fournisseur, (int) $compte['id'], 'utilisateur', (int) $compte['id']);

    return chemin_apres_connexion($compte);
}

/* -------------------------------------------------------------------------
   Liaisons (table connexions_sociales)
   ------------------------------------------------------------------------- */

/** Compte lié à cette identité (comptes supprimés exclus), ou null. */
function compte_par_liaison(string $fournisseur, string $identifiant): ?array
{
    $requete = bdd()->prepare(
        SQL_UTILISATEUR . ' JOIN connexions_sociales cs ON cs.utilisateur_id = u.id
         WHERE cs.fournisseur = :f AND cs.identifiant_fournisseur = :id AND u.supprime_le IS NULL'
    );
    $requete->execute(['f' => $fournisseur, 'id' => $identifiant]);
    $compte = $requete->fetch();

    return $compte === false ? null : $compte;
}

/** @return array<string, array> liaisons du compte, indexées par fournisseur */
function liaisons_du_compte(int $utilisateurId): array
{
    $requete = bdd()->prepare('SELECT fournisseur, email_fournisseur, cree_le, derniere_connexion_le
                               FROM connexions_sociales WHERE utilisateur_id = :id');
    $requete->execute(['id' => $utilisateurId]);
    $liaisons = [];
    foreach ($requete->fetchAll() as $ligne) {
        $liaisons[$ligne['fournisseur']] = $ligne;
    }

    return $liaisons;
}

function creer_liaison(int $utilisateurId, string $fournisseur, string $identifiant, ?string $email): void
{
    try {
        bdd()->prepare(
            'INSERT INTO connexions_sociales (utilisateur_id, fournisseur, identifiant_fournisseur, email_fournisseur, derniere_connexion_le)
             VALUES (:u, :f, :id, :email, NOW())'
        )->execute(['u' => $utilisateurId, 'f' => $fournisseur, 'id' => $identifiant, 'email' => $email]);
    } catch (PDOException $exception) {
        if ($exception->getCode() !== '23000') {
            throw $exception;
        }
        throw new ErreurConnexionSociale('Ce compte ' . nom_fournisseur($fournisseur) . ' est déjà lié à un compte SAMA MAILLOT, '
            . 'ou votre compte est déjà lié à un autre compte ' . nom_fournisseur($fournisseur) . '.');
    }
}

/**
 * Cas C confirmé (mot de passe, code SMS ou lien e-mail) : liaison créée puis connexion.
 * L'e-mail est désormais prouvé ; un compte invité devient un compte normal.
 */
function finaliser_liaison(array $identite, array $compte): string
{
    creer_liaison((int) $compte['id'], $identite['fournisseur'], $identite['identifiant'], $identite['email']);
    bdd()->prepare('UPDATE utilisateurs SET email_verifie = 1, est_invite = 0 WHERE id = :id')->execute(['id' => $compte['id']]);
    journaliser_action('liaison_' . $identite['fournisseur'], (int) $compte['id'], 'utilisateur', (int) $compte['id']);

    return ouvrir_session_sociale(trouver_utilisateur((int) $compte['id']) ?? $compte, $identite['fournisseur']);
}

/** Motif empêchant de délier ce fournisseur (dernier moyen de connexion), ou null. */
function refus_deliaison(array $utilisateur, string $fournisseur): ?string
{
    $liaisons = liaisons_du_compte((int) $utilisateur['id']);

    if (!isset($liaisons[$fournisseur])) {
        return 'Ce compte n\'est pas lié.';
    }
    if ($utilisateur['mot_de_passe_hache'] === null && count($liaisons) <= 1) {
        return 'C\'est votre seul moyen de connexion : définissez d\'abord un mot de passe (ou liez un autre compte), '
            . 'sinon vous ne pourriez plus vous connecter.';
    }

    return null;
}

function delier_fournisseur(int $utilisateurId, string $fournisseur): void
{
    bdd()->prepare('DELETE FROM connexions_sociales WHERE utilisateur_id = :id AND fournisseur = :f')
        ->execute(['id' => $utilisateurId, 'f' => $fournisseur]);
    journaliser_action('deliaison_' . $fournisseur, $utilisateurId, 'utilisateur', $utilisateurId);
}

/* -------------------------------------------------------------------------
   Identité en attente (session, durée limitée)
   ------------------------------------------------------------------------- */

/**
 * Garde l'identité vérifiée en session pour l'étape suivante, pour une durée limitée.
 * Seules les données d'identité sont gardées (jamais de jeton).
 */
function memoriser_identite(array $identite, string $etape, int $dureeSecondes, ?int $compteId = null): void
{
    $_SESSION['identite_sociale'] = [
        'fournisseur' => (string) $identite['fournisseur'],
        'identifiant' => (string) $identite['identifiant'],
        'email' => $identite['email'] ?? null,
        'email_par_code' => (bool) ($identite['email_par_code'] ?? false),   // e-mail prouvé par NOTRE code
        'email_refuse' => (bool) ($identite['email_refuse'] ?? false),
        'email_en_attente' => $identite['email_en_attente'] ?? null,
        'prenom' => (string) ($identite['prenom'] ?? ''),
        'nom' => (string) ($identite['nom'] ?? ''),
        'etape' => $etape,
        'compte_id' => $compteId,
        'expire' => time() + $dureeSecondes,
    ];
}

/** Identité vérifiée en attente pour cette étape ('bienvenue', 'lier', 'email'), ou null (absente ou expirée). */
function identite_en_attente(string $etape): ?array
{
    $identite = $_SESSION['identite_sociale'] ?? null;

    if (!is_array($identite) || ($identite['expire'] ?? 0) < time()) {
        oublier_identite();

        return null;
    }

    return ($identite['etape'] ?? '') === $etape ? $identite : null;
}

function oublier_identite(): void
{
    unset($_SESSION['identite_sociale']);
}

/* -------------------------------------------------------------------------
   Cas B : création du compte après acceptation des CGU
   ------------------------------------------------------------------------- */

/**
 * Crée le compte CLIENT (sans mot de passe, e-mail prouvé) ET sa liaison, dans une transaction.
 * Renvoie le compte créé.
 */
function creer_compte_social(array $identite, string $prenom, string $nom): array
{
    $pdo = bdd();
    $pdo->beginTransaction();

    try {
        $pdo->prepare(
            "INSERT INTO utilisateurs (role, prenom, nom, email, email_verifie, mot_de_passe_hache, cgu_acceptees_le)
             VALUES ('client', :prenom, :nom, :email, 1, NULL, NOW())"
        )->execute(['prenom' => $prenom, 'nom' => $nom, 'email' => $identite['email']]);
        $id = (int) $pdo->lastInsertId();

        creer_liaison($id, $identite['fournisseur'], $identite['identifiant'], $identite['email']);
        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        if ($exception instanceof PDOException && $exception->getCode() === '23000') {
            throw new ErreurConnexionSociale('Un compte existe déjà avec cette adresse e-mail. Connectez-vous pour le lier.');
        }
        throw $exception;
    }

    journaliser_action('inscription_via_' . $identite['fournisseur'], $id, 'utilisateur', $id);

    return trouver_utilisateur($id) ?? throw new RuntimeException('Compte introuvable après création.');
}

/** Prénom ou nom proposé par le fournisseur, ramené à ce que le formulaire accepte. */
function nom_propose(string $valeur): string
{
    $valeur = trim(preg_replace('/\s+/u', ' ', $valeur) ?? '');

    return valider_nom_personne($valeur, 'nom') === null ? $valeur : '';
}

/* -------------------------------------------------------------------------
   Cas C sans mot de passe : lien par e-mail (ou code SMS si téléphone vérifié)
   ------------------------------------------------------------------------- */

/**
 * Lien de confirmation de liaison envoyé à l'adresse du compte (usage unique, 10 minutes).
 * Renvoie l'adresse du lien, ou '' si 3 liens ont déjà été envoyés dans l'heure.
 */
function creer_lien_liaison(int $utilisateurId, string $email): string
{
    $requete = bdd()->prepare(
        "SELECT COUNT(*) FROM codes_verification
         WHERE utilisateur_id = :id AND type = 'liaison_sociale_email' AND cree_le > NOW() - INTERVAL 1 HOUR"
    );
    $requete->execute(['id' => $utilisateurId]);
    if ((int) $requete->fetchColumn() >= 3) {
        return '';
    }

    bdd()->prepare(
        "UPDATE codes_verification SET expire_le = NOW()
         WHERE utilisateur_id = :id AND type = 'liaison_sociale_email' AND utilise_le IS NULL AND expire_le > NOW()"
    )->execute(['id' => $utilisateurId]);

    $jeton = bin2hex(random_bytes(32));
    bdd()->prepare(
        "INSERT INTO codes_verification (utilisateur_id, type, destination, code_hache, expire_le)
         VALUES (:id, 'liaison_sociale_email', :email, :hache, NOW() + INTERVAL " . (SOCIAL_LIAISON_SECONDES / 60) . ' MINUTE)'
    )->execute(['id' => $utilisateurId, 'email' => $email, 'hache' => hash('sha256', $jeton)]);

    return url('connexion/lier-compte.php?lien=' . bdd()->lastInsertId() . '&jeton=' . $jeton);
}

/** Vérifie puis consomme un lien de liaison. Renvoie l'id du compte, ou null. */
function utiliser_lien_liaison(int $id, string $jeton): ?int
{
    if ($id <= 0 || preg_match('/^[a-f0-9]{64}$/', $jeton) !== 1) {
        return null;
    }

    $requete = bdd()->prepare(
        "SELECT utilisateur_id, code_hache FROM codes_verification
         WHERE id = :id AND type = 'liaison_sociale_email' AND utilise_le IS NULL AND expire_le > NOW()"
    );
    $requete->execute(['id' => $id]);
    $ligne = $requete->fetch();

    if ($ligne === false || !hash_equals($ligne['code_hache'], hash('sha256', $jeton))) {
        return null;
    }

    bdd()->prepare('UPDATE codes_verification SET utilise_le = NOW() WHERE id = :id')->execute(['id' => $id]);

    return (int) $ligne['utilisateur_id'];
}

/* -------------------------------------------------------------------------
   Facebook sans e-mail : code à 6 chiffres envoyé à l'adresse saisie
   ------------------------------------------------------------------------- */

/** Crée un code (15 minutes, 5 essais) ; null si 3 codes ont déjà été envoyés à cette adresse dans l'heure. */
function creer_code_email_social(string $email): ?string
{
    $requete = bdd()->prepare(
        "SELECT COUNT(*) FROM codes_verification
         WHERE destination = :email AND type = 'email_connexion_sociale' AND cree_le > NOW() - INTERVAL 1 HOUR"
    );
    $requete->execute(['email' => $email]);
    if ((int) $requete->fetchColumn() >= 3) {
        return null;
    }

    bdd()->prepare(
        "UPDATE codes_verification SET expire_le = NOW()
         WHERE destination = :email AND type = 'email_connexion_sociale' AND utilise_le IS NULL AND expire_le > NOW()"
    )->execute(['email' => $email]);

    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    bdd()->prepare(
        "INSERT INTO codes_verification (utilisateur_id, type, destination, code_hache, expire_le)
         VALUES (NULL, 'email_connexion_sociale', :email, :hache, NOW() + INTERVAL " . CODE_EMAIL_SOCIAL_MINUTES . ' MINUTE)'
    )->execute(['email' => $email, 'hache' => password_hash($code, PASSWORD_DEFAULT)]);

    return $code;
}

/** @return array{ok: bool, message: string} */
function verifier_code_email_social(string $email, string $saisie): array
{
    $echec = ['ok' => false, 'message' => 'Code incorrect ou expiré. Demandez un nouveau code.'];

    $requete = bdd()->prepare(
        "SELECT id, code_hache, essais FROM codes_verification
         WHERE destination = :email AND type = 'email_connexion_sociale' AND utilise_le IS NULL AND expire_le > NOW()
         ORDER BY id DESC LIMIT 1"
    );
    $requete->execute(['email' => $email]);
    $code = $requete->fetch();

    if ($code === false || (int) $code['essais'] >= CODE_EMAIL_SOCIAL_ESSAIS) {
        return $echec;
    }

    // L'essai est compté AVANT la comparaison.
    bdd()->prepare('UPDATE codes_verification SET essais = essais + 1 WHERE id = :id')->execute(['id' => $code['id']]);

    if (!password_verify(preg_replace('/\D+/', '', $saisie) ?? '', $code['code_hache'])) {
        $restants = CODE_EMAIL_SOCIAL_ESSAIS - ((int) $code['essais'] + 1);
        if ($restants > 0) {
            $echec['message'] = 'Code incorrect. Il vous reste ' . $restants . ' essai' . ($restants > 1 ? 's' : '') . '.';
        }

        return $echec;
    }

    bdd()->prepare('UPDATE codes_verification SET utilise_le = NOW() WHERE id = :id')->execute(['id' => $code['id']]);

    return ['ok' => true, 'message' => ''];
}

/* -------------------------------------------------------------------------
   Suppression des données demandée par Meta (rappel « Data Deletion Callback »)
   ------------------------------------------------------------------------- */

/**
 * Décode le « signed_request » envoyé par Meta : « signature.charge » en base64url,
 * signature HMAC-SHA256 vérifiée avec le secret de l'application (hash_equals).
 * Renvoie la charge, ou null si la signature ou le format est mauvais.
 */
function decoder_requete_signee_facebook(string $requeteSignee, string $secret): ?array
{
    $parties = explode('.', $requeteSignee);
    if (count($parties) !== 2 || $secret === '') {
        return null;
    }

    $signature = base64url_decoder($parties[0]);
    $charge = json_decode((string) base64url_decoder($parties[1]), true);

    if ($signature === false || !is_array($charge) || strtoupper((string) ($charge['algorithm'] ?? '')) !== 'HMAC-SHA256') {
        return null;
    }
    if (!hash_equals(hash_hmac('sha256', $parties[1], $secret, true), $signature)) {
        return null;
    }

    return $charge;
}

/**
 * Enregistre une demande de suppression : la liaison Facebook est supprimée tout de suite,
 * le compte est anonymisé s'il n'a plus aucun moyen de connexion (différé si commandes en cours).
 * Renvoie le code de confirmation (affiché sur la page de statut, sans donnée personnelle).
 */
function enregistrer_suppression_facebook(string $identifiantFacebook): string
{
    $requete = bdd()->prepare("SELECT utilisateur_id FROM connexions_sociales WHERE fournisseur = 'facebook' AND identifiant_fournisseur = :id");
    $requete->execute(['id' => $identifiantFacebook]);
    $utilisateurId = $requete->fetchColumn();
    $code = strtoupper(bin2hex(random_bytes(10)));

    $pdo = bdd();
    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM connexions_sociales WHERE fournisseur = 'facebook' AND identifiant_fournisseur = :id")
            ->execute(['id' => $identifiantFacebook]);
        // Seule l'empreinte de l'identifiant est gardée (preuve de la demande, sans donnée Facebook lisible).
        $pdo->prepare('INSERT INTO demandes_suppression_facebook (identifiant_fournisseur, utilisateur_id, code_confirmation)
                       VALUES (:empreinte, :u, :code)')
            ->execute(['empreinte' => hash('sha256', $identifiantFacebook), 'u' => $utilisateurId === false ? null : (int) $utilisateurId, 'code' => $code]);
        $demandeId = (int) $pdo->lastInsertId();
        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }

    traiter_demande_suppression($demandeId);

    return $code;
}

/** Termine une demande si possible. Vrai si elle est traitée. */
function traiter_demande_suppression(int $demandeId): bool
{
    $requete = bdd()->prepare("SELECT * FROM demandes_suppression_facebook WHERE id = :id AND statut = 'recue'");
    $requete->execute(['id' => $demandeId]);
    $demande = $requete->fetch();
    if ($demande === false) {
        return false;
    }

    $compte = $demande['utilisateur_id'] !== null ? trouver_utilisateur((int) $demande['utilisateur_id']) : null;

    // Le compte garde un autre moyen de connexion : seules les données Facebook (déjà supprimées) sont concernées.
    if ($compte !== null && $compte['mot_de_passe_hache'] === null && liaisons_du_compte((int) $compte['id']) === []) {
        if (refus_suppression_compte($compte) !== null) {
            return false;   // commandes ou argent en cours : nouvel essai par les tâches automatiques
        }
        supprimer_compte($compte);   // anonymisation ; l'historique des commandes reste (comptabilité)
    }

    bdd()->prepare("UPDATE demandes_suppression_facebook SET statut = 'traitee', traitee_le = NOW() WHERE id = :id")
        ->execute(['id' => $demandeId]);

    return true;
}

/** Tâche automatique : reprend les demandes en attente (commandes terminées depuis). */
function traiter_demandes_suppression_facebook(): int
{
    $traitees = 0;
    foreach (bdd()->query("SELECT id FROM demandes_suppression_facebook WHERE statut = 'recue'")->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $traitees += traiter_demande_suppression((int) $id) ? 1 : 0;
    }

    return $traitees;
}

/* -------------------------------------------------------------------------
   Journaux : jamais de secret, de jeton ni de code
   ------------------------------------------------------------------------- */

/** Masque les valeurs sensibles qui pourraient figurer dans un message technique (adresse, JSON). */
function masquer_secrets(string $texte): string
{
    $cles = 'access_token|input_token|appsecret_proof|code|client_secret|id_token|code_verifier|refresh_token|state|nonce';
    $texte = preg_replace('/(' . $cles . ')=[^&\s"\']+/i', '$1=***', $texte) ?? '';

    return preg_replace('/"(' . $cles . ')"\s*:\s*"[^"]*"/i', '"$1":"***"', $texte) ?? '';
}

function journaliser_erreur_sociale(string $fournisseur, Throwable $exception): void
{
    error_log(sprintf('Connexion %s : %s : %s (%s ligne %d)', $fournisseur, $exception::class,
        masquer_secrets(mb_substr($exception->getMessage(), 0, 300)), basename($exception->getFile()), $exception->getLine()));
}
