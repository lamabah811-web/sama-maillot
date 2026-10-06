<?php
/**
 * SAMA MAILLOT — envoi des e-mails (§22) avec PHPMailer et le SMTP d'Infomaniak (§27.2).
 *
 * Configuration (fichier .env) : SMTP_HOTE, SMTP_PORT, SMTP_CHIFFREMENT (ssl ou tls),
 * SMTP_UTILISATEUR, SMTP_MOT_DE_PASSE, SMTP_EXPEDITEUR.
 *   - SMTP configuré : l'e-mail part réellement (local comme production) ;
 *   - pas de SMTP en local : e-mail SIMULÉ, affiché à l'écran dans un bandeau « Mode développement » ;
 *   - pas de SMTP en production : échec propre, incident journalisé.
 * Le contenu n'est JAMAIS écrit dans les journaux : il peut contenir un lien secret (§31).
 */

declare(strict_types=1);

use PHPMailer\PHPMailer\Exception as ExceptionPhpMailer;
use PHPMailer\PHPMailer\PHPMailer;

if (is_file(DOSSIER_RACINE . '/vendor/autoload.php')) {
    require_once DOSSIER_RACINE . '/vendor/autoload.php';
}

/**
 * Envoie un e-mail texte.
 *
 * @param string|null $lien lien principal du message (affiché cliquable en mode développement)
 * @param bool $afficherEnLocal false : e-mail pour une AUTRE personne que celle qui navigue
 *                              (copie de notification…) : pas de bandeau en local
 * @return bool true si l'e-mail est parti (ou a été simulé en local)
 */
function envoyer_email(string $destinataire, string $sujet, string $texte, ?string $lien = null, bool $afficherEnLocal = true): bool
{
    $hote = trim((string) env('SMTP_HOTE', ''));

    if ($hote === '' || !class_exists(PHPMailer::class)) {
        if (!EN_PRODUCTION) {
            if ($afficherEnLocal && session_status() === PHP_SESSION_ACTIVE) {
                message_flash('dev', 'E-mail simulé pour ' . $destinataire . ' — « ' . $sujet . ' » : ' . $texte, $lien);
            }

            return true;
        }
        error_log('E-mail « ' . $sujet . ' » non envoyé : aucun serveur SMTP configuré (SMTP_HOTE dans .env).');

        return false;
    }

    $courrier = new PHPMailer(true);
    try {
        $courrier->isSMTP();
        $courrier->Host = $hote;
        $courrier->Port = (int) env('SMTP_PORT', '465');
        $courrier->SMTPAuth = true;
        $courrier->Username = (string) env('SMTP_UTILISATEUR', '');
        $courrier->Password = (string) env('SMTP_MOT_DE_PASSE', '');
        $courrier->SMTPSecure = strtolower((string) env('SMTP_CHIFFREMENT', 'ssl')) === 'tls'
            ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
        $courrier->CharSet = PHPMailer::CHARSET_UTF8;
        $courrier->Timeout = 15;

        $courrier->setFrom((string) env('SMTP_EXPEDITEUR', env('SMTP_UTILISATEUR', '')), NOM_SITE);
        $courrier->addAddress($destinataire);
        $courrier->Subject = $sujet;
        $courrier->Body = $texte . ($lien !== null ? "\n\n" . $lien : '') . "\n\n— " . NOM_SITE . ' · ' . APP_URL;

        return $courrier->send();
    } catch (ExceptionPhpMailer $exception) {
        // Jamais le contenu du message : seulement l'erreur technique.
        error_log('E-mail « ' . $sujet . ' » non envoyé : ' . $courrier->ErrorInfo);

        return false;
    }
}
