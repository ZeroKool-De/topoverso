-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:8889
-- Creato il: Giu 20, 2025 alle 14:03
-- Versione del server: 8.0.40
-- Versione PHP: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `topolino_catalog`
--

-- --------------------------------------------------------

--
-- Struttura della tabella `admin_users`
--

CREATE TABLE `admin_users` (
  `user_id` int NOT NULL,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `calculated_first_appearances`
--

CREATE TABLE `calculated_first_appearances` (
  `id` int NOT NULL,
  `character_id` int NOT NULL,
  `calculated_story_id` int DEFAULT NULL,
  `calculated_comic_id` int DEFAULT NULL,
  `calculated_publication_date` date DEFAULT NULL,
  `calculation_timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `characters`
--

CREATE TABLE `characters` (
  `character_id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `character_image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_appearance_comic_id` int DEFAULT NULL,
  `first_appearance_story_id` int DEFAULT NULL,
  `first_appearance_date` date DEFAULT NULL,
  `first_appearance_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_first_appearance_verified` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `comics`
--

CREATE TABLE `comics` (
  `comic_id` int NOT NULL,
  `issue_number` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `publication_date` date DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cover_image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cover_artist_id` int DEFAULT NULL,
  `cover_artists_json` json DEFAULT NULL,
  `back_cover_image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Percorso immagine retrocopertina',
  `variant_cover_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `variant_cover_artist_id` int DEFAULT NULL,
  `back_cover_artist_id` int DEFAULT NULL,
  `back_cover_artists_json` json DEFAULT NULL,
  `editor` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Editore del fumetto',
  `pages` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Numero di pagine (es. 128, 120+4)',
  `price` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Prezzo (es. 3.50 EUR, L. 500)',
  `periodicity` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Periodicità del fumetto (es. Mensile, Settimanale)',
  `custom_fields` json DEFAULT NULL,
  `gadget_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gadget_image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `comic_gadget_images`
--

CREATE TABLE `comic_gadget_images` (
  `gadget_image_id` int NOT NULL,
  `comic_id` int NOT NULL,
  `image_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `caption` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `uploaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `comic_persons`
--

CREATE TABLE `comic_persons` (
  `comic_id` int NOT NULL,
  `person_id` int NOT NULL,
  `role` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `comic_ratings`
--

CREATE TABLE `comic_ratings` (
  `rating_id` int NOT NULL,
  `comic_id` int NOT NULL,
  `rating` tinyint NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `voted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `comic_requests`
--

CREATE TABLE `comic_requests` (
  `request_id` int NOT NULL,
  `issue_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `visitor_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `request_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('new','viewed','fulfilled','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'new'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `comic_variant_covers`
--

CREATE TABLE `comic_variant_covers` (
  `variant_cover_id` int NOT NULL,
  `comic_id` int NOT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `caption` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `artist_id` int DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `uploaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `contact_messages`
--

CREATE TABLE `contact_messages` (
  `message_id` int NOT NULL,
  `contact_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_subject` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message_text` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `submitted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `admin_notes` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `custom_field_definitions`
--

CREATE TABLE `custom_field_definitions` (
  `field_key` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `field_label` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `field_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text',
  `entity_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'comic'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `error_reports`
--

CREATE TABLE `error_reports` (
  `report_id` int NOT NULL,
  `comic_id` int NOT NULL COMMENT 'ID dell''albo a cui si riferisce la segnalazione',
  `story_id` int DEFAULT NULL COMMENT 'ID della storia specifica, se la segnalazione la riguarda',
  `report_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Testo della segnalazione',
  `reporter_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Email (opzionale) di chi segnala',
  `report_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data e ora della segnalazione',
  `status` enum('new','viewed','in_progress','resolved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new' COMMENT 'Stato della segnalazione',
  `admin_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Note dell''admin sulla segnalazione',
  `admin_id_reviewer` int DEFAULT NULL COMMENT 'ID dell''admin che ha gestito/revisionato',
  `reviewed_at` timestamp NULL DEFAULT NULL COMMENT 'Data e ora della revisione admin'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `forum_posts`
--

CREATE TABLE `forum_posts` (
  `id` int NOT NULL,
  `thread_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `author_name` varchar(255) DEFAULT NULL,
  `content` text NOT NULL,
  `edited_at` timestamp NULL DEFAULT NULL,
  `edited_by_admin_id` int DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `forum_sections`
--

CREATE TABLE `forum_sections` (
  `id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `forum_threads`
--

CREATE TABLE `forum_threads` (
  `id` int NOT NULL,
  `section_id` int NOT NULL,
  `comic_id` int DEFAULT NULL,
  `story_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_post_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `historical_events`
--

CREATE TABLE `historical_events` (
  `event_id` int NOT NULL,
  `event_title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_date_start` date NOT NULL,
  `event_date_end` date DEFAULT NULL,
  `category` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `related_issue_start` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `related_issue_end` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event_image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `related_story_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int NOT NULL,
  `user_id` int NOT NULL COMMENT 'L''utente che riceve la notifica',
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `link_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `pending_characters`
--

CREATE TABLE `pending_characters` (
  `pending_character_id` int NOT NULL,
  `character_id_original` int DEFAULT NULL,
  `proposer_user_id` int NOT NULL,
  `action_type` enum('add','edit','delete') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `proposed_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewer_admin_id` int DEFAULT NULL,
  `admin_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `name_proposal` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description_proposal` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `character_image_proposal` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_appearance_comic_id_proposal` int DEFAULT NULL,
  `first_appearance_story_id_proposal` int DEFAULT NULL,
  `first_appearance_notes_proposal` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_first_appearance_verified_proposal` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `pending_comics`
--

CREATE TABLE `pending_comics` (
  `pending_comic_id` int NOT NULL,
  `comic_id_original` int DEFAULT NULL COMMENT 'ID del fumetto originale se è una modifica, NULL se nuova aggiunta',
  `proposer_user_id` int NOT NULL COMMENT 'ID utente (tabella users) che ha proposto',
  `action_type` enum('add','edit','delete') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Tipo di azione proposta',
  `status` enum('pending','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `proposed_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewer_admin_id` int DEFAULT NULL COMMENT 'ID admin (tabella admin_users) che ha revisionato',
  `admin_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `issue_number` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `publication_date` date DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cover_image_proposal` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Path proposto o gestito per nuova immagine',
  `cover_artist_id_proposal` int DEFAULT NULL,
  `back_cover_image_proposal` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `back_cover_artist_id_proposal` int DEFAULT NULL,
  `editor` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pages` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `periodicity` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `custom_fields_proposal` json DEFAULT NULL,
  `gadget_name_proposal` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gadget_image_proposal` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `staff_proposal_json` json DEFAULT NULL COMMENT 'JSON con ID persona e ruolo per lo staff dell''albo',
  `variant_covers_proposal_json` json DEFAULT NULL COMMENT 'JSON con info sulle variant proposte (path temporanei/didascalie)',
  `cover_artists_json` text COLLATE utf8mb4_unicode_ci,
  `back_cover_artists_json` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `pending_persons`
--

CREATE TABLE `pending_persons` (
  `pending_person_id` int NOT NULL,
  `person_id_original` int DEFAULT NULL,
  `proposer_user_id` int NOT NULL,
  `action_type` enum('add','edit','delete') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `proposed_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewer_admin_id` int DEFAULT NULL,
  `admin_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `name_proposal` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `biography_proposal` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `person_image_proposal` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `pending_stories`
--

CREATE TABLE `pending_stories` (
  `pending_story_id` int NOT NULL,
  `story_id_original` int DEFAULT NULL,
  `comic_id_context` int NOT NULL COMMENT 'ID del fumetto a cui si riferisce la storia',
  `proposer_user_id` int NOT NULL,
  `action_type` enum('add','edit','delete') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `proposed_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewer_admin_id` int DEFAULT NULL,
  `admin_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `series_id_proposal` int DEFAULT NULL,
  `title_proposal` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `story_title_main_proposal` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `part_number_proposal` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_parts_proposal` int DEFAULT NULL,
  `story_group_id_proposal` int DEFAULT NULL,
  `first_page_image_proposal` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sequence_in_comic_proposal` int DEFAULT '0',
  `series_episode_number_proposal` int DEFAULT NULL,
  `notes_proposal` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_ministory_proposal` tinyint(1) DEFAULT '0',
  `authors_proposal_json` json DEFAULT NULL COMMENT 'JSON con ID persona e ruolo per autori storia',
  `characters_proposal_json` json DEFAULT NULL COMMENT 'JSON con ID personaggi per storia'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `pending_story_series`
--

CREATE TABLE `pending_story_series` (
  `pending_series_id` int NOT NULL,
  `series_id_original` int DEFAULT NULL,
  `proposer_user_id` int NOT NULL,
  `action_type` enum('add','edit','delete') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `proposed_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewer_admin_id` int DEFAULT NULL,
  `admin_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `title_proposal` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description_proposal` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `image_path_proposal` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_date_proposal` date DEFAULT NULL COMMENT 'Proposta di data di inizio serie'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `persons`
--

CREATE TABLE `persons` (
  `person_id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `biography` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `person_image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `site_settings`
--

CREATE TABLE `site_settings` (
  `setting_key` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `stories`
--

CREATE TABLE `stories` (
  `story_id` int NOT NULL,
  `comic_id` int NOT NULL,
  `series_id` int DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `story_title_main` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Titolo principale della storia multi-parte',
  `part_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Numero/Nome della parte (es. 1, 2, Prologo)',
  `total_parts` int DEFAULT NULL COMMENT 'Numero totale di parti della storia, se noto',
  `story_group_id` int DEFAULT NULL COMMENT 'ID che raggruppa le parti della stessa storia (può essere lo story_id della prima parte)',
  `first_page_image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sequence_in_comic` int DEFAULT '0',
  `series_episode_number` int DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_ministory` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Indica se è una mini-storia/gag',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `story_characters`
--

CREATE TABLE `story_characters` (
  `story_id` int NOT NULL,
  `character_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `story_persons`
--

CREATE TABLE `story_persons` (
  `story_id` int NOT NULL,
  `person_id` int NOT NULL,
  `role` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `story_ratings`
--

CREATE TABLE `story_ratings` (
  `rating_id` int NOT NULL,
  `story_id` int NOT NULL,
  `rating` tinyint NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `voted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `story_series`
--

CREATE TABLE `story_series` (
  `series_id` int NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `image_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_date` date DEFAULT NULL COMMENT 'Data di inizio della serie',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `users`
--

CREATE TABLE `users` (
  `user_id` int NOT NULL,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `avatar_image_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `user_role` enum('user','contributor','admin') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `notes_admin` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `user_collections`
--

CREATE TABLE `user_collections` (
  `collection_id` int NOT NULL,
  `user_id` int NOT NULL,
  `comic_id` int NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `rating` tinyint DEFAULT NULL,
  `added_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `user_collection_placeholders`
--

CREATE TABLE `user_collection_placeholders` (
  `placeholder_id` int NOT NULL,
  `user_id` int NOT NULL,
  `issue_number_placeholder` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Numero albo segnalato dall''utente',
  `added_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('pending','linked','ignored') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT 'Stato del placeholder: pending (in attesa), linked (collegato a un albo reale), ignored (admin lo ignora)',
  `comic_id_linked` int DEFAULT NULL COMMENT 'ID dell''albo reale a cui è stato collegato'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indici per le tabelle scaricate
--

--
-- Indici per le tabelle `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indici per le tabelle `calculated_first_appearances`
--
ALTER TABLE `calculated_first_appearances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_character_id` (`character_id`),
  ADD KEY `calculated_story_id` (`calculated_story_id`),
  ADD KEY `calculated_comic_id` (`calculated_comic_id`);

--
-- Indici per le tabelle `characters`
--
ALTER TABLE `characters`
  ADD PRIMARY KEY (`character_id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `uk_characters_slug` (`slug`),
  ADD KEY `fk_first_appearance_comic` (`first_appearance_comic_id`),
  ADD KEY `fk_first_appearance_story` (`first_appearance_story_id`),
  ADD KEY `idx_first_appearance_date` (`first_appearance_date`);

--
-- Indici per le tabelle `comics`
--
ALTER TABLE `comics`
  ADD PRIMARY KEY (`comic_id`),
  ADD UNIQUE KEY `uk_comics_slug` (`slug`),
  ADD KEY `idx_issue_number` (`issue_number`),
  ADD KEY `idx_publication_date` (`publication_date`),
  ADD KEY `fk_cover_artist` (`cover_artist_id`),
  ADD KEY `fk_back_cover_artist` (`back_cover_artist_id`),
  ADD KEY `fk_variant_cover_artist` (`variant_cover_artist_id`);

--
-- Indici per le tabelle `comic_gadget_images`
--
ALTER TABLE `comic_gadget_images`
  ADD PRIMARY KEY (`gadget_image_id`),
  ADD KEY `comic_id` (`comic_id`);

--
-- Indici per le tabelle `comic_persons`
--
ALTER TABLE `comic_persons`
  ADD PRIMARY KEY (`comic_id`,`person_id`,`role`),
  ADD KEY `person_id` (`person_id`);

--
-- Indici per le tabelle `comic_ratings`
--
ALTER TABLE `comic_ratings`
  ADD PRIMARY KEY (`rating_id`),
  ADD KEY `comic_id` (`comic_id`),
  ADD KEY `idx_ip_comic_rating` (`ip_address`,`comic_id`);

--
-- Indici per le tabelle `comic_requests`
--
ALTER TABLE `comic_requests`
  ADD PRIMARY KEY (`request_id`);

--
-- Indici per le tabelle `comic_variant_covers`
--
ALTER TABLE `comic_variant_covers`
  ADD PRIMARY KEY (`variant_cover_id`),
  ADD KEY `comic_id` (`comic_id`),
  ADD KEY `fk_variant_artist` (`artist_id`);

--
-- Indici per le tabelle `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`message_id`);

--
-- Indici per le tabelle `custom_field_definitions`
--
ALTER TABLE `custom_field_definitions`
  ADD PRIMARY KEY (`field_key`);

--
-- Indici per le tabelle `error_reports`
--
ALTER TABLE `error_reports`
  ADD PRIMARY KEY (`report_id`),
  ADD KEY `fk_error_report_comic` (`comic_id`),
  ADD KEY `fk_error_report_story` (`story_id`),
  ADD KEY `idx_error_report_status` (`status`),
  ADD KEY `fk_error_report_admin_reviewer` (`admin_id_reviewer`);

--
-- Indici per le tabelle `forum_posts`
--
ALTER TABLE `forum_posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_thread_id` (`thread_id`),
  ADD KEY `idx_post_status` (`status`);

--
-- Indici per le tabelle `forum_sections`
--
ALTER TABLE `forum_sections`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `forum_threads`
--
ALTER TABLE `forum_threads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `story_id` (`story_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_comic_id` (`comic_id`),
  ADD KEY `idx_section_id` (`section_id`);

--
-- Indici per le tabelle `historical_events`
--
ALTER TABLE `historical_events`
  ADD PRIMARY KEY (`event_id`),
  ADD KEY `idx_event_date_start` (`event_date_start`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `fk_event_related_story` (`related_story_id`);

--
-- Indici per le tabelle `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_user_id_is_read` (`user_id`,`is_read`);

--
-- Indici per le tabelle `pending_characters`
--
ALTER TABLE `pending_characters`
  ADD PRIMARY KEY (`pending_character_id`),
  ADD KEY `character_id_original_pending` (`character_id_original`),
  ADD KEY `proposer_user_id_pending_char` (`proposer_user_id`),
  ADD KEY `reviewer_admin_id_pending_char` (`reviewer_admin_id`);

--
-- Indici per le tabelle `pending_comics`
--
ALTER TABLE `pending_comics`
  ADD PRIMARY KEY (`pending_comic_id`),
  ADD KEY `comic_id_original_pending` (`comic_id_original`),
  ADD KEY `proposer_user_id_pending_comic` (`proposer_user_id`),
  ADD KEY `reviewer_admin_id_pending_comic` (`reviewer_admin_id`);

--
-- Indici per le tabelle `pending_persons`
--
ALTER TABLE `pending_persons`
  ADD PRIMARY KEY (`pending_person_id`),
  ADD KEY `person_id_original_pending` (`person_id_original`),
  ADD KEY `proposer_user_id_pending_person` (`proposer_user_id`),
  ADD KEY `reviewer_admin_id_pending_person` (`reviewer_admin_id`);

--
-- Indici per le tabelle `pending_stories`
--
ALTER TABLE `pending_stories`
  ADD PRIMARY KEY (`pending_story_id`),
  ADD KEY `story_id_original_pending` (`story_id_original`),
  ADD KEY `comic_id_context_pending_story` (`comic_id_context`),
  ADD KEY `proposer_user_id_pending_story` (`proposer_user_id`),
  ADD KEY `reviewer_admin_id_pending_story` (`reviewer_admin_id`);

--
-- Indici per le tabelle `pending_story_series`
--
ALTER TABLE `pending_story_series`
  ADD PRIMARY KEY (`pending_series_id`),
  ADD KEY `series_id_original_pending` (`series_id_original`),
  ADD KEY `proposer_user_id_pending_series` (`proposer_user_id`),
  ADD KEY `reviewer_admin_id_pending_series` (`reviewer_admin_id`);

--
-- Indici per le tabelle `persons`
--
ALTER TABLE `persons`
  ADD PRIMARY KEY (`person_id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `uk_persons_slug` (`slug`);

--
-- Indici per le tabelle `site_settings`
--
ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indici per le tabelle `stories`
--
ALTER TABLE `stories`
  ADD PRIMARY KEY (`story_id`),
  ADD KEY `comic_id` (`comic_id`),
  ADD KEY `fk_story_series` (`series_id`),
  ADD KEY `idx_story_group_id` (`story_group_id`),
  ADD KEY `idx_part_number` (`part_number`);

--
-- Indici per le tabelle `story_characters`
--
ALTER TABLE `story_characters`
  ADD PRIMARY KEY (`story_id`,`character_id`),
  ADD KEY `character_id` (`character_id`);

--
-- Indici per le tabelle `story_persons`
--
ALTER TABLE `story_persons`
  ADD PRIMARY KEY (`story_id`,`person_id`,`role`),
  ADD KEY `person_id` (`person_id`);

--
-- Indici per le tabelle `story_ratings`
--
ALTER TABLE `story_ratings`
  ADD PRIMARY KEY (`rating_id`),
  ADD KEY `story_id` (`story_id`),
  ADD KEY `idx_ip_story_rating` (`ip_address`,`story_id`);

--
-- Indici per le tabelle `story_series`
--
ALTER TABLE `story_series`
  ADD PRIMARY KEY (`series_id`),
  ADD UNIQUE KEY `unique_title` (`title`(191)),
  ADD UNIQUE KEY `uk_story_series_slug` (`slug`);

--
-- Indici per le tabelle `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_user_role` (`user_role`);

--
-- Indici per le tabelle `user_collections`
--
ALTER TABLE `user_collections`
  ADD PRIMARY KEY (`collection_id`),
  ADD UNIQUE KEY `uk_user_comic` (`user_id`,`comic_id`),
  ADD KEY `comic_id` (`comic_id`);

--
-- Indici per le tabelle `user_collection_placeholders`
--
ALTER TABLE `user_collection_placeholders`
  ADD PRIMARY KEY (`placeholder_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `comic_id_linked` (`comic_id_linked`),
  ADD KEY `idx_issue_number_placeholder_status` (`issue_number_placeholder`,`status`);

--
-- AUTO_INCREMENT per le tabelle scaricate
--

--
-- AUTO_INCREMENT per la tabella `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `user_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `calculated_first_appearances`
--
ALTER TABLE `calculated_first_appearances`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `characters`
--
ALTER TABLE `characters`
  MODIFY `character_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `comics`
--
ALTER TABLE `comics`
  MODIFY `comic_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `comic_gadget_images`
--
ALTER TABLE `comic_gadget_images`
  MODIFY `gadget_image_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `comic_ratings`
--
ALTER TABLE `comic_ratings`
  MODIFY `rating_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `comic_requests`
--
ALTER TABLE `comic_requests`
  MODIFY `request_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `comic_variant_covers`
--
ALTER TABLE `comic_variant_covers`
  MODIFY `variant_cover_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `message_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `error_reports`
--
ALTER TABLE `error_reports`
  MODIFY `report_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `forum_posts`
--
ALTER TABLE `forum_posts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `forum_sections`
--
ALTER TABLE `forum_sections`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `forum_threads`
--
ALTER TABLE `forum_threads`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `historical_events`
--
ALTER TABLE `historical_events`
  MODIFY `event_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `pending_characters`
--
ALTER TABLE `pending_characters`
  MODIFY `pending_character_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `pending_comics`
--
ALTER TABLE `pending_comics`
  MODIFY `pending_comic_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `pending_persons`
--
ALTER TABLE `pending_persons`
  MODIFY `pending_person_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `pending_stories`
--
ALTER TABLE `pending_stories`
  MODIFY `pending_story_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `pending_story_series`
--
ALTER TABLE `pending_story_series`
  MODIFY `pending_series_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `persons`
--
ALTER TABLE `persons`
  MODIFY `person_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `stories`
--
ALTER TABLE `stories`
  MODIFY `story_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `story_ratings`
--
ALTER TABLE `story_ratings`
  MODIFY `rating_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `story_series`
--
ALTER TABLE `story_series`
  MODIFY `series_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `user_collections`
--
ALTER TABLE `user_collections`
  MODIFY `collection_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `user_collection_placeholders`
--
ALTER TABLE `user_collection_placeholders`
  MODIFY `placeholder_id` int NOT NULL AUTO_INCREMENT;

--
-- Limiti per le tabelle scaricate
--

--
-- Limiti per la tabella `calculated_first_appearances`
--
ALTER TABLE `calculated_first_appearances`
  ADD CONSTRAINT `fk_cfa_character` FOREIGN KEY (`character_id`) REFERENCES `characters` (`character_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cfa_comic` FOREIGN KEY (`calculated_comic_id`) REFERENCES `comics` (`comic_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cfa_story` FOREIGN KEY (`calculated_story_id`) REFERENCES `stories` (`story_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Limiti per la tabella `characters`
--
ALTER TABLE `characters`
  ADD CONSTRAINT `fk_first_appearance_comic` FOREIGN KEY (`first_appearance_comic_id`) REFERENCES `comics` (`comic_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_first_appearance_story` FOREIGN KEY (`first_appearance_story_id`) REFERENCES `stories` (`story_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Limiti per la tabella `comics`
--
ALTER TABLE `comics`
  ADD CONSTRAINT `fk_back_cover_artist` FOREIGN KEY (`back_cover_artist_id`) REFERENCES `persons` (`person_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cover_artist` FOREIGN KEY (`cover_artist_id`) REFERENCES `persons` (`person_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_variant_cover_artist` FOREIGN KEY (`variant_cover_artist_id`) REFERENCES `persons` (`person_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Limiti per la tabella `comic_gadget_images`
--
ALTER TABLE `comic_gadget_images`
  ADD CONSTRAINT `fk_gadget_comic` FOREIGN KEY (`comic_id`) REFERENCES `comics` (`comic_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limiti per la tabella `comic_persons`
--
ALTER TABLE `comic_persons`
  ADD CONSTRAINT `comic_persons_ibfk_1` FOREIGN KEY (`comic_id`) REFERENCES `comics` (`comic_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `comic_persons_ibfk_2` FOREIGN KEY (`person_id`) REFERENCES `persons` (`person_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limiti per la tabella `comic_ratings`
--
ALTER TABLE `comic_ratings`
  ADD CONSTRAINT `fk_comic_ratings_comic` FOREIGN KEY (`comic_id`) REFERENCES `comics` (`comic_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limiti per la tabella `comic_variant_covers`
--
ALTER TABLE `comic_variant_covers`
  ADD CONSTRAINT `fk_variant_artist` FOREIGN KEY (`artist_id`) REFERENCES `persons` (`person_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Limiti per la tabella `forum_posts`
--
ALTER TABLE `forum_posts`
  ADD CONSTRAINT `forum_posts_ibfk_1` FOREIGN KEY (`thread_id`) REFERENCES `forum_threads` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `forum_posts_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Limiti per la tabella `forum_threads`
--
ALTER TABLE `forum_threads`
  ADD CONSTRAINT `forum_threads_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `forum_sections` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `forum_threads_ibfk_2` FOREIGN KEY (`comic_id`) REFERENCES `comics` (`comic_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `forum_threads_ibfk_3` FOREIGN KEY (`story_id`) REFERENCES `stories` (`story_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `forum_threads_ibfk_4` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Limiti per la tabella `historical_events`
--
ALTER TABLE `historical_events`
  ADD CONSTRAINT `fk_event_related_story` FOREIGN KEY (`related_story_id`) REFERENCES `stories` (`story_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Limiti per la tabella `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notification_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limiti per la tabella `pending_characters`
--
ALTER TABLE `pending_characters`
  ADD CONSTRAINT `fk_pending_character_original_ref` FOREIGN KEY (`character_id_original`) REFERENCES `characters` (`character_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pending_character_proposer_ref` FOREIGN KEY (`proposer_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pending_character_reviewer_ref` FOREIGN KEY (`reviewer_admin_id`) REFERENCES `admin_users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Limiti per la tabella `pending_comics`
--
ALTER TABLE `pending_comics`
  ADD CONSTRAINT `fk_pending_comic_original_ref` FOREIGN KEY (`comic_id_original`) REFERENCES `comics` (`comic_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pending_comic_proposer_ref` FOREIGN KEY (`proposer_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pending_comic_reviewer_ref` FOREIGN KEY (`reviewer_admin_id`) REFERENCES `admin_users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Limiti per la tabella `pending_persons`
--
ALTER TABLE `pending_persons`
  ADD CONSTRAINT `fk_pending_person_original_ref` FOREIGN KEY (`person_id_original`) REFERENCES `persons` (`person_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pending_person_proposer_ref` FOREIGN KEY (`proposer_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pending_person_reviewer_ref` FOREIGN KEY (`reviewer_admin_id`) REFERENCES `admin_users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Limiti per la tabella `pending_stories`
--
ALTER TABLE `pending_stories`
  ADD CONSTRAINT `fk_pending_story_comic_context_ref` FOREIGN KEY (`comic_id_context`) REFERENCES `comics` (`comic_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pending_story_original_ref` FOREIGN KEY (`story_id_original`) REFERENCES `stories` (`story_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pending_story_proposer_ref` FOREIGN KEY (`proposer_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pending_story_reviewer_ref` FOREIGN KEY (`reviewer_admin_id`) REFERENCES `admin_users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Limiti per la tabella `pending_story_series`
--
ALTER TABLE `pending_story_series`
  ADD CONSTRAINT `fk_pending_series_original_ref` FOREIGN KEY (`series_id_original`) REFERENCES `story_series` (`series_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pending_series_proposer_ref` FOREIGN KEY (`proposer_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pending_series_reviewer_ref` FOREIGN KEY (`reviewer_admin_id`) REFERENCES `admin_users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Limiti per la tabella `stories`
--
ALTER TABLE `stories`
  ADD CONSTRAINT `fk_story_series` FOREIGN KEY (`series_id`) REFERENCES `story_series` (`series_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `stories_ibfk_1` FOREIGN KEY (`comic_id`) REFERENCES `comics` (`comic_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limiti per la tabella `story_characters`
--
ALTER TABLE `story_characters`
  ADD CONSTRAINT `story_characters_ibfk_1` FOREIGN KEY (`story_id`) REFERENCES `stories` (`story_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `story_characters_ibfk_2` FOREIGN KEY (`character_id`) REFERENCES `characters` (`character_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limiti per la tabella `story_persons`
--
ALTER TABLE `story_persons`
  ADD CONSTRAINT `story_persons_ibfk_1` FOREIGN KEY (`story_id`) REFERENCES `stories` (`story_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `story_persons_ibfk_2` FOREIGN KEY (`person_id`) REFERENCES `persons` (`person_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limiti per la tabella `story_ratings`
--
ALTER TABLE `story_ratings`
  ADD CONSTRAINT `fk_story_ratings_story` FOREIGN KEY (`story_id`) REFERENCES `stories` (`story_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limiti per la tabella `user_collections`
--
ALTER TABLE `user_collections`
  ADD CONSTRAINT `user_collections_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_collections_ibfk_2` FOREIGN KEY (`comic_id`) REFERENCES `comics` (`comic_id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `user_collection_placeholders`
--
ALTER TABLE `user_collection_placeholders`
  ADD CONSTRAINT `user_collection_placeholders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `user_collection_placeholders_ibfk_2` FOREIGN KEY (`comic_id_linked`) REFERENCES `comics` (`comic_id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
