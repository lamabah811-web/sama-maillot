<?php
/**
 * SAMA MAILLOT — notifications dans le site (§22.1).
 *
 * Cloche avec compteur dans l'en-tête, page « Notifications » (compte/notifications.php),
 * marquer comme lu. Les SMS et e-mails restent envoyés à part (sms.php, email.php).
 * Aucun contenu sensible (code de livraison, mot de passe) dans une notification.
 */

declare(strict_types=1);

/** Icône Bootstrap Icons selon le type de notification. */
const ICONES_NOTIFICATION = [
    'commande_nouvelle' => 'bi-bag-check',
    'commande_enregistree' => 'bi-receipt',
    'commande_confirmee' => 'bi-check2-circle',
    'commande_expediee' => 'bi-truck',
    'commande_livree' => 'bi-box-seam',
    'commande_recue' => 'bi-hand-thumbs-up',
    'commande_annulee' => 'bi-x-circle',
    'commande_rappel' => 'bi-hourglass-split',
    'boutique_suspendue' => 'bi-shop',
    'boutique_reactivee' => 'bi-shop',
    'compte_reactive' => 'bi-person-check',
    'maillot_masque' => 'bi-eye-slash',
    'maillot_supprime' => 'bi-trash3',
    'maillot_republie' => 'bi-eye',
    'role_modifie' => 'bi-person-gear',
    'signalement_nouveau' => 'bi-flag',
    'signalement_avertissement' => 'bi-exclamation-triangle',
    'abonnement_rappel' => 'bi-calendar-event',
    'abonnement_valide' => 'bi-calendar-check',
    'abonnement_refuse' => 'bi-calendar-x',
    'paiement_abonnement' => 'bi-wallet2',
    'suspension_automatique' => 'bi-exclamation-octagon',
    'contestation' => 'bi-chat-left-quote',
    'avis_invitation' => 'bi-star',
    'avis_nouveau' => 'bi-star-fill',
    'litige_ouvert' => 'bi-shield-exclamation',
    'litige_message' => 'bi-chat-dots',
    'litige_clos' => 'bi-shield-check',
    'litige_decide' => 'bi-hammer',
    'paiement_recu' => 'bi-credit-card',
    'paiement_echoue' => 'bi-credit-card-2-back',
    'paiement_anomalie' => 'bi-exclamation-diamond',
    'versement_effectue' => 'bi-send-check',
    'remboursement_effectue' => 'bi-arrow-counterclockwise',
    'code_bloque' => 'bi-shield-lock',
];

/**
 * Crée une notification pour un utilisateur.
 * $lien : chemin interne (ex. « vendeur/commande.php?id=12 »), jamais une adresse externe.
 */
function notifier(int $utilisateurId, string $type, string $titre, string $message, ?string $lien = null): void
{
    bdd()->prepare(
        'INSERT INTO notifications (utilisateur_id, type, titre, message, lien) VALUES (:utilisateur, :type, :titre, :message, :lien)'
    )->execute([
        'utilisateur' => $utilisateurId,
        'type' => mb_substr($type, 0, 40),
        'titre' => mb_substr($titre, 0, 120),
        'message' => mb_substr($message, 0, 255),
        'lien' => $lien !== null ? mb_substr($lien, 0, 255) : null,
    ]);

    // Copie par e-mail : TOUTES les notifications quand les SMS sont désactivés (l'e-mail remplace
    // le SMS) ; sinon seulement les importantes (§22.1, §22.2).
    if (!SMS_ACTIF || in_array($type, TYPES_COPIE_EMAIL, true)) {
        $requete = bdd()->prepare("SELECT email FROM utilisateurs WHERE id = :id AND statut = 'actif'");
        $requete->execute(['id' => $utilisateurId]);
        $email = $requete->fetchColumn();
        if (is_string($email) && !email_technique($email)) {   // pas d'e-mail vers un compte invité sans e-mail
            // En local sans SMTP : bandeau « e-mail simulé » seulement si l'e-mail est pour la personne connectée.
            $pourMoi = (int) (utilisateur_connecte()['id'] ?? 0) === $utilisateurId;
            envoyer_email($email, NOM_SITE . ' — ' . $titre, $message, $lien !== null ? url($lien) : null, $pourMoi);
        }
    }
}

/** Notifications dont une copie part aussi par e-mail quand les SMS sont actifs (§22.2). */
const TYPES_COPIE_EMAIL = [
    'commande_nouvelle', 'commande_confirmee', 'commande_expediee', 'commande_livree', 'commande_annulee',
    'boutique_suspendue', 'boutique_reactivee', 'signalement_avertissement',
    'abonnement_rappel', 'abonnement_valide', 'abonnement_refuse', 'maillot_masque', 'maillot_supprime',
    'litige_ouvert', 'litige_decide', 'paiement_recu', 'versement_effectue', 'remboursement_effectue',
];

/** Nombre de notifications non lues (pastille de la cloche). */
function nombre_notifications_non_lues(int $utilisateurId): int
{
    $requete = bdd()->prepare('SELECT COUNT(*) FROM notifications WHERE utilisateur_id = :id AND lue_le IS NULL');
    $requete->execute(['id' => $utilisateurId]);

    return (int) $requete->fetchColumn();
}

/** Dernières notifications d'un utilisateur, les plus récentes d'abord. */
function notifications_de(int $utilisateurId, int $limite = 50): array
{
    $requete = bdd()->prepare(
        'SELECT * FROM notifications WHERE utilisateur_id = :id ORDER BY cree_le DESC, id DESC LIMIT ' . max(1, $limite)
    );
    $requete->execute(['id' => $utilisateurId]);

    return $requete->fetchAll();
}

/** Une notification de cet utilisateur (contrôle d'accès), ou null. */
function notification_de(int $id, int $utilisateurId): ?array
{
    $requete = bdd()->prepare('SELECT * FROM notifications WHERE id = :id AND utilisateur_id = :utilisateur');
    $requete->execute(['id' => $id, 'utilisateur' => $utilisateurId]);
    $notification = $requete->fetch();

    return $notification === false ? null : $notification;
}

/** Marque comme lue une notification ($id) ou toutes celles de l'utilisateur ($id = null). */
function marquer_notifications_lues(int $utilisateurId, ?int $id = null): void
{
    $sql = 'UPDATE notifications SET lue_le = NOW() WHERE utilisateur_id = :utilisateur AND lue_le IS NULL';
    $parametres = ['utilisateur' => $utilisateurId];

    if ($id !== null) {
        $sql .= ' AND id = :id';
        $parametres['id'] = $id;
    }

    bdd()->prepare($sql)->execute($parametres);
}

/** « il y a 5 min », « il y a 2 h », « hier à 14h05 », « 03/10/2026 ». */
function date_relative(string $date): string
{
    $horodatage = strtotime($date);
    $ecart = time() - $horodatage;

    return match (true) {
        $ecart < 60 => 'à l\'instant',
        $ecart < 3600 => 'il y a ' . intdiv($ecart, 60) . ' min',
        $ecart < 86400 && date('Y-m-d', $horodatage) === date('Y-m-d') => 'il y a ' . intdiv($ecart, 3600) . ' h',
        date('Y-m-d', $horodatage) === date('Y-m-d', strtotime('-1 day')) => 'hier à ' . date('H\hi', $horodatage),
        default => date('d/m/Y', $horodatage),
    };
}
