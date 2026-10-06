<?php
/**
 * SAMA MAILLOT — abonnements des vendeurs (§18) : cycle de vie, paiement Wave manuel, rappels.
 *
 * - Essai gratuit (essais_gratuits, une fois par numéro), puis périodes payées (abonnements).
 *   L'état est TOUJOURS calculé à partir des dates en base, jamais d'une variable de session.
 * - États : essai, actif, verification (paiement à valider : boutique visible), grace (J0 à J+3 :
 *   visible mais ajout de maillots bloqué), expire (après J+3 : boutique masquée), aucun, suspendue.
 * - Renouvellement : 1, 3 ou 6 mois payés par Wave ; référence de transaction unique ; capture
 *   stockée hors du site public ; validation ou refus motivé par l'administrateur.
 * - Nouvelle période : à la fin de la précédente si le paiement a été envoyé avant l'expiration ou
 *   pendant la grâce ; sinon à la date de validation (§18.4).
 */

declare(strict_types=1);

const MOIS_ABONNEMENT = [1, 3, 6];
const DOSSIER_CAPTURES = DOSSIER_RACINE . '/stockage/paiements';

const STATUTS_PAIEMENT_ABONNEMENT = [
    'en_verification' => ['En vérification', 'attention'],
    'valide' => ['Validé', 'succes'],
    'refuse' => ['Refusé', 'erreur'],
];

/** Prix mensuel en FCFA (paramètre « abonnement_prix_fcfa »). */
function prix_abonnement(): int
{
    return max(0, (int) parametre('abonnement_prix_fcfa', (string) PRIX_ABONNEMENT_FCFA));
}

/**
 * Lien de paiement Wave Business de la plateforme avec le montant pré-rempli, ou null s'il n'est pas configuré.
 * Le paramètre garde le lien SANS montant (ex. https://pay.wave.com/m/M_sn_xxx/c/sn/) ; ?amount= est ajouté ici.
 * Ce lien n'est pas une preuve de paiement : le vendeur saisit ensuite l'identifiant Wave, vérifié par l'admin.
 */
function lien_paiement_wave(int $montant): ?string
{
    $lien = normaliser_lien_wave(parametre('lien_paiement_wave'));

    return $lien === null ? null : $lien . '?amount=' . $montant;
}

/** Lien Wave Business valide (https://pay.wave.com/…), sans paramètres ; null sinon. */
function normaliser_lien_wave(string $saisie): ?string
{
    $lien = strtok(trim($saisie), '?#');
    if (!is_string($lien) || preg_match('#^https://pay\.wave\.com/[A-Za-z0-9_/-]{3,200}$#', $lien) !== 1) {
        return null;
    }

    return $lien;
}

/**
 * Fin de l'essai et fin de la dernière période payée d'une boutique.
 *
 * @return array{essai: ?string, paye: ?string, fin: ?string}
 */
function periodes_boutique(array $boutique): array
{
    $requete = bdd()->prepare('SELECT fin_le FROM essais_gratuits WHERE utilisateur_id = :id');
    $requete->execute(['id' => $boutique['utilisateur_id']]);
    $essai = $requete->fetchColumn() ?: null;

    $requete = bdd()->prepare('SELECT MAX(fin_le) FROM abonnements WHERE boutique_id = :id');
    $requete->execute(['id' => $boutique['id']]);
    $paye = $requete->fetchColumn() ?: null;

    $fin = $essai;
    if ($paye !== null && ($fin === null || strtotime($paye) > strtotime($fin))) {
        $fin = $paye;
    }

    return ['essai' => $essai, 'paye' => $paye, 'fin' => $fin];
}

/** Paiement en attente de validation pour cette boutique, ou null. */
function paiement_en_verification(int $boutiqueId): ?array
{
    $requete = bdd()->prepare("SELECT * FROM paiements_abonnement WHERE boutique_id = :id AND statut = 'en_verification' ORDER BY id DESC LIMIT 1");
    $requete->execute(['id' => $boutiqueId]);
    $paiement = $requete->fetch();

    return $paiement === false ? null : $paiement;
}

/**
 * État de l'abonnement (§18.3).
 *
 * @return array{etat: string, libelle: string, fin: ?string, peut_ajouter: bool, peut_publier: bool, visible: bool}
 */
function calculer_etat_abonnement(array $boutique): array
{
    $etat = static fn (string $e, string $l, ?string $f, bool $ajout, bool $publier, bool $visible): array =>
        ['etat' => $e, 'libelle' => $l, 'fin' => $f, 'peut_ajouter' => $ajout, 'peut_publier' => $publier, 'visible' => $visible];

    if ($boutique['statut'] === 'suspendue') {
        return $etat('suspendue', 'Boutique suspendue', null, false, false, false);
    }

    $periodes = periodes_boutique($boutique);
    $fin = $periodes['fin'];
    $maintenant = time();

    if ($fin !== null && $maintenant <= strtotime($fin)) {
        return $periodes['paye'] !== null && strtotime($periodes['paye']) >= $maintenant
            ? $etat('actif', 'Abonnement actif', $fin, true, true, true)
            : $etat('essai', 'Essai gratuit', $fin, true, true, true);
    }

    // Échu : un paiement envoyé est en cours de vérification → la boutique reste visible (§18.4).
    if (paiement_en_verification((int) $boutique['id']) !== null) {
        return $etat('verification', 'Paiement en vérification', $fin, true, true, true);
    }

    if ($fin === null) {
        return $etat('aucun', 'Aucun abonnement', null, false, false, false);
    }

    $grace = max(0, (int) parametre('abonnement_grace_jours', '3')) * 86400;
    if ($maintenant <= strtotime($fin) + $grace) {
        return $etat('grace', 'Période de grâce', $fin, false, true, true);
    }

    return $etat('expire', 'Abonnement expiré', $fin, false, false, false);
}

/** Aligne boutiques.statut sur l'état calculé (visibilité des maillots dans le catalogue). */
function synchroniser_statut_boutique(array $boutique): string
{
    if ($boutique['statut'] === 'suspendue') {
        return 'suspendue';
    }

    $etat = calculer_etat_abonnement($boutique)['etat'];
    $cible = match ($etat) {
        'grace' => 'grace',
        'expire', 'aucun' => 'masquee',
        default => 'active',
    };

    if ($cible !== $boutique['statut']) {
        bdd()->prepare("UPDATE boutiques SET statut = :statut WHERE id = :id AND statut <> 'suspendue'")
            ->execute(['statut' => $cible, 'id' => $boutique['id']]);
        journaliser_action('boutique_' . $cible, null, 'boutique', (int) $boutique['id'], $boutique['statut'], $cible);
    }

    return $cible;
}

/* -------------------------------------------------------------------------
   Paiement Wave manuel (§18.4)
   ------------------------------------------------------------------------- */

/**
 * Le vendeur déclare son paiement Wave. Renvoie [erreurs par champ] (vide = enregistré).
 *
 * @param array|null $fichier entrée de $_FILES pour la capture d'écran
 */
function soumettre_paiement_abonnement(array $vendeur, array $boutique, int $mois, string $reference, ?array $fichier): array
{
    $erreurs = [];
    $reference = strtoupper(trim($reference));

    if (!in_array($mois, MOIS_ABONNEMENT, true)) {
        $erreurs['mois'] = 'Choisissez 1, 3 ou 6 mois.';
    }
    if (preg_match('/^[A-Z0-9][A-Z0-9\-_.]{5,39}$/', $reference) !== 1) {
        $erreurs['reference'] = 'Saisissez l\'identifiant de la transaction Wave (6 à 40 lettres ou chiffres).';
    } else {
        $requete = bdd()->prepare('SELECT COUNT(*) FROM paiements_abonnement WHERE reference_wave = :reference');
        $requete->execute(['reference' => $reference]);
        if ((int) $requete->fetchColumn() > 0) {
            $erreurs['reference'] = 'Cet identifiant de transaction a déjà été utilisé.';
        }
    }
    if (paiement_en_verification((int) $boutique['id']) !== null) {
        $erreurs['general'] = 'Un paiement est déjà en cours de vérification. Attendez la réponse de l\'administration.';
    }

    // Capture d'écran : image réelle (JPG, PNG, WebP, AVIF), stockée ré-encodée hors du site public.
    $nomCapture = null;
    if ($fichier === null || ($fichier['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        $erreurs['capture'] = 'Joignez la capture d\'écran du paiement Wave.';
    } elseif ($erreurs === []) {
        // Mêmes contrôles que les photos (type réel, taille, dimensions), puis RÉ-ENCODAGE en WebP :
        // le fichier d'origine n'est jamais conservé (contenu caché et métadonnées éliminés).
        try {
            $image = redimensionner_image(ouvrir_image_envoyee($fichier, 100), 2000, 2000, false);
            if (!is_dir(DOSSIER_CAPTURES)) {
                mkdir(DOSSIER_CAPTURES, 0750, true);
            }
            $nomCapture = bin2hex(random_bytes(16)) . '.webp';
            if (!imagewebp($image, DOSSIER_CAPTURES . '/' . $nomCapture, IMAGE_QUALITE_WEBP)) {
                $erreurs['capture'] = 'La capture n\'a pas pu être enregistrée. Réessayez.';
                $nomCapture = null;
            }
        } catch (ErreurImage $exception) {
            $erreurs['capture'] = 'Capture refusée : ' . $exception->getMessage();
        }
    }

    if ($erreurs !== []) {
        return $erreurs;
    }

    try {
        bdd()->prepare(
            'INSERT INTO paiements_abonnement (boutique_id, utilisateur_id, mois, montant, reference_wave, capture)
             VALUES (:boutique, :utilisateur, :mois, :montant, :reference, :capture)'
        )->execute([
            'boutique' => $boutique['id'], 'utilisateur' => $vendeur['id'], 'mois' => $mois,
            'montant' => prix_abonnement() * $mois, 'reference' => $reference, 'capture' => $nomCapture,
        ]);
    } catch (PDOException $exception) {
        @unlink(DOSSIER_CAPTURES . '/' . $nomCapture);
        if ($exception->getCode() !== '23000') {
            throw $exception;
        }

        return ['reference' => 'Cet identifiant de transaction a déjà été utilisé.'];
    }

    journaliser_action('paiement_abonnement_envoye', (int) $vendeur['id'], 'boutique', (int) $boutique['id'], null, $reference);
    synchroniser_statut_boutique($boutique);
    notifier_admins('paiement_abonnement', 'Paiement d\'abonnement à vérifier',
        '« ' . $boutique['nom'] . ' » : ' . format_fcfa(prix_abonnement() * $mois) . ' (' . $mois . ' mois), référence Wave ' . $reference . '.',
        'admin/abonnements.php');

    return [];
}

/** Validation par l'administrateur : crée la nouvelle période (§18.4). */
function valider_paiement_abonnement(array $admin, int $paiementId): ?string
{
    $pdo = bdd();
    $pdo->beginTransaction();

    try {
        $requete = $pdo->prepare("SELECT * FROM paiements_abonnement WHERE id = :id AND statut = 'en_verification' FOR UPDATE");
        $requete->execute(['id' => $paiementId]);
        $paiement = $requete->fetch();
        if ($paiement === false) {
            $pdo->rollBack();

            return 'Ce paiement a déjà été traité.';
        }

        $requete = $pdo->prepare('SELECT * FROM boutiques WHERE id = :id');
        $requete->execute(['id' => $paiement['boutique_id']]);
        $boutique = $requete->fetch();

        // Début : fin de la période précédente si le paiement a été envoyé avant l'expiration ou
        // pendant la grâce ; sinon (boutique déjà masquée) : maintenant.
        $fin = periodes_boutique($boutique)['fin'];
        $grace = max(0, (int) parametre('abonnement_grace_jours', '3')) * 86400;
        $envoye = strtotime($paiement['cree_le']);
        $debut = $fin !== null && $envoye <= strtotime($fin) + $grace ? strtotime($fin) : time();
        $finNouvelle = $debut + 30 * 86400 * (int) $paiement['mois'];

        $pdo->prepare(
            'INSERT INTO abonnements (boutique_id, paiement_id, debut_le, fin_le, mois, montant) VALUES (:boutique, :paiement, :debut, :fin, :mois, :montant)'
        )->execute([
            'boutique' => $boutique['id'], 'paiement' => $paiementId, 'debut' => date('Y-m-d H:i:s', $debut),
            'fin' => date('Y-m-d H:i:s', $finNouvelle), 'mois' => $paiement['mois'], 'montant' => $paiement['montant'],
        ]);
        $pdo->prepare("UPDATE paiements_abonnement SET statut = 'valide', traite_par = :admin, traite_le = NOW() WHERE id = :id")
            ->execute(['admin' => $admin['id'], 'id' => $paiementId]);
        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }

    synchroniser_statut_boutique($boutique);
    journaliser_action('admin_abonnement_valide', (int) $admin['id'], 'boutique', (int) $boutique['id'], null,
        $paiement['reference_wave'] . ' — jusqu\'au ' . date('d/m/Y', $finNouvelle));

    $vendeurId = (int) $boutique['utilisateur_id'];
    notifier($vendeurId, 'abonnement_valide', 'Paiement validé : abonnement actif',
        'Votre abonnement est actif jusqu\'au ' . date('d/m/Y', $finNouvelle) . '. Merci !', 'vendeur/abonnement.php');
    prevenir_vendeur($vendeurId, 'SAMA MAILLOT : paiement validé, votre abonnement est actif jusqu\'au ' . date('d/m/Y', $finNouvelle) . '.');

    return null;
}

/** Refus motivé par l'administrateur. */
function refuser_paiement_abonnement(array $admin, int $paiementId, string $motif): ?string
{
    $requete = bdd()->prepare("SELECT p.*, b.utilisateur_id AS vendeur_id FROM paiements_abonnement p JOIN boutiques b ON b.id = p.boutique_id
                               WHERE p.id = :id AND p.statut = 'en_verification'");
    $requete->execute(['id' => $paiementId]);
    $paiement = $requete->fetch();
    if ($paiement === false) {
        return 'Ce paiement a déjà été traité.';
    }

    bdd()->prepare("UPDATE paiements_abonnement SET statut = 'refuse', motif_refus = :motif, traite_par = :admin, traite_le = NOW() WHERE id = :id")
        ->execute(['motif' => $motif, 'admin' => $admin['id'], 'id' => $paiementId]);

    $requete = bdd()->prepare('SELECT * FROM boutiques WHERE id = :id');
    $requete->execute(['id' => $paiement['boutique_id']]);
    synchroniser_statut_boutique($requete->fetch());

    journaliser_action('admin_abonnement_refuse', (int) $admin['id'], 'boutique', (int) $paiement['boutique_id'], null, $paiement['reference_wave'] . ' — ' . $motif);
    $vendeurId = (int) $paiement['vendeur_id'];
    notifier($vendeurId, 'abonnement_refuse', 'Paiement d\'abonnement refusé', 'Motif : ' . $motif . '. Vous pouvez renvoyer un paiement.', 'vendeur/abonnement.php');
    prevenir_vendeur($vendeurId, 'SAMA MAILLOT : votre paiement d\'abonnement est refusé (' . $motif . '). Détails dans votre espace vendeur.');

    return null;
}

/* -------------------------------------------------------------------------
   Cycle de vie et rappels (§18.2) — tâche planifiée « abonnements »
   ------------------------------------------------------------------------- */

/** Envoie un rappel une seule fois par clé (notification + SMS non urgent). */
function rappel_abonnement(array $boutique, string $cle, string $titre, string $message): bool
{
    $requete = bdd()->prepare('INSERT IGNORE INTO rappels_abonnement (boutique_id, cle) VALUES (:boutique, :cle)');
    $requete->execute(['boutique' => $boutique['id'], 'cle' => $cle]);
    if ($requete->rowCount() !== 1) {
        return false;
    }

    $vendeurId = (int) $boutique['utilisateur_id'];
    notifier($vendeurId, 'abonnement_rappel', $titre, $message, 'vendeur/abonnement.php');

    $telephone = bdd()->prepare('SELECT telephone FROM utilisateurs WHERE id = :id');
    $telephone->execute(['id' => $vendeurId]);
    $numero = $telephone->fetchColumn();
    if (is_string($numero) && $numero !== '') {
        envoyer_sms($numero, 'SAMA MAILLOT : ' . $message, false, false);   // non urgent : jamais la nuit
    }

    return true;
}

/**
 * Pour chaque boutique : statut aligné sur l'abonnement, puis rappels J-2, J0, J+1, J+2,
 * masquage à J+3, rappel hebdomadaire pendant un mois.
 *
 * @return array{boutiques: int, rappels: int}
 */
function traiter_abonnements(): array
{
    $bilan = ['boutiques' => 0, 'rappels' => 0];
    $grace = max(0, (int) parametre('abonnement_grace_jours', '3'));
    $avant = max(0, (int) parametre('abonnement_rappel_jours', '2'));

    foreach (bdd()->query('SELECT * FROM boutiques WHERE supprime_le IS NULL')->fetchAll() as $boutique) {
        $bilan['boutiques']++;
        $statut = synchroniser_statut_boutique($boutique);
        if ($statut === 'suspendue') {
            continue;
        }

        $etat = calculer_etat_abonnement($boutique);
        if ($etat['fin'] === null || $etat['etat'] === 'verification') {
            continue;
        }

        $fin = strtotime($etat['fin']);
        $jours = (int) floor((time() - $fin) / 86400);   // < 0 : avant la fin ; 0 : jour de fin…
        $dateFin = date('Y-m-d', $fin);

        if (in_array($etat['etat'], ['essai', 'actif'], true) && $fin - time() <= $avant * 86400) {
            $bilan['rappels'] += (int) rappel_abonnement($boutique, 'j-' . $avant . ':' . $dateFin, 'Votre abonnement se termine bientôt',
                'Votre ' . ($etat['etat'] === 'essai' ? 'essai gratuit' : 'abonnement') . ' se termine le ' . date('d/m/Y', $fin)
                . '. Renouvelez-le pour garder vos maillots en ligne.');
        } elseif ($etat['etat'] === 'grace') {
            $restants = max(0, $grace - $jours);
            $bilan['rappels'] += (int) rappel_abonnement($boutique, 'grace:' . date('Y-m-d'), 'Votre abonnement est terminé',
                'Votre abonnement est terminé. Vos maillots seront masqués dans ' . $restants . ' jour' . ($restants > 1 ? 's' : '')
                . '. Ajout de maillots bloqué : renouvelez-le dès maintenant.');
        } elseif ($etat['etat'] === 'expire') {
            if (rappel_abonnement($boutique, 'expire:' . $dateFin, 'Vos maillots sont masqués',
                'Votre abonnement a expiré : votre boutique et vos maillots sont masqués. Vous pouvez encore livrer vos commandes en cours et renouveler à tout moment.')) {
                $bilan['rappels']++;
            } elseif ($jours >= $grace + 7 && $jours <= $grace + 30) {   // chaque semaine pendant un mois, à partir de J+3+7
                $bilan['rappels'] += (int) rappel_abonnement($boutique, 'hebdo:' . date('o-W'), 'Votre boutique est masquée',
                    'Votre boutique est toujours masquée. Renouvelez votre abonnement pour remettre vos maillots en ligne.');
            }
        }
    }

    return $bilan;
}

/** Revenus d'abonnement validés (tableau de bord admin). */
function revenus_abonnement(?string $depuis = null): int
{
    $sql = "SELECT COALESCE(SUM(montant), 0) FROM paiements_abonnement WHERE statut = 'valide'";
    if ($depuis !== null) {
        $requete = bdd()->prepare($sql . ' AND traite_le >= :depuis');
        $requete->execute(['depuis' => $depuis]);

        return (int) $requete->fetchColumn();
    }

    return (int) bdd()->query($sql)->fetchColumn();
}
