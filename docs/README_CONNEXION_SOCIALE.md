# Connexion avec Google et Facebook — guide de configuration

Ce guide explique, pas à pas, comment activer les boutons « Continuer avec Google » et
« Continuer avec Facebook » de SAMA MAILLOT.

Tant qu'un fournisseur n'est pas configuré, rien ne change sur le site :

- son bouton n'apparaît pas ;
- ses pages répondent « page introuvable » (404).

## Sommaire

1. [Ce qu'il faut savoir avant de commencer](#1-ce-quil-faut-savoir-avant-de-commencer)
2. [Installer les bibliothèques](#2-installer-les-bibliothèques)
3. [Mettre à jour la base de données](#3-mettre-à-jour-la-base-de-données)
4. [Google : Google Cloud Console](#4-google--google-cloud-console)
5. [Facebook : Meta for Developers](#5-facebook--meta-for-developers)
6. [Activer ou désactiver un fournisseur (.env)](#6-activer-ou-désactiver-un-fournisseur-env)
7. [Tester en local, puis sur la recette](#7-tester-en-local-puis-sur-la-recette)
8. [Liste de vérification](#8-liste-de-vérification)

---

## 1. Ce qu'il faut savoir avant de commencer

**Comment ça marche.** Tout se passe côté serveur, sans aucun script Google ni Facebook dans les pages :

1. Le visiteur clique sur « Continuer avec Google » (ou Facebook).
2. Il est envoyé chez Google (ou Facebook), qui lui demande d'accepter.
3. Google renvoie le visiteur sur notre page de retour avec un **code à usage unique**.
4. Notre serveur échange ce code et **vérifie l'identité** :
   - Google : signature de l'`id_token` ;
   - Facebook : contrôle `/debug_token` et `appsecret_proof`.
5. Le compte est ouvert, créé ou lié.

**Ce que le site garde.** Uniquement l'identifiant que Google ou Facebook attribue à la
personne pour notre application, son prénom, son nom et son e-mail. Aucun jeton d'accès
n'est conservé : ni en base, ni dans les journaux.

**Un compte est toujours retrouvé par (fournisseur + identifiant).** L'e-mail seul ne suffit
jamais. Si un compte SAMA MAILLOT existe déjà avec la même adresse, la personne doit le
prouver avant toute liaison :

- avec son mot de passe ;
- ou, pour un compte sans mot de passe, par un lien reçu par e-mail (ou un code SMS si les
  SMS sont actifs).

**Règles fixes :**

- Les comptes **administrateur** ne peuvent pas utiliser Google ni Facebook : ils se
  connectent par mot de passe.
- Un **vendeur** peut les utiliser. La vérification de son téléphone reste exigée à l'entrée
  de l'espace vendeur (si `VERIFICATION_SMS=1`).
- Un compte **suspendu ou désactivé** est refusé.
- Au plus **10 connexions** Google / Facebook démarrées par adresse IP et par tranche de
  15 minutes.

**Les adresses de retour.** Il y en a une par fournisseur, à déclarer à l'identique chez
Google et chez Meta (`APP_URL` + chemin) :

| Environnement | Google | Facebook |
|---|---|---|
| Local (XAMPP) | `http://localhost/sama-maillot/connexion/google-retour.php` | `http://localhost/sama-maillot/connexion/facebook-retour.php` |
| Recette | `https://recette.votre-domaine.sn/connexion/google-retour.php` | `https://recette.votre-domaine.sn/connexion/facebook-retour.php` |
| Production | `https://www.votre-domaine.sn/connexion/google-retour.php` | `https://www.votre-domaine.sn/connexion/facebook-retour.php` |

Remplacez `votre-domaine.sn` par le vrai domaine. Une seule lettre de différence
(`http`/`https`, `www`, `/` final) et Google ou Facebook refusent la connexion.

---

## 2. Installer les bibliothèques

À la racine du projet :

```bash
composer require league/oauth2-google league/oauth2-facebook
```

C'est déjà fait dans le dépôt : `composer.json` les contient. Sur un serveur neuf, il suffit de :

```bash
composer install --no-dev
```

Le dossier `vendor/` n'est pas versionné : il faut l'installer, ou l'envoyer, sur chaque serveur.

**Certificats (XAMPP sous Windows).** Les appels vers Google et Facebook vérifient toujours le
certificat HTTPS. XAMPP est déjà réglé (`curl.cainfo` et `openssl.cafile` pointent vers
`C:\xampp\php\cacert.pem`). Si une connexion échoue avec « SSL certificate problem », vérifiez
ces deux lignes dans `php.ini`, puis redémarrez Apache.

---

## 3. Mettre à jour la base de données

Sur une base existante, importer **une fois** `sql/migrations/015_connexion_sociale.sql` (phpMyAdmin → Importer).

La migration :

- crée les tables `connexions_sociales`, `demandes_suppression_facebook` et `tentatives_connexion_sociale` ;
- ajoute la colonne `utilisateurs.email_verifie` ;
- complète les types de `codes_verification`.

Une base neuve créée avec `sql/base_de_donnees.sql` contient déjà tout.

---

## 4. Google : Google Cloud Console

Adresse : <https://console.cloud.google.com/>, avec le compte Google de l'entreprise.

### 4.1 Créer le projet

1. En haut, cliquez sur le sélecteur de projet, puis **Nouveau projet**.
2. Nom : `SAMA MAILLOT`. Cliquez sur **Créer**, puis sélectionnez ce projet.

### 4.2 Écran de consentement OAuth

Menu **API et services → Écran de consentement OAuth** (parfois « Google Auth Platform → Branding »).

1. Type d'utilisateurs : **Externe**, puis **Créer**.
2. Renseignez :
   - **Nom de l'application** : `SAMA MAILLOT` ;
   - **Adresse e-mail d'assistance** : l'adresse du support ;
   - **Logo** : le logo carré du site (120 × 120 px). Facultatif. Avec un logo, Google fait vérifier
     la marque, ce qui prend quelques jours : vous pouvez l'ajouter plus tard ;
   - **Page d'accueil** : `https://www.votre-domaine.sn/` ;
   - **Règles de confidentialité** : `https://www.votre-domaine.sn/pages/confidentialite.php` ;
   - **Conditions d'utilisation** : `https://www.votre-domaine.sn/pages/cgu.php` ;
   - **Domaines autorisés** : `votre-domaine.sn` ;
   - **Coordonnées du développeur** : votre e-mail.
3. **Champs d'application (scopes)** : ajoutez seulement `openid`, `.../auth/userinfo.email` et
   `.../auth/userinfo.profile`. Ce sont des scopes « non sensibles » : pas d'examen par Google.
4. **Utilisateurs test** : tant que l'application est « En test », ajoutez les adresses Gmail qui
   ont le droit d'essayer (la vôtre, celles de l'équipe).

### 4.3 Identifiants « Application Web »

Menu **API et services → Identifiants → Créer des identifiants → ID client OAuth**.

1. Type d'application : **Application Web**. Nom : `SAMA MAILLOT — site`.
2. **Origines JavaScript autorisées** : laissez vide (le site n'utilise aucun script Google).
3. **URI de redirection autorisés**, ajoutez les trois adresses Google du tableau du §1 :
   - local ;
   - recette ;
   - production.
4. Cliquez sur **Créer**. Copiez l'**ID client** et le **Code secret du client**, à placer dans
   `.env` (§6). Ne les envoyez jamais par WhatsApp ni par e-mail, et ne les mettez jamais sur GitHub.

### 4.4 Passer en production

Quand les tests sont bons : **Écran de consentement → Publier l'application** (statut
« En production »). Sans cette étape, seuls les utilisateurs test peuvent se connecter.

---

## 5. Facebook : Meta for Developers

Adresse : <https://developers.facebook.com/>, avec le compte Facebook de l'administrateur de la page de l'entreprise.

### 5.1 Créer l'application

1. **Mes applications → Créer une application**.
2. Cas d'utilisation : **« Authentifier et demander des données aux utilisateurs avec Facebook Login »**.
   Si on vous demande un type d'application, choisissez **Consommateur**.
3. Nom : `SAMA MAILLOT`, e-mail de contact : celui du support. Cliquez sur **Créer**.

### 5.2 Produit « Facebook Login »

1. Dans le tableau de bord, ajoutez (ou personnalisez) **Facebook Login**.
2. **Autorisations** : seulement `public_profile` et `email`. N'ajoutez rien d'autre : pas
   d'amis, pas de publication.
3. **Facebook Login → Paramètres** :
   - **Connexion OAuth client** : Oui ;
   - **Connexion OAuth Web** : Oui ;
   - **Forcer HTTPS** : Oui (`http://localhost` reste accepté pour les essais en local) ;
   - **Utiliser le mode strict pour les URI de redirection** : Oui ;
   - **URI de redirection OAuth valides** : les trois adresses Facebook du tableau du §1 ;
   - **Connexion depuis des appareils** : Non.
   - Enregistrez.

### 5.3 Paramètres de base

Menu **Paramètres de l'application → Général** :

- **Icône de l'application** : 1024 × 1024 px, le logo du site ;
- **Catégorie** : « Shopping » (ou « Commerce ») ;
- **URL de la politique de confidentialité** : `https://www.votre-domaine.sn/pages/confidentialite.php` ;
- **URL des conditions de service** : `https://www.votre-domaine.sn/pages/cgu.php` ;
- **Suppression des données utilisateur** : choisissez **URL de rappel de suppression des données**
  et indiquez `https://www.votre-domaine.sn/facebook/suppression-donnees.php` ;
- **Domaines de l'application** : `votre-domaine.sn`.

Copiez l'**ID de l'application** et la **Clé secrète** (bouton « Afficher »), à placer dans `.env` (§6).

### 5.4 Testeurs (mode développement)

Tant que l'application est en mode **Développement**, seules les personnes ajoutées dans
**Rôles de l'application → Rôles** (administrateurs, développeurs, testeurs) peuvent se connecter.
Ajoutez-y les comptes Facebook de l'équipe.

### 5.5 Passer en mode « Live »

Quand les tests sont bons, passez le bouton en haut de la page de **Développement** à **Live**.
Meta vérifie alors que l'icône, la catégorie, la politique de confidentialité et l'URL de
suppression des données sont renseignées.

`public_profile` et `email` font partie des autorisations par défaut de Facebook Login : elles ne
demandent en principe **pas d'examen de l'application** par Meta. Si Meta en demande un, c'est
qu'une autre autorisation a été ajoutée : retirez-la.

**Version de l'API.** Le site appelle une version épinglée de l'API Graph (`FACEBOOK_VERSION_API`,
par défaut `v21.0`). Meta retire les anciennes versions environ deux ans après leur sortie et
prévient par e-mail. Il suffit alors de changer cette valeur dans `.env`, sans toucher au code.

---

## 6. Activer ou désactiver un fournisseur (.env)

Dans le fichier `.env` du serveur (jamais dans le code, jamais sur GitHub) :

```ini
GOOGLE_ACTIF=true
GOOGLE_CLIENT_ID=123456789-abcdef.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-...
GOOGLE_URL_RETOUR=

FACEBOOK_ACTIF=true
FACEBOOK_APP_ID=1234567890123456
FACEBOOK_APP_SECRET=0123456789abcdef0123456789abcdef
FACEBOOK_VERSION_API=v21.0
FACEBOOK_URL_RETOUR=
```

- `..._ACTIF=false` (ou un identifiant vide) : bouton masqué, pages en 404, aucune erreur visible.
- `..._URL_RETOUR` vide : adresse calculée à partir de `APP_URL`. Ne la remplissez que si elle doit
  être différente, et alors exactement comme chez Google / Meta.
- Après un changement de `.env`, rien à redémarrer : la valeur est lue à chaque page.

---

## 7. Tester en local, puis sur la recette

### En local (XAMPP)

1. `APP_URL=http://localhost/sama-maillot` dans `.env`.
2. Google : votre adresse Gmail doit être dans les **utilisateurs test** (§4.2). Facebook : votre
   compte doit avoir un **rôle** dans l'application (§5.4).
3. Ouvrez `http://localhost/sama-maillot/connexion/` : les deux boutons apparaissent.
4. Le rappel de suppression Facebook ne peut pas être appelé par Meta en local (Meta ne voit pas
   votre ordinateur). Il est testé par les tests automatiques
   (`php vendor/bin/phpunit --filter ConnexionSociale`), puis sur la recette.

### Sur la recette (Infomaniak, HTTPS)

1. `.env` de la recette : `APP_URL=https://recette.votre-domaine.sn` et les mêmes identifiants
   (ou une deuxième application de test).
2. Vérifiez que les adresses de recette figurent bien chez Google et chez Meta.
3. Déroulez la liste de vérification ci-dessous.

En cas de problème, le détail technique est dans `journaux/php-AAAA-MM.log`, sans aucun jeton ni
secret. Le visiteur, lui, ne voit qu'un message simple.

---

## 8. Liste de vérification

Les tests automatiques (`tests/ConnexionSocialeTest.php`, 28 tests) couvrent déjà :

- la signature et les contrôles de l'`id_token` ;
- `/debug_token`, `appsecret_proof` et PKCE ;
- le state ;
- la signature Meta ;
- les règles A / B / C ;
- la déliaison ;
- la suppression ;
- la redirection.

À refaire à la main sur la recette, avec de vrais comptes :

### Google

- [ ] Nouveau compte : page « Bienvenue », CGU obligatoires, compte créé (client, sans mot de passe)
- [ ] Compte déjà lié : connexion directe
- [ ] E-mail déjà inscrit : page « Lier votre compte », mot de passe demandé, puis liaison
- [ ] Annulation chez Google : retour à la connexion avec « Connexion Google annulée. »
- [ ] State falsifié (modifier `state=` dans l'adresse de retour) : refus, message simple
- [ ] Adresse Gmail non vérifiée : refus avec un message clair

### Facebook

- [ ] Nouveau compte
- [ ] Compte déjà lié
- [ ] E-mail déjà inscrit : liaison par mot de passe
- [ ] Permission e-mail refusée : bouton « Autoriser l'accès à mon e-mail », nouvelle demande acceptée
- [ ] Compte Facebook sans e-mail : saisie de l'e-mail, code à 6 chiffres, puis création
- [ ] Annulation : « Connexion Facebook annulée. »
- [ ] State falsifié : refus
- [ ] Jeton d'une autre application refusé par `/debug_token` (couvert par les tests automatiques)

### Suppression des données Facebook

- [ ] Dans Facebook, retirer l'application et demander la suppression : la page de statut s'affiche avec le code
- [ ] Signature falsifiée : réponse 400 (tests automatiques)
- [ ] Page de statut : accessible par le code, sans aucune donnée personnelle

### Règles

- [ ] Compte suspendu : refusé
- [ ] Administrateur : refusé (« se connectent uniquement avec leur mot de passe »)
- [ ] Vendeur sans téléphone vérifié (`VERIFICATION_SMS=1`) : bloqué à l'entrée de l'espace vendeur
- [ ] Panier rempli avant la connexion : conservé après
- [ ] Page demandée avant la connexion : on y revient après
- [ ] `connexion/google.php?redirection=//site-pirate.com` : aucune redirection externe
- [ ] Mon compte → « Google et Facebook » : délier le dernier moyen de connexion est refusé, avec explication
- [ ] Compte sans mot de passe : « Définir un mot de passe » envoie un lien par e-mail

### Affichage

- [ ] Boutons corrects en mode clair
- [ ] Boutons corrects en mode sombre
- [ ] Boutons corrects sur mobile 360 px : deux boutons côte à côte, même taille, icône centrée
- [ ] Au clic : bouton désactivé avec indicateur de chargement (pas de double clic)
- [ ] Fournisseur désactivé dans `.env` : bouton absent, pages en 404
