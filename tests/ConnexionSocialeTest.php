<?php
/**
 * Connexion avec Google et Facebook : vérification de l'id_token, du jeton Facebook, du state,
 * de la signature Meta, règles A / B / C, déliaison, suppression des données, redirection.
 *
 * Aucun appel réseau : Google et Facebook sont simulés par un client HTTP Guzzle de test
 * (MockHandler) et par une clé RSA générée pour l'occasion.
 */

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class ConnexionSocialeTest extends TestCase
{
    private const CLIENT_GOOGLE = 'client-test.apps.googleusercontent.com';
    private const APP_FACEBOOK = '123456789012345';
    private const SECRET_FACEBOOK = 'secret-de-test-facebook';

    private static OpenSSLAsymmetricKey $clePrivee;
    private static array $jwk;

    public static function setUpBeforeClass(): void
    {
        putenv('GOOGLE_ACTIF=true');
        putenv('GOOGLE_CLIENT_ID=' . self::CLIENT_GOOGLE);
        putenv('GOOGLE_CLIENT_SECRET=secret-google');
        putenv('FACEBOOK_ACTIF=true');
        putenv('FACEBOOK_APP_ID=' . self::APP_FACEBOOK);
        putenv('FACEBOOK_APP_SECRET=' . self::SECRET_FACEBOOK);
        putenv('FACEBOOK_VERSION_API=v21.0');

        // Sous Windows (XAMPP), OpenSSL a besoin de son fichier de configuration pour créer une clé.
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        foreach ([getenv('OPENSSL_CONF') ?: '', dirname(PHP_BINARY) . '/extras/openssl/openssl.cnf'] as $configuration) {
            if ($configuration !== '' && is_file($configuration)) {
                $options['config'] = $configuration;
                break;
            }
        }
        $cle = openssl_pkey_new($options);
        self::assertNotFalse($cle, 'Création de la clé RSA de test impossible : ' . openssl_error_string());
        self::$clePrivee = $cle;
        $details = openssl_pkey_get_details(self::$clePrivee);
        self::$jwk = ['kty' => 'RSA', 'kid' => 'cle-test', 'alg' => 'RS256', 'use' => 'sig',
            'n' => self::b64($details['rsa']['n']), 'e' => self::b64($details['rsa']['e'])];
        $GLOBALS['sm_cache_cles_google'] = sys_get_temp_dir() . '/sama-test-google-cles-' . getmypid() . '.json';
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['sm_client_http_oauth']);
        $_SESSION = [];
        @unlink($GLOBALS['sm_cache_cles_google']);
    }

    private static function b64(string $octets): string
    {
        return rtrim(strtr(base64_encode($octets), '+/', '-_'), '=');
    }

    /** id_token signé avec la clé de test ; $modifs remplace ou ajoute des revendications. */
    private static function idToken(array $modifs = [], string $kid = 'cle-test'): string
    {
        $charge = array_merge([
            'iss' => 'https://accounts.google.com', 'aud' => self::CLIENT_GOOGLE, 'sub' => '1098765432',
            'email' => 'awa.diop@gmail.com', 'email_verified' => true, 'given_name' => 'Awa', 'family_name' => 'Diop',
            'nonce' => 'nonce-attendu', 'iat' => time(), 'exp' => time() + 3600,
        ], $modifs);
        $debut = self::b64(json_encode(['alg' => 'RS256', 'kid' => $kid, 'typ' => 'JWT'])) . '.' . self::b64(json_encode($charge));
        openssl_sign($debut, $signature, self::$clePrivee, OPENSSL_ALGO_SHA256);

        return $debut . '.' . self::b64($signature);
    }

    /** Remplace Google / Facebook par des réponses préparées (dans l'ordre des appels). */
    private static function simuler(Response ...$reponses): MockHandler
    {
        $simulateur = new MockHandler($reponses);
        $GLOBALS['sm_client_http_oauth'] = new Client(['handler' => HandlerStack::create($simulateur)]);

        return $simulateur;
    }

    private static function json(array $donnees, array $entetes = []): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'] + $entetes, json_encode($donnees));
    }

    private static function attenteValide(string $fournisseur, string $mode = 'connexion'): array
    {
        $_SESSION['oauth_en_cours'] = ['fournisseur' => $fournisseur, 'state' => str_repeat('a', 64), 'mode' => $mode,
            'utilisateur_id' => 0, 'nonce' => 'nonce-attendu', 'verificateur' => 'v', 'expire' => time() + 600];

        return $_SESSION['oauth_en_cours'];
    }

    /* ---------------------------------------------------------------------
       Google : id_token
       --------------------------------------------------------------------- */

    public function testIdTokenGoogleValideAccepte(): void
    {
        $revendications = verifier_id_token_google(self::idToken(), 'nonce-attendu', [self::$jwk]);
        self::assertSame('1098765432', $revendications['sub']);
    }

    /** @return array<string, array{0: array, 1: string}> */
    public static function idTokensRefuses(): array
    {
        return [
            'mauvaise audience' => [['aud' => 'autre-client'], 'nonce-attendu'],
            'mauvais émetteur' => [['iss' => 'https://pirate.example'], 'nonce-attendu'],
            'expiré' => [['exp' => time() - 3600], 'nonce-attendu'],
            'nonce différent' => [[], 'autre-nonce'],
            'sub absent' => [['sub' => ''], 'nonce-attendu'],
        ];
    }

    /** @dataProvider idTokensRefuses */
    #[\PHPUnit\Framework\Attributes\DataProvider('idTokensRefuses')]
    public function testIdTokenGoogleRefuse(array $modifs, string $nonce): void
    {
        $this->expectException(ErreurConnexionSociale::class);
        verifier_id_token_google(self::idToken($modifs), $nonce, [self::$jwk]);
    }

    public function testIdTokenFalsifieOuSansSignatureRefuse(): void
    {
        [$entete, $charge, $signature] = explode('.', self::idToken());
        $chargeModifiee = self::b64(json_encode(['sub' => 'autre'] + json_decode(base64url_decoder($charge), true)));

        foreach ([
            $entete . '.' . $chargeModifiee . '.' . $signature,                                     // charge modifiée
            self::b64(json_encode(['alg' => 'none', 'kid' => 'cle-test'])) . '.' . $charge . '.',  // « alg: none »
            self::idToken([], 'kid-inconnu'),                                                       // clé inconnue
        ] as $jeton) {
            try {
                verifier_id_token_google($jeton, 'nonce-attendu', [self::$jwk]);
                self::fail('Un id_token falsifié doit être refusé.');
            } catch (ErreurConnexionSociale) {
                self::assertTrue(true);
            }
        }
    }

    public function testFluxGoogleCompletAvecClesDeGoogle(): void
    {
        self::simuler(
            self::json(['access_token' => 'jeton-google', 'token_type' => 'Bearer', 'expires_in' => 3600, 'id_token' => self::idToken()]),
            self::json(['keys' => [self::$jwk]], ['Cache-Control' => 'public, max-age=3600'])
        );

        $identite = identite_google('code-recu', 'verificateur-pkce', 'nonce-attendu');

        self::assertSame(['google', '1098765432', 'awa.diop@gmail.com', 'Awa', 'Diop'],
            [$identite['fournisseur'], $identite['identifiant'], $identite['email'], $identite['prenom'], $identite['nom']]);
        self::assertStringNotContainsString('jeton-google', serialize($identite), 'Le jeton d\'accès n\'est jamais conservé.');
    }

    public function testFluxGoogleEnvoieLeCodeVerifierPkce(): void
    {
        $historique = [];
        $pile = HandlerStack::create(new MockHandler([
            self::json(['access_token' => 'x', 'token_type' => 'Bearer', 'id_token' => self::idToken()]),
            self::json(['keys' => [self::$jwk]]),
        ]));
        $pile->push(Middleware::history($historique));
        $GLOBALS['sm_client_http_oauth'] = new Client(['handler' => $pile]);

        identite_google('code-recu', 'verificateur-pkce', 'nonce-attendu');

        // 1re requête : échange du code, côté serveur, avec le code_verifier PKCE.
        parse_str((string) $historique[0]['request']->getBody(), $corps);
        self::assertSame('POST', $historique[0]['request']->getMethod());
        self::assertSame('verificateur-pkce', $corps['code_verifier']);
        self::assertSame('code-recu', $corps['code']);
    }

    public function testEmailGoogleNonVerifieRefuse(): void
    {
        self::simuler(
            self::json(['access_token' => 'x', 'token_type' => 'Bearer', 'id_token' => self::idToken(['email_verified' => false])]),
            self::json(['keys' => [self::$jwk]])
        );

        $this->expectException(ErreurConnexionSociale::class);
        $this->expectExceptionMessageMatches('/pas vérifiée/');
        identite_google('code', 'v', 'nonce-attendu');
    }

    /* ---------------------------------------------------------------------
       Facebook : /debug_token + appsecret_proof
       --------------------------------------------------------------------- */

    public function testFluxFacebookComplet(): void
    {
        $simulateur = self::simuler(
            self::json(['access_token' => 'jeton-fb', 'token_type' => 'bearer', 'expires_in' => 5000]),
            self::json(['data' => ['app_id' => self::APP_FACEBOOK, 'is_valid' => true, 'user_id' => '555', 'expires_at' => time() + 5000]]),
            self::json(['id' => '555', 'first_name' => 'Moussa', 'last_name' => 'Ndiaye', 'email' => 'moussa@exemple.sn'])
        );

        $identite = identite_facebook('code-recu');

        self::assertSame(['facebook', '555', 'moussa@exemple.sn'], [$identite['fournisseur'], $identite['identifiant'], $identite['email']]);
        parse_str($simulateur->getLastRequest()->getUri()->getQuery(), $parametres);
        self::assertSame(hash_hmac('sha256', 'jeton-fb', self::SECRET_FACEBOOK), $parametres['appsecret_proof'], 'appsecret_proof envoyé avec /me.');
        self::assertSame('id,first_name,last_name,email', $parametres['fields']);
    }

    public function testJetonDUneAutreApplicationRefuse(): void
    {
        self::simuler(
            self::json(['access_token' => 'jeton-fb', 'token_type' => 'bearer']),
            self::json(['data' => ['app_id' => '999999999999', 'is_valid' => true, 'user_id' => '555']])
        );

        $this->expectException(ErreurConnexionSociale::class);
        identite_facebook('code');
    }

    public function testControlesDebugToken(): void
    {
        $base = ['app_id' => self::APP_FACEBOOK, 'is_valid' => true, 'user_id' => '555', 'expires_at' => time() + 60];
        self::assertSame('555', verifier_jeton_facebook(['data' => $base]));

        foreach ([['is_valid' => false], ['expires_at' => time() - 10], ['user_id' => ''], ['app_id' => '1']] as $modif) {
            try {
                verifier_jeton_facebook(['data' => array_merge($base, $modif)]);
                self::fail('Jeton refusé attendu : ' . json_encode($modif));
            } catch (ErreurConnexionSociale) {
                self::assertTrue(true);
            }
        }
    }

    public function testFacebookSansEmailPermissionRefusee(): void
    {
        self::simuler(
            self::json(['access_token' => 'jeton-fb', 'token_type' => 'bearer']),
            self::json(['data' => ['app_id' => self::APP_FACEBOOK, 'is_valid' => true, 'user_id' => '556']]),
            self::json(['id' => '556', 'first_name' => 'Fatou', 'last_name' => 'Sow']),
            self::json(['data' => [['permission' => 'public_profile', 'status' => 'granted'], ['permission' => 'email', 'status' => 'declined']]])
        );

        $identite = identite_facebook('code');
        self::assertNull($identite['email']);
        self::assertTrue($identite['email_refuse'], 'Permission e-mail refusée : on proposera de la redemander.');

        $chemin = traiter_identite_sociale($identite, ['mode' => 'connexion']);
        self::assertSame('connexion/completer-email.php', $chemin);
        self::assertNotNull(identite_en_attente('email'));
    }

    /* ---------------------------------------------------------------------
       state et annulation
       --------------------------------------------------------------------- */

    public function testStateFalsifieRefuseEtUsageUnique(): void
    {
        self::attenteValide('google');
        try {
            lire_retour_oauth('google', ['state' => str_repeat('b', 64), 'code' => 'c']);
            self::fail('Un state falsifié doit être refusé.');
        } catch (ErreurConnexionSociale) {
            self::assertArrayNotHasKey('oauth_en_cours', $_SESSION, 'Le state est effacé même après un échec.');
        }

        self::attenteValide('google');
        self::assertSame('c', lire_retour_oauth('google', ['state' => str_repeat('a', 64), 'code' => 'c'])['code']);

        $this->expectException(ErreurConnexionSociale::class);   // rejouer le même retour : refusé
        lire_retour_oauth('google', ['state' => str_repeat('a', 64), 'code' => 'c']);
    }

    public function testAnnulationParLUtilisateur(): void
    {
        self::attenteValide('facebook');
        $this->expectExceptionMessage('Connexion Facebook annulée.');
        lire_retour_oauth('facebook', ['error' => 'access_denied', 'error_reason' => 'user_denied', 'state' => str_repeat('a', 64)]);
    }

    public function testStateDUnAutreFournisseurRefuse(): void
    {
        self::attenteValide('google');
        $this->expectException(ErreurConnexionSociale::class);
        lire_retour_oauth('facebook', ['state' => str_repeat('a', 64), 'code' => 'c']);
    }

    /* ---------------------------------------------------------------------
       Règles A, B, C
       --------------------------------------------------------------------- */

    private static function identite(array $modifs = []): array
    {
        return array_merge(['fournisseur' => 'google', 'identifiant' => 'sub-' . uniqid(), 'email' => 'nouveau-' . uniqid() . '@gmail.com',
            'email_refuse' => false, 'prenom' => 'Awa', 'nom' => 'Diop'], $modifs);
    }

    public function testCasBNouveauCompteApresCgu(): void
    {
        $identite = self::identite();
        self::assertSame('connexion/bienvenue.php', traiter_identite_sociale($identite, ['mode' => 'connexion']));

        $compte = creer_compte_social(identite_en_attente('bienvenue'), 'Awa', 'Diop');
        self::assertSame('client', $compte['role']);
        self::assertSame(1, (int) $compte['email_verifie']);
        self::assertNull($compte['mot_de_passe_hache']);
        self::assertSame((int) $compte['id'], (int) compte_par_liaison('google', $identite['identifiant'])['id'], 'Liaison créée avec le compte.');
    }

    public function testCasCCompteExistantJamaisLieAutomatiquement(): void
    {
        $existant = creer_utilisateur_test();
        $identite = self::identite(['email' => $existant['email']]);

        self::assertSame('connexion/lier-compte.php', traiter_identite_sociale($identite, ['mode' => 'connexion']));
        self::assertNull(compte_par_liaison('google', $identite['identifiant']), 'Aucune liaison avant la preuve (mot de passe).');
        self::assertSame((int) $existant['id'], identite_en_attente('lier')['compte_id']);

        // Création concurrente avec la même adresse : refusée, rien de créé à moitié (transaction).
        try {
            creer_compte_social($identite, 'Awa', 'Diop');
            self::fail('Adresse déjà utilisée : création refusée.');
        } catch (ErreurConnexionSociale) {
            self::assertNull(compte_par_liaison('google', $identite['identifiant']));
        }
    }

    public function testCasALiaisonConnue(): void
    {
        $compte = creer_utilisateur_test();
        creer_liaison((int) $compte['id'], 'facebook', '777' . random_int(1000, 9999), null);
        $liaison = liaisons_du_compte((int) $compte['id'])['facebook'];
        self::assertNotNull($liaison);

        $identifiant = bdd()->query('SELECT identifiant_fournisseur FROM connexions_sociales WHERE utilisateur_id = ' . (int) $compte['id'])->fetchColumn();
        self::assertSame((int) $compte['id'], (int) compte_par_liaison('facebook', (string) $identifiant)['id']);
    }

    public function testAdministrateurEtCompteSuspenduRefuses(): void
    {
        $admin = creer_utilisateur_test('admin');
        self::assertNotNull(refus_connexion_sociale($admin));

        $suspendu = creer_utilisateur_test();
        bdd()->prepare("UPDATE utilisateurs SET statut = 'suspendu' WHERE id = ?")->execute([$suspendu['id']]);
        self::assertNotNull(refus_connexion_sociale(trouver_utilisateur((int) $suspendu['id'])));

        $this->expectException(ErreurConnexionSociale::class);
        traiter_identite_sociale(self::identite(['email' => $admin['email']]), ['mode' => 'connexion']);
    }

    public function testDelierLeDernierMoyenDeConnexionRefuse(): void
    {
        $compte = creer_compte_social(self::identite(), 'Awa', 'Diop');   // sans mot de passe
        self::assertNotNull(refus_deliaison($compte, 'google'), 'Seul moyen de connexion : on ne peut pas le délier.');

        $avecMotDePasse = creer_utilisateur_test();
        creer_liaison((int) $avecMotDePasse['id'], 'google', 'sub-' . uniqid(), null);
        self::assertNull(refus_deliaison($avecMotDePasse, 'google'), 'Avec un mot de passe, la déliaison est permise.');
    }

    /* ---------------------------------------------------------------------
       Suppression des données (Meta)
       --------------------------------------------------------------------- */

    private static function requeteSignee(array $charge, string $secret = self::SECRET_FACEBOOK): string
    {
        $partie = self::b64(json_encode($charge));

        return self::b64(hash_hmac('sha256', $partie, $secret, true)) . '.' . $partie;
    }

    public function testSignatureMetaVerifiee(): void
    {
        $charge = ['algorithm' => 'HMAC-SHA256', 'issued_at' => time(), 'user_id' => '12345'];

        self::assertSame('12345', decoder_requete_signee_facebook(self::requeteSignee($charge), self::SECRET_FACEBOOK)['user_id']);
        self::assertNull(decoder_requete_signee_facebook(self::requeteSignee($charge, 'mauvais-secret'), self::SECRET_FACEBOOK), 'Signature falsifiée refusée.');
        self::assertNull(decoder_requete_signee_facebook(self::requeteSignee(['algorithm' => 'none'] + $charge), self::SECRET_FACEBOOK));
        self::assertNull(decoder_requete_signee_facebook('pas-une-requete', self::SECRET_FACEBOOK));
    }

    public function testSuppressionFacebookAnonymiseUnCompteSansAutreMoyen(): void
    {
        $idFacebook = (string) random_int(10 ** 9, 10 ** 10);
        $compte = creer_compte_social(self::identite(['fournisseur' => 'facebook', 'identifiant' => $idFacebook]), 'Fatou', 'Sow');

        $code = enregistrer_suppression_facebook($idFacebook);

        self::assertMatchesRegularExpression('/^[A-F0-9]{20}$/', $code);
        self::assertNull(trouver_utilisateur((int) $compte['id']), 'Compte anonymisé (supprimé).');
        $demande = bdd()->query("SELECT * FROM demandes_suppression_facebook WHERE code_confirmation = '$code'")->fetch();
        self::assertSame('traitee', $demande['statut']);
        self::assertNotSame($idFacebook, $demande['identifiant_fournisseur'], 'Seule l\'empreinte de l\'identifiant est gardée.');
    }

    public function testSuppressionFacebookGardeUnCompteAvecMotDePasse(): void
    {
        $compte = creer_utilisateur_test();
        $idFacebook = (string) random_int(10 ** 9, 10 ** 10);
        creer_liaison((int) $compte['id'], 'facebook', $idFacebook, null);

        enregistrer_suppression_facebook($idFacebook);

        self::assertNotNull(trouver_utilisateur((int) $compte['id']), 'Le compte garde son mot de passe : il n\'est pas supprimé.');
        self::assertSame([], liaisons_du_compte((int) $compte['id']), 'Les données Facebook sont supprimées.');
    }

    public function testSuppressionDiffereeSiCommandeEnCours(): void
    {
        [, $boutique] = creer_vendeur_test();
        $idFacebook = (string) random_int(10 ** 9, 10 ** 10);
        $compte = creer_compte_social(self::identite(['fournisseur' => 'facebook', 'identifiant' => $idFacebook]), 'Ibou', 'Fall');
        creer_commandes($compte, [article_test(creer_maillot_test((int) $boutique['id']))], adresse_test(), []);

        $code = enregistrer_suppression_facebook($idFacebook);

        self::assertNotNull(trouver_utilisateur((int) $compte['id']), 'Commande en cours : anonymisation différée.');
        self::assertSame('recue', bdd()->query("SELECT statut FROM demandes_suppression_facebook WHERE code_confirmation = '$code'")->fetchColumn());
    }

    /* ---------------------------------------------------------------------
       Redirection après connexion, journaux
       --------------------------------------------------------------------- */

    public function testRedirectionInterneSeulement(): void
    {
        foreach (['//site-pirate.com', '/\\site-pirate.com', 'https://site-pirate.com', 'panier/', '/a b'] as $mauvaise) {
            unset($_SESSION['retour_apres_connexion']);
            memoriser_redirection($mauvaise);
            self::assertArrayNotHasKey('retour_apres_connexion', $_SESSION, 'Refusée : ' . $mauvaise);
        }

        memoriser_redirection('/panier/commander.php?zone=1');
        self::assertSame('panier/commander.php?zone=1', $_SESSION['retour_apres_connexion']);
    }

    public function testAucunSecretDansLesJournaux(): void
    {
        $message = masquer_secrets('GET https://graph.facebook.com/v21.0/debug_token?input_token=EAAB123&access_token=123|secret '
            . '{"access_token":"ya29.abc","id_token":"eyJ..."} code=4/0Ab');

        foreach (['EAAB123', '123|secret', 'ya29.abc', 'eyJ...', '4/0Ab'] as $secret) {
            self::assertStringNotContainsString($secret, $message);
        }
    }
}
