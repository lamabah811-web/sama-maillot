<?php
/**
 * SAMA MAILLOT — amorce des tests automatisés (§32.4).
 *
 * 1. Recrée la base de test « sama_maillot_test » à partir de sql/base_de_donnees.sql
 *    (même schéma et mêmes données de référence que le site, aucune donnée réelle).
 * 2. Charge le site avec cette base (variables d'environnement fixées dans phpunit.xml).
 * 3. Fournit des fonctions de préparation : clients, vendeurs, boutiques, maillots.
 */

declare(strict_types=1);

const BASE_DE_TEST = 'sama_maillot_test';

if (getenv('DB_NAME') !== BASE_DE_TEST) {
    fwrite(STDERR, "Sécurité : les tests doivent utiliser la base " . BASE_DE_TEST . " (voir phpunit.xml).\n");
    exit(1);
}

require __DIR__ . '/../includes/config.php';

// Base de test neuve à chaque lancement : schéma complet, nom de base remplacé.
$schema = str_replace('sama_maillot', BASE_DE_TEST, (string) file_get_contents(DOSSIER_RACINE . '/sql/base_de_donnees.sql'));
$serveur = new PDO(sprintf('mysql:host=%s;port=%s;charset=utf8mb4', env('DB_HOST', '127.0.0.1'), env('DB_PORT', '3306')),
    (string) env('DB_USER', 'root'), (string) env('DB_PASS', ''), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$serveur->exec('DROP DATABASE IF EXISTS ' . BASE_DE_TEST);
$serveur->exec($schema);
unset($serveur);

if (bdd()->query('SELECT DATABASE()')->fetchColumn() !== BASE_DE_TEST) {
    fwrite(STDERR, "Sécurité : connexion inattendue à une autre base que " . BASE_DE_TEST . ".\n");
    exit(1);
}

/* -------------------------------------------------------------------------
   Préparation des données de test
   ------------------------------------------------------------------------- */

/** Crée un utilisateur et renvoie sa ligne complète (comme utilisateur_connecte()). */
function creer_utilisateur_test(string $role = 'client'): array
{
    static $numero = 0;
    $numero++;
    bdd()->prepare(
        "INSERT INTO utilisateurs (role, prenom, nom, email, mot_de_passe_hache, statut, cgu_acceptees_le)
         VALUES (:role, 'Test', :nom, :email, :hache, 'actif', NOW())"
    )->execute(['role' => $role, 'nom' => 'N' . $numero, 'email' => "test$numero-" . uniqid() . '@exemple.sn', 'hache' => password_hash('Test2026', PASSWORD_DEFAULT)]);

    return trouver_utilisateur((int) bdd()->lastInsertId());
}

/**
 * Crée un vendeur avec sa boutique (essai gratuit en cours, tarif 1 500 FCFA pour la zone 1).
 *
 * @return array{0: array, 1: array} [vendeur, boutique]
 */
function creer_vendeur_test(int $joursEssai = 14): array
{
    $vendeur = creer_utilisateur_test('vendeur');
    $slug = 'boutique-' . uniqid();
    bdd()->prepare("INSERT INTO boutiques (utilisateur_id, nom, slug, ville, numero_wave, statut) VALUES (:u, :nom, :slug, 'Dakar', '+221770000099', 'active')")
        ->execute(['u' => $vendeur['id'], 'nom' => 'Boutique ' . $slug, 'slug' => $slug]);
    $boutiqueId = (int) bdd()->lastInsertId();
    bdd()->prepare("INSERT INTO tarifs_livraison (boutique_id, zone_id, tarif, delai) VALUES (:b, 1, 1500, '24-48h')")->execute(['b' => $boutiqueId]);
    bdd()->prepare(
        "INSERT INTO essais_gratuits (telephone, utilisateur_id, debut_le, fin_le)
         VALUES (:tel, :u, NOW() - INTERVAL 1 DAY, NOW() + INTERVAL $joursEssai DAY)"
    )->execute(['tel' => '+2217' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT), 'u' => $vendeur['id']]);

    return [$vendeur, boutique_par_id_test($boutiqueId)];
}

function boutique_par_id_test(int $id): array
{
    $requete = bdd()->prepare('SELECT * FROM boutiques WHERE id = :id');
    $requete->execute(['id' => $id]);

    return $requete->fetch();
}

/** Crée un maillot publié. $stocks : ['M' => 2, 'L' => 1]. Renvoie l'id. */
function creer_maillot_test(int $boutiqueId, int $prix = 10000, array $stocks = ['M' => 5]): int
{
    $slug = 'maillot-' . uniqid();
    bdd()->prepare(
        "INSERT INTO maillots (boutique_id, categorie_id, equipe_id, titre, slug, saison, type, authenticite, etat, prix, statut, publie_le)
         VALUES (:b, 1, (SELECT MIN(id) FROM equipes), :titre, :slug, '2025/2026', 'domicile', 'officiel', 'neuf_etiquette', :prix, 'publie', NOW())"
    )->execute(['b' => $boutiqueId, 'titre' => 'Maillot ' . $slug, 'slug' => $slug, 'prix' => $prix]);
    $id = (int) bdd()->lastInsertId();
    foreach ($stocks as $taille => $stock) {
        bdd()->prepare('INSERT INTO maillot_tailles (maillot_id, taille, stock) VALUES (:m, :t, :s)')->execute(['m' => $id, 't' => $taille, 's' => $stock]);
    }

    return $id;
}

function stock_test(int $maillotId, string $taille): int
{
    $requete = bdd()->prepare('SELECT stock FROM maillot_tailles WHERE maillot_id = :m AND taille = :t');
    $requete->execute(['m' => $maillotId, 't' => $taille]);

    return (int) $requete->fetchColumn();
}

function commande_test(int $id): array
{
    $requete = bdd()->prepare('SELECT * FROM commandes WHERE id = :id');
    $requete->execute(['id' => $id]);

    return $requete->fetch();
}

/** Article de panier pour creer_commandes(). */
function article_test(int $maillotId, string $taille = 'M', int $quantite = 1): array
{
    return ['maillot_id' => $maillotId, 'taille' => $taille, 'flocage_nom' => '', 'flocage_numero' => '', 'quantite' => $quantite];
}

/** Adresse de livraison dans la zone 1 (Dakar). */
function adresse_test(): array
{
    return ['zone_id' => 1, 'ville' => 'Dakar', 'quartier' => 'Mermoz', 'repere' => '', 'telephone' => '+221771234567'];
}
