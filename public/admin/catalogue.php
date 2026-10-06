<?php
/**
 * SAMA MAILLOT — administration : catalogue (§23.2).
 *
 * Trois onglets : catégories, équipes, zones de livraison. Créer, modifier, activer /
 * désactiver. Pas de suppression : les maillots, tarifs et commandes y font référence ;
 * un élément désactivé disparaît des listes du site mais l'historique reste intact.
 * Chaque modification est journalisée.
 */
require __DIR__ . '/../../includes/config.php';

$admin = exiger_admin();
$onglets = ['categories' => 'Catégories', 'equipes' => 'Équipes', 'zones' => 'Zones de livraison'];
$onglet = is_string($_GET['onglet'] ?? $_POST['onglet'] ?? null) && isset($onglets[$_GET['onglet'] ?? $_POST['onglet']])
    ? ($_GET['onglet'] ?? $_POST['onglet']) : 'categories';
$erreurs = [];

/** Slug unique dans une table (ajoute -2, -3… si besoin). */
function slug_unique(string $table, string $texte, int $saufId = 0): string
{
    $base = creer_slug($texte) ?: 'element';
    $slug = $base;
    for ($i = 2; ; $i++) {
        $requete = bdd()->prepare("SELECT COUNT(*) FROM $table WHERE slug = :slug AND id <> :id");
        $requete->execute(['slug' => $slug, 'id' => $saufId]);
        if ((int) $requete->fetchColumn() === 0) {
            return $slug;
        }
        $slug = $base . '-' . $i;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifier_csrf()) {
        $erreurs['general'] = 'Votre session a expiré. Merci de réessayer.';
    } else {
        $action = champ_post('action');
        $id = (int) champ_post('id');
        $nom = champ_post('nom');
        $pdo = bdd();

        try {
            switch ($onglet . ':' . $action) {
                // ---- Catégories ----
                case 'categories:enregistrer':
                    $description = champ_post('description');
                    $ordre = max(0, min(255, (int) champ_post('ordre')));
                    if (mb_strlen($nom) < 2 || mb_strlen($nom) > 60 || mb_strlen($description) > 255) {
                        $erreurs['general'] = 'Nom de 2 à 60 caractères, description de 255 caractères maximum.';
                        break;
                    }
                    if ($id > 0) {
                        $pdo->prepare('UPDATE categories SET nom = :nom, description = :description, ordre = :ordre WHERE id = :id')
                            ->execute(['nom' => $nom, 'description' => $description, 'ordre' => $ordre, 'id' => $id]);
                    } else {
                        $pdo->prepare('INSERT INTO categories (slug, nom, description, ordre) VALUES (:slug, :nom, :description, :ordre)')
                            ->execute(['slug' => slug_unique('categories', $nom), 'nom' => $nom, 'description' => $description, 'ordre' => $ordre]);
                        $id = (int) $pdo->lastInsertId();
                    }
                    journaliser_action('admin_catalogue_categorie', (int) $admin['id'], 'categorie', $id, null, $nom);
                    break;

                // ---- Équipes ----
                case 'equipes:enregistrer':
                    $pays = champ_post('pays');
                    $type = champ_post('type') === 'club' ? 'club' : 'selection';
                    if (mb_strlen($nom) < 2 || mb_strlen($nom) > 80 || mb_strlen($pays) < 2 || mb_strlen($pays) > 60) {
                        $erreurs['general'] = 'Indiquez le nom de l\'équipe (2 à 80 caractères) et son pays.';
                        break;
                    }
                    $doublon = $pdo->prepare('SELECT COUNT(*) FROM equipes WHERE nom = :nom AND id <> :id');
                    $doublon->execute(['nom' => $nom, 'id' => $id]);
                    if ((int) $doublon->fetchColumn() > 0) {
                        $erreurs['general'] = 'Cette équipe existe déjà.';
                        break;
                    }
                    if ($id > 0) {
                        $pdo->prepare('UPDATE equipes SET nom = :nom, pays = :pays, type = :type WHERE id = :id')
                            ->execute(['nom' => $nom, 'pays' => $pays, 'type' => $type, 'id' => $id]);
                    } else {
                        $pdo->prepare('INSERT INTO equipes (slug, nom, pays, type) VALUES (:slug, :nom, :pays, :type)')
                            ->execute(['slug' => slug_unique('equipes', $nom), 'nom' => $nom, 'pays' => $pays, 'type' => $type]);
                        $id = (int) $pdo->lastInsertId();
                    }
                    journaliser_action('admin_catalogue_equipe', (int) $admin['id'], 'equipe', $id, null, $nom);
                    break;

                // ---- Zones de livraison ----
                case 'zones:enregistrer':
                    $description = champ_post('description');
                    $ordre = max(0, min(255, (int) champ_post('ordre')));
                    if (mb_strlen($nom) < 2 || mb_strlen($nom) > 60 || mb_strlen($description) < 2 || mb_strlen($description) > 255) {
                        $erreurs['general'] = 'Indiquez le nom de la zone (2 à 60 caractères) et des exemples de quartiers ou de villes.';
                        break;
                    }
                    $doublon = $pdo->prepare('SELECT COUNT(*) FROM zones_livraison WHERE nom = :nom AND id <> :id');
                    $doublon->execute(['nom' => $nom, 'id' => $id]);
                    if ((int) $doublon->fetchColumn() > 0) {
                        $erreurs['general'] = 'Cette zone existe déjà.';
                        break;
                    }
                    if ($id > 0) {
                        $pdo->prepare('UPDATE zones_livraison SET nom = :nom, description = :description, ordre = :ordre WHERE id = :id')
                            ->execute(['nom' => $nom, 'description' => $description, 'ordre' => $ordre, 'id' => $id]);
                    } else {
                        $pdo->prepare('INSERT INTO zones_livraison (nom, description, ordre) VALUES (:nom, :description, :ordre)')
                            ->execute(['nom' => $nom, 'description' => $description, 'ordre' => $ordre]);
                        $id = (int) $pdo->lastInsertId();
                    }
                    journaliser_action('admin_catalogue_zone', (int) $admin['id'], 'zone', $id, null, $nom);
                    break;

                // ---- Activer / désactiver (les trois onglets) ----
                case 'categories:basculer':
                case 'equipes:basculer':
                case 'zones:basculer':
                    $table = ['categories' => 'categories', 'equipes' => 'equipes', 'zones' => 'zones_livraison'][$onglet];
                    $pdo->prepare("UPDATE $table SET actif = 1 - actif WHERE id = :id")->execute(['id' => $id]);
                    journaliser_action('admin_catalogue_activation', (int) $admin['id'], $onglet, $id);
                    break;

                default:
                    $erreurs['general'] = 'Action inconnue.';
            }
        } catch (PDOException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }
            $erreurs['general'] = 'Ce nom est déjà utilisé.';
        }

        if ($erreurs === []) {
            message_flash('succes', 'Modification enregistrée.');
            rediriger('admin/catalogue.php?onglet=' . $onglet);
        }
    }
}

// ---- Données de l'onglet ----
$q = is_string($_GET['q'] ?? null) ? trim(mb_substr($_GET['q'], 0, 80)) : '';
$lignes = match ($onglet) {
    'categories' => bdd()->query(
        'SELECT c.*, (SELECT COUNT(*) FROM maillots m WHERE m.categorie_id = c.id AND m.statut = \'publie\') AS utilisations
         FROM categories c ORDER BY c.ordre, c.nom'
    )->fetchAll(),
    'zones' => bdd()->query(
        'SELECT z.*, (SELECT COUNT(*) FROM tarifs_livraison t WHERE t.zone_id = z.id) AS utilisations
         FROM zones_livraison z ORDER BY z.ordre, z.nom'
    )->fetchAll(),
    default => (static function () use ($q): array {
        $requete = bdd()->prepare(
            'SELECT e.*, (SELECT COUNT(*) FROM maillots m WHERE m.equipe_id = e.id AND m.statut = \'publie\') AS utilisations
             FROM equipes e WHERE (:q = \'\' OR e.nom LIKE :q2 OR e.pays LIKE :q3) ORDER BY e.type, e.nom'
        );
        $requete->execute(['q' => $q, 'q2' => '%' . $q . '%', 'q3' => '%' . $q . '%']);

        return $requete->fetchAll();
    })(),
};

$titre_page = 'Catalogue';
$admin_actif = 'catalogue';

require __DIR__ . '/../../includes/admin_entete.php';
?>

<div class="sm-admin__titre">
    <div>
        <h1>Catalogue</h1>
        <p>Catégories, équipes et zones de livraison proposées sur le site. Un élément désactivé n'apparaît plus, l'historique reste intact.</p>
    </div>
</div>

<nav class="sm-onglets mb-3" aria-label="Rubriques du catalogue">
    <?php foreach ($onglets as $cle => $libelle): ?>
        <a class="sm-onglet" href="<?= e(url('admin/catalogue.php?onglet=' . $cle)) ?>"<?= $cle === $onglet ? ' aria-current="page"' : '' ?>><?= e($libelle) ?></a>
    <?php endforeach; ?>
</nav>

<?= alerte_formulaire($erreurs) ?>

<div class="row g-3">
    <!-- Ajout -->
    <div class="col-12 col-xl-4 order-xl-2">
        <section class="sm-panneau">
            <header class="sm-panneau__entete">
                <span class="sm-kpi__icone sm-teinte--or" aria-hidden="true"><i class="bi bi-plus-lg"></i></span>
                <div><h2>Ajouter</h2><p><?= e(['categories' => 'Une catégorie', 'equipes' => 'Une équipe', 'zones' => 'Une zone de livraison'][$onglet]) ?></p></div>
            </header>
            <form method="post" class="d-grid gap-2">
                <?= champ_csrf() ?>
                <input type="hidden" name="onglet" value="<?= e($onglet) ?>">
                <input type="hidden" name="action" value="enregistrer">
                <label class="form-label small mb-0" for="nouveau-nom">Nom</label>
                <input class="form-control" type="text" id="nouveau-nom" name="nom" maxlength="80" required>
                <?php if ($onglet === 'equipes'): ?>
                    <label class="form-label small mb-0" for="nouveau-pays">Pays</label>
                    <input class="form-control" type="text" id="nouveau-pays" name="pays" maxlength="60" required>
                    <label class="form-label small mb-0" for="nouveau-type">Type</label>
                    <select class="form-select" id="nouveau-type" name="type">
                        <option value="selection">Sélection nationale</option>
                        <option value="club">Club</option>
                    </select>
                <?php else: ?>
                    <label class="form-label small mb-0" for="nouveau-description"><?= $onglet === 'zones' ? 'Quartiers ou villes (exemples)' : 'Description' ?></label>
                    <input class="form-control" type="text" id="nouveau-description" name="description" maxlength="255" <?= $onglet === 'zones' ? 'required' : '' ?>>
                    <label class="form-label small mb-0" for="nouveau-ordre">Ordre d'affichage</label>
                    <input class="form-control" type="number" id="nouveau-ordre" name="ordre" min="0" max="255" value="<?= e(count($lignes) + 1) ?>">
                <?php endif; ?>
                <button class="btn btn-or mt-1" type="submit"><i class="bi bi-plus-lg" aria-hidden="true"></i> Ajouter</button>
            </form>
        </section>
    </div>

    <!-- Liste -->
    <div class="col-12 col-xl-8">
        <section class="sm-panneau">
            <header class="sm-panneau__entete">
                <span class="sm-kpi__icone sm-teinte--violet" aria-hidden="true"><i class="bi bi-list-ul"></i></span>
                <div><h2><?= e($onglets[$onglet]) ?></h2><p><?= e(count($lignes)) ?> élément<?= count($lignes) > 1 ? 's' : '' ?></p></div>
                <?php if ($onglet === 'equipes'): ?>
                    <form method="get" class="d-flex gap-2">
                        <input type="hidden" name="onglet" value="equipes">
                        <label class="visually-hidden" for="q">Rechercher une équipe</label>
                        <input class="form-control form-control-sm" type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Rechercher…">
                    </form>
                <?php endif; ?>
            </header>

            <ul class="sm-liste-catalogue">
                <?php foreach ($lignes as $ligne): ?>
                    <li class="<?= (int) $ligne['actif'] === 1 ? '' : 'sm-liste-catalogue--inactif' ?>">
                        <div class="sm-liste-catalogue__resume">
                            <div>
                                <strong><?= e($ligne['nom']) ?></strong>
                                <span>
                                    <?php if ($onglet === 'equipes'): ?>
                                        <?= e($ligne['type'] === 'club' ? 'Club' : 'Sélection') ?> · <?= e($ligne['pays']) ?>
                                    <?php else: ?>
                                        <?= e($ligne['description']) ?>
                                    <?php endif; ?>
                                    · <?= e($ligne['utilisations']) ?> <?= $onglet === 'zones' ? 'boutique(s)' : 'maillot(s)' ?>
                                </span>
                            </div>
                            <?= (int) $ligne['actif'] === 1 ? '<span class="sm-statut sm-statut--succes">Actif</span>' : '<span class="sm-statut sm-statut--erreur">Désactivé</span>' ?>
                            <form method="post">
                                <?= champ_csrf() ?>
                                <input type="hidden" name="onglet" value="<?= e($onglet) ?>">
                                <input type="hidden" name="action" value="basculer">
                                <input type="hidden" name="id" value="<?= e($ligne['id']) ?>">
                                <button class="btn btn-sm btn-link" type="submit"><?= (int) $ligne['actif'] === 1 ? 'Désactiver' : 'Activer' ?></button>
                            </form>
                            <details class="sm-moderation">
                                <summary class="btn btn-sm btn-contour">Modifier</summary>
                                <form method="post" class="sm-moderation__formulaire d-grid gap-2">
                                    <?= champ_csrf() ?>
                                    <input type="hidden" name="onglet" value="<?= e($onglet) ?>">
                                    <input type="hidden" name="action" value="enregistrer">
                                    <input type="hidden" name="id" value="<?= e($ligne['id']) ?>">
                                    <label class="visually-hidden" for="nom-<?= e($ligne['id']) ?>">Nom</label>
                                    <input class="form-control form-control-sm" type="text" id="nom-<?= e($ligne['id']) ?>" name="nom" value="<?= e($ligne['nom']) ?>" maxlength="80" required>
                                    <?php if ($onglet === 'equipes'): ?>
                                        <label class="visually-hidden" for="pays-<?= e($ligne['id']) ?>">Pays</label>
                                        <input class="form-control form-control-sm" type="text" id="pays-<?= e($ligne['id']) ?>" name="pays" value="<?= e($ligne['pays']) ?>" maxlength="60" required>
                                        <label class="visually-hidden" for="type-<?= e($ligne['id']) ?>">Type</label>
                                        <select class="form-select form-select-sm" id="type-<?= e($ligne['id']) ?>" name="type">
                                            <option value="selection"<?= $ligne['type'] === 'selection' ? ' selected' : '' ?>>Sélection nationale</option>
                                            <option value="club"<?= $ligne['type'] === 'club' ? ' selected' : '' ?>>Club</option>
                                        </select>
                                    <?php else: ?>
                                        <label class="visually-hidden" for="description-<?= e($ligne['id']) ?>">Description</label>
                                        <input class="form-control form-control-sm" type="text" id="description-<?= e($ligne['id']) ?>" name="description" value="<?= e($ligne['description']) ?>" maxlength="255">
                                        <label class="visually-hidden" for="ordre-<?= e($ligne['id']) ?>">Ordre</label>
                                        <input class="form-control form-control-sm" type="number" id="ordre-<?= e($ligne['id']) ?>" name="ordre" value="<?= e($ligne['ordre']) ?>" min="0" max="255">
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-or" type="submit">Enregistrer</button>
                                </form>
                            </details>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    </div>
</div>

<?php require __DIR__ . '/../../includes/admin_pied.php'; ?>
