-- =============================================================================
-- SAMA MAILLOT — base de données complète (structure + données de démonstration)
-- Export du 16/10/2026 pour le dépôt de fin de formation.
--
-- Import : phpMyAdmin → onglet « Importer » → choisir ce fichier → « Importer ».
-- La base « sama_maillot » est créée automatiquement.
-- Comptes de test (mot de passe Test2026) : voir README.md.
-- =============================================================================

CREATE DATABASE IF NOT EXISTS sama_maillot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sama_maillot;

-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: sama_maillot_depot
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `abonnements`
--

DROP TABLE IF EXISTS `abonnements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `abonnements` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `boutique_id` int(10) unsigned NOT NULL,
  `paiement_id` int(10) unsigned DEFAULT NULL,
  `debut_le` datetime NOT NULL,
  `fin_le` datetime NOT NULL,
  `mois` tinyint(3) unsigned NOT NULL,
  `montant` int(10) unsigned NOT NULL,
  `cree_le` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_abonnements_boutique` (`boutique_id`,`fin_le`),
  KEY `fk_abonnements_paiement` (`paiement_id`),
  CONSTRAINT `fk_abonnements_boutique` FOREIGN KEY (`boutique_id`) REFERENCES `boutiques` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_abonnements_paiement` FOREIGN KEY (`paiement_id`) REFERENCES `paiements_abonnement` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `abonnements`
--

LOCK TABLES `abonnements` WRITE;
/*!40000 ALTER TABLE `abonnements` DISABLE KEYS */;
/*!40000 ALTER TABLE `abonnements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `adresses`
--

DROP TABLE IF EXISTS `adresses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `adresses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `utilisateur_id` int(10) unsigned NOT NULL,
  `zone_id` int(10) unsigned NOT NULL,
  `ville` varchar(60) NOT NULL,
  `quartier` varchar(80) NOT NULL,
  `repere` varchar(160) NOT NULL DEFAULT '' COMMENT 'Point de repère (près de la pharmacie…)',
  `telephone` varchar(13) NOT NULL,
  `cree_le` datetime NOT NULL DEFAULT current_timestamp(),
  `utilisee_le` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_adresses_utilisateur` (`utilisateur_id`,`utilisee_le`),
  KEY `fk_adresses_zone` (`zone_id`),
  CONSTRAINT `fk_adresses_utilisateur` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_adresses_zone` FOREIGN KEY (`zone_id`) REFERENCES `zones_livraison` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `adresses`
--

LOCK TABLES `adresses` WRITE;
/*!40000 ALTER TABLE `adresses` DISABLE KEYS */;
INSERT INTO `adresses` VALUES (10,23,2,'tivaoune peul','apix','','+221706464785','2026-10-05 20:51:49','2026-10-07 08:29:51'),(23,14,1,'Dakar','Mermoz','','+221771234567','2026-10-06 18:13:29','2026-10-06 18:13:29'),(24,14,1,'Dakar','Mermoz','','+221771234567','2026-10-06 18:13:39','2026-10-06 18:13:39'),(25,14,1,'Dakar','Mermoz','','+221771234567','2026-10-06 18:13:42','2026-10-06 18:13:42'),(26,14,1,'Dakar','Mermoz','','+221771234567','2026-10-06 18:14:33','2026-10-06 18:14:33'),(27,14,1,'Dakar','Mermoz','','+221771234567','2026-10-06 18:14:43','2026-10-06 18:14:43'),(28,14,1,'Dakar','Mermoz','','+221771234567','2026-10-06 18:14:46','2026-10-06 18:14:46');
/*!40000 ALTER TABLE `adresses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `avis`
--

DROP TABLE IF EXISTS `avis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `avis` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `commande_id` int(10) unsigned NOT NULL,
  `boutique_id` int(10) unsigned NOT NULL,
  `client_id` int(10) unsigned NOT NULL,
  `note` tinyint(3) unsigned NOT NULL COMMENT '1 à 5',
  `commentaire` varchar(500) DEFAULT NULL,
  `modifications` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Une seule modification autorisée',
  `reponse` varchar(500) DEFAULT NULL COMMENT 'Réponse publique du vendeur',
  `reponse_le` datetime DEFAULT NULL,
  `visible` tinyint(1) NOT NULL DEFAULT 1,
  `motif_masquage` varchar(255) DEFAULT NULL,
  `cree_le` datetime NOT NULL DEFAULT current_timestamp(),
  `modifie_le` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_avis_commande` (`commande_id`),
  KEY `idx_avis_boutique` (`boutique_id`,`visible`,`cree_le`),
  KEY `fk_avis_client` (`client_id`),
  CONSTRAINT `fk_avis_boutique` FOREIGN KEY (`boutique_id`) REFERENCES `boutiques` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_avis_client` FOREIGN KEY (`client_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_avis_commande` FOREIGN KEY (`commande_id`) REFERENCES `commandes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `avis`
--

LOCK TABLES `avis` WRITE;
/*!40000 ALTER TABLE `avis` DISABLE KEYS */;
INSERT INTO `avis` VALUES (12,19,17,23,5,'Livraison rapide et maillot de très bonne qualitè',0,'Merci beaucoup','2026-10-06 19:05:36',1,NULL,'2026-10-06 19:04:34','2026-10-06 19:05:36');
/*!40000 ALTER TABLE `avis` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `boutiques`
--

DROP TABLE IF EXISTS `boutiques`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `boutiques` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `utilisateur_id` int(10) unsigned NOT NULL,
  `nom` varchar(60) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `ville` varchar(60) NOT NULL,
  `description` text DEFAULT NULL,
  `logo` varchar(100) DEFAULT NULL COMMENT 'Fichier dans public/uploads/boutiques/',
  `banniere` varchar(100) DEFAULT NULL COMMENT 'Fichier dans public/uploads/boutiques/',
  `numero_whatsapp` varchar(13) DEFAULT NULL,
  `numero_wave` varchar(13) DEFAULT NULL COMMENT 'Obligatoire avant le premier versement',
  `retrait_sur_place` tinyint(1) NOT NULL DEFAULT 0,
  `adresse_retrait` varchar(255) DEFAULT NULL,
  `statut` enum('active','grace','masquee','suspendue') NOT NULL DEFAULT 'active',
  `suspension_auto_le` datetime DEFAULT NULL COMMENT 'Date de la suspension automatique (signalements)',
  `contestation` text DEFAULT NULL COMMENT 'Contestation envoyée par le vendeur suspendu',
  `contestation_le` datetime DEFAULT NULL,
  `cree_le` datetime NOT NULL DEFAULT current_timestamp(),
  `modifie_le` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `supprime_le` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_boutiques_utilisateur` (`utilisateur_id`),
  UNIQUE KEY `uq_boutiques_nom` (`nom`),
  UNIQUE KEY `uq_boutiques_slug` (`slug`),
  CONSTRAINT `fk_boutiques_utilisateur` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `boutiques`
--

LOCK TABLES `boutiques` WRITE;
/*!40000 ALTER TABLE `boutiques` DISABLE KEYS */;
INSERT INTO `boutiques` VALUES (4,12,'Dakar Foot Store','dakar-foot-store','Dakar','Maillots des sélections nationales et des grands clubs, livrés à Dakar et en banlieue.',NULL,NULL,'+221770000001','+221770000001',1,'Médina, rue 22, Dakar','active',NULL,NULL,NULL,'2026-10-02 23:27:59','2026-10-15 23:24:03',NULL),(5,13,'Thiès Maillots Rétro','thies-maillots-retro','Thiès','Spécialiste des maillots rétro et vintage : les tenues qui ont marqué l\'histoire du football.',NULL,NULL,'+221780000002',NULL,0,NULL,'masquee',NULL,NULL,NULL,'2026-10-02 23:27:59','2026-10-15 22:06:15',NULL),(17,1,'Maillot shop','maillot-shop','Dakar',NULL,NULL,'c506f25cc27e7252e9a9beb6aa704594.webp','+221706464785',NULL,0,NULL,'active',NULL,NULL,NULL,'2026-10-05 16:12:09','2026-10-05 18:31:19',NULL);
/*!40000 ALTER TABLE `boutiques` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(40) NOT NULL,
  `nom` varchar(60) NOT NULL,
  `description` varchar(255) NOT NULL,
  `ordre` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (1,'equipes-nationales','Équipes nationales','Sénégal, Maroc, France, Brésil… les maillots des sélections.',1,1),(2,'clubs','Clubs','Les clubs d\'Europe, d\'Afrique et d\'ailleurs.',2,1),(3,'retro','Rétro','Les maillots qui ont marqué l\'histoire du football.',3,1);
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `codes_verification`
--

DROP TABLE IF EXISTS `codes_verification`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `codes_verification` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `utilisateur_id` int(10) unsigned DEFAULT NULL,
  `type` enum('verification_telephone','reinitialisation_sms','reinitialisation_email','liaison_sociale_email','liaison_sociale_sms','email_connexion_sociale') NOT NULL,
  `destination` varchar(190) NOT NULL COMMENT 'Téléphone ou e-mail',
  `code_hache` varchar(255) NOT NULL,
  `essais` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `expire_le` datetime NOT NULL,
  `utilise_le` datetime DEFAULT NULL,
  `cree_le` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_codes_destination` (`destination`,`type`,`cree_le`),
  KEY `fk_codes_utilisateur` (`utilisateur_id`),
  CONSTRAINT `fk_codes_utilisateur` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `codes_verification`
--

LOCK TABLES `codes_verification` WRITE;
/*!40000 ALTER TABLE `codes_verification` DISABLE KEYS */;
/*!40000 ALTER TABLE `codes_verification` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `commande_historique`
--

DROP TABLE IF EXISTS `commande_historique`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `commande_historique` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `commande_id` int(10) unsigned NOT NULL,
  `ancien_statut` varchar(30) DEFAULT NULL,
  `nouveau_statut` varchar(30) NOT NULL,
  `auteur_id` int(10) unsigned DEFAULT NULL COMMENT 'NULL = système',
  `auteur_role` enum('client','vendeur','admin','systeme') NOT NULL,
  `motif` varchar(255) DEFAULT NULL,
  `cree_le` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_historique_commande` (`commande_id`,`cree_le`),
  KEY `fk_historique_auteur` (`auteur_id`),
  CONSTRAINT `fk_historique_auteur` FOREIGN KEY (`auteur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_historique_commande` FOREIGN KEY (`commande_id`) REFERENCES `commandes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=153 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `commande_historique`
--

LOCK TABLES `commande_historique` WRITE;
/*!40000 ALTER TABLE `commande_historique` DISABLE KEYS */;
INSERT INTO `commande_historique` VALUES (27,1,NULL,'attente_confirmation',23,'client',NULL,'2026-10-06 14:10:18'),(28,1,'attente_confirmation','confirmee',1,'vendeur',NULL,'2026-10-06 14:11:27'),(29,1,'confirmee','expediee',1,'vendeur',NULL,'2026-10-06 14:11:31'),(30,1,'expediee','livree',1,'vendeur','Remis au client (paiement à la livraison)','2026-10-06 14:11:36'),(68,19,NULL,'attente_confirmation',23,'client',NULL,'2026-10-06 19:02:15'),(69,19,'attente_confirmation','confirmee',1,'vendeur',NULL,'2026-10-06 19:02:56'),(70,19,'confirmee','expediee',1,'vendeur',NULL,'2026-10-06 19:02:59'),(71,19,'expediee','livree',1,'vendeur','Remis au client (paiement à la livraison)','2026-10-06 19:03:03'),(72,19,'livree','recue',23,'client','Réception confirmée par le client','2026-10-06 19:03:30'),(106,29,NULL,'attente_confirmation',23,'client',NULL,'2026-10-07 08:29:51'),(107,29,'attente_confirmation','confirmee',1,'vendeur',NULL,'2026-10-07 08:30:22'),(108,29,'confirmee','expediee',1,'vendeur',NULL,'2026-10-07 08:30:29'),(109,29,'expediee','livree',1,'vendeur','Remis au client (paiement à la livraison)','2026-10-07 08:30:33'),(110,29,'livree','en_litige',23,'client','Maillot non reçu','2026-10-07 08:31:47'),(117,1,'livree','recue',NULL,'systeme','Délai de réclamation écoulé','2026-10-08 16:16:35'),(128,39,NULL,'attente_paiement',71,'client','Paiement en ligne en cours','2026-10-10 15:36:28'),(129,39,'attente_paiement','attente_confirmation',NULL,'systeme','Paiement en ligne reçu','2026-10-10 15:36:33'),(130,40,NULL,'attente_confirmation',72,'client',NULL,'2026-10-11 02:08:17'),(131,40,'attente_confirmation','confirmee',1,'vendeur',NULL,'2026-10-11 02:09:51'),(132,40,'confirmee','expediee',1,'vendeur',NULL,'2026-10-11 02:09:56'),(133,40,'expediee','livree',1,'vendeur','Remis au client (paiement à la livraison)','2026-10-11 02:09:58'),(134,39,'attente_confirmation','annulee',NULL,'systeme','Non confirmée par le vendeur sous 48 h','2026-10-13 09:22:18'),(135,40,'livree','recue',NULL,'systeme','Délai de réclamation écoulé','2026-10-13 09:22:18'),(136,41,NULL,'attente_confirmation',83,'client',NULL,'2026-10-13 10:04:14'),(137,42,NULL,'attente_confirmation',84,'client',NULL,'2026-10-13 10:05:44'),(138,42,'attente_confirmation','confirmee',1,'vendeur',NULL,'2026-10-13 10:06:07'),(139,42,'confirmee','expediee',1,'vendeur',NULL,'2026-10-13 10:06:12'),(140,42,'expediee','livree',1,'vendeur','Remis au client (paiement à la livraison)','2026-10-13 10:06:15'),(141,43,NULL,'attente_confirmation',86,'client',NULL,'2026-10-13 10:25:11'),(142,43,'attente_confirmation','confirmee',1,'vendeur',NULL,'2026-10-13 10:25:51'),(143,43,'confirmee','expediee',1,'vendeur',NULL,'2026-10-13 10:25:54'),(144,43,'expediee','livree',1,'vendeur','Remis au client (paiement à la livraison)','2026-10-13 10:25:55'),(145,41,'attente_confirmation','annulee',NULL,'systeme','Non confirmée par le vendeur sous 48 h','2026-10-15 11:10:18'),(146,42,'livree','recue',NULL,'systeme','Délai de réclamation écoulé','2026-10-15 11:10:18'),(147,43,'livree','recue',NULL,'systeme','Délai de réclamation écoulé','2026-10-15 11:10:18'),(148,44,NULL,'attente_confirmation',88,'client',NULL,'2026-10-15 15:58:42'),(149,29,'en_litige','recue',85,'admin','Réclamation jugée fondée','2026-10-15 16:08:27'),(150,44,'attente_confirmation','confirmee',1,'vendeur',NULL,'2026-10-15 16:09:47'),(151,44,'confirmee','expediee',1,'vendeur',NULL,'2026-10-15 16:09:49'),(152,44,'expediee','livree',1,'vendeur','Remis au client (paiement à la livraison)','2026-10-15 16:09:52');
/*!40000 ALTER TABLE `commande_historique` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `commande_lignes`
--

DROP TABLE IF EXISTS `commande_lignes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `commande_lignes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `commande_id` int(10) unsigned NOT NULL,
  `maillot_id` int(10) unsigned DEFAULT NULL COMMENT 'NULL si le maillot n''existe plus',
  `titre` varchar(100) NOT NULL,
  `image` varchar(100) DEFAULT NULL COMMENT 'Miniature au moment de la commande',
  `taille` varchar(12) NOT NULL,
  `flocage_nom` varchar(12) NOT NULL DEFAULT '',
  `flocage_numero` varchar(2) NOT NULL DEFAULT '',
  `flocage_supplement` int(10) unsigned NOT NULL DEFAULT 0,
  `prix_unitaire` int(10) unsigned NOT NULL COMMENT 'Prix du maillot + flocage (FCFA)',
  `quantite` tinyint(3) unsigned NOT NULL,
  `total_ligne` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_commande_lignes_commande` (`commande_id`),
  KEY `fk_commande_lignes_maillot` (`maillot_id`),
  CONSTRAINT `fk_commande_lignes_commande` FOREIGN KEY (`commande_id`) REFERENCES `commandes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_commande_lignes_maillot` FOREIGN KEY (`maillot_id`) REFERENCES `maillots` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `commande_lignes`
--

LOCK TABLES `commande_lignes` WRITE;
/*!40000 ALTER TABLE `commande_lignes` DISABLE KEYS */;
INSERT INTO `commande_lignes` VALUES (14,1,31,'Maillot Barçalone','be4d26720fc2b0d1971559c6750ed047.webp','XL','','',0,5000,2,10000),(24,19,31,'Maillot Barçalone','be4d26720fc2b0d1971559c6750ed047.webp','L','','',0,5000,1,5000),(34,29,32,'Maillot paris','6c7b438e25adb75029a9e44bb72c2f42.webp','XL','','',0,6000,1,6000),(44,39,34,'Maillot Chelsea domicile 2024/2025','cbe71d8498eaa46d238f73c6aa59a599.webp','XL','','',0,42000,1,42000),(45,40,32,'Maillot paris','6c7b438e25adb75029a9e44bb72c2f42.webp','XL','','',0,6000,1,6000),(46,41,34,'Maillot Chelsea domicile 2024/2025','cbe71d8498eaa46d238f73c6aa59a599.webp','L','','',0,42000,1,42000),(47,42,31,'Maillot Barçalone','be4d26720fc2b0d1971559c6750ed047.webp','L','','',0,5000,1,5000),(48,43,32,'Maillot paris','6c7b438e25adb75029a9e44bb72c2f42.webp','XL','','',0,6000,1,6000),(49,44,36,'Maillot France 1998 domicile rétro','91ed30c7d1b828736507fd17c1877dcc.webp','L','','',0,9000,1,9000);
/*!40000 ALTER TABLE `commande_lignes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `commandes`
--

DROP TABLE IF EXISTS `commandes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `commandes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `numero` varchar(20) NOT NULL COMMENT 'CMD-2026-000142',
  `client_id` int(10) unsigned NOT NULL,
  `boutique_id` int(10) unsigned NOT NULL,
  `origine` enum('site','whatsapp') NOT NULL DEFAULT 'site',
  `mode_paiement` enum('livraison','en_ligne') NOT NULL DEFAULT 'livraison',
  `transaction_id` int(10) unsigned DEFAULT NULL,
  `statut` enum('attente_paiement','attente_confirmation','confirmee','expediee','livree','recue','en_litige','annulee','remboursee') NOT NULL DEFAULT 'attente_confirmation',
  `sous_total` int(10) unsigned NOT NULL COMMENT 'FCFA, maillots + flocages',
  `frais_livraison` int(10) unsigned NOT NULL DEFAULT 0,
  `frais_a_convenir` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = pas de tarif pour cette zone : frais fix├®s avec le vendeur',
  `total` int(10) unsigned NOT NULL,
  `frais_transaction` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'FCFA retenus (frais de l''agrégateur)',
  `montant_vendeur` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'FCFA reversés au vendeur (paiement en ligne)',
  `mode_livraison` enum('livraison','retrait') NOT NULL DEFAULT 'livraison',
  `zone_id` int(10) unsigned DEFAULT NULL,
  `zone_nom` varchar(60) NOT NULL DEFAULT '',
  `delai_livraison` varchar(40) NOT NULL DEFAULT '',
  `client_nom` varchar(101) NOT NULL,
  `telephone_client` varchar(13) NOT NULL,
  `email_client` varchar(190) DEFAULT NULL COMMENT 'E-mail donné à la commande (facultatif, commande sans compte)',
  `jeton_suivi_chiffre` varchar(255) DEFAULT NULL COMMENT 'Lien de suivi d''une commande sans compte (chiffré)',
  `adresse_ville` varchar(60) NOT NULL DEFAULT '',
  `adresse_quartier` varchar(80) NOT NULL DEFAULT '',
  `adresse_repere` varchar(160) NOT NULL DEFAULT '',
  `adresse_retrait` varchar(255) NOT NULL DEFAULT '' COMMENT 'Copie de l''adresse de retrait de la boutique',
  `code_livraison_chiffre` varchar(255) DEFAULT NULL COMMENT 'Paiement en ligne uniquement (┬º14.1) ; NULL en paiement ├á la livraison',
  `code_essais` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT '5 essais maximum (§14.1)',
  `livree_avec_code` tinyint(1) DEFAULT NULL,
  `motif_annulation` varchar(255) DEFAULT NULL,
  `rappel_envoye_le` datetime DEFAULT NULL COMMENT 'Rappel au vendeur : 12 h restantes',
  `confirmee_le` datetime DEFAULT NULL,
  `expediee_le` datetime DEFAULT NULL,
  `livree_le` datetime DEFAULT NULL,
  `recue_le` datetime DEFAULT NULL,
  `annulee_le` datetime DEFAULT NULL,
  `cree_le` datetime NOT NULL DEFAULT current_timestamp(),
  `payee_le` datetime DEFAULT NULL,
  `modifie_le` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_commandes_numero` (`numero`),
  KEY `idx_commandes_client` (`client_id`,`cree_le`),
  KEY `idx_commandes_boutique` (`boutique_id`,`statut`,`cree_le`),
  KEY `idx_commandes_statut` (`statut`,`cree_le`),
  KEY `fk_commandes_zone` (`zone_id`),
  KEY `idx_commandes_transaction` (`transaction_id`),
  CONSTRAINT `fk_commandes_boutique` FOREIGN KEY (`boutique_id`) REFERENCES `boutiques` (`id`),
  CONSTRAINT `fk_commandes_client` FOREIGN KEY (`client_id`) REFERENCES `utilisateurs` (`id`),
  CONSTRAINT `fk_commandes_transaction` FOREIGN KEY (`transaction_id`) REFERENCES `transactions_paiement` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_commandes_zone` FOREIGN KEY (`zone_id`) REFERENCES `zones_livraison` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `commandes`
--

LOCK TABLES `commandes` WRITE;
/*!40000 ALTER TABLE `commandes` DISABLE KEYS */;
INSERT INTO `commandes` VALUES (1,'CMD-2026-000001',23,17,'site','livraison',NULL,'recue',10000,0,1,10000,0,0,'livraison',2,'Banlieue','À convenir avec le vendeur','Ablaye Diallo','+221706464785',NULL,NULL,'tivaoune peul','apix','','',NULL,0,0,NULL,NULL,'2026-10-06 14:11:27','2026-10-06 14:11:31','2026-10-06 14:11:36','2026-10-08 16:16:35',NULL,'2026-10-06 14:10:18',NULL,'2026-10-08 16:16:35'),(19,'CMD-2026-000019',23,17,'site','livraison',NULL,'recue',5000,0,1,5000,0,0,'livraison',2,'Banlieue','À convenir avec le vendeur','Ablaye Diallo','+221706464785',NULL,NULL,'tivaoune peul','apix','','',NULL,0,0,NULL,NULL,'2026-10-06 19:02:56','2026-10-06 19:02:59','2026-10-06 19:03:03','2026-10-06 19:03:30',NULL,'2026-10-06 19:02:15',NULL,'2026-10-06 19:03:30'),(29,'CMD-2026-000029',23,17,'site','livraison',NULL,'recue',6000,0,1,6000,0,0,'livraison',2,'Banlieue','À convenir avec le vendeur','Ablaye Diallo','+221706464785',NULL,NULL,'tivaoune peul','apix','','',NULL,0,0,NULL,NULL,'2026-10-07 08:30:22','2026-10-07 08:30:29','2026-10-07 08:30:33','2026-10-15 16:08:27',NULL,'2026-10-07 08:29:51',NULL,'2026-10-15 16:08:27'),(39,'CMD-2026-000039',71,4,'site','en_ligne',10,'annulee',42000,2000,0,44000,0,44000,'livraison',2,'Banlieue','48-72h','Aliou Bah','+221706464785',NULL,'8jsOP24qObW6q2UWhdyE2gsXTIuDgf0mM05gvusxE/8Q+KsyBTnqM+eK6ef9tDNVz4y+7KuvlXRShfosuwkDuaNEPQxb+O12zi1mcQhwUPZFaWAIZ2dO7I2yJAg=','Dakar','apix','','','zyRVNwQZQ+z9yIJFSdocCRcUyxJ/8GUidfqYHNokjWc=',0,NULL,'Non confirmée par le vendeur sous 48 h','2026-10-11 15:39:09',NULL,NULL,NULL,NULL,'2026-10-13 09:22:18','2026-10-10 15:36:28','2026-10-10 15:36:33','2026-10-13 09:22:18'),(40,'CMD-2026-000040',72,17,'site','livraison',NULL,'recue',6000,0,1,6000,0,0,'livraison',2,'Banlieue','À convenir avec le vendeur','Aliou Bah','+221706464785',NULL,'qPx5Fp7sABBikAVWC9L4N3KD5kg5a4ltP8Z84KthvNw/yS8ptc66SO5/SFQTPtnPQ3TWxsbJbqBJ76ehcDIdd8i7k7TxCk2yL1mVR5febupBuo5u++1G+vA5DQ8=','Dakar','apix','','',NULL,0,0,NULL,NULL,'2026-10-11 02:09:51','2026-10-11 02:09:56','2026-10-11 02:09:58','2026-10-13 09:22:18',NULL,'2026-10-11 02:08:17',NULL,'2026-10-13 09:22:18'),(41,'CMD-2026-000041',83,4,'site','livraison',NULL,'annulee',42000,2000,0,44000,0,0,'livraison',2,'Banlieue','48-72h','Aliou Bah','+221706464785',NULL,'NMcicvTcejn+/8razR801KKIQU09df3RGcu11wh2YhlQ+zDqyQJwYtve3TcwRNAeyEIkqsYOaLtBystroTZRU+SM+jsZYFJKMnAHvah5/3IKrD9e7oeNU1ppGpg=','Dakar','apix','','',NULL,0,NULL,'Non confirmée par le vendeur sous 48 h',NULL,NULL,NULL,NULL,NULL,'2026-10-15 11:10:18','2026-10-13 10:04:14',NULL,'2026-10-15 11:10:18'),(42,'CMD-2026-000042',84,17,'site','livraison',NULL,'recue',5000,0,1,5000,0,0,'livraison',2,'Banlieue','À convenir avec le vendeur','Aliou Bah','+221706464785',NULL,'yPQhyvul4B+KexJaIUXJosO2ELW+eCblNBlHhNvK3uatafcquZEd6Xv3OHhmTmlSPh1egIn5fUh8rJBEkBqkg+aJZZK356PGuu1CkqDoiL9/AxPrF7tAi8KxD4o=','Dakar','apix','','',NULL,0,0,NULL,NULL,'2026-10-13 10:06:07','2026-10-13 10:06:12','2026-10-13 10:06:15','2026-10-15 11:10:18',NULL,'2026-10-13 10:05:44',NULL,'2026-10-15 11:10:18'),(43,'CMD-2026-000043',86,17,'site','livraison',NULL,'recue',6000,0,1,6000,0,0,'livraison',2,'Banlieue','À convenir avec le vendeur','Aliou Bah','+221706464785',NULL,'TfpgKyOrTVxcZw/NFRAwmoPgk1Sqt/3RCnjoEmBbys+sIwilg5C2VU/G5O4KF8Vrow/Ho/Cw3angkgeBP6GCpuTFG43Dxk/Ceq0EPEHxRPyV/aTCjHgzAh4FPlQ=','Dakar','apix','','',NULL,0,0,NULL,NULL,'2026-10-13 10:25:51','2026-10-13 10:25:54','2026-10-13 10:25:55','2026-10-15 11:10:18',NULL,'2026-10-13 10:25:11',NULL,'2026-10-15 11:10:18'),(44,'CMD-2026-000044',88,17,'site','livraison',NULL,'livree',9000,0,1,9000,0,0,'livraison',2,'Banlieue','À convenir avec le vendeur','Aliou Bah','+221706464785',NULL,'ploM/FyjARdSVpw36axmA+tihazY4P22nHWUUCtZ+4WB6fIsqUGiVk2vFLG6/CBBhfTEny1UKpBBaCJy0sLyaUManYJvoUIp7hLZa1Q7E0dh97AY5tIqwJ+6wP8=','Dakar','apix','','',NULL,0,0,NULL,NULL,'2026-10-15 16:09:47','2026-10-15 16:09:49','2026-10-15 16:09:52',NULL,NULL,'2026-10-15 15:58:42',NULL,'2026-10-15 16:09:52');
/*!40000 ALTER TABLE `commandes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `connexions_sociales`
--

DROP TABLE IF EXISTS `connexions_sociales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `connexions_sociales` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `utilisateur_id` int(10) unsigned NOT NULL,
  `fournisseur` enum('google','facebook') NOT NULL,
  `identifiant_fournisseur` varchar(255) NOT NULL COMMENT '« sub » Google ou « id » Facebook (propre à l''application)',
  `email_fournisseur` varchar(255) DEFAULT NULL,
  `cree_le` datetime NOT NULL DEFAULT current_timestamp(),
  `derniere_connexion_le` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sociales_fournisseur_identifiant` (`fournisseur`,`identifiant_fournisseur`),
  UNIQUE KEY `uq_sociales_utilisateur_fournisseur` (`utilisateur_id`,`fournisseur`),
  CONSTRAINT `fk_sociales_utilisateur` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `connexions_sociales`
--

LOCK TABLES `connexions_sociales` WRITE;
/*!40000 ALTER TABLE `connexions_sociales` DISABLE KEYS */;
/*!40000 ALTER TABLE `connexions_sociales` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `demandes_suppression_facebook`
--

DROP TABLE IF EXISTS `demandes_suppression_facebook`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `demandes_suppression_facebook` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `identifiant_fournisseur` varchar(255) NOT NULL,
  `utilisateur_id` int(10) unsigned DEFAULT NULL COMMENT 'Compte concerné (anonymisation différée si commandes en cours)',
  `code_confirmation` char(20) NOT NULL,
  `statut` enum('recue','traitee') NOT NULL DEFAULT 'recue',
  `recue_le` datetime NOT NULL DEFAULT current_timestamp(),
  `traitee_le` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_suppression_code` (`code_confirmation`),
  KEY `idx_suppression_statut` (`statut`),
  KEY `fk_suppression_utilisateur` (`utilisateur_id`),
  CONSTRAINT `fk_suppression_utilisateur` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `demandes_suppression_facebook`
--

LOCK TABLES `demandes_suppression_facebook` WRITE;
/*!40000 ALTER TABLE `demandes_suppression_facebook` DISABLE KEYS */;
/*!40000 ALTER TABLE `demandes_suppression_facebook` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `envois_sms`
--

DROP TABLE IF EXISTS `envois_sms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `envois_sms` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `telephone` varchar(13) NOT NULL,
  `message` varchar(480) NOT NULL,
  `urgent` tinyint(1) NOT NULL DEFAULT 1,
  `statut` enum('en_attente','envoye','simule','echec') NOT NULL DEFAULT 'en_attente',
  `essais` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `erreur` varchar(255) DEFAULT NULL,
  `programme_le` datetime NOT NULL DEFAULT current_timestamp(),
  `envoye_le` datetime DEFAULT NULL,
  `cree_le` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_envois_statut` (`statut`,`programme_le`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `envois_sms`
--

LOCK TABLES `envois_sms` WRITE;
/*!40000 ALTER TABLE `envois_sms` DISABLE KEYS */;
INSERT INTO `envois_sms` VALUES (7,'+221780000002','',0,'simule',0,NULL,'2026-10-06 17:25:25','2026-10-06 17:25:25','2026-10-06 17:25:25');
/*!40000 ALTER TABLE `envois_sms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `equipes`
--

DROP TABLE IF EXISTS `equipes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `equipes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(80) NOT NULL,
  `nom` varchar(80) NOT NULL,
  `pays` varchar(60) NOT NULL,
  `type` enum('selection','club') NOT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_equipes_slug` (`slug`),
  KEY `idx_equipes_type_nom` (`type`,`nom`)
) ENGINE=InnoDB AUTO_INCREMENT=136 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `equipes`
--

LOCK TABLES `equipes` WRITE;
/*!40000 ALTER TABLE `equipes` DISABLE KEYS */;
INSERT INTO `equipes` VALUES (1,'senegal','Sénégal','Sénégal','selection',1),(2,'cote-d-ivoire','Côte d\'Ivoire','Côte d\'Ivoire','selection',1),(3,'maroc','Maroc','Maroc','selection',1),(4,'algerie','Algérie','Algérie','selection',1),(5,'tunisie','Tunisie','Tunisie','selection',1),(6,'egypte','Égypte','Égypte','selection',1),(7,'nigeria','Nigeria','Nigeria','selection',1),(8,'cameroun','Cameroun','Cameroun','selection',1),(9,'ghana','Ghana','Ghana','selection',1),(10,'mali','Mali','Mali','selection',1),(11,'guinee','Guinée','Guinée','selection',1),(12,'burkina-faso','Burkina Faso','Burkina Faso','selection',1),(13,'gambie','Gambie','Gambie','selection',1),(14,'mauritanie','Mauritanie','Mauritanie','selection',1),(15,'cap-vert','Cap-Vert','Cap-Vert','selection',1),(16,'rd-congo','RD Congo','RD Congo','selection',1),(17,'afrique-du-sud','Afrique du Sud','Afrique du Sud','selection',1),(18,'france','France','France','selection',1),(19,'espagne','Espagne','Espagne','selection',1),(20,'portugal','Portugal','Portugal','selection',1),(21,'allemagne','Allemagne','Allemagne','selection',1),(22,'italie','Italie','Italie','selection',1),(23,'angleterre','Angleterre','Angleterre','selection',1),(24,'pays-bas','Pays-Bas','Pays-Bas','selection',1),(25,'belgique','Belgique','Belgique','selection',1),(26,'croatie','Croatie','Croatie','selection',1),(27,'bresil','Brésil','Brésil','selection',1),(28,'argentine','Argentine','Argentine','selection',1),(29,'uruguay','Uruguay','Uruguay','selection',1),(30,'mexique','Mexique','Mexique','selection',1),(31,'etats-unis','États-Unis','États-Unis','selection',1),(32,'japon','Japon','Japon','selection',1),(33,'jaraaf','ASC Jaraaf','Sénégal','club',1),(34,'casa-sports','Casa Sports','Sénégal','club',1),(35,'generation-foot','Génération Foot','Sénégal','club',1),(36,'teungueth-fc','Teungueth FC','Sénégal','club',1),(37,'as-pikine','AS Pikine','Sénégal','club',1),(38,'al-ahly','Al Ahly','Égypte','club',1),(39,'wydad','Wydad AC','Maroc','club',1),(40,'raja','Raja CA','Maroc','club',1),(41,'esperance-tunis','Espérance de Tunis','Tunisie','club',1),(42,'mamelodi-sundowns','Mamelodi Sundowns','Afrique du Sud','club',1),(43,'psg','Paris Saint-Germain','France','club',1),(44,'olympique-de-marseille','Olympique de Marseille','France','club',1),(45,'olympique-lyonnais','Olympique lyonnais','France','club',1),(46,'as-monaco','AS Monaco','France','club',1),(47,'losc','LOSC Lille','France','club',1),(48,'real-madrid','Real Madrid','Espagne','club',1),(49,'fc-barcelone','FC Barcelone','Espagne','club',1),(50,'atletico-madrid','Atlético de Madrid','Espagne','club',1),(51,'manchester-united','Manchester United','Angleterre','club',1),(52,'manchester-city','Manchester City','Angleterre','club',1),(53,'liverpool','Liverpool','Angleterre','club',1),(54,'chelsea','Chelsea','Angleterre','club',1),(55,'arsenal','Arsenal','Angleterre','club',1),(56,'tottenham','Tottenham','Angleterre','club',1),(57,'bayern-munich','Bayern Munich','Allemagne','club',1),(58,'borussia-dortmund','Borussia Dortmund','Allemagne','club',1),(59,'juventus','Juventus','Italie','club',1),(60,'ac-milan','AC Milan','Italie','club',1),(61,'inter-milan','Inter Milan','Italie','club',1),(62,'naples','SSC Naples','Italie','club',1),(63,'as-rome','AS Rome','Italie','club',1),(64,'al-nassr','Al-Nassr','Arabie saoudite','club',1),(65,'al-hilal','Al-Hilal','Arabie saoudite','club',1),(66,'inter-miami','Inter Miami','États-Unis','club',1);
/*!40000 ALTER TABLE `equipes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `essais_gratuits`
--

DROP TABLE IF EXISTS `essais_gratuits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `essais_gratuits` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `telephone` varchar(13) NOT NULL,
  `utilisateur_id` int(10) unsigned DEFAULT NULL,
  `debut_le` datetime NOT NULL,
  `fin_le` datetime NOT NULL,
  `cree_le` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_essais_telephone` (`telephone`),
  KEY `fk_essais_utilisateur` (`utilisateur_id`),
  CONSTRAINT `fk_essais_utilisateur` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `essais_gratuits`
--

LOCK TABLES `essais_gratuits` WRITE;
/*!40000 ALTER TABLE `essais_gratuits` DISABLE KEYS */;
INSERT INTO `essais_gratuits` VALUES (3,'+221770000001',12,'2026-10-05 15:34:51','2026-10-19 15:34:51','2026-10-05 15:34:51'),(4,'+221780000002',13,'2026-09-20 15:34:51','2026-10-04 15:34:51','2026-10-05 15:34:51'),(12,'+221706464785',1,'2026-10-05 16:19:34','2026-10-19 16:19:34','2026-10-05 16:19:34');
/*!40000 ALTER TABLE `essais_gratuits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `evenements_paiement`
--

DROP TABLE IF EXISTS `evenements_paiement`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evenements_paiement` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `transaction_id` int(10) unsigned DEFAULT NULL,
  `type` varchar(40) NOT NULL COMMENT 'initiation, notification, verification, signature_invalide…',
  `detail` varchar(255) NOT NULL DEFAULT '',
  `cree_le` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_evenements_transaction` (`transaction_id`,`cree_le`),
  CONSTRAINT `fk_evenements_transaction` FOREIGN KEY (`transaction_id`) REFERENCES `transactions_paiement` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `evenements_paiement`
--

LOCK TABLES `evenements_paiement` WRITE;
/*!40000 ALTER TABLE `evenements_paiement` DISABLE KEYS */;
INSERT INTO `evenements_paiement` VALUES (22,NULL,'signature_invalide','simulateur','2026-10-06 21:23:40'),(26,NULL,'signature_invalide','simulateur','2026-10-07 20:21:11'),(30,10,'initiation','simulateur · 44000 FCFA','2026-10-10 15:36:28'),(31,10,'notification','simulateur','2026-10-10 15:36:33'),(32,10,'verification','reussi','2026-10-10 15:36:33');
/*!40000 ALTER TABLE `evenements_paiement` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `favoris`
--

DROP TABLE IF EXISTS `favoris`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `favoris` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `utilisateur_id` int(10) unsigned NOT NULL,
  `maillot_id` int(10) unsigned NOT NULL,
  `cree_le` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_favoris` (`utilisateur_id`,`maillot_id`),
  KEY `fk_favoris_maillot` (`maillot_id`),
  CONSTRAINT `fk_favoris_maillot` FOREIGN KEY (`maillot_id`) REFERENCES `maillots` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_favoris_utilisateur` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `favoris`
--

LOCK TABLES `favoris` WRITE;
/*!40000 ALTER TABLE `favoris` DISABLE KEYS */;
/*!40000 ALTER TABLE `favoris` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jetons_connexion`
--

DROP TABLE IF EXISTS `jetons_connexion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jetons_connexion` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `utilisateur_id` int(10) unsigned NOT NULL,
  `selecteur` char(24) NOT NULL,
  `validateur_hache` char(64) NOT NULL,
  `expire_le` datetime NOT NULL,
  `cree_le` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_jetons_selecteur` (`selecteur`),
  KEY `fk_jetons_utilisateur` (`utilisateur_id`),
  CONSTRAINT `fk_jetons_utilisateur` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jetons_connexion`
--

LOCK TABLES `jetons_connexion` WRITE;
/*!40000 ALTER TABLE `jetons_connexion` DISABLE KEYS */;
/*!40000 ALTER TABLE `jetons_connexion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `journal_actions`
--

DROP TABLE IF EXISTS `journal_actions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `journal_actions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `utilisateur_id` int(10) unsigned DEFAULT NULL COMMENT 'Auteur de l''action (NULL = système)',
  `action` varchar(60) NOT NULL,
  `objet_type` varchar(40) DEFAULT NULL,
  `objet_id` int(10) unsigned DEFAULT NULL,
  `ancienne_valeur` text DEFAULT NULL,
  `nouvelle_valeur` text DEFAULT NULL,
  `adresse_ip` varchar(45) DEFAULT NULL,
  `cree_le` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_journal_action` (`action`,`cree_le`),
  KEY `idx_journal_objet` (`objet_type`,`objet_id`),
  KEY `fk_journal_utilisateur` (`utilisateur_id`),
  CONSTRAINT `fk_journal_utilisateur` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=357 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `journal_actions`
--

LOCK TABLES `journal_actions` WRITE;
/*!40000 ALTER TABLE `journal_actions` DISABLE KEYS */;
INSERT INTO `journal_actions` VALUES (1,1,'inscription_client','utilisateur',1,NULL,NULL,NULL,'2026-10-02 20:11:14'),(10,NULL,'inscription_client','utilisateur',9,NULL,NULL,NULL,'2026-10-02 22:48:55'),(15,23,'inscription_client','utilisateur',23,NULL,NULL,NULL,'2026-10-05 15:35:12'),(26,1,'client_devenu_vendeur','utilisateur',1,NULL,NULL,NULL,'2026-10-05 16:12:09'),(88,NULL,'admin_role_modifie','utilisateur',23,'client','admin — Changement de rôle par l\'administration',NULL,'2026-10-06 13:14:07'),(89,NULL,'admin_role_modifie','utilisateur',23,'admin','client — Changement de rôle par l\'administration',NULL,'2026-10-06 13:14:13'),(90,NULL,'admin_parametre_modifie','parametre',NULL,'confirmation_vendeur_heures = 24','confirmation_vendeur_heures = 48',NULL,'2026-10-06 14:01:14'),(91,NULL,'admin_parametre_modifie','parametre',NULL,'numero_whatsapp_support = ','numero_whatsapp_support = +221706464785',NULL,'2026-10-06 14:01:14'),(92,NULL,'admin_parametre_modifie','parametre',NULL,'email_support = ','email_support = lamabah811@gmail.com',NULL,'2026-10-06 14:01:14'),(109,23,'commande_creee','commande',1,NULL,NULL,NULL,'2026-10-06 14:10:18'),(110,1,'commande_confirmee','commande',1,NULL,NULL,NULL,'2026-10-06 14:11:27'),(111,1,'commande_expediee','commande',1,NULL,NULL,NULL,'2026-10-06 14:11:31'),(112,1,'commande_livree','commande',1,NULL,NULL,NULL,'2026-10-06 14:11:36'),(113,23,'signalement_vendeur','boutique',17,NULL,'non_conforme',NULL,'2026-10-06 14:13:09'),(119,NULL,'boutique_grace','boutique',5,'active','grace',NULL,'2026-10-06 15:22:04'),(125,NULL,'admin_catalogue_categorie','categorie',1,NULL,'Équipes nationales',NULL,'2026-10-06 15:25:42'),(128,NULL,'boutique_grace','boutique',5,'active','grace',NULL,'2026-10-06 17:25:25'),(178,23,'commande_creee','commande',19,NULL,NULL,NULL,'2026-10-06 19:02:15'),(179,1,'commande_confirmee','commande',19,NULL,NULL,NULL,'2026-10-06 19:02:56'),(180,1,'commande_expediee','commande',19,NULL,NULL,NULL,'2026-10-06 19:02:59'),(181,1,'commande_livree','commande',19,NULL,NULL,NULL,'2026-10-06 19:03:03'),(182,23,'commande_recue','commande',19,NULL,NULL,NULL,'2026-10-06 19:03:30'),(183,23,'avis_publie','commande',19,NULL,'5',NULL,'2026-10-06 19:04:34'),(213,NULL,'admin_parametre_modifie','parametre',NULL,'signalements_periode_jours = 90','signalements_periode_jours = 60',NULL,'2026-10-06 20:47:11'),(214,NULL,'admin_parametre_modifie','parametre',NULL,'essai_gratuit_jours = 14','essai_gratuit_jours = 15',NULL,'2026-10-06 20:47:11'),(215,NULL,'admin_parametre_modifie','parametre',NULL,'numero_wave_plateforme = ','numero_wave_plateforme = +221706363785',NULL,'2026-10-06 20:47:11'),(232,23,'commande_creee','commande',29,NULL,NULL,NULL,'2026-10-07 08:29:51'),(233,1,'commande_confirmee','commande',29,NULL,NULL,NULL,'2026-10-07 08:30:22'),(234,1,'commande_expediee','commande',29,NULL,NULL,NULL,'2026-10-07 08:30:29'),(235,1,'commande_livree','commande',29,NULL,NULL,NULL,'2026-10-07 08:30:33'),(236,23,'commande_en_litige','commande',29,NULL,NULL,NULL,'2026-10-07 08:31:47'),(237,23,'litige_ouvert','commande',29,NULL,'non_recu',NULL,'2026-10-07 08:31:47'),(238,NULL,'boutique_masquee','boutique',5,'grace','masquee',NULL,'2026-10-07 16:03:57'),(248,NULL,'commande_recue','commande',1,NULL,NULL,NULL,'2026-10-08 16:16:35'),(260,71,'commande_creee','commande',39,NULL,'en_ligne',NULL,'2026-10-10 15:36:28'),(261,NULL,'commande_attente_confirmation','commande',39,NULL,NULL,NULL,'2026-10-10 15:36:33'),(262,72,'commande_creee','commande',40,NULL,NULL,NULL,'2026-10-11 02:08:17'),(263,1,'commande_confirmee','commande',40,NULL,NULL,NULL,'2026-10-11 02:09:51'),(264,1,'commande_expediee','commande',40,NULL,NULL,NULL,'2026-10-11 02:09:56'),(265,1,'commande_livree','commande',40,NULL,NULL,NULL,'2026-10-11 02:09:58'),(315,1,'liaison_google','utilisateur',1,NULL,NULL,NULL,'2026-10-11 18:49:12'),(316,1,'connexion_via_google','utilisateur',1,NULL,NULL,NULL,'2026-10-11 18:49:12'),(317,NULL,'inscription_via_google','utilisateur',82,NULL,NULL,NULL,'2026-10-11 19:11:56'),(318,NULL,'connexion_via_google','utilisateur',82,NULL,NULL,NULL,'2026-10-11 19:11:56'),(319,NULL,'connexion_via_google','utilisateur',82,NULL,NULL,NULL,'2026-10-11 19:12:40'),(320,NULL,'connexion_via_google','utilisateur',82,NULL,NULL,NULL,'2026-10-11 19:15:03'),(321,NULL,'connexion_via_google','utilisateur',82,NULL,NULL,NULL,'2026-10-11 19:35:37'),(322,NULL,'connexion_via_google','utilisateur',82,NULL,NULL,NULL,'2026-10-11 19:45:55'),(323,NULL,'commande_annulee','commande',39,NULL,NULL,NULL,'2026-10-13 09:22:18'),(324,NULL,'commande_recue','commande',40,NULL,NULL,NULL,'2026-10-13 09:22:18'),(325,NULL,'connexion_via_google','utilisateur',82,NULL,NULL,NULL,'2026-10-13 09:47:14'),(326,NULL,'connexion_via_google','utilisateur',82,NULL,NULL,NULL,'2026-10-13 09:55:02'),(327,1,'connexion_via_google','utilisateur',1,NULL,NULL,NULL,'2026-10-13 10:00:08'),(328,83,'commande_creee','commande',41,NULL,NULL,NULL,'2026-10-13 10:04:14'),(329,1,'connexion_via_google','utilisateur',1,NULL,NULL,NULL,'2026-10-13 10:04:34'),(330,84,'commande_creee','commande',42,NULL,NULL,NULL,'2026-10-13 10:05:44'),(331,1,'connexion_via_google','utilisateur',1,NULL,NULL,NULL,'2026-10-13 10:05:59'),(332,1,'commande_confirmee','commande',42,NULL,NULL,NULL,'2026-10-13 10:06:07'),(333,1,'commande_expediee','commande',42,NULL,NULL,NULL,'2026-10-13 10:06:12'),(334,1,'commande_livree','commande',42,NULL,NULL,NULL,'2026-10-13 10:06:15'),(335,NULL,'connexion_via_google','utilisateur',82,NULL,NULL,NULL,'2026-10-13 10:07:28'),(336,85,'inscription_client','utilisateur',85,NULL,NULL,NULL,'2026-10-13 10:09:57'),(337,1,'connexion_via_google','utilisateur',1,NULL,NULL,NULL,'2026-10-13 10:19:42'),(338,86,'commande_creee','commande',43,NULL,NULL,NULL,'2026-10-13 10:25:11'),(339,1,'connexion_via_google','utilisateur',1,NULL,NULL,NULL,'2026-10-13 10:25:33'),(340,1,'commande_confirmee','commande',43,NULL,NULL,NULL,'2026-10-13 10:25:51'),(341,1,'commande_expediee','commande',43,NULL,NULL,NULL,'2026-10-13 10:25:54'),(342,1,'commande_livree','commande',43,NULL,NULL,NULL,'2026-10-13 10:25:55'),(343,1,'connexion_via_google','utilisateur',1,NULL,NULL,NULL,'2026-10-13 11:32:42'),(344,NULL,'commande_annulee','commande',41,NULL,NULL,NULL,'2026-10-15 11:10:18'),(345,NULL,'commande_recue','commande',42,NULL,NULL,NULL,'2026-10-15 11:10:18'),(346,NULL,'commande_recue','commande',43,NULL,NULL,NULL,'2026-10-15 11:10:18'),(347,88,'commande_creee','commande',44,NULL,NULL,NULL,'2026-10-15 15:58:42'),(348,1,'connexion_via_google','utilisateur',1,NULL,NULL,NULL,'2026-10-15 16:00:23'),(349,85,'commande_recue','commande',29,NULL,NULL,NULL,'2026-10-15 16:08:27'),(350,85,'admin_litige_fondee','litige',8,NULL,'jjkdfgukwdsukv dsmodev b jg  blig ujsd',NULL,'2026-10-15 16:08:27'),(351,85,'admin_signalement_retenu','signalement',5,NULL,'lbidsg_ lisd_osd',NULL,'2026-10-15 16:08:59'),(352,1,'connexion_via_google','utilisateur',1,NULL,NULL,NULL,'2026-10-15 16:09:25'),(353,1,'commande_confirmee','commande',44,NULL,NULL,NULL,'2026-10-15 16:09:47'),(354,1,'commande_expediee','commande',44,NULL,NULL,NULL,'2026-10-15 16:09:49'),(355,1,'commande_livree','commande',44,NULL,NULL,NULL,'2026-10-15 16:09:52'),(356,1,'connexion_via_google','utilisateur',1,NULL,NULL,NULL,'2026-10-15 22:10:18');
/*!40000 ALTER TABLE `journal_actions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `litige_messages`
--

DROP TABLE IF EXISTS `litige_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `litige_messages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `litige_id` int(10) unsigned NOT NULL,
  `auteur_id` int(10) unsigned DEFAULT NULL,
  `auteur_role` enum('client','vendeur','admin') NOT NULL,
  `message` text NOT NULL,
  `cree_le` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_litige_messages` (`litige_id`,`cree_le`),
  KEY `fk_litige_messages_auteur` (`auteur_id`),
  CONSTRAINT `fk_litige_messages_auteur` FOREIGN KEY (`auteur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_litige_messages_litige` FOREIGN KEY (`litige_id`) REFERENCES `litiges` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `litige_messages`
--

LOCK TABLES `litige_messages` WRITE;
/*!40000 ALTER TABLE `litige_messages` DISABLE KEYS */;
INSERT INTO `litige_messages` VALUES (16,8,23,'client','J\'ai pas reçus le maillot','2026-10-07 08:31:47'),(17,8,NULL,'admin','fyodioqiclre','2026-10-07 08:33:50'),(20,8,NULL,'admin','Est-ce que le maillot est bien','2026-10-10 13:33:19'),(21,8,85,'admin','Réclamation jugée fondée : jjkdfgukwdsukv dsmodev b jg  blig ujsd','2026-10-15 16:08:27');
/*!40000 ALTER TABLE `litige_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `litige_photos`
--

DROP TABLE IF EXISTS `litige_photos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `litige_photos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `message_id` int(10) unsigned NOT NULL,
  `fichier` varchar(100) NOT NULL COMMENT 'stockage/litiges/ (hors du site public)',
  `cree_le` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_litige_photos` (`message_id`),
  CONSTRAINT `fk_litige_photos_message` FOREIGN KEY (`message_id`) REFERENCES `litige_messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `litige_photos`
--

LOCK TABLES `litige_photos` WRITE;
/*!40000 ALTER TABLE `litige_photos` DISABLE KEYS */;
/*!40000 ALTER TABLE `litige_photos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `litiges`
--

DROP TABLE IF EXISTS `litiges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `litiges` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `commande_id` int(10) unsigned NOT NULL,
  `client_id` int(10) unsigned NOT NULL,
  `boutique_id` int(10) unsigned NOT NULL,
  `motif` varchar(40) NOT NULL,
  `statut` enum('ouvert','clos_amiable','decide') NOT NULL DEFAULT 'ouvert',
  `statut_avant` varchar(30) NOT NULL COMMENT 'Statut de la commande avant le litige',
  `reponse_limite_le` datetime NOT NULL COMMENT 'Le vendeur a 48 h pour répondre',
  `vendeur_a_repondu` tinyint(1) NOT NULL DEFAULT 0,
  `decision` enum('fondee','non_fondee') DEFAULT NULL,
  `decision_motif` varchar(500) DEFAULT NULL,
  `decide_par` int(10) unsigned DEFAULT NULL,
  `clos_le` datetime DEFAULT NULL,
  `cree_le` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_litiges_commande` (`commande_id`),
  KEY `idx_litiges_statut` (`statut`,`cree_le`),
  KEY `idx_litiges_boutique` (`boutique_id`),
  KEY `fk_litiges_client` (`client_id`),
  KEY `fk_litiges_admin` (`decide_par`),
  CONSTRAINT `fk_litiges_admin` FOREIGN KEY (`decide_par`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_litiges_boutique` FOREIGN KEY (`boutique_id`) REFERENCES `boutiques` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_litiges_client` FOREIGN KEY (`client_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_litiges_commande` FOREIGN KEY (`commande_id`) REFERENCES `commandes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `litiges`
--

LOCK TABLES `litiges` WRITE;
/*!40000 ALTER TABLE `litiges` DISABLE KEYS */;
INSERT INTO `litiges` VALUES (8,29,23,17,'non_recu','decide','livree','2026-10-09 08:31:47',0,'fondee','jjkdfgukwdsukv dsmodev b jg  blig ujsd',85,'2026-10-15 16:08:27','2026-10-07 08:31:47');
/*!40000 ALTER TABLE `litiges` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `maillot_images`
--

DROP TABLE IF EXISTS `maillot_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `maillot_images` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `maillot_id` int(10) unsigned NOT NULL,
  `fichier` varchar(100) NOT NULL COMMENT 'Grande version 1200 px, WebP',
  `miniature` varchar(100) NOT NULL COMMENT 'Miniature 400 px, WebP',
  `ordre` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `cree_le` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_images_maillot` (`maillot_id`,`ordre`),
  CONSTRAINT `fk_images_maillot` FOREIGN KEY (`maillot_id`) REFERENCES `maillots` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `maillot_images`
--

LOCK TABLES `maillot_images` WRITE;
/*!40000 ALTER TABLE `maillot_images` DISABLE KEYS */;
INSERT INTO `maillot_images` VALUES (2,21,'f5bbbd3a89dc56dad20da219dc7d44c2.webp','c9dd161d9d45576717a82e7e805ddbe0.webp',0,'2026-10-05 15:36:10'),(4,24,'98c214a517e85a0df030f201f57873c3.webp','2052e29581a7eb6910472c2a2c638274.webp',0,'2026-10-05 16:41:38'),(13,31,'8d9e45a53fd6d2b18652368ad378f7e9.webp','be4d26720fc2b0d1971559c6750ed047.webp',0,'2026-10-05 18:18:31'),(14,31,'bfa56b8921d3f1e9df6269e1cda39bf8.webp','275737164070c4ac37aa7986dc82b5d5.webp',1,'2026-10-05 18:18:31'),(16,10,'5e8fa674c447b7ba8cd97e59d072b6ba.webp','a2d2be57cc10d6475df1592421ed0a3d.webp',0,'2026-10-07 19:48:37'),(17,9,'0ba77e1c1e436af6f8f7649340c11a6d.webp','08718e04e249f95ae1e4f98c998387bd.webp',0,'2026-10-07 19:48:38'),(18,12,'b119a916c07f63317acaa7ed989faafe.webp','cf8f850ebb5764f4760918db17c294b2.webp',0,'2026-10-07 19:48:38'),(19,33,'cad92085c8750fe1c54f98a99c7fd017.webp','245a1d0af2e4da6b5c89ff82343d813b.webp',0,'2026-10-07 19:48:39'),(20,34,'3a52cb6ba5ea8cef79241650e235557e.webp','cbe71d8498eaa46d238f73c6aa59a599.webp',0,'2026-10-07 19:48:39'),(21,35,'13b44a7e084f6a0723ead307652fff3b.webp','43c27bf86f8dd41a3396b21ffd5d188d.webp',0,'2026-10-07 19:48:40'),(22,8,'7c7f1aba074fd2a81a399d75f7685257.webp','658d3138cc7d857db41ea9072f6a52a3.webp',0,'2026-10-07 19:48:40'),(23,36,'459bfe9eeb6de936363b91603ef7bc40.webp','91ed30c7d1b828736507fd17c1877dcc.webp',0,'2026-10-13 10:35:52'),(24,37,'2d294c51ca76fa0197212b366f90f3ef.webp','8ed34d41703339635676afcf58f532f3.webp',0,'2026-10-13 10:35:52'),(25,38,'a204c96331ef411b3153d550571d87d5.webp','49ce89603f5f1bd5b22808a27d7e32cf.webp',0,'2026-10-13 10:35:52'),(26,32,'9c2320cc623c4db5092cdb47a9d7ea00.webp','568c8bce5f6f69358e6ff37c0da9a275.webp',0,'2026-10-15 22:17:03');
/*!40000 ALTER TABLE `maillot_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `maillot_tailles`
--

DROP TABLE IF EXISTS `maillot_tailles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `maillot_tailles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `maillot_id` int(10) unsigned NOT NULL,
  `taille` varchar(12) NOT NULL COMMENT 'XS à XXXL, 4-6 ans à 12-14 ans',
  `stock` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_maillot_tailles` (`maillot_id`,`taille`),
  KEY `idx_maillot_tailles_taille` (`taille`,`stock`),
  CONSTRAINT `fk_tailles_maillot` FOREIGN KEY (`maillot_id`) REFERENCES `maillots` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=341 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `maillot_tailles`
--

LOCK TABLES `maillot_tailles` WRITE;
/*!40000 ALTER TABLE `maillot_tailles` DISABLE KEYS */;
INSERT INTO `maillot_tailles` VALUES (1,1,'S',3),(2,1,'M',4),(3,1,'L',5),(4,1,'XL',6),(5,1,'XXL',1),(6,1,'XS',0),(7,1,'XXXL',0),(8,2,'S',4),(9,2,'M',5),(10,2,'L',6),(11,2,'XL',1),(12,2,'XXL',2),(13,2,'XS',0),(14,2,'XXXL',0),(15,3,'S',5),(16,3,'M',6),(17,3,'L',1),(18,3,'XL',2),(19,3,'XXL',3),(20,3,'XS',0),(21,3,'XXXL',0),(22,4,'S',6),(23,4,'M',1),(24,4,'L',2),(25,4,'XL',3),(26,4,'XXL',4),(27,4,'XS',0),(28,4,'XXXL',0),(29,5,'S',1),(30,5,'M',2),(31,5,'L',3),(32,5,'XL',4),(33,5,'XXL',5),(34,5,'XS',0),(35,5,'XXXL',0),(36,6,'S',0),(37,6,'M',0),(38,6,'L',0),(39,6,'XL',0),(40,6,'XXL',0),(41,6,'XS',0),(42,6,'XXXL',0),(43,7,'S',3),(44,7,'M',4),(45,7,'L',5),(46,7,'XL',6),(47,7,'XXL',1),(48,7,'XS',0),(49,7,'XXXL',0),(50,8,'S',4),(51,8,'M',5),(52,8,'L',6),(53,8,'XL',1),(54,8,'XXL',2),(55,8,'XS',0),(56,8,'XXXL',0),(57,9,'S',5),(58,9,'M',6),(59,9,'L',1),(60,9,'XL',2),(61,9,'XXL',3),(62,9,'XS',0),(63,9,'XXXL',0),(64,10,'S',6),(65,10,'M',1),(66,10,'L',2),(67,10,'XL',3),(68,10,'XXL',4),(69,10,'XS',0),(70,10,'XXXL',0),(71,11,'S',1),(72,11,'M',2),(73,11,'L',3),(74,11,'XL',4),(75,11,'XXL',5),(76,11,'XS',0),(77,11,'XXXL',0),(78,12,'S',2),(79,12,'M',3),(80,12,'L',4),(81,12,'XL',5),(82,12,'XXL',6),(83,12,'XS',0),(84,12,'XXXL',0),(85,13,'S',3),(86,13,'M',4),(87,13,'L',5),(88,13,'XL',6),(89,13,'XXL',1),(90,13,'XS',0),(91,13,'XXXL',0),(92,14,'S',4),(93,14,'M',5),(94,14,'L',6),(95,14,'XL',1),(96,14,'XXL',2),(97,14,'XS',0),(98,14,'XXXL',0),(99,15,'S',5),(100,15,'M',6),(101,15,'L',1),(102,15,'XL',2),(103,15,'XXL',3),(104,15,'XS',0),(105,15,'XXXL',0),(106,16,'S',6),(107,16,'M',1),(108,16,'L',2),(109,16,'XL',3),(110,16,'XXL',4),(111,16,'XS',0),(112,16,'XXXL',0),(113,17,'S',1),(114,17,'M',2),(115,17,'L',3),(116,17,'XL',4),(117,17,'XXL',5),(118,17,'XS',0),(119,17,'XXXL',0),(120,18,'S',0),(121,18,'M',0),(122,18,'L',0),(123,18,'XL',0),(124,18,'XXL',0),(125,18,'XS',0),(126,18,'XXXL',0),(127,19,'S',3),(128,19,'M',4),(129,19,'L',5),(130,19,'XL',6),(131,19,'XXL',1),(132,19,'XS',0),(133,19,'XXXL',0),(134,20,'S',4),(135,20,'M',5),(136,20,'L',6),(137,20,'XL',1),(138,20,'XXL',2),(139,20,'XS',0),(140,20,'XXXL',0),(271,21,'S',2),(272,21,'M',3),(276,24,'XS',4),(277,24,'S',3),(278,24,'M',7),(279,24,'L',10),(280,24,'XL',15),(281,24,'XXL',0),(294,31,'XS',5),(295,31,'S',7),(296,31,'M',8),(297,31,'L',7),(298,31,'XL',10),(299,31,'XXL',0),(303,32,'S',4),(304,32,'M',7),(305,32,'L',10),(306,32,'XL',12),(307,32,'XXL',0),(308,33,'S',3),(309,33,'M',5),(310,33,'L',4),(311,33,'XL',2),(312,34,'S',3),(313,34,'M',5),(314,34,'L',4),(315,34,'XL',2),(316,35,'S',3),(317,35,'M',5),(318,35,'L',4),(319,35,'XL',2),(326,37,'S',2),(327,37,'M',3),(328,37,'L',3),(329,37,'XL',2),(330,38,'S',2),(331,38,'M',3),(332,38,'L',3),(333,38,'XL',2),(337,36,'M',1),(338,36,'L',2),(339,36,'XL',0);
/*!40000 ALTER TABLE `maillot_tailles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `maillots`
--

DROP TABLE IF EXISTS `maillots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `maillots` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `boutique_id` int(10) unsigned NOT NULL,
  `categorie_id` int(10) unsigned NOT NULL,
  `equipe_id` int(10) unsigned NOT NULL,
  `titre` varchar(100) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `saison` varchar(9) NOT NULL COMMENT 'AAAA/AAAA ou AAAA (rétro)',
  `type` enum('domicile','exterieur','third','gardien','entrainement','autre') NOT NULL,
  `authenticite` enum('officiel','replique','version_fan') NOT NULL,
  `etat` enum('neuf_etiquette','neuf_sans_etiquette','occasion_tres_bon','occasion_bon') NOT NULL,
  `prix` int(10) unsigned NOT NULL COMMENT 'FCFA, de 1 000 à 500 000',
  `flocage_propose` tinyint(1) NOT NULL DEFAULT 0,
  `flocage_supplement` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'FCFA',
  `description` text DEFAULT NULL,
  `statut` enum('brouillon','publie','masque_vendeur','masque_admin','supprime') NOT NULL DEFAULT 'brouillon',
  `texte_recherche` varchar(255) NOT NULL DEFAULT '',
  `publie_le` datetime DEFAULT NULL,
  `cree_le` datetime NOT NULL DEFAULT current_timestamp(),
  `modifie_le` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `supprime_le` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_maillots_statut_publie` (`statut`,`publie_le`),
  KEY `idx_maillots_categorie` (`categorie_id`),
  KEY `idx_maillots_equipe` (`equipe_id`),
  KEY `idx_maillots_prix` (`prix`),
  KEY `idx_maillots_boutique` (`boutique_id`),
  FULLTEXT KEY `ft_maillots_recherche` (`texte_recherche`),
  CONSTRAINT `fk_maillots_boutique` FOREIGN KEY (`boutique_id`) REFERENCES `boutiques` (`id`),
  CONSTRAINT `fk_maillots_categorie` FOREIGN KEY (`categorie_id`) REFERENCES `categories` (`id`),
  CONSTRAINT `fk_maillots_equipe` FOREIGN KEY (`equipe_id`) REFERENCES `equipes` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `maillots`
--

LOCK TABLES `maillots` WRITE;
/*!40000 ALTER TABLE `maillots` DISABLE KEYS */;
INSERT INTO `maillots` VALUES (1,4,1,1,'Maillot Sénégal domicile 2025/2026','maillot-senegal-domicile-2025-2026','2025/2026','domicile','officiel','neuf_etiquette',35000,1,3000,'[Donnée de test] Maillot domicile blanc des Lions de la Teranga. Coupe ajustée, tissu respirant.','supprime','Maillot Sénégal domicile 2025/2026 Sénégal 2025/2026','2026-10-02 22:27:59','2026-10-02 23:27:59','2026-10-13 10:39:24','2026-10-13 10:39:24'),(2,4,1,1,'Maillot Sénégal extérieur 2025/2026','maillot-senegal-exterieur-2025-2026','2025/2026','exterieur','replique','neuf_sans_etiquette',12000,1,2000,'[Donnée de test] Réplique du maillot extérieur vert. Vendu comme réplique.','supprime','Maillot Sénégal extérieur 2025/2026 Sénégal 2025/2026','2026-10-02 21:27:59','2026-10-02 23:27:59','2026-10-13 10:39:24','2026-10-13 10:39:24'),(3,4,1,3,'Maillot Maroc domicile 2024/2025','maillot-maroc-domicile-2024-2025','2024/2025','domicile','version_fan','neuf_etiquette',25000,0,0,'[Donnée de test] Version fan du maillot rouge des Lions de l\'Atlas.','supprime','Maillot Maroc domicile 2024/2025 Maroc 2024/2025','2026-10-02 20:27:59','2026-10-02 23:27:59','2026-10-13 10:39:24','2026-10-13 10:39:24'),(4,4,1,2,'Maillot Côte d\'Ivoire domicile 2024','maillot-cote-d-ivoire-domicile-2024','2024','domicile','officiel','neuf_sans_etiquette',30000,1,3000,'[Donnée de test] Maillot orange des Éléphants.','supprime','Maillot Côte d\'Ivoire domicile 2024 Côte d\'Ivoire 2024','2026-10-02 19:27:59','2026-10-02 23:27:59','2026-10-13 10:39:24','2026-10-13 10:39:24'),(5,4,1,27,'Maillot Brésil domicile 2024/2025','maillot-bresil-domicile-2024-2025','2024/2025','domicile','replique','neuf_etiquette',10000,1,2000,'[Donnée de test] Réplique du maillot jaune de la Seleção.','supprime','Maillot Brésil domicile 2024/2025 Brésil 2024/2025','2026-10-02 18:27:59','2026-10-02 23:27:59','2026-10-13 10:39:24','2026-10-13 10:39:24'),(6,4,1,18,'Maillot France domicile 2024/2025','maillot-france-domicile-2024-2025','2024/2025','domicile','officiel','occasion_tres_bon',22000,0,0,'[Donnée de test] Porté deux fois, comme neuf.','supprime','Maillot France domicile 2024/2025 France 2024/2025','2026-10-02 17:27:59','2026-10-02 23:27:59','2026-10-13 10:39:24','2026-10-13 10:39:24'),(7,4,1,28,'Maillot Argentine domicile 2024','maillot-argentine-domicile-2024','2024','domicile','version_fan','neuf_etiquette',27000,1,3000,'[Donnée de test] Rayures ciel et blanc.','supprime','Maillot Argentine domicile 2024 Argentine 2024','2026-10-02 16:27:59','2026-10-02 23:27:59','2026-10-13 10:39:24','2026-10-13 10:39:24'),(8,4,2,48,'Maillot Real Madrid domicile 2025/2026','maillot-real-madrid-domicile-2025-2026','2025/2026','domicile','replique','neuf_etiquette',10000,1,2000,'[Donnée de test] Maillot blanc domicile.','publie','Maillot Real Madrid domicile 2025/2026 Real Madrid 2025/2026','2026-10-02 15:27:59','2026-10-02 23:27:59','2026-10-15 22:06:41',NULL),(9,4,2,49,'Maillot FC Barcelone domicile 2025/2026','maillot-fc-barcelone-domicile-2025-2026','2025/2026','domicile','replique','neuf_etiquette',8500,1,1500,'[Donnée de test] Réplique blaugrana.','publie','Maillot FC Barcelone domicile 2025/2026 FC Barcelone 2025/2026','2026-10-02 14:27:59','2026-10-02 23:27:59','2026-10-15 22:06:41',NULL),(10,4,2,43,'Maillot PSG domicile 2025/2026','maillot-psg-domicile-2025-2026','2025/2026','domicile','replique','neuf_etiquette',10000,1,2000,'[Donnée de test] Bleu marine, bande centrale rouge.','publie','Maillot PSG domicile 2025/2026 Paris Saint-Germain 2025/2026','2026-10-02 13:27:59','2026-10-02 23:27:59','2026-10-15 22:06:41',NULL),(11,4,2,53,'Maillot Liverpool domicile 2024/2025','maillot-liverpool-domicile-2024-2025','2024/2025','domicile','version_fan','neuf_sans_etiquette',24000,0,0,'[Donnée de test] Rouge, version supporter.','supprime','Maillot Liverpool domicile 2024/2025 Liverpool 2024/2025','2026-10-02 12:27:59','2026-10-02 23:27:59','2026-10-13 10:39:24','2026-10-13 10:39:24'),(12,4,2,52,'Maillot Manchester City domicile 2024/2025','maillot-manchester-city-domicile-2024-2025','2024/2025','domicile','replique','neuf_etiquette',7500,0,0,'[Donnée de test] Réplique bleu ciel.','publie','Maillot Manchester City domicile 2024/2025 Manchester City 2024/2025','2026-10-02 11:27:59','2026-10-02 23:27:59','2026-10-15 22:06:41',NULL),(13,4,2,64,'Maillot Al-Nassr domicile 2024/2025','maillot-al-nassr-domicile-2024-2025','2024/2025','domicile','replique','neuf_etiquette',12500,1,2000,'[Donnée de test] Jaune et bleu.','supprime','Maillot Al-Nassr domicile 2024/2025 Al-Nassr 2024/2025','2026-10-02 10:27:59','2026-10-02 23:27:59','2026-10-13 10:39:24','2026-10-13 10:39:24'),(14,4,2,33,'Maillot ASC Jaraaf domicile 2024/2025','maillot-asc-jaraaf-domicile-2024-2025','2024/2025','domicile','officiel','neuf_etiquette',15000,1,2000,'[Donnée de test] Le club de la Médina.','supprime','Maillot ASC Jaraaf domicile 2024/2025 ASC Jaraaf 2024/2025','2026-10-02 09:27:59','2026-10-02 23:27:59','2026-10-13 10:39:24','2026-10-13 10:39:24'),(15,5,3,1,'Maillot Sénégal 2002 rétro','maillot-senegal-2002-retro','2002','domicile','replique','neuf_etiquette',18000,1,2500,'[Donnée de test] L\'épopée de 2002, réédition rétro.','supprime','Maillot Sénégal 2002 rétro Sénégal 2002','2026-10-02 08:27:59','2026-10-02 23:27:59','2026-10-13 10:39:24','2026-10-13 10:39:24'),(16,5,3,8,'Maillot Cameroun 2002 rétro sans manches','maillot-cameroun-2002-retro-sans-manches','2002','domicile','replique','neuf_etiquette',17000,0,0,'[Donnée de test] Le célèbre maillot sans manches.','supprime','Maillot Cameroun 2002 rétro sans manches Cameroun 2002','2026-10-02 07:27:59','2026-10-02 23:27:59','2026-10-13 10:39:24','2026-10-13 10:39:24'),(17,5,3,24,'Maillot Pays-Bas 1988 rétro','maillot-pays-bas-1988-retro','1988','domicile','replique','neuf_etiquette',16000,0,0,'[Donnée de test] Orange et motifs géométriques.','supprime','Maillot Pays-Bas 1988 rétro Pays-Bas 1988','2026-10-02 06:27:59','2026-10-02 23:27:59','2026-10-13 10:39:24','2026-10-13 10:39:24'),(18,5,3,60,'Maillot AC Milan 1989/1990 rétro','maillot-ac-milan-1989-1990-retro','1989/1990','domicile','officiel','occasion_bon',65000,0,0,'[Donnée de test] Maillot d\'époque, bon état.','supprime','Maillot AC Milan 1989/1990 rétro AC Milan 1989/1990','2026-10-02 05:27:59','2026-10-02 23:27:59','2026-10-13 10:39:24','2026-10-13 10:39:24'),(19,5,3,27,'Maillot Brésil 1970 rétro','maillot-bresil-1970-retro','1970','domicile','replique','neuf_etiquette',15000,1,2000,'[Donnée de test] Le Brésil de 1970, réédition.','supprime','Maillot Brésil 1970 rétro Brésil 1970','2026-10-02 04:27:59','2026-10-02 23:27:59','2026-10-13 10:39:24','2026-10-13 10:39:24'),(20,5,3,18,'Maillot France 1998 rétro','maillot-france-1998-retro','1998','domicile','replique','neuf_sans_etiquette',16500,1,2000,'[Donnée de test] Réédition du maillot de 1998.','supprime','Maillot France 1998 rétro France 1998','2026-10-02 03:27:59','2026-10-02 23:27:59','2026-10-13 10:39:24','2026-10-13 10:39:24'),(21,4,1,1,'Maillot S?n?gal domicile 2025/2026 vendeur','maillot-s-n-gal-domicile-2025-2026-vendeur','2025/2026','domicile','officiel','neuf_etiquette',28000,0,0,NULL,'supprime','Maillot S?n?gal domicile 2025/2026 vendeur Sénégal 2025/2026','2026-10-05 15:36:10','2026-10-05 15:36:10','2026-10-05 15:37:03','2026-10-05 15:37:03'),(24,17,2,49,'Maillot Barçalone','maillot-bar-alone','2026/2027','domicile','version_fan','neuf_etiquette',5000,0,0,'Millot barcelone 2026/2027 de très bonne qualiter','supprime','Maillot Barçalone FC Barcelone 2026/2027','2026-10-05 16:41:38','2026-10-05 16:41:38','2026-10-05 17:01:08','2026-10-05 17:01:08'),(31,17,2,49,'Maillot Barçalone','maillot-bar-alone','2026/2027','domicile','version_fan','neuf_etiquette',5000,1,2500,'Miallot domicile de très bonne qualité','publie','Maillot Barçalone FC Barcelone 2026/2027','2026-10-05 18:18:31','2026-10-05 18:18:31','2026-10-05 18:18:31',NULL),(32,17,2,43,'Maillot paris','maillot-paris','2026/2027','domicile','version_fan','neuf_etiquette',6000,0,0,NULL,'publie','Maillot paris Paris Saint-Germain 2026/2027','2026-10-07 08:27:17','2026-10-07 08:27:17','2026-10-07 08:27:17',NULL),(33,4,2,57,'Maillot Bayern Munich domicile 2024/2025','maillot-bayern-munich-domicile-2024-2025','2024/2025','domicile','replique','neuf_etiquette',9500,0,0,'[Donnée de test] Rouge et blanc, sponsor Telekom.','publie','Maillot Bayern Munich domicile 2024/2025 Bayern Munich 2024/2025','2026-10-07 19:48:36','2026-10-07 19:48:36','2026-10-15 22:06:41',NULL),(34,4,2,54,'Maillot Chelsea domicile 2024/2025','maillot-chelsea-domicile-2024-2025','2024/2025','domicile','replique','neuf_etiquette',9500,0,0,'[Donnée de test] Bleu royal à motifs graphiques.','publie','Maillot Chelsea domicile 2024/2025 Chelsea 2024/2025','2026-10-07 19:48:36','2026-10-07 19:48:36','2026-10-15 22:06:41',NULL),(35,4,1,19,'Maillot Espagne extérieur 2024','maillot-espagne-ext-rieur-2024','2024','exterieur','version_fan','neuf_etiquette',9000,0,0,'[Donnée de test] Blanc cassé, finitions rouge et or.','publie','Maillot Espagne extérieur 2024 Espagne 2024','2026-10-07 19:48:36','2026-10-07 19:48:36','2026-10-15 22:06:41',NULL),(36,17,3,18,'Maillot France 1998 domicile rétro','maillot-france-1998-domicile-retro','1998','domicile','version_fan','neuf_etiquette',9000,0,0,'Le maillot bleu de la France championne du monde 1998, avec la bande rouge et le coq. Réplique, coupe classique.','publie','Maillot France 1998 domicile rétro France 1998','2026-10-13 10:35:52','2026-10-13 10:35:52','2026-10-13 11:33:52',NULL),(37,4,3,1,'Maillot Sénégal 2002 extérieur rétro','maillot-senegal-2002-exterieur-retro','2002','exterieur','replique','neuf_etiquette',9500,0,0,'Le maillot vert des Lions de la Téranga de la Coupe du monde 2002, bandes jaunes et rouges. Réplique Le Coq Sportif.','publie','Maillot Sénégal 2002 extérieur rétro Sénégal 2002','2026-10-13 10:35:52','2026-10-13 10:35:52','2026-10-13 10:38:17',NULL),(38,17,2,52,'Maillot Manchester City domicile 2025-2026','maillot-manchester-city-domicile-2025-2026','2025/2026','domicile','replique','neuf_etiquette',8500,0,0,'Maillot domicile bleu ciel de Manchester City, sponsor Etihad Airways. Réplique, tissu léger.','publie','Maillot Manchester City domicile 2025-2026 Manchester City 2025/2026','2026-10-13 10:35:52','2026-10-13 10:35:52','2026-10-13 10:35:52',NULL);
/*!40000 ALTER TABLE `maillots` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `messages_contact`
--

DROP TABLE IF EXISTS `messages_contact`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `messages_contact` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `utilisateur_id` int(10) unsigned DEFAULT NULL COMMENT 'Compte connecté au moment de l''envoi',
  `nom` varchar(101) NOT NULL,
  `email` varchar(190) NOT NULL,
  `telephone` varchar(13) DEFAULT NULL,
  `sujet` enum('commande','vendeur','compte','signalement','partenariat','autre') NOT NULL,
  `numero_commande` varchar(20) DEFAULT NULL,
  `message` text NOT NULL,
  `adresse_ip` varchar(45) DEFAULT NULL,
  `statut` enum('nouveau','traite') NOT NULL DEFAULT 'nouveau',
  `cree_le` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_messages_statut` (`statut`,`cree_le`),
  KEY `idx_messages_ip` (`adresse_ip`,`cree_le`),
  KEY `fk_messages_utilisateur` (`utilisateur_id`),
  CONSTRAINT `fk_messages_utilisateur` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `messages_contact`
--

LOCK TABLES `messages_contact` WRITE;
/*!40000 ALTER TABLE `messages_contact` DISABLE KEYS */;
INSERT INTO `messages_contact` VALUES (5,1,'Lama Bah','lbah89381@gmail.com','+221706464785','autre',NULL,'comment on peut devenir un vendeur sur SAMA MAILLOT','::1','nouveau','2026-10-06 17:30:20');
/*!40000 ALTER TABLE `messages_contact` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mouvements_portefeuille`
--

DROP TABLE IF EXISTS `mouvements_portefeuille`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mouvements_portefeuille` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `boutique_id` int(10) unsigned NOT NULL,
  `commande_id` int(10) unsigned DEFAULT NULL,
  `versement_id` int(10) unsigned DEFAULT NULL,
  `operation` enum('credit','deblocage','blocage','liberation','remboursement','versement') NOT NULL,
  `compartiment` enum('en_attente','disponible','bloque','verse') NOT NULL,
  `montant` int(11) NOT NULL COMMENT 'FCFA, positif ou négatif',
  `libelle` varchar(160) NOT NULL DEFAULT '',
  `cree_le` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_mouvements_boutique` (`boutique_id`,`compartiment`),
  KEY `idx_mouvements_commande` (`commande_id`),
  KEY `fk_mouvements_versement` (`versement_id`),
  CONSTRAINT `fk_mouvements_boutique` FOREIGN KEY (`boutique_id`) REFERENCES `boutiques` (`id`),
  CONSTRAINT `fk_mouvements_commande` FOREIGN KEY (`commande_id`) REFERENCES `commandes` (`id`),
  CONSTRAINT `fk_mouvements_versement` FOREIGN KEY (`versement_id`) REFERENCES `versements` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mouvements_portefeuille`
--

LOCK TABLES `mouvements_portefeuille` WRITE;
/*!40000 ALTER TABLE `mouvements_portefeuille` DISABLE KEYS */;
INSERT INTO `mouvements_portefeuille` VALUES (14,4,39,NULL,'credit','en_attente',44000,'Commande CMD-2026-000039 payée en ligne','2026-10-10 15:36:33'),(15,4,39,NULL,'remboursement','en_attente',-44000,'Commande CMD-2026-000039 annulée : client remboursé','2026-10-13 09:22:18');
/*!40000 ALTER TABLE `mouvements_portefeuille` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `utilisateur_id` int(10) unsigned NOT NULL,
  `type` varchar(40) NOT NULL COMMENT 'Ex. : commande_nouvelle, commande_confirmee…',
  `titre` varchar(120) NOT NULL,
  `message` varchar(255) NOT NULL,
  `lien` varchar(255) DEFAULT NULL COMMENT 'Chemin interne à ouvrir (ex. vendeur/commande.php?id=12)',
  `lue_le` datetime DEFAULT NULL,
  `cree_le` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notifications_utilisateur` (`utilisateur_id`,`lue_le`,`cree_le`),
  CONSTRAINT `fk_notifications_utilisateur` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=297 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (37,23,'role_modifie','Votre compte est maintenant « administrateur »','Changement effectué par l\'administration.','admin/',NULL,'2026-10-06 13:14:07'),(38,23,'role_modifie','Votre compte est maintenant « client »','Changement effectué par l\'administration.','compte/',NULL,'2026-10-06 13:14:13'),(59,1,'commande_nouvelle','Nouvelle commande CMD-2026-000001','Ablaye Diallo a commandé pour 10 000 FCFA (+ livraison à convenir). À confirmer sous 24 h.','vendeur/commande.php?id=1','2026-10-06 17:27:56','2026-10-06 14:10:18'),(60,23,'commande_enregistree','Commande CMD-2026-000001 enregistrée','Le vendeur doit la confirmer, puis il vous appellera pour la livraison.','compte/commande.php?id=1',NULL,'2026-10-06 14:10:18'),(61,23,'commande_confirmee','Commande CMD-2026-000001 confirmée','Le vendeur prépare votre maillot.','compte/commande.php?id=1',NULL,'2026-10-06 14:11:27'),(62,23,'commande_expediee','Commande CMD-2026-000001 en route','Le vendeur vous contactera pour la remise. Vous payez à la réception.','compte/commande.php?id=1',NULL,'2026-10-06 14:11:31'),(63,23,'commande_livree','Commande CMD-2026-000001 livrée','Le vendeur a marqué la commande livrée. Confirmez la réception, ou signalez un problème sous 48 h.','compte/commande.php?id=1',NULL,'2026-10-06 14:11:36'),(75,13,'abonnement_rappel','Votre abonnement est terminé','Votre abonnement est terminé. Vos maillots seront masqués dans 2 jours. Ajout de maillots bloqué : renouvelez-le dès maintenant.','vendeur/abonnement.php',NULL,'2026-10-06 15:22:04'),(85,13,'abonnement_rappel','Votre abonnement est terminé','Votre abonnement est terminé. Vos maillots seront masqués dans 1 jour. Ajout de maillots bloqué : renouvelez-le dès maintenant.','vendeur/abonnement.php',NULL,'2026-10-06 17:25:25'),(152,1,'commande_nouvelle','Nouvelle commande CMD-2026-000019','Ablaye Diallo a commandé pour 5 000 FCFA (+ livraison à convenir). À confirmer sous 24 h.','vendeur/commande.php?id=19',NULL,'2026-10-06 19:02:15'),(153,23,'commande_enregistree','Commande CMD-2026-000019 enregistrée','Le vendeur doit la confirmer, puis il vous appellera pour la livraison.','compte/commande.php?id=19',NULL,'2026-10-06 19:02:15'),(154,23,'commande_confirmee','Commande CMD-2026-000019 confirmée','Le vendeur prépare votre maillot.','compte/commande.php?id=19',NULL,'2026-10-06 19:02:56'),(155,23,'commande_expediee','Commande CMD-2026-000019 en route','Le vendeur vous contactera pour la remise. Vous payez à la réception.','compte/commande.php?id=19',NULL,'2026-10-06 19:02:59'),(156,23,'commande_livree','Commande CMD-2026-000019 livrée','Le vendeur a marqué la commande livrée. Confirmez la réception, ou signalez un problème sous 48 h.','compte/commande.php?id=19',NULL,'2026-10-06 19:03:03'),(157,1,'commande_recue','Commande CMD-2026-000019 reçue','Le client a confirmé la réception.','vendeur/commande.php?id=19',NULL,'2026-10-06 19:03:30'),(158,23,'avis_invitation','Notez le vendeur de la commande CMD-2026-000019','Votre avis aide les autres clients à choisir. Cela prend 30 secondes.','compte/avis.php?commande=19',NULL,'2026-10-06 19:03:30'),(159,1,'avis_nouveau','Nouvel avis : ★★★★★','« Livraison rapide et maillot de très bonne qualitè »','vendeur/avis.php',NULL,'2026-10-06 19:04:34'),(209,13,'abonnement_rappel','Votre abonnement est terminé','Votre abonnement est terminé. Vos maillots seront masqués dans 1 jour. Ajout de maillots bloqué : renouvelez-le dès maintenant.','vendeur/abonnement.php',NULL,'2026-10-07 08:20:16'),(210,1,'commande_nouvelle','Nouvelle commande CMD-2026-000029','Ablaye Diallo a commandé pour 6 000 FCFA (+ livraison à convenir). À confirmer sous 24 h.','vendeur/commande.php?id=29',NULL,'2026-10-07 08:29:51'),(211,23,'commande_enregistree','Commande CMD-2026-000029 enregistrée','Le vendeur doit la confirmer, puis il vous appellera pour la livraison.','compte/commande.php?id=29',NULL,'2026-10-07 08:29:51'),(212,23,'commande_confirmee','Commande CMD-2026-000029 confirmée','Le vendeur prépare votre maillot.','compte/commande.php?id=29',NULL,'2026-10-07 08:30:22'),(213,23,'commande_expediee','Commande CMD-2026-000029 en route','Le vendeur vous contactera pour la remise. Vous payez à la réception.','compte/commande.php?id=29',NULL,'2026-10-07 08:30:29'),(214,23,'commande_livree','Commande CMD-2026-000029 livrée','Le vendeur a marqué la commande livrée. Confirmez la réception, ou signalez un problème sous 48 h.','compte/commande.php?id=29',NULL,'2026-10-07 08:30:33'),(215,1,'litige_ouvert','Réclamation sur la commande CMD-2026-000029','Maillot non reçu. Répondez sous 48 h : sans réponse, l\'administration tranchera.','vendeur/litige.php?id=8','2026-10-07 08:32:24','2026-10-07 08:31:47'),(217,23,'litige_message','Réclamation CMD-2026-000029 : nouveau message','fyodioqiclre','compte/litige.php?id=8',NULL,'2026-10-07 08:33:50'),(218,1,'litige_message','Réclamation CMD-2026-000029 : nouveau message','fyodioqiclre','vendeur/litige.php?id=8','2026-10-07 08:34:18','2026-10-07 08:33:50'),(219,13,'abonnement_rappel','Vos maillots sont masqués','Votre abonnement a expiré : votre boutique et vos maillots sont masqués. Vous pouvez encore livrer vos commandes en cours et renouveler à tout moment.','vendeur/abonnement.php',NULL,'2026-10-07 16:03:57'),(237,1,'commande_recue','Commande CMD-2026-000001 reçue','Délai de réclamation écoulé : commande terminée.','vendeur/commande.php?id=1',NULL,'2026-10-08 16:16:35'),(238,23,'avis_invitation','Notez le vendeur de la commande CMD-2026-000001','Votre avis aide les autres clients à choisir. Cela prend 30 secondes.','compte/avis.php?commande=1',NULL,'2026-10-08 16:16:35'),(239,23,'litige_message','Réclamation CMD-2026-000029 : nouveau message','Est-ce que le maillot est bien','compte/litige.php?id=8',NULL,'2026-10-10 13:33:19'),(240,1,'litige_message','Réclamation CMD-2026-000029 : nouveau message','Est-ce que le maillot est bien','vendeur/litige.php?id=8',NULL,'2026-10-10 13:33:19'),(258,12,'commande_nouvelle','Nouvelle commande CMD-2026-000039 (payée en ligne)','Aliou Bah a payé 44 000 FCFA. À confirmer sous 48 h. Demandez le code de livraison à la remise.','vendeur/commande.php?id=39',NULL,'2026-10-10 15:36:33'),(259,71,'paiement_recu','Paiement reçu : commande CMD-2026-000039','44 000 FCFA payés en ligne. Votre code de livraison est dans la commande : donnez-le seulement à la réception.','commande/suivi.php?c=39&j=f53c73b19f8df4cfc3a40da4f1e43feba0c90f7fdc64e0b749f1350e5793caf6',NULL,'2026-10-10 15:36:33'),(260,1,'commande_nouvelle','Nouvelle commande CMD-2026-000040','Aliou Bah a commandé pour 6 000 FCFA (+ livraison à convenir). À confirmer sous 24 h.','vendeur/commande.php?id=40',NULL,'2026-10-11 02:08:17'),(261,72,'commande_enregistree','Commande CMD-2026-000040 enregistrée','Le vendeur doit la confirmer, puis il vous appellera pour la livraison.','commande/suivi.php?c=40&j=4fe10434c9113943bdd4e6b5079d24d913772ed404bcf516dc2c913f589ec94c',NULL,'2026-10-11 02:08:17'),(262,72,'commande_confirmee','Commande CMD-2026-000040 confirmée','Le vendeur prépare votre maillot.','commande/suivi.php?c=40&j=4fe10434c9113943bdd4e6b5079d24d913772ed404bcf516dc2c913f589ec94c',NULL,'2026-10-11 02:09:51'),(263,72,'commande_expediee','Commande CMD-2026-000040 en route','Le vendeur vous contactera pour la remise. Vous payez à la réception.','commande/suivi.php?c=40&j=4fe10434c9113943bdd4e6b5079d24d913772ed404bcf516dc2c913f589ec94c',NULL,'2026-10-11 02:09:56'),(264,72,'commande_livree','Commande CMD-2026-000040 livrée','Le vendeur a marqué la commande livrée. Confirmez la réception, ou signalez un problème sous 48 h.','commande/suivi.php?c=40&j=4fe10434c9113943bdd4e6b5079d24d913772ed404bcf516dc2c913f589ec94c',NULL,'2026-10-11 02:09:58'),(265,12,'commande_rappel','Commande CMD-2026-000039 à confirmer','Moins de 12 h restantes, sinon elle sera annulée automatiquement.','vendeur/commande.php?id=39',NULL,'2026-10-11 15:39:09'),(266,71,'commande_annulee','Commande CMD-2026-000039 annulée','Non confirmée par le vendeur sous 48 h. Vous serez remboursé de 44 000 FCFA par SAMA MAILLOT.','commande/suivi.php?c=39&j=f53c73b19f8df4cfc3a40da4f1e43feba0c90f7fdc64e0b749f1350e5793caf6',NULL,'2026-10-13 09:22:18'),(267,12,'commande_annulee','Commande CMD-2026-000039 annulée','Non confirmée par le vendeur sous 48 h. Vous serez remboursé de 44 000 FCFA par SAMA MAILLOT.','vendeur/commande.php?id=39','2026-10-15 12:51:11','2026-10-13 09:22:18'),(268,1,'commande_recue','Commande CMD-2026-000040 reçue','Délai de réclamation écoulé : commande terminée.','vendeur/commande.php?id=40',NULL,'2026-10-13 09:22:18'),(269,72,'avis_invitation','Notez le vendeur de la commande CMD-2026-000040','Votre avis aide les autres clients à choisir. Cela prend 30 secondes.','compte/avis.php?commande=40',NULL,'2026-10-13 09:22:18'),(270,12,'commande_nouvelle','Nouvelle commande CMD-2026-000041','Aliou Bah a commandé pour 44 000 FCFA. À confirmer sous 24 h.','vendeur/commande.php?id=41','2026-10-15 12:51:11','2026-10-13 10:04:14'),(271,83,'commande_enregistree','Commande CMD-2026-000041 enregistrée','Le vendeur doit la confirmer, puis il vous appellera pour la livraison.','commande/suivi.php?c=41&j=7affeb572c14f92df436d4497a44c4e4fe3d3cdb9265ca41226c1f2991ffeaa1',NULL,'2026-10-13 10:04:14'),(272,1,'commande_nouvelle','Nouvelle commande CMD-2026-000042','Aliou Bah a commandé pour 5 000 FCFA (+ livraison à convenir). À confirmer sous 24 h.','vendeur/commande.php?id=42',NULL,'2026-10-13 10:05:44'),(273,84,'commande_enregistree','Commande CMD-2026-000042 enregistrée','Le vendeur doit la confirmer, puis il vous appellera pour la livraison.','commande/suivi.php?c=42&j=54f7d530cb2c68d31a9852745e122a2ad627a87ddfeb14cfa3a2969ef08c7cc4',NULL,'2026-10-13 10:05:44'),(274,84,'commande_confirmee','Commande CMD-2026-000042 confirmée','Le vendeur prépare votre maillot.','commande/suivi.php?c=42&j=54f7d530cb2c68d31a9852745e122a2ad627a87ddfeb14cfa3a2969ef08c7cc4',NULL,'2026-10-13 10:06:07'),(275,84,'commande_expediee','Commande CMD-2026-000042 en route','Le vendeur vous contactera pour la remise. Vous payez à la réception.','commande/suivi.php?c=42&j=54f7d530cb2c68d31a9852745e122a2ad627a87ddfeb14cfa3a2969ef08c7cc4',NULL,'2026-10-13 10:06:12'),(276,84,'commande_livree','Commande CMD-2026-000042 livrée','Le vendeur a marqué la commande livrée. Confirmez la réception, ou signalez un problème sous 48 h.','commande/suivi.php?c=42&j=54f7d530cb2c68d31a9852745e122a2ad627a87ddfeb14cfa3a2969ef08c7cc4',NULL,'2026-10-13 10:06:15'),(277,1,'commande_nouvelle','Nouvelle commande CMD-2026-000043','Aliou Bah a commandé pour 6 000 FCFA (+ livraison à convenir). À confirmer sous 24 h.','vendeur/commande.php?id=43',NULL,'2026-10-13 10:25:11'),(278,86,'commande_enregistree','Commande CMD-2026-000043 enregistrée','Le vendeur doit la confirmer, puis il vous appellera pour la livraison.','commande/suivi.php?c=43&j=7cc73dfcc93af714e4224a46376a2ad42252e1bd4cb7bf1185436366105ad645',NULL,'2026-10-13 10:25:11'),(279,86,'commande_confirmee','Commande CMD-2026-000043 confirmée','Le vendeur prépare votre maillot.','commande/suivi.php?c=43&j=7cc73dfcc93af714e4224a46376a2ad42252e1bd4cb7bf1185436366105ad645',NULL,'2026-10-13 10:25:51'),(280,86,'commande_expediee','Commande CMD-2026-000043 en route','Le vendeur vous contactera pour la remise. Vous payez à la réception.','commande/suivi.php?c=43&j=7cc73dfcc93af714e4224a46376a2ad42252e1bd4cb7bf1185436366105ad645',NULL,'2026-10-13 10:25:54'),(281,86,'commande_livree','Commande CMD-2026-000043 livrée','Le vendeur a marqué la commande livrée. Confirmez la réception, ou signalez un problème sous 48 h.','commande/suivi.php?c=43&j=7cc73dfcc93af714e4224a46376a2ad42252e1bd4cb7bf1185436366105ad645',NULL,'2026-10-13 10:25:55'),(282,83,'commande_annulee','Commande CMD-2026-000041 annulée','Non confirmée par le vendeur sous 48 h.','commande/suivi.php?c=41&j=7affeb572c14f92df436d4497a44c4e4fe3d3cdb9265ca41226c1f2991ffeaa1',NULL,'2026-10-15 11:10:18'),(283,12,'commande_annulee','Commande CMD-2026-000041 annulée','Non confirmée par le vendeur sous 48 h.','vendeur/commande.php?id=41','2026-10-15 12:51:11','2026-10-15 11:10:18'),(284,1,'commande_recue','Commande CMD-2026-000042 reçue','Délai de réclamation écoulé : commande terminée.','vendeur/commande.php?id=42',NULL,'2026-10-15 11:10:18'),(285,84,'avis_invitation','Notez le vendeur de la commande CMD-2026-000042','Votre avis aide les autres clients à choisir. Cela prend 30 secondes.','compte/avis.php?commande=42',NULL,'2026-10-15 11:10:18'),(286,1,'commande_recue','Commande CMD-2026-000043 reçue','Délai de réclamation écoulé : commande terminée.','vendeur/commande.php?id=43',NULL,'2026-10-15 11:10:18'),(287,86,'avis_invitation','Notez le vendeur de la commande CMD-2026-000043','Votre avis aide les autres clients à choisir. Cela prend 30 secondes.','compte/avis.php?commande=43',NULL,'2026-10-15 11:10:18'),(288,13,'abonnement_rappel','Votre boutique est masquée','Votre boutique est toujours masquée. Renouvelez votre abonnement pour remettre vos maillots en ligne.','vendeur/abonnement.php',NULL,'2026-10-15 11:10:18'),(289,1,'commande_nouvelle','Nouvelle commande CMD-2026-000044','Aliou Bah a commandé pour 9 000 FCFA (+ livraison à convenir). À confirmer sous 24 h.','vendeur/commande.php?id=44',NULL,'2026-10-15 15:58:42'),(290,88,'commande_enregistree','Commande CMD-2026-000044 enregistrée','Le vendeur doit la confirmer, puis il vous appellera pour la livraison.','commande/suivi.php?c=44&j=0d068c888d4dd5005f5fedb79dee68e6c60d91dd33cc2cd550fa86c62db624a1',NULL,'2026-10-15 15:58:42'),(291,1,'commande_recue','Commande CMD-2026-000029 reçue','Délai de réclamation écoulé : commande terminée.','vendeur/commande.php?id=29',NULL,'2026-10-15 16:08:27'),(292,23,'litige_decide','Réclamation CMD-2026-000029 : décision','Réclamation jugée fondée. jjkdfgukwdsukv dsmodev b jg  blig ujsd','compte/litige.php?id=8',NULL,'2026-10-15 16:08:27'),(293,1,'litige_decide','Réclamation CMD-2026-000029 : décision','Réclamation jugée fondée. jjkdfgukwdsukv dsmodev b jg  blig ujsd','vendeur/litige.php?id=8',NULL,'2026-10-15 16:08:27'),(294,88,'commande_confirmee','Commande CMD-2026-000044 confirmée','Le vendeur prépare votre maillot.','commande/suivi.php?c=44&j=0d068c888d4dd5005f5fedb79dee68e6c60d91dd33cc2cd550fa86c62db624a1',NULL,'2026-10-15 16:09:47'),(295,88,'commande_expediee','Commande CMD-2026-000044 en route','Le vendeur vous contactera pour la remise. Vous payez à la réception.','commande/suivi.php?c=44&j=0d068c888d4dd5005f5fedb79dee68e6c60d91dd33cc2cd550fa86c62db624a1',NULL,'2026-10-15 16:09:49'),(296,88,'commande_livree','Commande CMD-2026-000044 livrée','Le vendeur a marqué la commande livrée. Confirmez la réception, ou signalez un problème sous 48 h.','commande/suivi.php?c=44&j=0d068c888d4dd5005f5fedb79dee68e6c60d91dd33cc2cd550fa86c62db624a1',NULL,'2026-10-15 16:09:52');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `paiements_abonnement`
--

DROP TABLE IF EXISTS `paiements_abonnement`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `paiements_abonnement` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `boutique_id` int(10) unsigned NOT NULL,
  `utilisateur_id` int(10) unsigned NOT NULL,
  `mois` tinyint(3) unsigned NOT NULL COMMENT '1, 3 ou 6 mois',
  `montant` int(10) unsigned NOT NULL COMMENT 'FCFA',
  `reference_wave` varchar(40) NOT NULL,
  `capture` varchar(100) DEFAULT NULL COMMENT 'Fichier dans stockage/paiements/ (hors web)',
  `statut` enum('en_verification','valide','refuse') NOT NULL DEFAULT 'en_verification',
  `motif_refus` varchar(255) DEFAULT NULL,
  `traite_par` int(10) unsigned DEFAULT NULL,
  `traite_le` datetime DEFAULT NULL,
  `cree_le` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_paiements_reference` (`reference_wave`),
  KEY `idx_paiements_statut` (`statut`,`cree_le`),
  KEY `idx_paiements_boutique` (`boutique_id`,`cree_le`),
  KEY `fk_paiements_utilisateur` (`utilisateur_id`),
  KEY `fk_paiements_admin` (`traite_par`),
  CONSTRAINT `fk_paiements_admin` FOREIGN KEY (`traite_par`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_paiements_boutique` FOREIGN KEY (`boutique_id`) REFERENCES `boutiques` (`id`),
  CONSTRAINT `fk_paiements_utilisateur` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `paiements_abonnement`
--

LOCK TABLES `paiements_abonnement` WRITE;
/*!40000 ALTER TABLE `paiements_abonnement` DISABLE KEYS */;
/*!40000 ALTER TABLE `paiements_abonnement` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `panier_lignes`
--

DROP TABLE IF EXISTS `panier_lignes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `panier_lignes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `panier_id` int(10) unsigned NOT NULL,
  `maillot_id` int(10) unsigned NOT NULL,
  `taille` varchar(12) NOT NULL,
  `flocage_nom` varchar(12) NOT NULL DEFAULT '' COMMENT 'Vide = sans flocage',
  `flocage_numero` varchar(2) NOT NULL DEFAULT '',
  `quantite` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `prix_vu` int(10) unsigned NOT NULL COMMENT 'Prix unitaire affiché à l''ajout (FCFA)',
  `ajoute_le` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_panier_lignes` (`panier_id`,`maillot_id`,`taille`,`flocage_nom`,`flocage_numero`),
  KEY `fk_panier_lignes_maillot` (`maillot_id`),
  CONSTRAINT `fk_panier_lignes_maillot` FOREIGN KEY (`maillot_id`) REFERENCES `maillots` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_panier_lignes_panier` FOREIGN KEY (`panier_id`) REFERENCES `paniers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=93 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `panier_lignes`
--

LOCK TABLES `panier_lignes` WRITE;
/*!40000 ALTER TABLE `panier_lignes` DISABLE KEYS */;
INSERT INTO `panier_lignes` VALUES (27,14,31,'XL','','',1,5000,'2026-10-06 01:13:35'),(65,18,33,'S','','',1,40000,'2026-10-08 09:35:30');
/*!40000 ALTER TABLE `panier_lignes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `paniers`
--

DROP TABLE IF EXISTS `paniers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `paniers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `utilisateur_id` int(10) unsigned DEFAULT NULL,
  `jeton_hache` char(64) DEFAULT NULL COMMENT 'SHA-256 du jeton du cookie (panier invité)',
  `expire_le` datetime DEFAULT NULL COMMENT 'Panier invité : supprimé après 7 jours',
  `cree_le` datetime NOT NULL DEFAULT current_timestamp(),
  `modifie_le` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_paniers_utilisateur` (`utilisateur_id`),
  UNIQUE KEY `uq_paniers_jeton` (`jeton_hache`),
  CONSTRAINT `fk_paniers_utilisateur` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=59 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `paniers`
--

LOCK TABLES `paniers` WRITE;
/*!40000 ALTER TABLE `paniers` DISABLE KEYS */;
INSERT INTO `paniers` VALUES (18,23,NULL,NULL,'2026-10-06 13:24:54','2026-10-06 13:24:54'),(25,14,NULL,NULL,'2026-10-06 18:13:29','2026-10-06 18:13:29'),(41,1,NULL,NULL,'2026-10-11 02:09:38','2026-10-11 02:09:38');
/*!40000 ALTER TABLE `paniers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `parametres`
--

DROP TABLE IF EXISTS `parametres`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `parametres` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cle` varchar(60) NOT NULL,
  `valeur` varchar(255) NOT NULL,
  `description` varchar(255) NOT NULL,
  `modifie_le` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_parametres_cle` (`cle`)
) ENGINE=InnoDB AUTO_INCREMENT=115 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `parametres`
--

LOCK TABLES `parametres` WRITE;
/*!40000 ALTER TABLE `parametres` DISABLE KEYS */;
INSERT INTO `parametres` VALUES (1,'essai_gratuit_jours','15','Durée de l\'essai gratuit (jours)','2026-10-06 20:47:11'),(2,'abonnement_prix_fcfa','3000','Prix de l\'abonnement mensuel (FCFA)','2026-10-02 19:57:52'),(3,'abonnement_rappel_jours','2','Rappel avant fin d\'abonnement (jours)','2026-10-02 19:57:52'),(4,'abonnement_grace_jours','3','Période de grâce avant masquage (jours)','2026-10-02 19:57:52'),(5,'deblocage_avec_code_heures','24','Délai de déblocage des fonds avec code de livraison (heures)','2026-10-02 19:57:52'),(6,'deblocage_sans_code_heures','48','Délai de déblocage des fonds sans code (heures)','2026-10-02 19:57:52'),(7,'confirmation_vendeur_heures','48','Délai de confirmation d\'une commande par le vendeur (heures)','2026-10-06 14:01:14'),(8,'paiement_en_ligne_minutes','30','Délai de paiement en ligne (minutes)','2026-10-02 19:57:52'),(9,'litige_reponse_vendeur_heures','48','Délai de réponse du vendeur à un litige (heures)','2026-10-02 19:57:52'),(10,'frais_transaction_pourcentage','0','Frais de transaction déduits (%) — taux réel de l\'agrégateur, à renseigner','2026-10-02 19:57:52'),(11,'commission_pourcentage','0','Commission sur les ventes (%)','2026-10-02 19:57:52'),(12,'versement_jours','mardi,vendredi','Jours de versement aux vendeurs','2026-10-02 19:57:52'),(13,'versement_minimum_fcfa','1000','Montant minimum de versement (FCFA)','2026-10-02 19:57:52'),(14,'signalements_avant_suspension','3','Signalements de clients différents avant suspension','2026-10-02 19:57:52'),(15,'signalements_periode_jours','60','Période de comptage des signalements (jours)','2026-10-06 20:47:11'),(16,'avis_delai_jours','30','Délai pour laisser un avis (jours)','2026-10-02 19:57:52'),(17,'commandes_livraison_simultanees','3','Commandes à la livraison simultanées par client','2026-10-02 19:57:52'),(18,'refus_livraison_avant_blocage','3','Refus de livraison avant blocage du paiement à la livraison','2026-10-02 19:57:52'),(19,'photos_par_maillot','8','Nombre maximum de photos par maillot','2026-10-05 19:04:16'),(20,'photo_taille_max_mo','5','Taille maximale d\'une photo (Mo)','2026-10-02 19:57:52'),(21,'mise_en_avant_7_jours_fcfa','1000','Tarif de mise en avant 7 jours (FCFA)','2026-10-02 19:57:52'),(22,'mise_en_avant_30_jours_fcfa','3000','Tarif de mise en avant 30 jours (FCFA)','2026-10-02 19:57:52'),(23,'numero_wave_plateforme','+221706363785','Numéro Wave de la plateforme — à renseigner','2026-10-06 20:47:11'),(24,'numero_whatsapp_support','+221706464785','Numéro WhatsApp du support — à renseigner','2026-10-06 14:01:14'),(50,'email_support','lamabah811@gmail.com','Adresse e-mail du support (copie des messages de contact) — à renseigner','2026-10-06 14:01:14'),(104,'lien_paiement_wave','https://pay.wave.com/m/M_sn_8TToekiomTZB/c/sn/','Lien de paiement Wave Business (pay.wave.com) — montant ajouté automatiquement','2026-10-06 19:17:09'),(105,'entreprise_raison_sociale','','Raison sociale de l\'éditeur (ex. SAMA MAILLOT SUARL)','2026-10-06 20:27:40'),(106,'entreprise_forme_juridique','','Forme juridique (entreprise individuelle, SUARL…) et capital','2026-10-06 20:27:40'),(107,'entreprise_adresse','','Adresse du siège','2026-10-06 20:27:40'),(108,'entreprise_ninea','','NINEA','2026-10-06 20:27:40'),(109,'entreprise_rccm','','Numéro RCCM','2026-10-06 20:27:40'),(110,'entreprise_responsable','','Directeur de la publication','2026-10-06 20:27:40'),(111,'cdp_declaration','','Numéro de récépissé de déclaration à la CDP','2026-10-06 20:27:40');
/*!40000 ALTER TABLE `parametres` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rappels_abonnement`
--

DROP TABLE IF EXISTS `rappels_abonnement`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rappels_abonnement` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `boutique_id` int(10) unsigned NOT NULL,
  `cle` varchar(40) NOT NULL COMMENT 'Ex. j-2:2026-10-19, grace:2026-10-20, hebdo:2026-43',
  `envoye_le` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rappels` (`boutique_id`,`cle`),
  CONSTRAINT `fk_rappels_boutique` FOREIGN KEY (`boutique_id`) REFERENCES `boutiques` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=120 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rappels_abonnement`
--

LOCK TABLES `rappels_abonnement` WRITE;
/*!40000 ALTER TABLE `rappels_abonnement` DISABLE KEYS */;
INSERT INTO `rappels_abonnement` VALUES (9,5,'grace:2026-10-06','2026-10-06 17:25:25'),(30,5,'grace:2026-10-07','2026-10-07 08:20:16'),(36,5,'expire:2026-10-04','2026-10-07 16:03:57'),(99,5,'hebdo:2026-42','2026-10-15 11:10:18');
/*!40000 ALTER TABLE `rappels_abonnement` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `remboursements`
--

DROP TABLE IF EXISTS `remboursements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `remboursements` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `commande_id` int(10) unsigned NOT NULL,
  `client_id` int(10) unsigned NOT NULL,
  `montant` int(10) unsigned NOT NULL,
  `motif` varchar(255) NOT NULL,
  `statut` enum('a_rembourser','rembourse') NOT NULL DEFAULT 'a_rembourser',
  `reference` varchar(60) DEFAULT NULL,
  `admin_id` int(10) unsigned DEFAULT NULL,
  `rembourse_le` datetime DEFAULT NULL,
  `cree_le` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_remboursements_commande` (`commande_id`),
  KEY `idx_remboursements_statut` (`statut`,`cree_le`),
  KEY `fk_remboursements_client` (`client_id`),
  KEY `fk_remboursements_admin` (`admin_id`),
  CONSTRAINT `fk_remboursements_admin` FOREIGN KEY (`admin_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_remboursements_client` FOREIGN KEY (`client_id`) REFERENCES `utilisateurs` (`id`),
  CONSTRAINT `fk_remboursements_commande` FOREIGN KEY (`commande_id`) REFERENCES `commandes` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `remboursements`
--

LOCK TABLES `remboursements` WRITE;
/*!40000 ALTER TABLE `remboursements` DISABLE KEYS */;
INSERT INTO `remboursements` VALUES (3,39,71,44000,'Non confirmée par le vendeur sous 48 h','a_rembourser',NULL,NULL,NULL,'2026-10-13 09:22:18');
/*!40000 ALTER TABLE `remboursements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL COMMENT 'Utilisé dans le code : client, vendeur, admin',
  `libelle` varchar(50) NOT NULL COMMENT 'Affiché à l''écran',
  `description` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'client','Client','Commande, paie, suit ses commandes, confirme la réception, note et signale les vendeurs.'),(2,'vendeur','Vendeur','Gère sa boutique, ses maillots, son stock, ses commandes, son abonnement et son portefeuille.'),(3,'admin','Administrateur','Modère, arbitre les litiges, valide les abonnements, effectue les versements.');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `signalements`
--

DROP TABLE IF EXISTS `signalements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `signalements` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `auteur_id` int(10) unsigned NOT NULL COMMENT 'Client qui signale',
  `type` enum('vendeur','maillot') NOT NULL,
  `boutique_id` int(10) unsigned NOT NULL,
  `maillot_id` int(10) unsigned DEFAULT NULL COMMENT 'Signalement de maillot uniquement',
  `motif` varchar(40) NOT NULL,
  `description` text NOT NULL,
  `statut` enum('ouvert','retenu','classe') NOT NULL DEFAULT 'ouvert' COMMENT 'classe = jugé infondé : ne compte plus pour la suspension',
  `decision` varchar(255) DEFAULT NULL COMMENT 'Motif de la décision de l''administrateur',
  `traite_par` int(10) unsigned DEFAULT NULL,
  `traite_le` datetime DEFAULT NULL,
  `cree_le` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_signalements_statut` (`statut`,`cree_le`),
  KEY `idx_signalements_boutique` (`boutique_id`,`type`,`cree_le`),
  KEY `idx_signalements_auteur` (`auteur_id`,`boutique_id`,`cree_le`),
  KEY `fk_signalements_maillot` (`maillot_id`),
  KEY `fk_signalements_admin` (`traite_par`),
  CONSTRAINT `fk_signalements_admin` FOREIGN KEY (`traite_par`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_signalements_auteur` FOREIGN KEY (`auteur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_signalements_boutique` FOREIGN KEY (`boutique_id`) REFERENCES `boutiques` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_signalements_maillot` FOREIGN KEY (`maillot_id`) REFERENCES `maillots` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `signalements`
--

LOCK TABLES `signalements` WRITE;
/*!40000 ALTER TABLE `signalements` DISABLE KEYS */;
INSERT INTO `signalements` VALUES (5,23,'vendeur',17,NULL,'non_conforme','Le maillot n\'est pas de bonne qualité','retenu','lbidsg_ lisd_osd',85,'2026-10-15 16:08:59','2026-10-06 14:13:09'),(14,23,'vendeur',17,NULL,'arnaque','Réclamation fondée sur la commande CMD-2026-000029','retenu','jjkdfgukwdsukv dsmodev b jg  blig ujsd',85,'2026-10-15 16:08:27','2026-10-15 16:08:27');
/*!40000 ALTER TABLE `signalements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tarifs_livraison`
--

DROP TABLE IF EXISTS `tarifs_livraison`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tarifs_livraison` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `boutique_id` int(10) unsigned NOT NULL,
  `zone_id` int(10) unsigned NOT NULL,
  `tarif` int(10) unsigned NOT NULL COMMENT 'FCFA, payé par le client',
  `delai` varchar(40) NOT NULL COMMENT 'Délai indicatif, ex. 24-48h',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tarifs_boutique_zone` (`boutique_id`,`zone_id`),
  KEY `fk_tarifs_zone` (`zone_id`),
  CONSTRAINT `fk_tarifs_boutique` FOREIGN KEY (`boutique_id`) REFERENCES `boutiques` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tarifs_zone` FOREIGN KEY (`zone_id`) REFERENCES `zones_livraison` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tarifs_livraison`
--

LOCK TABLES `tarifs_livraison` WRITE;
/*!40000 ALTER TABLE `tarifs_livraison` DISABLE KEYS */;
INSERT INTO `tarifs_livraison` VALUES (1,4,1,1500,'24-48h'),(2,4,2,2000,'48-72h');
/*!40000 ALTER TABLE `tarifs_livraison` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tentatives_connexion`
--

DROP TABLE IF EXISTS `tentatives_connexion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tentatives_connexion` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `identifiant` varchar(190) NOT NULL,
  `adresse_ip` varchar(45) NOT NULL,
  `reussie` tinyint(1) NOT NULL DEFAULT 0,
  `cree_le` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tentatives_identifiant` (`identifiant`,`cree_le`),
  KEY `idx_tentatives_ip` (`adresse_ip`,`cree_le`)
) ENGINE=InnoDB AUTO_INCREMENT=248 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tentatives_connexion`
--

LOCK TABLES `tentatives_connexion` WRITE;
/*!40000 ALTER TABLE `tentatives_connexion` DISABLE KEYS */;
/*!40000 ALTER TABLE `tentatives_connexion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tentatives_connexion_sociale`
--

DROP TABLE IF EXISTS `tentatives_connexion_sociale`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tentatives_connexion_sociale` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `fournisseur` enum('google','facebook') NOT NULL,
  `adresse_ip` varchar(45) NOT NULL,
  `cree_le` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tentatives_sociales_ip` (`adresse_ip`,`cree_le`)
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tentatives_connexion_sociale`
--

LOCK TABLES `tentatives_connexion_sociale` WRITE;
/*!40000 ALTER TABLE `tentatives_connexion_sociale` DISABLE KEYS */;
/*!40000 ALTER TABLE `tentatives_connexion_sociale` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transactions_paiement`
--

DROP TABLE IF EXISTS `transactions_paiement`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transactions_paiement` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `reference` varchar(40) NOT NULL COMMENT 'Référence unique envoyée à l''agrégateur',
  `fournisseur` varchar(20) NOT NULL COMMENT 'simulateur, paytech, paydunya…',
  `client_id` int(10) unsigned NOT NULL,
  `montant` int(10) unsigned NOT NULL COMMENT 'FCFA attendus',
  `montant_recu` int(10) unsigned DEFAULT NULL COMMENT 'FCFA confirmés par l''agrégateur',
  `statut` enum('initie','reussi','echoue','annule','expire','inconnu','montant_incorrect') NOT NULL DEFAULT 'initie',
  `moyen` varchar(30) DEFAULT NULL COMMENT 'wave, orange_money, free_money, carte',
  `reference_fournisseur` varchar(100) DEFAULT NULL,
  `url_paiement` varchar(500) DEFAULT NULL,
  `simulateur_etat` varchar(20) DEFAULT NULL COMMENT 'Simulateur de test uniquement : état côté « agrégateur »',
  `expire_le` datetime NOT NULL,
  `traitee_le` datetime DEFAULT NULL COMMENT 'Statut final appliqué aux commandes (une seule fois)',
  `cree_le` datetime NOT NULL DEFAULT current_timestamp(),
  `modifie_le` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_transactions_reference` (`reference`),
  KEY `idx_transactions_statut` (`statut`,`expire_le`),
  KEY `idx_transactions_client` (`client_id`),
  CONSTRAINT `fk_transactions_client` FOREIGN KEY (`client_id`) REFERENCES `utilisateurs` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transactions_paiement`
--

LOCK TABLES `transactions_paiement` WRITE;
/*!40000 ALTER TABLE `transactions_paiement` DISABLE KEYS */;
INSERT INTO `transactions_paiement` VALUES (10,'PAY-261010-5B529DF4B8','simulateur',71,44000,44000,'reussi','wave','SIM-10','http://localhost/sama-maillot/paiement/simulateur.php?ref=PAY-261010-5B529DF4B8','reussi','2026-10-10 16:06:28','2026-10-10 15:36:33','2026-10-10 15:36:28','2026-10-10 15:36:33');
/*!40000 ALTER TABLE `transactions_paiement` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `utilisateurs`
--

DROP TABLE IF EXISTS `utilisateurs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `utilisateurs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `role` varchar(20) NOT NULL COMMENT 'client, vendeur ou admin (voir table roles)',
  `est_invite` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = compte créé par une commande sans compte',
  `prenom` varchar(50) NOT NULL,
  `nom` varchar(50) NOT NULL,
  `email` varchar(190) NOT NULL,
  `email_verifie` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = adresse e-mail prouvée',
  `telephone` varchar(13) DEFAULT NULL COMMENT 'Format +221XXXXXXXXX',
  `telephone_verifie_le` datetime DEFAULT NULL,
  `mot_de_passe_hache` varchar(255) DEFAULT NULL COMMENT 'password_hash() ; NULL pour un compte Google',
  `google_id` varchar(64) DEFAULT NULL,
  `statut` enum('actif','desactive','suspendu') NOT NULL DEFAULT 'actif',
  `refus_livraison` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Refus ou client injoignable à la livraison (§12.5)',
  `version_session` int(10) unsigned NOT NULL DEFAULT 1 COMMENT '+1 = toutes les sessions sont fermées',
  `cgu_acceptees_le` datetime NOT NULL,
  `derniere_connexion_le` datetime DEFAULT NULL,
  `cree_le` datetime NOT NULL DEFAULT current_timestamp(),
  `modifie_le` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `supprime_le` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_utilisateurs_email` (`email`),
  UNIQUE KEY `uq_utilisateurs_google_id` (`google_id`),
  UNIQUE KEY `uq_utilisateurs_telephone_role` (`telephone`,`role`),
  KEY `idx_utilisateurs_role_statut` (`role`,`statut`),
  CONSTRAINT `fk_utilisateurs_role` FOREIGN KEY (`role`) REFERENCES `roles` (`code`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=90 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `utilisateurs`
--

LOCK TABLES `utilisateurs` WRITE;
/*!40000 ALTER TABLE `utilisateurs` DISABLE KEYS */;
INSERT INTO `utilisateurs` VALUES (1,'vendeur',0,'Lama','Bah','lbah89381@gmail.com',1,'+221706464785',NULL,'$2y$10$nEVaWn5wGyBVBZa.rXxIk.8/MLmEla1rKFLN2AUbJMLhzxr1vwxFy',NULL,'actif',0,1,'2026-10-05 16:12:09','2026-10-15 22:10:18','2026-10-02 20:11:14','2026-10-16 15:38:12',NULL),(12,'vendeur',0,'Moussa','Test','vendeur1@test.sama',0,'+221770000001','2026-10-02 23:27:59','$2y$10$nEVaWn5wGyBVBZa.rXxIk.8/MLmEla1rKFLN2AUbJMLhzxr1vwxFy',NULL,'actif',0,1,'2026-10-02 23:27:59','2026-10-16 15:16:06','2026-10-02 23:27:59','2026-10-16 15:38:12',NULL),(13,'vendeur',0,'Awa','Test','vendeur2@test.sama',0,'+221780000002','2026-10-02 23:27:59','$2y$10$nEVaWn5wGyBVBZa.rXxIk.8/MLmEla1rKFLN2AUbJMLhzxr1vwxFy',NULL,'actif',0,1,'2026-10-02 23:27:59','2026-10-16 15:16:07','2026-10-02 23:27:59','2026-10-16 15:38:12',NULL),(14,'client',0,'Client','Test','client@test.sama',1,NULL,NULL,'$2y$10$nEVaWn5wGyBVBZa.rXxIk.8/MLmEla1rKFLN2AUbJMLhzxr1vwxFy',NULL,'actif',0,2,'2026-10-02 23:27:59','2026-10-16 15:16:05','2026-10-02 23:27:59','2026-10-16 15:38:12',NULL),(23,'client',0,'Ablaye','Diallo','lamarana2@gmail.com',0,NULL,NULL,'$2y$10$nEVaWn5wGyBVBZa.rXxIk.8/MLmEla1rKFLN2AUbJMLhzxr1vwxFy',NULL,'actif',0,1,'2026-10-05 15:35:12','2026-10-10 14:43:54','2026-10-05 15:35:12','2026-10-16 15:38:12',NULL),(71,'client',1,'Aliou','Bah','invite-8e1f4f76f2d6d7175d1c@invite.invalid',0,NULL,NULL,NULL,NULL,'actif',0,1,'2026-10-10 15:36:28',NULL,'2026-10-10 15:36:28','2026-10-10 15:36:28',NULL),(72,'client',1,'Aliou','Bah','invite-33a05d0156947e97b55d@invite.invalid',0,NULL,NULL,NULL,NULL,'actif',0,1,'2026-10-11 02:08:16',NULL,'2026-10-11 02:08:16','2026-10-11 02:08:16',NULL),(83,'client',1,'Aliou','Bah','invite-2239eaac3487da6eab0e@invite.invalid',0,NULL,NULL,NULL,NULL,'actif',0,1,'2026-10-13 10:04:14',NULL,'2026-10-13 10:04:14','2026-10-13 10:04:14',NULL),(84,'client',1,'Aliou','Bah','invite-f45e4de4d13cf193805d@invite.invalid',0,NULL,NULL,NULL,NULL,'actif',0,1,'2026-10-13 10:05:44',NULL,'2026-10-13 10:05:44','2026-10-13 10:05:44',NULL),(85,'admin',0,'Lamarana','Bah','lamabah811@gmail.com',0,NULL,NULL,'$2y$10$nEVaWn5wGyBVBZa.rXxIk.8/MLmEla1rKFLN2AUbJMLhzxr1vwxFy',NULL,'actif',0,1,'2026-10-13 10:09:57','2026-10-15 16:07:51','2026-10-13 10:09:57','2026-10-16 15:38:12',NULL),(86,'client',1,'Aliou','Bah','invite-0b4c48922b9d174e9f9f@invite.invalid',0,NULL,NULL,NULL,NULL,'actif',0,1,'2026-10-13 10:25:11',NULL,'2026-10-13 10:25:11','2026-10-13 10:25:11',NULL),(88,'client',1,'Aliou','Bah','invite-9a75e418ebd8be6acd0d@invite.invalid',0,NULL,NULL,NULL,NULL,'actif',0,1,'2026-10-15 15:58:42',NULL,'2026-10-15 15:58:42','2026-10-15 15:58:42',NULL),(89,'admin',0,'Admin','Test','admin@test.sama',1,NULL,NULL,'$2y$10$nEVaWn5wGyBVBZa.rXxIk.8/MLmEla1rKFLN2AUbJMLhzxr1vwxFy',NULL,'actif',0,1,'2026-10-16 15:16:01','2026-10-16 15:16:04','2026-10-16 15:16:01','2026-10-16 15:38:12',NULL);
/*!40000 ALTER TABLE `utilisateurs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `versements`
--

DROP TABLE IF EXISTS `versements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `versements` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `boutique_id` int(10) unsigned NOT NULL,
  `montant` int(10) unsigned NOT NULL,
  `numero_wave` varchar(13) NOT NULL COMMENT 'Copie du numéro Wave au moment du versement',
  `reference_wave` varchar(60) NOT NULL,
  `admin_id` int(10) unsigned DEFAULT NULL,
  `cree_le` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_versements_reference` (`reference_wave`),
  KEY `idx_versements_boutique` (`boutique_id`,`cree_le`),
  KEY `fk_versements_admin` (`admin_id`),
  CONSTRAINT `fk_versements_admin` FOREIGN KEY (`admin_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_versements_boutique` FOREIGN KEY (`boutique_id`) REFERENCES `boutiques` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `versements`
--

LOCK TABLES `versements` WRITE;
/*!40000 ALTER TABLE `versements` DISABLE KEYS */;
/*!40000 ALTER TABLE `versements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `zones_livraison`
--

DROP TABLE IF EXISTS `zones_livraison`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `zones_livraison` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nom` varchar(60) NOT NULL,
  `description` varchar(255) NOT NULL COMMENT 'Exemples de quartiers ou de villes',
  `ordre` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_zones_nom` (`nom`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `zones_livraison`
--

LOCK TABLES `zones_livraison` WRITE;
/*!40000 ALTER TABLE `zones_livraison` DISABLE KEYS */;
INSERT INTO `zones_livraison` VALUES (1,'Dakar','Plateau, Médina, Almadies, Parcelles, Grand Yoff…',1,1),(2,'Banlieue','Pikine, Guédiawaye, Keur Massar, Rufisque…',2,1),(3,'Thiès, Mbour, Saly','Thiès, Mbour, Saly et environs',3,1),(4,'Autres régions','Saint-Louis, Touba, Kaolack, Ziguinchor…',4,1);
/*!40000 ALTER TABLE `zones_livraison` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'sama_maillot_depot'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed
