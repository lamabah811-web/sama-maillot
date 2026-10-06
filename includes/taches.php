<?php
/**
 * SAMA MAILLOT — tâches automatiques (§27.3).
 *
 * En production, elles sont lancées par le cron d'Infomaniak (dossier taches/). Par sécurité,
 * et pour qu'elles tournent aussi en local sans cron, le site les lance lui-même au plus une
 * fois toutes les 10 minutes, à l'ouverture d'une page (verrou de fichier : jamais en double).
 */

declare(strict_types=1);

const TACHES_INTERVALLE_SECONDES = 600;

/** Lance toutes les tâches automatiques. @return array<string, mixed> bilan */
function executer_taches_automatiques(): array
{
    return [
        'paiements' => traiter_paiements_en_attente(),
        'commandes' => traiter_commandes_echues(),
        'abonnements' => traiter_abonnements(),
        'sms' => traiter_file_sms(),
        'purge' => purger_donnees_anciennes(),
    ];
}

/**
 * Durées de conservation annoncées dans la politique de confidentialité :
 * journaux de sécurité 12 mois maximum (tentatives de connexion supprimées, adresses IP
 * effacées du journal d'audit, qui reste conservé), codes de vérification expirés depuis 30 jours.
 */
function purger_donnees_anciennes(): array
{
    $pdo = bdd();

    return [
        'tentatives' => $pdo->exec('DELETE FROM tentatives_connexion WHERE cree_le < NOW() - INTERVAL 12 MONTH'),
        'ip_journal' => $pdo->exec('UPDATE journal_actions SET adresse_ip = NULL WHERE adresse_ip IS NOT NULL AND cree_le < NOW() - INTERVAL 12 MONTH'),
        'codes' => $pdo->exec('DELETE FROM codes_verification WHERE expire_le < NOW() - INTERVAL 30 DAY'),
    ];
}

/** Lance les tâches si la dernière exécution date de plus de 10 minutes (sans jamais bloquer la page). */
function executer_taches_si_besoin(): void
{
    $fichier = DOSSIER_JOURNAUX . '/derniere_execution_taches.txt';
    if (is_file($fichier) && time() - (int) @file_get_contents($fichier) < TACHES_INTERVALLE_SECONDES) {
        return;
    }

    $verrou = @fopen(DOSSIER_JOURNAUX . '/taches.verrou', 'c');
    if ($verrou === false || !flock($verrou, LOCK_EX | LOCK_NB)) {
        return;   // une autre requête s'en occupe déjà
    }

    try {
        file_put_contents($fichier, (string) time());
        executer_taches_automatiques();
    } catch (Throwable $exception) {
        error_log('Tâches automatiques : ' . $exception->getMessage());
    } finally {
        flock($verrou, LOCK_UN);
        fclose($verrou);
    }
}
