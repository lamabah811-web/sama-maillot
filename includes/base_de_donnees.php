<?php
/**
 * SAMA MAILLOT — connexion à la base de données (MySQL 8 / MariaDB) avec PDO.
 *
 * Utilisation :
 *     $requete = bdd()->prepare('SELECT * FROM maillots WHERE id = :id');
 *     $requete->execute(['id' => $id]);
 *
 * La connexion n'est ouverte qu'au premier appel de bdd(), puis réutilisée :
 * une page qui n'utilise pas la base ne s'y connecte jamais.
 */

declare(strict_types=1);

/**
 * Renvoie la connexion PDO unique de l'application.
 *
 * @throws RuntimeException si la base est injoignable (message générique, détail dans les journaux)
 */
function bdd(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        env('DB_HOST', '127.0.0.1'),
        env('DB_PORT', '3306'),
        env('DB_NAME', 'sama_maillot')
    );

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,        // toute erreur SQL lève une exception
        PDO::ATTR_EMULATE_PREPARES => false,                // vraies requêtes préparées (§24)
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,   // résultats en tableaux associatifs
        PDO::ATTR_STRINGIFY_FETCHES => false,               // les entiers restent des entiers
    ];

    try {
        $pdo = new PDO($dsn, (string) env('DB_USER', 'root'), (string) env('DB_PASS', ''), $options);

        // Les dates MySQL à l'heure de Dakar (UTC+0), comme PHP.
        $pdo->exec("SET time_zone = '+00:00'");
    } catch (PDOException $exception) {
        // Le message PDO peut contenir l'hôte ou l'utilisateur : il reste dans les journaux.
        error_log('Connexion à la base impossible : ' . $exception->getMessage());
        throw new RuntimeException('La base de données est momentanément indisponible.');
    }

    return $pdo;
}
