# SAMA MAILLOT

Marketplace sénégalaise de vente et d'achat de maillots de football.
Référence fonctionnelle et technique : **cahier des charges v2.1**.

- PHP 8.2 · MySQL 8 / MariaDB · PDO · HTML5 · CSS3 · Bootstrap 5.3 (CDN) · JavaScript vanilla
- Application PHP traditionnelle (rendu côté serveur), sans framework, sans npm
- Devise : FCFA (montants entiers) · Fuseau horaire : Africa/Dakar

## Organisation des fichiers (§28)

```
sama-maillot/
├── public/                 ← SEULE racine web
│   ├── .htaccess           ← pages d'erreur, pas de listing, redirections, cache
│   ├── index.php           ← accueil
│   ├── css/sama.css        ← thèmes clair / sombre et styles du site
│   ├── js/theme-init.js    ← thème appliqué avant l'affichage (recopié inline dans le <head>)
│   ├── js/sama.js          ← bascule de thème, barre basse mobile
│   ├── images/            ← logo-clair/sombre.webp (en-tête, sans fond), favicon.svg (s'adapte au thème), apple-touch-icon.png
│   ├── pages/bientot.php   ← « Bientôt disponible » pour les pages pas encore créées
│   └── erreurs/            ← 403.php, 404.php, 500.php
├── includes/               ← hors web
│   ├── config.php          ← à inclure en haut de chaque page (charge tout le reste)
│   ├── base_de_donnees.php ← bdd() : connexion PDO unique
│   ├── fonctions.php       ← e(), format_fcfa(), url(), rediriger(), message_flash()...
│   ├── securite.php        ← session, en-têtes HTTP, CSRF
│   ├── entete.php
│   └── pied_de_page.php
├── journaux/               ← fichiers de logs (hors web)
├── sql/base_de_donnees.sql
├── .htaccess               ← sert public/ à l’adresse localhost/sama-maillot, protège le reste
├── .env.exemple            ← modèle de configuration (le vrai .env n'est jamais versionné)
└── .gitignore
```

## Installation sous XAMPP (Windows), pas à pas

### 1. Placer le projet

Le projet doit se trouver dans `C:\xampp\htdocs\sama-maillot`.

### 2. Créer le fichier `.env`

Copier `.env.exemple` en `.env` (même dossier), puis vérifier les valeurs.
Avec XAMPP, l'utilisateur MySQL par défaut est `root` sans mot de passe.

### 3. Créer la base de données

1. Ouvrir le panneau XAMPP et démarrer **Apache** et **MySQL**.
2. Aller sur http://localhost/phpmyadmin
3. Onglet **Importer** → choisir `sql/base_de_donnees.sql` → **Importer**.
   La base `sama_maillot` (utf8mb4) apparaît dans la liste de gauche.

### 4. Ouvrir le site

Aucune configuration d'Apache n'est nécessaire. Vérifier que `.env` contient :

```
APP_URL=http://localhost/sama-maillot
```

puis ouvrir **http://localhost/sama-maillot** dans n'importe quel navigateur.

Comment ça marche : le fichier `.htaccess` à la racine du projet redirige **en interne**
chaque adresse vers le dossier `public/`. Les fichiers sensibles (`.env`, `includes/`,
`journaux/`, `sql/`) restent inaccessibles depuis le navigateur (erreur 403 ou 404).

### En cas de problème

- **« localhost a refusé de se connecter »** : Apache est arrêté. Panneau XAMPP → **Start**
  sur la ligne Apache.
- **Redirection vers `localhost:8080`** : dans `C:\xampp\apache\conf\httpd.conf`, la ligne
  `ServerName` doit être `ServerName localhost:80` (même port que la ligne `Listen 80`),
  puis redémarrer Apache.
- **Page sans mise en forme** : `APP_URL` dans `.env` ne correspond pas à l'adresse tapée.

### En production (Infomaniak)

Faire pointer le domaine directement sur le dossier `public/` (§33.2) et mettre
`APP_URL=https://www.domaine.sn` dans `.env`. Les fichiers `.htaccess` fonctionnent
sans modification.

### (Facultatif) Compression et cache

Pour se rapprocher de la production, activer dans `C:\xampp\apache\conf\httpd.conf`
en retirant le `#` devant ces lignes, puis redémarrer Apache :

```apache
LoadModule deflate_module modules/mod_deflate.so
LoadModule expires_module modules/mod_expires.so
```

## Comptes utilisateurs (étape 2)

- **Mettre la base à jour** : réimporter `sql/base_de_donnees.sql` dans phpMyAdmin. Le script
  ne crée que les tables manquantes et ne supprime jamais de données.
- **Migrations** (`sql/migrations/`) : pour une base créée avant un changement de structure.
  À importer une seule fois, dans l'ordre des numéros. `001_table_roles.sql` remplace la
  colonne `utilisateurs.role` par la table `roles` (`utilisateurs.role_id`) ;
  `002_role_en_clair.sql` réécrit le rôle en clair (`utilisateurs.role` = `client`, `vendeur`
  ou `admin`), relié à `roles.code`.
- **Changer le rôle d'un compte** (phpMyAdmin, onglet SQL) :
  `UPDATE utilisateurs SET role = 'admin' WHERE email = 'adresse@exemple.sn';`
- **SMS et e-mails en local** : aucun fournisseur n'est encore branché. Avec `APP_ENV=local`,
  chaque SMS ou e-mail est **affiché à l'écran** dans un bandeau « Mode développement »
  (code SMS, lien de réinitialisation). Rien n'est écrit dans les journaux. En production,
  l'envoi échoue proprement tant que le fournisseur SMS et le SMTP ne sont pas configurés.
- **Devenir vendeur** : un client connecté qui ouvre `inscription/vendeur.php` garde son compte
  (même e-mail, même mot de passe, favoris) ; son rôle passe à `vendeur`. Un vendeur peut
  toujours acheter, sauf dans sa propre boutique (§5.1).
- **Vérification SMS des vendeurs** : désactivée tant qu'aucun fournisseur SMS n'est branché
  (`VERIFICATION_SMS=0` dans `.env`) ; la boutique et l'essai gratuit démarrent directement.
  Mettre `VERIFICATION_SMS=1` pour la réactiver.
- Pages : `inscription/` (client), `inscription/vendeur.php`, `connexion/`,
  `mot-de-passe/` (oubli), `compte/` (client), `vendeur/` (tableau de bord).

## Catalogue (étape 3)

- Pages : `catalogue/` (et `/catalogue/equipes-nationales`, `/catalogue/clubs`, `/catalogue/retro`),
  fiche `/maillot/<titre>-<id>`, boutique `/boutique/<nom>`, `compte/favoris.php`.
- La recherche (en-tête) affiche le catalogue filtré : insensible aux accents et à la casse.
- **Jeu de données de test** (développement et recette uniquement, jamais en production) :
  importer `sql/donnees_test.sql` → 2 boutiques « (TEST) », 20 maillots, comptes en `@test.sama`
  (mot de passe `Test2026`). Pour tout retirer : `sql/supprimer_donnees_test.sql`.

## Espace vendeur (étape 4 — lot 3)

- Pages : `vendeur/` (tableau de bord), `vendeur/maillots.php`, `vendeur/maillot.php`
  (créer / modifier), `vendeur/boutique.php` (logo, bannière, numéros), `vendeur/livraison.php`.
- **Extension PHP GD obligatoire** (photos) : dans `C:\xampp\php\php.ini`, la ligne
  `extension=gd` ne doit pas commencer par `;`. Redémarrer Apache après modification.
- Photos : envoyées dans `public/uploads/` (ré-encodées en WebP, métadonnées supprimées).
  Le fichier `public/uploads/.htaccess` interdit toute exécution de script : ne pas le supprimer.
- Base existante : importer `sql/migrations/003_espace_vendeur.sql` (zones et tarifs de livraison).
- Tailles proposées : XS à XXXL uniquement (pas de tailles enfants).
- Photos : 1 obligatoire pour publier, jusqu'à 8 par maillot (`PHOTOS_PAR_MAILLOT_MAX`), affichées en carrousel
  sur la fiche du maillot. Le vendeur peut les ajouter en plusieurs fois.

## Panier et commandes (étape 5 — lot 4)

- Base existante : importer `sql/migrations/004_panier_commandes.sql` (paniers, adresses, commandes,
  lignes, historique). Ajouter dans `.env` une clé `CLE_CHIFFREMENT` (voir `.env.exemple`) :
  elle chiffre les codes de livraison.
- Pages : `panier/` (panier invité gardé 7 jours, fusionné à la connexion), `panier/commander.php`
  (adresse → récapitulatif → confirmation), `compte/commandes.php` et `compte/commande.php`
  (suivi, code de livraison, annulation, réception), `vendeur/commandes.php` et
  `vendeur/commande.php` (confirmer, expédier, code de livraison, livrée sans code, client
  injoignable), `commande/whatsapp.php` (commande via WhatsApp).
- Paiement : à la livraison uniquement pour l'instant (le paiement en ligne arrive au lot 8).
- **Pas de code de livraison en paiement à la livraison** (décision du porteur du projet) : le
  vendeur marque la commande livrée, le client confirme la réception (ou « Reçue » après 48 h).
  Le code ne servira qu'au paiement en ligne. Base existante : importer
  `sql/migrations/008_code_livraison_facultatif.sql`.
- Une commande par boutique ; stock réservé à la commande et rendu à l'annulation ; seules les
  transitions de statut du §12.4 sont acceptées, chacune est historisée.
- **Tâches planifiées** (dossier `taches/`, jamais accessible depuis le navigateur) :
  `commandes_expirees.php` toutes les 5 min, `debloquer_fonds.php` toutes les 15 min.
  En local, elles tournent aussi automatiquement à l'ouverture des pages de commandes.
  Lancement à la main : `C:\xampp\php\php.exe C:\xampp\htdocs\sama-maillot\taches\commandes_expirees.php`

## Notifications (§22.1)

- Base existante : importer `sql/migrations/006_frais_a_convenir.sql` puis `007_notifications.sql`.
- Cloche de l'en-tête avec le nombre de notifications non lues ; page `compte/notifications.php`
  (ouvrir une notification la marque comme lue, « Tout marquer comme lu »).
- Vendeur : nouvelle commande, annulation par le client, réception confirmée, rappel « 12 h
  restantes ». Client : commande enregistrée, confirmée, expédiée, livrée, annulée.

## Pages À propos et Contact

- `pages/a-propos.php` : présentation, engagements ; les chiffres affichés (maillots, boutiques,
  équipes) sont lus en base, jamais inventés.
- `pages/contact.php` : formulaire enregistré dans `messages_contact` (base existante : importer
  `sql/migrations/005_messages_contact.sql`). Anti-spam : champ piège, délai minimal, 3 messages
  par heure et par IP. Paramètres à renseigner (table `parametres`) : `numero_whatsapp_support`
  (affiche le bouton WhatsApp du support) et `email_support` (copie des messages par e-mail).

## Administration (étape 6 — lot 5, partie 1)

- Accès : compte au rôle `admin` (voir « Changer le rôle d'un compte ») ; après connexion, arrivée sur `admin/`.
- Pages : `admin/` (tableau de bord, chiffres réels, files prioritaires), `admin/utilisateurs.php` et
  `admin/utilisateur.php` (désactiver, suspendre, réactiver un compte ; suspendre ou réactiver une
  boutique), `admin/maillots.php` (masquer, supprimer, remettre en ligne), `admin/commandes.php`
  (consultation et historique), `admin/journal.php` (journal d'audit filtrable).
- Chaque action exige un motif, prévient la personne concernée et est journalisée.
- Boutique suspendue : boutique et maillots masqués, plus de nouvelles commandes, mais le vendeur
  garde l'accès à ses commandes en cours pour les livrer (§21.2).

## Administration, partie 2 (lot 5) : catalogue, signalements, paramètres

- Base existante : importer `sql/migrations/009_signalements.sql`.
- `admin/catalogue.php` : catégories, équipes, zones de livraison (ajouter, modifier, activer /
  désactiver ; pas de suppression pour garder l'historique).
- **Signalements** : seul un client ayant passé une commande non annulée **chez ce vendeur** peut
  signaler ce vendeur ou l'un de ses maillots (`signalement/?boutique=…` ou `?maillot=…`). Le lien
  n'apparaît que pour ces clients. 3 clients différents en 90 jours → boutique suspendue
  automatiquement ; le vendeur conteste depuis son tableau de bord ; l'admin retient, classe
  (infondé) ou lève la suspension dans `admin/signalements.php`.
- `admin/parametres.php` : paramètres de l'annexe A, contrôlés et journalisés. Ceux qui servent à une
  étape à venir sont signalés sur la page.

## Abonnements, SMS et e-mails (lot 6)

- Base existante : importer `sql/migrations/010_abonnements.sql`. Dépendances : `composer install`
  (PHPMailer, dans `vendor/`, hors du site public).
- **Abonnements** : essai de 14 jours, puis `abonnement_prix_fcfa` par mois. État calculé à partir
  des dates en base : essai, actif, paiement en vérification, grâce (3 jours, ajout bloqué),
  expiré (boutique masquée, commandes en cours toujours accessibles).
- **Renouvellement** (`vendeur/abonnement.php`) : 1, 3 ou 6 mois payés par Wave au numéro
  `numero_wave_plateforme` (à renseigner dans Administration → Paramètres), référence Wave unique,
  capture d'écran stockée dans `stockage/paiements/` (hors du site public). Validation ou refus
  motivé dans `admin/abonnements.php`.
- **Rappels** : J-2, J0 à J+2 (quotidien), masquage à J+3, puis chaque semaine pendant un mois.
- **SMS désactivés** (décision du porteur du projet, `SMS_ACTIF=0` dans `.env`) : aucune SMS n'est
  envoyé ; **toutes les notifications partent par e-mail**, et « mot de passe oublié » envoie
  toujours un lien par e-mail (même si la personne saisit son téléphone). Pour les rallumer :
  `SMS_ACTIF=1` + un fournisseur (ci-dessous).
- **SMS (si réactivés)** : file d'attente `envois_sms` (texte effacé après envoi, rien de non urgent entre 22h et
  7h). `.env` : `SMS_FOURNISSEUR=orange` + `ORANGE_AUTORISATION` + `ORANGE_EXPEDITEUR` (connecteur à
  tester avec un vrai compte Orange). Vide = SMS simulés en local.
- **E-mails** : PHPMailer + SMTP Infomaniak (`SMTP_HOTE`, `SMTP_PORT`, `SMTP_CHIFFREMENT`,
  `SMTP_UTILISATEUR`, `SMTP_MOT_DE_PASSE`, `SMTP_EXPEDITEUR`). Copie par e-mail des notifications
  importantes. Vide = e-mails simulés en local.
- **Tâches planifiées** (cron Infomaniak) : `taches/envoyer_sms.php` chaque minute,
  `taches/commandes_expirees.php` toutes les 5 min, `taches/debloquer_fonds.php` toutes les 15 min,
  `taches/abonnements.php` chaque jour à 8h. Le site les lance aussi lui-même au plus toutes les
  10 minutes (utile en local, sans cron).

## Avis et réclamations (lot 7)

- Base existante : importer `sql/migrations/011_avis_litiges.sql`.
- **Avis publics** : quand une commande passe « Reçue », le client est invité à noter le vendeur
  (1 à 5 étoiles, commentaire facultatif de 500 caractères) dans les 30 jours (`avis_delai_jours`).
  Un avis par commande, modifiable une fois sous 7 jours. Il s'affiche sur la page de la boutique
  (prénom + initiale, « Achat vérifié »), avec la réponse publique du vendeur (`vendeur/avis.php`).
  La note du vendeur (cartes, fiche, boutique) = moyenne des avis visibles, « Nouveau vendeur » sous
  3 avis. L'administration masque un avis abusif (`admin/avis.php`, motif journalisé).
- **Réclamations** (paiement à la livraison = médiation) : le client ouvre une réclamation sur une
  commande expédiée, livrée ou reçue depuis moins de 48 h (`compte/reclamation.php`) : motif,
  description, jusqu'à 3 photos privées (stockées dans `stockage/litiges/`, visibles seulement par
  le client, le vendeur et l'administration). La commande passe « En litige ». Le vendeur a 48 h
  pour répondre (`litige_reponse_vendeur_heures`) ; fil d'échanges à trois. Le client peut clore à
  l'amiable ; sinon l'administration tranche (`admin/litiges.php`) : **fondée** = signalement
  retenu du vendeur (suspension automatique au seuil), **non fondée**. La commande est alors terminée.

## Paiement en ligne, portefeuille et versements (lot 8)

- Base existante : importer `sql/migrations/012_paiement_en_ligne.sql`.
- **Fournisseur** : `PAIEMENT_FOURNISSEUR` dans `.env`. `simulateur` = simulateur de TEST (bandeau
  « MODE TEST », refusé en production) ; vide = paiement en ligne affiché « bientôt ». Un agrégateur
  réel (PayTech, PayDunya…) s'ajoute dans `includes/metier/paiements.php` (3 fonctions :
  initier, vérifier la notification, consulter l'API) dès que le compte marchand existe.
- **Parcours** : le client choisit « Payer en ligne » → commandes « en attente de paiement »
  (stock réservé 30 min, `paiement_en_ligne_minutes`) → page de l'agrégateur → notification signée
  (`public/webhooks/paiement.php`), vérifiée puis contrôlée par l'API → commandes « à confirmer »,
  vendeur prévenu, code de livraison visible par le client. Échec, annulation ou expiration →
  commandes annulées, stock remis. Montant différent → commandes bloquées, admin alerté.
  Impossible avec des frais de livraison « à convenir ».
- **Séquestre / portefeuille** (`vendeur/portefeuille.php`) : registre `mouvements_portefeuille`
  (uniquement des ajouts, soldes toujours recalculés) : en attente → disponible à la réception
  (24 h après une livraison avec code, 48 h sans code) ; bloqué en cas de réclamation.
- **Versements** (`admin/paiements.php`) : mardi et vendredi, à partir de 1 000 FCFA ; l'admin fait
  le virement Wave puis saisit la référence. **Remboursements** : commande payée puis annulée, ou
  réclamation fondée → remboursement à effectuer, puis référence saisie (commande « Remboursée »).
- **Abonnements** : lien Wave Business de la plateforme (`lien_paiement_wave`, Administration →
  Paramètres) ; le montant (`?amount=`) est ajouté selon la durée choisie. Le vendeur saisit ensuite
  l'identifiant Wave, vérifié par l'admin (le lien n'est pas une preuve de paiement).
- **Tâche planifiée** : `taches/paiements.php` toutes les 5 minutes (paiements expirés ou à revérifier).

## Recette et lancement (lot 9) — partie 1 : contenus et SEO

- Base existante : importer `sql/migrations/013_informations_legales.sql`.
- **Pages** (`public/pages/`) : CGU, CGV (avec le mandat d'encaissement), confidentialité (loi n° 2008-12),
  mentions légales, politique vendeur, FAQ, comment ça marche. Montants et délais lus dans les paramètres.
- **Informations de l'éditeur** (raison sociale, NINEA, RCCM, adresse, récépissé CDP) : Administration →
  Paramètres → « Informations légales ». Tant qu'elles manquent, les pages affichent « à compléter ».
  Textes à faire valider par un juriste avant la production (§25).
- **Suppression de compte** (`compte/supprimer.php`) : anonymisation, refusée tant qu'une commande,
  un remboursement ou de l'argent du portefeuille est en cours.
- **SEO** : `/sitemap.xml` et `/robots.txt` générés par PHP ; adresse canonique et image de partage
  (Open Graph) sur les fiches et les boutiques ; données structurées Product et FAQPage.
  Hors production (`APP_ENV` ≠ `production`) : en-tête `X-Robots-Tag: noindex` et robots.txt fermé.
- **Purge automatique** : tentatives de connexion et adresses IP du journal de plus de 12 mois.

## Recette et lancement (lot 9) — partie 2 : sécurité (§24, §32.2)

Audit du code (toutes les pages) puis attaques réelles sur le site local, comptes de test uniquement.

- **Vérifié sans faille** : injection SQL (y compris à l'aveugle par temporisation), XSS réfléchi et stocké
  (avis, réponses, réclamations, signalements, contact, adresses), CSRF (sans jeton, faux jeton, jeton
  d'une autre session), accès d'un vendeur ou d'un client aux données d'un autre, actions admin par un
  non-admin, fichiers piégés (PHP renommé en .jpg, SVG, image polyglotte), prix et quantités manipulés,
  empreinte falsifiée, double soumission, force brute (connexion, code de livraison), fixation de session,
  notification de paiement falsifiée, fichiers sensibles (.env, includes/, sql/, journaux/, stockage/),
  erreurs sans trace technique.
- **Corrigé pendant la recette** : version de PHP masquée (`X-Powered-By`) ; captures d'abonnement
  désormais ré-encodées en WebP comme les autres images ; au plus 3 liens « mot de passe oublié » par
  compte et par heure (anti-inondation) ; alerte de l'administration quand un code de livraison est bloqué.
- **À la mise en production** : `expose_php = Off` dans la configuration PHP ; vérifier que
  `REMOTE_ADDR` est bien l'adresse du visiteur (sinon le blocage par IP, 20 échecs en 15 min,
  toucherait tout le monde).

## Recette et lancement (lot 9) — partie 3 : performance, mise en production, tests

- **Mise en production** : suivre **`docs/MISE_EN_PRODUCTION.md`** (Infomaniak, pas à pas), puis
  vérifier **Administration → État du système** (liste de contrôle calculée en direct : PHP, HTTPS,
  erreurs masquées, droits MySQL, `.env` protégé, cron, SMTP, paiements, informations légales…).
- **Une seule tâche cron** toutes les 5 minutes : `php taches/toutes.php`.
- **Utilisateur MySQL limité** (SELECT, INSERT, UPDATE, DELETE) : `sql/utilisateur_production.sql`.
- **Poids des pages** (premier chargement, compressé) : accueil, catalogue, fiche, boutique ≈ 330–345 Ko
  (objectif < 1 Mo, §29.3). Score Lighthouse à mesurer après la mise en ligne (PageSpeed Insights).
- **CSS/JS minifiés** : `php outils/minifier.php` après chaque modification de `sama.css` ou `sama.js`
  (les versions `.min` ne sont servies que si elles sont à jour).
- **Mobile** : barre « Ajouter au panier » fixe en bas de la fiche maillot (§29.2).
- **Tests automatisés** (§32.4) : `vendor/bin/phpunit` — 32 tests sur les commandes (totaux, stock,
  dernière pièce, statuts), le paiement et le portefeuille (double notification, montant incorrect,
  déblocage 24 h, litige, remboursement, versement), les abonnements (essai, J-2, grâce, masquage,
  renouvellement) et la confiance (signalements, suspension automatique, avis). Base séparée
  `sama_maillot_test`, recréée à chaque lancement : la base du site n'est jamais touchée.

## Commander sans compte

- Base existante : importer `sql/migrations/014_commande_sans_compte.sql`.
- Le visiteur commande **sans s'inscrire** : nom complet (obligatoire), téléphone, adresse et e-mail
  facultatif. Un « compte invité » (sans mot de passe, impossible de s'y connecter) rattache la commande.
- **Suivi par lien secret** (`commande/suivi.php?c=…&j=…`, jeton de 64 caractères chiffré en base),
  affiché après la commande et envoyé par e-mail s'il en a donné un : statut, annulation, réception,
  WhatsApp du vendeur, paiement en ligne en attente.
- **Anti-abus** : 3 commandes à la livraison en cours par numéro de téléphone, refus à la livraison
  comptés par numéro, au plus 5 commandes sans compte par heure et par adresse IP.
- **Devenir un vrai compte** : « Mot de passe oublié » avec son e-mail ; le compte invité devient un compte
  normal avec ses commandes (avis et réclamations restent réservés aux comptes).

## Règles de développement

- Chaque page publique commence par `require __DIR__ . '/../includes/config.php';`
- Tout affichage de donnée passe par `e()`. Tout formulaire POST contient `<?= champ_csrf() ?>`
  et son traitement commence par `verifier_csrf()`, puis se termine par `rediriger()`.
- Toute requête SQL passe par `bdd()->prepare(...)` (jamais de variable dans le texte SQL).
- Les couleurs viennent **uniquement** des variables CSS de `public/css/sama.css` (`--sm-*`).
- Lien vers une page : `url_page('cle')` (tableau `PAGES` de `includes/config.php`).
  Tant que la page n'existe pas, le lien mène à « Bientôt disponible ».
- En production : `APP_ENV=production` dans `.env` (erreurs masquées et écrites dans `journaux/`).
