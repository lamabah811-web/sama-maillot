<?php
/**
 * SAMA MAILLOT — contrôles de l'« État du système » (Administration, §33.2).
 *
 * Liste de vérification de la mise en production, calculée en direct sur le serveur.
 * Chaque contrôle : [rubrique, libellé, niveau ('ok' | 'attention' | 'erreur'), détail / action à faire].
 */

declare(strict_types=1);

function controles_systeme(): array
{
    $production = APP_ENV === 'production';
    $grave = $production ? 'erreur' : 'attention';   // bloquant en production, simple rappel ailleurs
    $c = [];

    // ---- Serveur et PHP ----
    $c[] = ['Serveur', 'Version de PHP', version_compare(PHP_VERSION, '8.2.0', '>=') ? 'ok' : 'erreur', 'PHP ' . PHP_VERSION . ' (8.2 minimum).'];
    foreach (['pdo_mysql', 'gd', 'mbstring', 'curl', 'fileinfo', 'openssl'] as $extension) {
        $c[] = ['Serveur', 'Extension ' . $extension, extension_loaded($extension) ? 'ok' : 'erreur', extension_loaded($extension) ? 'Présente.' : 'À activer dans la configuration PHP.'];
    }
    $webp = function_exists('gd_info') && !empty(gd_info()['WebP Support']);
    $c[] = ['Serveur', 'Images WebP (GD)', $webp ? 'ok' : 'erreur', $webp ? 'Prise en charge.' : 'GD sans WebP : les photos ne peuvent pas être enregistrées.'];
    $c[] = ['Serveur', 'Extension intl', extension_loaded('intl') ? 'ok' : 'attention', extension_loaded('intl') ? 'Présente.' : 'Conseillée (recherche sans accents plus précise) ; le site fonctionne sans.'];
    $televersement = min(ini_octets((string) ini_get('upload_max_filesize')), ini_octets((string) ini_get('post_max_size')));
    $photoMax = max(1, (int) parametre('photo_taille_max_mo', '5')) * 1048576;
    $c[] = ['Serveur', 'Taille des envois', $televersement >= $photoMax ? 'ok' : 'attention',
        'upload_max_filesize = ' . ini_get('upload_max_filesize') . ', post_max_size = ' . ini_get('post_max_size')
        . ($televersement >= $photoMax ? '.' : ' : inférieur à la taille maximale d\'une photo (' . intdiv($photoMax, 1048576) . ' Mo).')];
    $c[] = ['Serveur', 'Bibliothèques Composer', is_file(DOSSIER_RACINE . '/vendor/autoload.php') ? 'ok' : 'erreur',
        is_file(DOSSIER_RACINE . '/vendor/autoload.php') ? 'vendor/ présent (PHPMailer, connexion Google / Facebook).' : 'Lancer « composer install --no-dev » ou envoyer le dossier vendor/.'];

    // ---- Sécurité ----
    $c[] = ['Sécurité', 'Environnement', $production ? 'ok' : 'attention', 'APP_ENV = ' . APP_ENV . ($production ? '.' : ' : mettre « production » sur le site public, « recette » sur le site de test.')];
    $c[] = ['Sécurité', 'HTTPS', est_https() ? 'ok' : $grave, est_https() ? 'Connexion chiffrée.' : 'Activer le certificat SSL (Let\'s Encrypt) et forcer HTTPS dans le Manager Infomaniak.'];
    $c[] = ['Sécurité', 'Adresse du site (APP_URL)', !$production || str_starts_with(APP_URL, 'https://') ? 'ok' : 'erreur', APP_URL];
    $affichage = in_array(strtolower((string) ini_get('display_errors')), ['1', 'on', 'stdout'], true);
    $c[] = ['Sécurité', 'Affichage des erreurs PHP', !$affichage ? 'ok' : $grave, !$affichage ? 'Désactivé (erreurs uniquement dans journaux/).' : 'Activé : désactiver en production.'];
    $c[] = ['Sécurité', 'Version de PHP cachée', !ini_get('expose_php') ? 'ok' : 'attention', !ini_get('expose_php') ? 'expose_php = Off.' : 'Mettre expose_php = Off (l\'en-tête est déjà retiré par le site).'];
    try {
        cle_chiffrement();
        $c[] = ['Sécurité', 'Clé de chiffrement', 'ok', 'CLE_CHIFFREMENT valide.'];
    } catch (Throwable) {
        $c[] = ['Sécurité', 'Clé de chiffrement', 'erreur', 'CLE_CHIFFREMENT absente ou invalide dans .env (voir README).'];
    }
    $droits = implode(' ', bdd()->query('SHOW GRANTS FOR CURRENT_USER()')->fetchAll(PDO::FETCH_COLUMN));
    $tropDeDroits = preg_match('/ALL PRIVILEGES|\bDROP\b|\bALTER\b|GRANT OPTION/i', $droits) === 1;
    $c[] = ['Sécurité', 'Droits de l\'utilisateur MySQL', $tropDeDroits ? $grave : 'ok',
        $tropDeDroits ? 'L\'utilisateur du site a trop de droits : créer un utilisateur limité à SELECT, INSERT, UPDATE, DELETE (sql/utilisateur_production.sql).' : 'Limités à la lecture et l\'écriture des lignes.'];
    $env = sonder_adresse(url('.env'));
    $c[] = ['Sécurité', 'Fichier .env protégé', $env !== 200 ? 'ok' : 'erreur', $env !== 200 ? 'Inaccessible depuis Internet (code ' . $env . ').' : 'LISIBLE depuis Internet : le domaine doit pointer vers le dossier public/.'];
    foreach (['public/uploads/.htaccess' => 'Exécution de PHP bloquée dans uploads/', 'stockage/.htaccess' => 'Dossier stockage/ privé'] as $fichier => $libelle) {
        $c[] = ['Sécurité', $libelle, is_file(DOSSIER_RACINE . '/' . $fichier) ? 'ok' : 'erreur', is_file(DOSSIER_RACINE . '/' . $fichier) ? $fichier . ' présent.' : $fichier . ' MANQUANT.'];
    }
    $comptesTest = (int) bdd()->query("SELECT COUNT(*) FROM utilisateurs WHERE email LIKE '%@test.sama' AND supprime_le IS NULL")->fetchColumn();
    $c[] = ['Sécurité', 'Comptes de test', $comptesTest === 0 ? 'ok' : ($production ? 'erreur' : 'attention'),
        $comptesTest === 0 ? 'Aucun.' : $comptesTest . ' compte(s) @test.sama à supprimer avant l\'ouverture au public.'];

    // ---- Dossiers ----
    foreach (['public/uploads/maillots', 'public/uploads/boutiques', 'stockage/paiements', 'stockage/litiges', 'journaux'] as $dossier) {
        $chemin = DOSSIER_RACINE . '/' . $dossier;
        $ok = is_dir($chemin) && is_writable($chemin);
        $c[] = ['Dossiers', $dossier, $ok ? 'ok' : 'erreur', $ok ? 'Accessible en écriture.' : 'À créer ou rendre accessible en écriture au serveur web.'];
    }

    // ---- Tâches automatiques ----
    $cron = (int) @file_get_contents(DOSSIER_JOURNAUX . '/derniere_execution_cron.txt');
    $ecart = $cron > 0 ? time() - $cron : null;
    $c[] = ['Tâches', 'Tâche planifiée (cron)', $ecart !== null && $ecart < 20 * 60 ? 'ok' : $grave,
        $ecart === null ? 'Jamais exécutée : programmer « php …/taches/toutes.php » toutes les 5 minutes.'
            : 'Dernière exécution il y a ' . intdiv($ecart, 60) . ' min' . ($ecart < 20 * 60 ? '.' : ' : vérifier le cron.')];

    // ---- Communication ----
    $smtp = trim((string) env('SMTP_HOTE', '')) !== '';
    $c[] = ['Communication', 'E-mails (SMTP)', $smtp ? 'ok' : $grave, $smtp ? 'SMTP configuré (' . env('SMTP_HOTE') . ').' : 'SMTP_HOTE vide : aucun e-mail ne part (notifications, mot de passe oublié).'];
    $c[] = ['Communication', 'SMS', 'ok', SMS_ACTIF ? 'Activés (' . (env('SMS_FOURNISSEUR') ?: 'aucun fournisseur') . ').' : 'Désactivés : tout passe par e-mail.'];
    foreach (['email_support' => 'E-mail du support', 'numero_whatsapp_support' => 'WhatsApp du support'] as $cle => $libelle) {
        $c[] = ['Communication', $libelle, parametre($cle) !== '' ? 'ok' : 'attention', parametre($cle) !== '' ? parametre($cle) : 'À renseigner dans Paramètres.'];
    }

    // ---- Paiements ----
    $fournisseur = strtolower(trim((string) env('PAIEMENT_FOURNISSEUR', '')));
    $c[] = ['Paiements', 'Paiement en ligne', match (true) {
        $fournisseur === 'simulateur' && $production => 'erreur',
        $fournisseur === '' => 'attention',
        default => 'ok',
    }, match (true) {
        $fournisseur === 'simulateur' && $production => 'Simulateur de TEST configuré en production : il est désactivé ; brancher un agrégateur réel ou vider PAIEMENT_FOURNISSEUR.',
        $fournisseur === 'simulateur' => 'Simulateur de test (normal en local et en recette).',
        $fournisseur === '' => 'Désactivé : seuls le paiement à la livraison et WhatsApp sont proposés (possible au lancement, §34.1).',
        default => 'Fournisseur : ' . $fournisseur . '.',
    }];
    $wave = lien_paiement_wave(prix_abonnement()) !== null || parametre('numero_wave_plateforme') !== '';
    $c[] = ['Paiements', 'Paiement des abonnements (Wave)', $wave ? 'ok' : 'erreur', $wave ? 'Lien ou numéro Wave configuré.' : 'Renseigner le lien Wave Business ou le numéro Wave dans Paramètres.'];

    // ---- Contenus ----
    $manquantes = array_filter(['entreprise_raison_sociale', 'entreprise_adresse', 'entreprise_ninea', 'entreprise_rccm', 'entreprise_responsable', 'cdp_declaration'],
        static fn (string $cle): bool => trim(parametre($cle)) === '');
    $c[] = ['Contenus', 'Informations légales', $manquantes === [] ? 'ok' : $grave,
        $manquantes === [] ? 'Complètes.' : count($manquantes) . ' information(s) à compléter (Paramètres → Informations légales).'];
    $aJour = true;
    foreach (['css/sama.css', 'js/sama.js'] as $source) {
        $min = DOSSIER_PUBLIC . '/' . preg_replace('/\.(css|js)$/', '.min.$1', $source);
        $aJour = $aJour && is_file($min) && filemtime($min) >= filemtime(DOSSIER_PUBLIC . '/' . $source);
    }
    $c[] = ['Contenus', 'CSS et JS minifiés', $aJour ? 'ok' : 'attention', $aJour ? 'À jour.' : 'Relancer « php outils/minifier.php » (les sources sont servies en attendant).'];

    return $c;
}

/** « 8M », « 2G »… en octets. */
function ini_octets(string $valeur): int
{
    $valeur = trim($valeur);
    $nombre = (int) $valeur;

    return match (strtolower(substr($valeur, -1))) {
        'g' => $nombre * 1073741824,
        'm' => $nombre * 1048576,
        'k' => $nombre * 1024,
        default => $nombre,
    };
}

/** Code HTTP d'une adresse du site (0 si injoignable), sans suivre les redirections. */
function sonder_adresse(string $adresse): int
{
    $curl = curl_init($adresse);
    curl_setopt_array($curl, [CURLOPT_NOBODY => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5, CURLOPT_FOLLOWLOCATION => false]);
    curl_exec($curl);
    $code = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    return $code;
}
