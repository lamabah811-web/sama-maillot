<?php
/**
 * SAMA MAILLOT — administration : fiche d'un utilisateur (§23.2).
 *
 * Compte : désactiver, suspendre, réactiver (motif obligatoire, sessions fermées).
 * Boutique : suspendre / lever la suspension (§21.2) — le vendeur garde ses commandes en cours.
 * Rôle : client, vendeur ou administrateur (création ou masquage de la boutique si besoin).
 * Chaque action est journalisée (§31).
 */
require __DIR__ . '/../../includes/config.php';

$admin = exiger_admin();

$cible = trouver_utilisateur((int) ($_GET['id'] ?? $_POST['id'] ?? 0));
if ($cible === null) {
    afficher_erreur(404);
}
$id = (int) $cible['id'];
$boutique = boutique_du_vendeur($id);
$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifier_csrf()) {
        $erreurs['general'] = 'Votre session a expiré. Merci de réessayer.';
    } else {
        $motif = lire_motif_admin();
        $action = champ_post('action');

        // Changement de rôle : pas de motif à saisir, une mention standard va dans le journal.
        if ($action === 'role') {
            $motif = 'Changement de rôle par l\'administration';
        }

        if ($motif === null) {
            $erreurs['general'] = 'Indiquez un motif (5 caractères minimum) : il est enregistré dans le journal.';
        } else {
            $erreur = match ($action) {
                'activer' => changer_statut_utilisateur($admin, $id, 'actif', $motif),
                'desactiver' => changer_statut_utilisateur($admin, $id, 'desactive', $motif),
                'suspendre' => changer_statut_utilisateur($admin, $id, 'suspendu', $motif),
                'suspendre_boutique' => $boutique !== null ? changer_statut_boutique($admin, (int) $boutique['id'], true, $motif) : 'Pas de boutique.',
                'reactiver_boutique' => $boutique !== null ? changer_statut_boutique($admin, (int) $boutique['id'], false, $motif) : 'Pas de boutique.',
                'role' => changer_role_utilisateur($admin, $id, champ_post('role'), $motif, champ_post('nom_boutique'), champ_post('ville')),
                default => 'Action inconnue.',
            };

            if ($erreur === null) {
                message_flash('succes', 'Action enregistrée et journalisée.');
                rediriger('admin/utilisateur.php?id=' . $id);
            }
            $erreurs['general'] = $erreur;
        }
    }
}

// Activité réelle
$requete = bdd()->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(statut = 'recue'), 0) AS recues, COALESCE(SUM(statut = 'annulee'), 0) AS annulees
                           FROM commandes WHERE client_id = :id");
$requete->execute(['id' => $id]);
$achats = $requete->fetch();

$ventes = null;
$maillots = null;
if ($boutique !== null) {
    $requete = bdd()->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(statut = 'recue'), 0) AS recues, COALESCE(SUM(statut = 'annulee'), 0) AS annulees,
                                      COALESCE(SUM(CASE WHEN statut = 'recue' THEN total ELSE 0 END), 0) AS volume
                               FROM commandes WHERE boutique_id = :id");
    $requete->execute(['id' => $boutique['id']]);
    $ventes = $requete->fetch();
    $maillots = statistiques_boutique((int) $boutique['id']);
}

$requete = bdd()->prepare(
    "SELECT * FROM journal_actions WHERE (objet_type = 'utilisateur' AND objet_id = :id) OR utilisateur_id = :id2
        OR (objet_type = 'boutique' AND objet_id = :boutique) ORDER BY cree_le DESC, id DESC LIMIT 15"
);
$requete->execute(['id' => $id, 'id2' => $id, 'boutique' => $boutique['id'] ?? 0]);
$journal = $requete->fetchAll();

$modifiable = $cible['role'] !== 'admin' && $id !== (int) $admin['id'];

$titre_page = $cible['prenom'] . ' ' . $cible['nom'];
$admin_actif = $cible['role'] === 'vendeur' ? 'vendeurs' : 'utilisateurs';

require __DIR__ . '/../../includes/admin_entete.php';
?>

<section>
    <div>

        <div class="d-flex flex-wrap align-items-center gap-2 mb-4">
            <a class="btn btn-sm btn-contour" href="<?= e(url('admin/utilisateurs.php')) ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Utilisateurs</a>
            <h1 class="sm-titre-section mb-0 mx-2"><?= e($cible['prenom'] . ' ' . $cible['nom']) ?></h1>
            <?= badge_statut(STATUTS_UTILISATEUR, $cible['statut']) ?>
            <span class="badge text-bg-light border"><?= e($cible['role_libelle']) ?></span>
        </div>

        <?= alerte_formulaire($erreurs) ?>

        <div class="row g-4">
            <div class="col-12 col-lg-7">
                <div class="sm-tuile mb-3">
                    <h2 class="h6 fw-bold">Profil</h2>
                    <dl class="sm-recap__lignes mb-0">
                        <dt>E-mail</dt><dd class="text-break"><?= e($cible['email']) ?></dd>
                        <dt>Téléphone</dt>
                        <dd><?= $cible['telephone'] !== null ? e(formater_telephone($cible['telephone'])) . ($cible['telephone_verifie_le'] !== null ? ' ✓' : '') : '—' ?></dd>
                        <dt>Inscrit le</dt><dd><?= e(date_commande($cible['cree_le'])) ?></dd>
                        <dt>Dernière connexion</dt><dd><?= $cible['derniere_connexion_le'] !== null ? e(date_commande($cible['derniere_connexion_le'])) : 'Jamais' ?></dd>
                        <dt>Refus à la livraison</dt><dd><?= e($cible['refus_livraison']) ?></dd>
                        <dt>Commandes passées</dt><dd><?= e($achats['total']) ?> (<?= e($achats['recues']) ?> reçues, <?= e($achats['annulees']) ?> annulées)</dd>
                    </dl>
                </div>

                <?php if ($boutique !== null): ?>
                    <div class="sm-tuile mb-3">
                        <h2 class="h6 fw-bold d-flex flex-wrap align-items-center gap-2">
                            <i class="bi bi-shop" aria-hidden="true"></i>
                            <a href="<?= e(url_boutique($boutique['slug'])) ?>"><?= e($boutique['nom']) ?></a>
                            <?= badge_statut(STATUTS_BOUTIQUE, $boutique['statut']) ?>
                        </h2>
                        <dl class="sm-recap__lignes mb-0">
                            <dt>Ville</dt><dd><?= e($boutique['ville']) ?></dd>
                            <dt>Abonnement</dt><dd><?= e(etat_abonnement($boutique)['libelle']) ?></dd>
                            <dt>Maillots</dt><dd><?= e($maillots['publies']) ?> publiés, <?= e($maillots['brouillons']) ?> brouillons</dd>
                            <dt>Commandes reçues</dt><dd><?= e($ventes['total']) ?> (<?= e($ventes['recues']) ?> reçues, <?= e($ventes['annulees']) ?> annulées)</dd>
                            <dt>Volume des ventes reçues</dt><dd><?= e(format_fcfa((int) $ventes['volume'])) ?></dd>
                        </dl>
                        <a class="btn btn-sm btn-contour mt-3" href="<?= e(url('admin/maillots.php?boutique=' . (int) $boutique['id'])) ?>">Voir ses maillots</a>
                    </div>
                <?php endif; ?>

                <div class="sm-tuile">
                    <h2 class="h6 fw-bold">Historique (journal)</h2>
                    <?php if ($journal === []): ?>
                        <p class="small text-body-secondary mb-0">Aucune action enregistrée.</p>
                    <?php else: ?>
                        <ol class="sm-suivi">
                            <?php foreach ($journal as $ligne): ?>
                                <li>
                                    <strong><?= e(libelle_action_journal($ligne['action'])) ?></strong>
                                    <span class="small text-body-secondary"><?= e(date_commande($ligne['cree_le'])) ?></span>
                                    <?php if ($ligne['nouvelle_valeur'] !== null): ?><span class="small d-block"><?= e($ligne['nouvelle_valeur']) ?></span><?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-12 col-lg-5">
                <?php if ($id !== (int) $admin['id']): ?>
                    <!-- Rôle du compte -->
                    <form method="post" class="sm-tuile mb-3">
                        <?= champ_csrf() ?>
                        <input type="hidden" name="id" value="<?= e($id) ?>">
                        <input type="hidden" name="action" value="role">
                        <h2 class="h6 fw-bold"><i class="bi bi-person-gear" aria-hidden="true"></i> Rôle du compte</h2>
                        <label class="form-label small" for="role">Nouveau rôle</label>
                        <select class="form-select mb-2" id="role" name="role">
                            <?php foreach (['client' => 'Client', 'vendeur' => 'Vendeur', 'admin' => 'Administrateur'] as $cle => $libelle): ?>
                                <option value="<?= e($cle) ?>"<?= $cible['role'] === $cle ? ' selected' : '' ?>><?= e($libelle) ?><?= $cible['role'] === $cle ? ' (actuel)' : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($boutique === null): ?>
                            <p class="small text-body-secondary mb-2">Pour en faire un vendeur, indiquez sa boutique :</p>
                            <div class="row g-2 mb-2">
                                <div class="col-7">
                                    <label class="visually-hidden" for="nom_boutique">Nom de la boutique</label>
                                    <input class="form-control" type="text" id="nom_boutique" name="nom_boutique" maxlength="60" placeholder="Nom de la boutique">
                                </div>
                                <div class="col-5">
                                    <label class="visually-hidden" for="ville">Ville</label>
                                    <input class="form-control" type="text" id="ville" name="ville" maxlength="60" placeholder="Ville">
                                </div>
                            </div>
                        <?php else: ?>
                            <p class="small text-body-secondary mb-2">Hors rôle vendeur, sa boutique « <?= e($boutique['nom']) ?> » est masquée (rien n'est supprimé) ; elle revient s'il redevient vendeur.</p>
                        <?php endif; ?>
                        <button class="btn btn-or" type="submit">Changer le rôle</button>
                    </form>
                <?php endif; ?>

                <?php if (!$modifiable): ?>
                    <div class="sm-tuile"><p class="mb-0 small text-body-secondary"><?= $id === (int) $admin['id'] ? 'C\'est votre compte : il ne peut pas être modifié ici.' : 'Le statut d\'un administrateur ne se modifie pas ici : changez d\'abord son rôle.' ?></p></div>
                <?php else: ?>
                    <form method="post" class="sm-tuile mb-3">
                        <?= champ_csrf() ?>
                        <input type="hidden" name="id" value="<?= e($id) ?>">
                        <h2 class="h6 fw-bold">Compte</h2>
                        <p class="small text-body-secondary">Désactivé ou suspendu : la personne ne peut plus se connecter (ses sessions sont fermées).</p>
                        <label class="form-label small" for="motif-compte">Motif (obligatoire, journalisé)</label>
                        <input class="form-control mb-2" type="text" id="motif-compte" name="motif" maxlength="255" required minlength="5">
                        <div class="d-flex flex-wrap gap-2">
                            <?php if ($cible['statut'] !== 'actif'): ?>
                                <button class="btn btn-or" type="submit" name="action" value="activer">Réactiver le compte</button>
                            <?php else: ?>
                                <button class="btn btn-contour sm-bouton-danger" type="submit" name="action" value="suspendre">Suspendre</button>
                                <button class="btn btn-contour sm-bouton-danger" type="submit" name="action" value="desactiver">Désactiver</button>
                            <?php endif; ?>
                        </div>
                    </form>

                    <?php if ($boutique !== null): ?>
                        <form method="post" class="sm-tuile">
                            <?= champ_csrf() ?>
                            <input type="hidden" name="id" value="<?= e($id) ?>">
                            <h2 class="h6 fw-bold">Boutique</h2>
                            <p class="small text-body-secondary">
                                Suspendue : boutique et maillots masqués, plus de nouvelles commandes. Le vendeur garde l'accès
                                à ses commandes en cours pour les livrer, et il est prévenu.
                            </p>
                            <label class="form-label small" for="motif-boutique">Motif (obligatoire, journalisé)</label>
                            <input class="form-control mb-2" type="text" id="motif-boutique" name="motif" maxlength="255" required minlength="5">
                            <?php if ($boutique['statut'] === 'suspendue'): ?>
                                <button class="btn btn-or" type="submit" name="action" value="reactiver_boutique">Lever la suspension</button>
                            <?php else: ?>
                                <button class="btn btn-contour sm-bouton-danger" type="submit" name="action" value="suspendre_boutique">Suspendre la boutique</button>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../../includes/admin_pied.php'; ?>
