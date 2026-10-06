# SAMA MAILLOT — Mise en production chez Infomaniak

Guide pas à pas (§33). Comptez environ une demi-journée la première fois. Faites d'abord tout le
parcours sur le **site de recette** (sous-domaine de test), puis recommencez sur le **site public**.

À la fin, **Administration → État du système** doit être entièrement vert : c'est la liste de
contrôle officielle du lancement.

---

## 0. Avant de commencer

- [ ] Structure légale créée (entreprise individuelle ou SUARL) avec **NINEA** et **RCCM** (§25).
- [ ] Textes légaux relus par un juriste ou un comptable (CGU, CGV, confidentialité, politique vendeur).
- [ ] Déclaration auprès de la **CDP** (Commission de protection des données personnelles) faite.
- [ ] Nom de domaine choisi (ex. `samamaillot.sn`) et rattaché à l'hébergement Infomaniak.
- [ ] Une sauvegarde de la base locale : `C:\xampp\mysql\bin\mysqldump -uroot sama_maillot > sauvegarde.sql`.

## 1. Hébergement et PHP

Dans le Manager Infomaniak (hébergement web) :

1. **Version de PHP : 8.2** (ou plus récente).
2. Extensions : `pdo_mysql`, `gd` (avec WebP), `mbstring`, `curl`, `fileinfo`, `openssl` ; `intl` conseillée.
3. Réglages PHP :
   - `display_errors = Off`
   - `expose_php = Off`
   - `upload_max_filesize = 10M` et `post_max_size = 40M` (plusieurs photos par envoi)
   - `date.timezone = Africa/Dakar`

## 2. Envoi des fichiers

1. Avant l'envoi, sur votre ordinateur : `composer install --no-dev` (retire les outils de test de `vendor/`),
   puis `php outils/minifier.php`. Ensuite, envoyez **tout le projet** (FTP/SFTP ou Git), y compris `vendor/`
   (ou lancez `composer install --no-dev` sur le serveur si l'accès SSH le permet). Après l'envoi, en local,
   `composer install` remet les outils de test.
2. **N'envoyez pas** : `.env` local, `tests/`, `phpunit.xml`, `journaux/*.log`, le contenu de `stockage/` et de `public/uploads/`
   (sauf pour reprendre les données de la recette).
3. **Très important** : faites pointer le **dossier racine du site** vers le sous-dossier **`public/`**.
   Ainsi `.env`, `includes/`, `sql/`, `journaux/` et `stockage/` ne sont jamais accessibles depuis Internet.
4. Vérifiez que ces dossiers sont accessibles en écriture par le serveur web :
   `public/uploads/maillots`, `public/uploads/boutiques`, `stockage/paiements`, `stockage/litiges`, `journaux`.

## 3. Base de données

1. Créez la base (MariaDB 10.6+ ou MySQL 8, encodage `utf8mb4`).
2. Avec le compte d'**administration** de la base (phpMyAdmin), importez `sql/base_de_donnees.sql`
   (pour une base vierge, il contient tout, y compris les migrations).
   - Le fichier commence par `CREATE DATABASE … sama_maillot` / `USE sama_maillot` : si votre base porte un
     autre nom, remplacez ces deux lignes par le nom de votre base avant l'import.
3. Créez l'**utilisateur du site** avec uniquement les droits `SELECT, INSERT, UPDATE, DELETE`
   (Manager Infomaniak, ou `sql/utilisateur_production.sql`). C'est lui qui va dans `.env`.
4. Mise à jour d'un site déjà en ligne : importez seulement les nouveaux fichiers de `sql/migrations/`
   (dans l'ordre des numéros), toujours avec le compte d'administration.

## 4. Fichier `.env`

Copiez `.env.exemple` en `.env` **à la racine du projet** (à côté de `public/`, pas dedans) et remplissez :

| Variable | Recette | Production |
|---|---|---|
| `APP_ENV` | `recette` | `production` |
| `APP_URL` | `https://test.samamaillot.sn` | `https://samamaillot.sn` |
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` | base de recette | base de production (utilisateur limité) |
| `CLE_CHIFFREMENT` | **nouvelle** clé | **nouvelle** clé, gardée en lieu sûr |
| `SMTP_*` | boîte e-mail Infomaniak | boîte e-mail Infomaniak (ex. `noreply@…`) |
| `PAIEMENT_FOURNISSEUR` | `simulateur` | vide, ou l'agrégateur réel une fois branché |
| `SMS_ACTIF`, `VERIFICATION_SMS` | `0` | `0` (tant qu'il n'y a pas de fournisseur SMS) |

- Générer une clé : `php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"`.
- **Ne perdez jamais la clé de production** : sans elle, les codes de livraison déjà créés deviennent illisibles.
- Le simulateur de paiement est automatiquement **refusé** quand `APP_ENV=production`.

## 5. HTTPS

1. Activez le certificat **Let's Encrypt** (gratuit) pour le domaine.
2. Activez l'option « **forcer HTTPS** » du Manager. (À défaut : retirez les `#` des deux lignes
   « forcer HTTPS » dans `public/.htaccess`.)
3. Vérifiez : `http://…` doit rediriger vers `https://…`.

## 6. Tâche planifiée (cron)

Une seule ligne, **toutes les 5 minutes** :

```
*/5 * * * *  php /chemin/vers/sama-maillot/taches/toutes.php
```

Elle gère les paiements expirés, les commandes non confirmées, le déblocage des fonds, les abonnements
(rappels, grâce, masquage), la file des SMS et la purge des données anciennes. L'« État du système »
indique l'heure de sa dernière exécution.

## 7. Premier compte administrateur

1. Inscrivez-vous normalement sur le site (avec une adresse e-mail que vous contrôlez).
2. Dans phpMyAdmin, donnez-lui le rôle administrateur :
   ```sql
   UPDATE utilisateurs SET role = 'admin' WHERE email = 'votre@adresse.sn';
   ```
3. Reconnectez-vous : l'espace Administration est disponible.

## 8. Paramètres (Administration → Paramètres)

- [ ] Informations légales : raison sociale, forme juridique, adresse, NINEA, RCCM, directeur de la publication, récépissé CDP.
- [ ] Lien de paiement Wave Business (abonnements) et/ou numéro Wave de la plateforme.
- [ ] E-mail et WhatsApp du support.
- [ ] Prix de l'abonnement, durée de l'essai, délais (valeurs du cahier des charges par défaut).
- [ ] Catalogue : catégories, équipes et zones de livraison (Administration → Catalogue).

## 9. Site de recette (test)

- `APP_ENV=recette` : le site demande aux moteurs de recherche de ne pas l'indexer et `robots.txt` est fermé.
- Protégez-le en plus **par mot de passe** (protection de dossier dans le Manager Infomaniak).
  Si un agrégateur de paiement doit envoyer ses notifications à la recette, excluez le chemin
  `webhooks/` de cette protection.
- Utilisez des comptes de démonstration (client, vendeur, admin) et le simulateur de paiement.

## 10. Vérifications avant l'ouverture

- [ ] **Administration → État du système** : tout est vert.
- [ ] Aucun compte `@test.sama` en production (supprimés ou jamais importés).
- [ ] Parcours complet (§36) : inscription, recherche « Sénégal », commande à la livraison, commande via
      WhatsApp, livraison, réception, avis ; vendeur : boutique, maillot, commande, abonnement Wave ;
      admin : validation d'abonnement, signalement, réclamation.
- [ ] Mot de passe oublié : l'e-mail arrive (vérifier aussi le dossier « indésirables »).
- [ ] Test sur un vrai téléphone Android (360 px) en 3G/4G, et sur iPhone.
- [ ] Mesure **PageSpeed Insights** (https://pagespeed.web.dev) sur l'accueil, le catalogue et une fiche :
      performance mobile **80 ou plus** (§29.3).
- [ ] Partage d'une fiche maillot sur WhatsApp : l'aperçu (titre, prix, photo) s'affiche.
- [ ] Déclarer `https://…/sitemap.xml` dans Google Search Console.

## 11. Sauvegardes et maintenance (§33.3)

- Sauvegarde quotidienne automatique Infomaniak (vérifier qu'elle est active).
- **Chaque semaine** : export de la base **et** des dossiers `public/uploads/` et `stockage/`, conservé
  hors du serveur. Tester une restauration une fois par trimestre (sur la recette).
- Après toute modification du CSS ou du JavaScript : `php outils/minifier.php`.
- Toute modification passe d'abord par la recette.
- Mettre à jour PHP et les dépendances Composer régulièrement.
- Consulter `journaux/php-AAAA-MM.log` en cas de problème (jamais affiché aux visiteurs).
