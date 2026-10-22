-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 22-10-2026 a las 21:07:28
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `callaudit`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ai_provider_configs`
--

CREATE TABLE `ai_provider_configs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `provider_code` varchar(100) NOT NULL,
  `display_name` varchar(150) NOT NULL,
  `billing_mode` enum('FREE','PAID','MIXED') NOT NULL DEFAULT 'FREE',
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  `authorized_paid_fallback` tinyint(1) NOT NULL DEFAULT 0,
  `monthly_budget` decimal(12,4) DEFAULT NULL,
  `daily_budget` decimal(12,4) DEFAULT NULL,
  `monthly_limit` int(10) UNSIGNED DEFAULT NULL,
  `model_name` varchar(150) DEFAULT NULL,
  `encrypted_secret` longblob DEFAULT NULL,
  `secret_key_version` varchar(50) DEFAULT NULL,
  `configuration_json` longtext DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `audit_activity_log`
--

CREATE TABLE `audit_activity_log` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `actor_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `event_type` varchar(100) NOT NULL,
  `entity_type` varchar(100) NOT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `description` varchar(1000) DEFAULT NULL,
  `metadata_json` longtext DEFAULT NULL,
  `source_ip` varchar(45) DEFAULT NULL,
  `occurred_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `audit_calls`
--

CREATE TABLE `audit_calls` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `campaign_id` bigint(20) UNSIGNED NOT NULL,
  `source_id` bigint(20) UNSIGNED DEFAULT NULL,
  `external_call_id` varchar(255) DEFAULT NULL,
  `agent_external_id` varchar(255) DEFAULT NULL,
  `agent_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `phone_masked` varchar(100) DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `duration_seconds` int(10) UNSIGNED DEFAULT NULL,
  `audio_storage_key` varchar(1000) DEFAULT NULL,
  `audio_sha256` char(64) DEFAULT NULL,
  `audio_mime_type` varchar(100) DEFAULT NULL,
  `audio_size_bytes` bigint(20) UNSIGNED DEFAULT NULL,
  `audio_status` enum('NOT_RECEIVED','PENDING','AVAILABLE','MISSING','ERROR','DELETED') NOT NULL DEFAULT 'NOT_RECEIVED',
  `transcript_storage_key` varchar(1000) DEFAULT NULL,
  `metadata_json` longtext DEFAULT NULL,
  `processing_status` enum('NEW','READY','IN_PROGRESS','COMPLETED','REVIEW','ERROR','ARCHIVED') NOT NULL DEFAULT 'NEW',
  `imported_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `audit_forms`
--

CREATE TABLE `audit_forms` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('ACTIVE','INACTIVE','ARCHIVED') NOT NULL DEFAULT 'ACTIVE',
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `audit_forms`
--

INSERT INTO `audit_forms` (`id`, `tenant_id`, `name`, `description`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 'VENTAS CALIDDA PERU', 'EVALUAREMOS LAS VENTAS DIARIAS DE CALIDDA PERU', 'ACTIVE', 7, '2026-10-21 18:53:26', '2026-10-21 18:53:26');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `audit_form_questions`
--

CREATE TABLE `audit_form_questions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `form_version_id` bigint(20) UNSIGNED NOT NULL,
  `position` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `question_text` varchar(1000) NOT NULL,
  `help_text` text DEFAULT NULL,
  `answer_type` enum('YES_NO','SCALE','SINGLE_CHOICE','MULTIPLE_CHOICE','TEXT','NUMBER') NOT NULL DEFAULT 'YES_NO',
  `options_json` longtext DEFAULT NULL,
  `weight` decimal(8,2) NOT NULL DEFAULT 1.00,
  `is_critical` tinyint(1) NOT NULL DEFAULT 0,
  `required_answer` tinyint(1) NOT NULL DEFAULT 1,
  `allow_na` tinyint(1) NOT NULL DEFAULT 0,
  `na_score` decimal(8,2) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ;

--
-- Volcado de datos para la tabla `audit_form_questions`
--

INSERT INTO `audit_form_questions` (`id`, `tenant_id`, `form_version_id`, `position`, `question_text`, `help_text`, `answer_type`, `options_json`, `weight`, `is_critical`, `required_answer`, `allow_na`, `na_score`, `active`, `created_at`) VALUES
(1, 1, 1, 1, '¿El agente saludó al cliente y se identificó correctamente?', NULL, 'YES_NO', NULL, 10.00, 0, 1, 0, NULL, 1, '2026-10-21 19:02:39'),
(2, 1, 1, 2, '¿El agente verificó la identidad del cliente siguiendo el procedimiento establecido?', NULL, 'YES_NO', NULL, 20.00, 1, 1, 0, NULL, 1, '2026-10-21 19:36:15'),
(3, 1, 1, 3, '¿El agente escuchó atentamente y comprendió la necesidad del cliente?', 'Escala sugerida: 1 = Muy deficiente, 2 = Deficiente, 3 = Regular, 4 = Bueno, 5 = Excelente.', 'SCALE', NULL, 1.00, 0, 1, 0, NULL, 1, '2026-10-21 19:37:39'),
(4, 1, 1, 4, '¿La información proporcionada al cliente fue correcta?', NULL, 'YES_NO', NULL, 15.00, 1, 1, 0, NULL, 1, '2026-10-21 19:38:33'),
(5, 1, 1, 5, '¿El agente mantuvo un tono de voz respetuoso y profesional?', 'Escala sugerida: 1 a 5.', 'SCALE', NULL, 10.00, 0, 1, 0, NULL, 1, '2026-10-21 19:39:43'),
(6, 1, 1, 6, '¿El agente resolvió la consulta o proporcionó una solución adecuada?', NULL, 'YES_NO', NULL, 15.00, 0, 1, 1, NULL, 1, '2026-10-21 19:40:50'),
(7, 1, 1, 7, '¿El agente cumplió con el protocolo de cierre de la llamada?', NULL, 'YES_NO', NULL, 5.00, 0, 1, 0, NULL, 1, '2026-10-21 19:46:23'),
(8, 1, 1, 8, '¿Qué errores o incumplimientos se detectaron durante la llamada?', NULL, 'TEXT', NULL, 0.01, 0, 0, 1, NULL, 1, '2026-10-21 19:47:31'),
(9, 1, 1, 9, '¿Cuál fue el resultado final de la llamada?', NULL, 'SINGLE_CHOICE', '[\"Venta concretada\",\"consulta resuelta\",\"seguimiento pendiente\",\"cliente no interesado\",\"llamada no efectiva\"]', 5.00, 0, 1, 0, NULL, 1, '2026-10-21 19:50:01'),
(10, 1, 1, 10, 'Observaciones y recomendaciones del auditor', NULL, 'TEXT', NULL, 0.01, 0, 1, 1, NULL, 1, '2026-10-21 19:58:11'),
(11, 1, 2, 1, '¿El agente saludó al cliente y se identificó correctamente?', NULL, 'YES_NO', NULL, 10.00, 0, 1, 0, NULL, 1, '2026-10-21 20:05:04'),
(12, 1, 2, 2, '¿El agente verificó la identidad del cliente siguiendo el procedimiento establecido?', NULL, 'YES_NO', NULL, 20.00, 1, 1, 0, NULL, 1, '2026-10-21 20:05:04'),
(13, 1, 2, 3, '¿El agente escuchó atentamente y comprendió la necesidad del cliente?', 'Escala sugerida: 1 = Muy deficiente, 2 = Deficiente, 3 = Regular, 4 = Bueno, 5 = Excelente.', 'SCALE', NULL, 1.00, 0, 1, 0, NULL, 1, '2026-10-21 20:05:04'),
(14, 1, 2, 4, '¿La información proporcionada al cliente fue correcta?', NULL, 'YES_NO', NULL, 15.00, 1, 1, 0, NULL, 1, '2026-10-21 20:05:04'),
(15, 1, 2, 5, '¿El agente mantuvo un tono de voz respetuoso y profesional?', 'Escala sugerida: 1 a 5.', 'SCALE', NULL, 10.00, 0, 1, 0, NULL, 1, '2026-10-21 20:05:04'),
(16, 1, 2, 6, '¿El agente resolvió la consulta o proporcionó una solución adecuada?', NULL, 'YES_NO', NULL, 15.00, 0, 1, 1, NULL, 1, '2026-10-21 20:05:04'),
(17, 1, 2, 7, '¿El agente cumplió con el protocolo de cierre de la llamada?', NULL, 'YES_NO', NULL, 5.00, 0, 1, 0, NULL, 1, '2026-10-21 20:05:04'),
(18, 1, 2, 8, '¿Qué errores o incumplimientos se detectaron durante la llamada?', NULL, 'TEXT', NULL, 0.01, 0, 0, 1, NULL, 1, '2026-10-21 20:05:04'),
(19, 1, 2, 9, '¿Cuál fue el resultado final de la llamada?', NULL, 'SINGLE_CHOICE', '[\"Venta concretada\",\"consulta resuelta\",\"seguimiento pendiente\",\"cliente no interesado\",\"llamada no efectiva\"]', 5.00, 0, 1, 0, NULL, 1, '2026-10-21 20:05:04'),
(20, 1, 2, 10, 'Observaciones y recomendaciones del auditor', NULL, 'TEXT', NULL, 0.01, 0, 1, 1, NULL, 1, '2026-10-21 20:05:04');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `audit_form_versions`
--

CREATE TABLE `audit_form_versions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `form_id` bigint(20) UNSIGNED NOT NULL,
  `version_number` int(10) UNSIGNED NOT NULL,
  `status` enum('DRAFT','PUBLISHED','RETIRED') NOT NULL DEFAULT 'DRAFT',
  `passing_score` decimal(7,2) DEFAULT NULL,
  `na_policy` enum('EXCLUDE','ZERO','FULL') NOT NULL DEFAULT 'EXCLUDE',
  `published_by` bigint(20) UNSIGNED DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ;

--
-- Volcado de datos para la tabla `audit_form_versions`
--

INSERT INTO `audit_form_versions` (`id`, `tenant_id`, `form_id`, `version_number`, `status`, `passing_score`, `na_policy`, `published_by`, `published_at`, `created_by`, `created_at`) VALUES
(1, 1, 1, 1, 'DRAFT', NULL, 'EXCLUDE', NULL, NULL, 7, '2026-10-21 18:53:26'),
(2, 1, 1, 2, 'PUBLISHED', 80.00, 'ZERO', 7, '2026-10-21 15:05:56', 7, '2026-10-21 20:05:04');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `audit_jobs`
--

CREATE TABLE `audit_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `job_type` varchar(100) NOT NULL,
  `entity_type` varchar(100) NOT NULL,
  `entity_id` bigint(20) UNSIGNED NOT NULL,
  `idempotency_key` varchar(191) NOT NULL,
  `payload_json` longtext DEFAULT NULL,
  `status` enum('PENDING','RUNNING','COMPLETED','RETRY','FAILED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  `priority` int(11) NOT NULL DEFAULT 100,
  `attempts` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `max_attempts` int(10) UNSIGNED NOT NULL DEFAULT 5,
  `available_at` datetime NOT NULL DEFAULT current_timestamp(),
  `locked_at` datetime DEFAULT NULL,
  `locked_by` varchar(150) DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `last_error_code` varchar(100) DEFAULT NULL,
  `last_error_message` varchar(1000) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `audit_results`
--

CREATE TABLE `audit_results` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `task_id` bigint(20) UNSIGNED NOT NULL,
  `evaluator_type` enum('HUMAN','AI') NOT NULL,
  `evaluator_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ai_provider` varchar(100) DEFAULT NULL,
  `ai_model` varchar(150) DEFAULT NULL,
  `verdict` enum('PASS','FAIL','REVIEW','INCONCLUSIVE') NOT NULL DEFAULT 'REVIEW',
  `score` decimal(7,2) DEFAULT NULL,
  `analysis` longtext DEFAULT NULL,
  `strengths_json` longtext DEFAULT NULL,
  `improvement_json` longtext DEFAULT NULL,
  `confidence` decimal(6,5) DEFAULT NULL,
  `status` enum('DRAFT','SUBMITTED','SUPERSEDED') NOT NULL DEFAULT 'DRAFT',
  `evaluated_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `audit_result_answers`
--

CREATE TABLE `audit_result_answers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `result_id` bigint(20) UNSIGNED NOT NULL,
  `question_id` bigint(20) UNSIGNED NOT NULL,
  `answer_value` longtext DEFAULT NULL,
  `numeric_score` decimal(8,2) DEFAULT NULL,
  `is_na` tinyint(1) NOT NULL DEFAULT 0,
  `critical_failure` tinyint(1) NOT NULL DEFAULT 0,
  `comment` text DEFAULT NULL,
  `evidence_json` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `audit_selection_policies`
--

CREATE TABLE `audit_selection_policies` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `campaign_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `method` enum('MANUAL','RANDOM','RULES','AUTOMATIC') NOT NULL,
  `configuration_json` longtext DEFAULT NULL,
  `priority` int(11) NOT NULL DEFAULT 100,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `audit_tasks`
--

CREATE TABLE `audit_tasks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `call_id` bigint(20) UNSIGNED NOT NULL,
  `stage_id` bigint(20) UNSIGNED NOT NULL,
  `assigned_to` bigint(20) UNSIGNED DEFAULT NULL,
  `assigned_by` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('PENDING','ASSIGNED','IN_PROGRESS','SUBMITTED','REVIEW','COMPLETED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  `priority` int(11) NOT NULL DEFAULT 100,
  `due_at` datetime DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `audit_tenant_settings`
--

CREATE TABLE `audit_tenant_settings` (
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `default_audio_retention_days` int(10) UNSIGNED DEFAULT NULL,
  `require_human_review_after_ai` tinyint(1) NOT NULL DEFAULT 0,
  `allow_ai_processing` tinyint(1) NOT NULL DEFAULT 0,
  `audio_storage_backend` enum('LOCAL','NAS','S3_COMPATIBLE') NOT NULL DEFAULT 'LOCAL',
  `audio_storage_config_json` longtext DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `audit_workflow_stages`
--

CREATE TABLE `audit_workflow_stages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `campaign_id` bigint(20) UNSIGNED NOT NULL,
  `stage_number` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `form_version_id` bigint(20) UNSIGNED NOT NULL,
  `human_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `ai_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `ai_required` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `campaigns`
--

CREATE TABLE `campaigns` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `slug` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `campaigns`
--

INSERT INTO `campaigns` (`id`, `tenant_id`, `name`, `slug`, `description`, `status`, `start_date`, `end_date`, `created_by`, `created_at`, `updated_at`) VALUES
(3, 6, 'BARRIDO VENTA', 'BARRIDO-VENTA', 'BARRIDO PRUEBAS.', 'ACTIVE', '2026-09-25', '2026-12-30', 12, '2026-09-24 18:57:30', '2026-10-15 18:16:13'),
(4, 6, 'VENTAS 2', 'VENTAS 2 - PANDORA', 'PRUEBA Y MAS', 'ACTIVE', '2026-10-01', '2026-10-31', 8, '2026-10-14 20:28:44', '2026-10-15 18:01:18');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `campaign_users`
--

CREATE TABLE `campaign_users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `campaign_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `status` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `campaign_users`
--

INSERT INTO `campaign_users` (`id`, `tenant_id`, `campaign_id`, `user_id`, `status`, `assigned_at`) VALUES
(1, 6, 3, 15, 'ACTIVE', '2026-09-24 14:02:25'),
(2, 6, 3, 20, 'ACTIVE', '2026-10-15 13:16:32');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `permissions`
--

CREATE TABLE `permissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `status` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `permissions`
--

INSERT INTO `permissions` (`id`, `name`, `slug`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Ver empresas', 'tenants.view', 'Permite visualizar empresas.', 'ACTIVE', '2026-09-17 16:52:24', '2026-09-17 16:52:24'),
(2, 'Crear empresas', 'tenants.create', 'Permite crear empresas.', 'ACTIVE', '2026-09-17 16:52:24', '2026-09-17 16:52:24'),
(3, 'Editar empresas', 'tenants.edit', 'Permite editar empresas.', 'ACTIVE', '2026-09-17 16:52:24', '2026-09-17 16:52:24'),
(4, 'Eliminar empresas', 'tenants.delete', 'Permite eliminar empresas.', 'ACTIVE', '2026-09-17 16:52:24', '2026-09-17 16:52:24'),
(5, 'Ver usuarios', 'users.view', 'Permite visualizar usuarios.', 'ACTIVE', '2026-09-17 16:52:24', '2026-09-17 16:52:24'),
(6, 'Crear usuarios', 'users.create', 'Permite crear usuarios.', 'ACTIVE', '2026-09-17 16:52:24', '2026-09-17 16:52:24'),
(7, 'Editar usuarios', 'users.edit', 'Permite editar usuarios.', 'ACTIVE', '2026-09-17 16:52:24', '2026-09-17 16:52:24'),
(8, 'Eliminar usuarios', 'users.delete', 'Permite eliminar usuarios.', 'ACTIVE', '2026-09-17 16:52:24', '2026-09-17 16:52:24'),
(9, 'Ver roles', 'roles.view', 'Permite visualizar roles.', 'ACTIVE', '2026-09-17 16:52:24', '2026-09-17 16:52:24'),
(10, 'Crear roles', 'roles.create', 'Permite crear roles.', 'ACTIVE', '2026-09-17 16:52:24', '2026-09-17 16:52:24'),
(11, 'Editar roles', 'roles.edit', 'Permite editar roles.', 'ACTIVE', '2026-09-17 16:52:24', '2026-09-17 16:52:24'),
(12, 'Eliminar roles', 'roles.delete', 'Permite eliminar roles.', 'ACTIVE', '2026-09-17 16:52:24', '2026-09-17 16:52:24'),
(13, 'Ver permisos', 'permissions.view', 'Permite visualizar permisos.', 'ACTIVE', '2026-09-17 16:52:24', '2026-09-17 16:52:24'),
(14, 'Crear permisos', 'permissions.create', 'Permite crear permisos.', 'ACTIVE', '2026-09-17 16:52:24', '2026-09-17 16:52:24'),
(15, 'Editar permisos', 'permissions.edit', 'Permite editar permisos.', 'ACTIVE', '2026-09-17 16:52:24', '2026-09-17 16:52:24'),
(16, 'Eliminar permisos', 'permissions.delete', 'Permite eliminar permisos.', 'ACTIVE', '2026-09-17 16:52:24', '2026-09-17 16:52:24'),
(17, 'Ver auditorías', 'audits.view', 'Permite visualizar auditorías.', 'ACTIVE', '2026-09-17 16:52:24', '2026-09-17 16:52:24'),
(18, 'Crear auditorías', 'audits.create', 'Permite crear auditorías.', 'ACTIVE', '2026-09-17 16:52:24', '2026-09-17 16:52:24'),
(19, 'Editar auditorías', 'audits.edit', 'Permite editar auditorías.', 'ACTIVE', '2026-09-17 16:52:24', '2026-09-17 16:52:24'),
(20, 'Eliminar auditorías', 'audits.delete', 'Permite eliminar auditorías.', 'ACTIVE', '2026-09-17 16:52:24', '2026-09-17 16:52:24'),
(21, 'Ver llamadas', 'calls.view', 'Permite visualizar llamadas.', 'ACTIVE', '2026-09-17 16:52:24', '2026-09-17 16:52:24'),
(22, 'Ver campañas', 'campaigns.view', 'Permite visualizar campañas.', 'ACTIVE', '2026-09-17 16:52:24', '2026-09-17 16:52:24'),
(23, 'Crear campañas', 'campaigns.create', 'Permite crear campañas.', 'ACTIVE', '2026-09-17 16:52:24', '2026-09-17 16:52:24'),
(24, 'Editar campañas', 'campaigns.edit', 'Permite editar campañas.', 'ACTIVE', '2026-09-17 16:52:24', '2026-09-17 16:52:24'),
(25, 'Ver reportes', 'reports.view', 'Permite visualizar reportes.', 'ACTIVE', '2026-09-17 16:52:24', '2026-09-17 16:52:24'),
(26, 'Exportar reportes', 'reports.export', 'Permite exportar reportes.', 'ACTIVE', '2026-09-17 16:52:24', '2026-09-17 16:52:24'),
(27, 'Ver dashboard', 'dashboard.view', 'Permite visualizar el dashboard.', 'ACTIVE', '2026-09-17 16:52:24', '2026-09-17 16:52:24');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `status` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id`, `tenant_id`, `name`, `slug`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, NULL, 'Super Administrador', 'SUPERADMIN', 'Administrador global de la plataforma CallAudit.', 'ACTIVE', '2026-09-17 17:17:01', '2026-09-17 17:17:01'),
(6, 2, 'Administrador', 'ADMIN', 'Administrador de la empresa.', 'ACTIVE', '2026-09-17 20:46:26', '2026-09-17 20:46:26'),
(10, 1, 'Administrador', 'ADMIN', 'Administrador de la empresa.', 'ACTIVE', '2026-09-17 20:46:26', '2026-09-17 20:46:26'),
(11, 6, 'Administrador', 'ADMIN', 'Administrador de la empresa.', 'ACTIVE', '2026-09-17 20:46:26', '2026-09-17 20:46:26'),
(13, 2, 'Supervisor', 'SUPERVISOR', 'Supervisor de la empresa.', 'ACTIVE', '2026-09-17 20:46:26', '2026-09-17 20:46:26'),
(17, 1, 'Supervisor', 'SUPERVISOR', 'Supervisor de la empresa.', 'ACTIVE', '2026-09-17 20:46:26', '2026-09-17 20:46:26'),
(18, 6, 'Supervisor', 'SUPERVISOR', 'Supervisor de la empresa.', 'ACTIVE', '2026-09-17 20:46:26', '2026-09-17 20:46:26'),
(20, 2, 'Auditor', 'AUDITOR', 'Auditor de llamadas y evaluaciones.', 'ACTIVE', '2026-09-17 20:46:26', '2026-09-17 20:46:26'),
(24, 1, 'Auditor', 'AUDITOR', 'Auditor de llamadas y evaluaciones.', 'ACTIVE', '2026-09-17 20:46:26', '2026-09-17 20:46:26'),
(25, 6, 'Auditor', 'AUDITOR', 'Auditor de llamadas y evaluaciones.', 'ACTIVE', '2026-09-17 20:46:26', '2026-09-17 20:46:26'),
(27, 2, 'Agente', 'AGENTE', 'Usuario operativo de la empresa.', 'ACTIVE', '2026-09-17 20:46:26', '2026-09-17 20:46:26'),
(31, 1, 'Agente', 'AGENTE', 'Usuario operativo de la empresa.', 'ACTIVE', '2026-09-17 20:46:26', '2026-09-17 20:46:26'),
(32, 6, 'Agente', 'AGENTE', 'Usuario operativo de la empresa.', 'ACTIVE', '2026-09-17 20:46:26', '2026-09-17 20:46:26');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_id` bigint(20) UNSIGNED NOT NULL,
  `permission_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `role_permissions`
--

INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`) VALUES
(1, 1, '2026-09-17 17:51:18'),
(1, 2, '2026-09-17 17:51:18'),
(1, 3, '2026-09-17 17:51:18'),
(1, 4, '2026-09-17 17:51:18'),
(1, 5, '2026-09-17 17:51:18'),
(1, 6, '2026-09-17 17:51:18'),
(1, 7, '2026-09-17 17:51:18'),
(1, 8, '2026-09-17 17:51:18'),
(1, 9, '2026-09-17 17:51:18'),
(1, 10, '2026-09-17 17:51:18'),
(1, 11, '2026-09-17 17:51:18'),
(1, 12, '2026-09-17 17:51:18'),
(1, 13, '2026-09-17 17:51:18'),
(1, 14, '2026-09-17 17:51:18'),
(1, 15, '2026-09-17 17:51:18'),
(1, 16, '2026-09-17 17:51:18'),
(1, 17, '2026-09-17 17:51:18'),
(1, 18, '2026-09-17 17:51:18'),
(1, 19, '2026-09-17 17:51:18'),
(1, 20, '2026-09-17 17:51:18'),
(1, 21, '2026-09-17 17:51:18'),
(1, 22, '2026-09-17 17:51:18'),
(1, 23, '2026-09-17 17:51:18'),
(1, 24, '2026-09-17 17:51:18'),
(1, 25, '2026-09-17 17:51:18'),
(1, 26, '2026-09-17 17:51:18'),
(1, 27, '2026-09-17 17:51:18'),
(6, 5, '2026-09-17 20:48:55'),
(6, 6, '2026-09-17 20:48:55'),
(6, 7, '2026-09-17 20:48:55'),
(6, 8, '2026-09-17 20:48:55'),
(6, 9, '2026-09-17 20:48:55'),
(6, 10, '2026-09-17 20:48:55'),
(6, 11, '2026-09-17 20:48:55'),
(6, 12, '2026-09-17 20:48:55'),
(6, 13, '2026-09-17 20:48:55'),
(6, 14, '2026-09-17 20:48:55'),
(6, 15, '2026-09-17 20:48:55'),
(6, 16, '2026-09-17 20:48:55'),
(6, 17, '2026-09-17 20:48:55'),
(6, 18, '2026-09-17 20:48:55'),
(6, 19, '2026-09-17 20:48:55'),
(6, 20, '2026-09-17 20:48:55'),
(6, 21, '2026-09-17 20:48:55'),
(6, 22, '2026-09-17 20:48:55'),
(6, 23, '2026-09-17 20:48:55'),
(6, 24, '2026-09-17 20:48:55'),
(6, 25, '2026-09-17 20:48:55'),
(6, 26, '2026-09-17 20:48:55'),
(6, 27, '2026-09-17 20:48:55'),
(10, 5, '2026-09-17 20:48:55'),
(10, 6, '2026-09-17 20:48:55'),
(10, 7, '2026-09-17 20:48:55'),
(10, 8, '2026-09-17 20:48:55'),
(10, 9, '2026-09-17 20:48:55'),
(10, 10, '2026-09-17 20:48:55'),
(10, 11, '2026-09-17 20:48:55'),
(10, 12, '2026-09-17 20:48:55'),
(10, 13, '2026-09-17 20:48:55'),
(10, 14, '2026-09-17 20:48:55'),
(10, 15, '2026-09-17 20:48:55'),
(10, 16, '2026-09-17 20:48:55'),
(10, 17, '2026-09-17 20:48:55'),
(10, 18, '2026-09-17 20:48:55'),
(10, 19, '2026-09-17 20:48:55'),
(10, 20, '2026-09-17 20:48:55'),
(10, 21, '2026-09-17 20:48:55'),
(10, 22, '2026-09-17 20:48:55'),
(10, 23, '2026-09-17 20:48:55'),
(10, 24, '2026-09-17 20:48:55'),
(10, 25, '2026-09-17 20:48:55'),
(10, 26, '2026-09-17 20:48:55'),
(10, 27, '2026-09-17 20:48:55'),
(11, 5, '2026-09-17 20:48:55'),
(11, 6, '2026-09-17 20:48:55'),
(11, 7, '2026-09-17 20:48:55'),
(11, 8, '2026-09-17 20:48:55'),
(11, 9, '2026-09-17 20:48:55'),
(11, 10, '2026-09-17 20:48:55'),
(11, 11, '2026-09-17 20:48:55'),
(11, 12, '2026-09-17 20:48:55'),
(11, 13, '2026-09-17 20:48:55'),
(11, 14, '2026-09-17 20:48:55'),
(11, 15, '2026-09-17 20:48:55'),
(11, 16, '2026-09-17 20:48:55'),
(11, 17, '2026-09-17 20:48:55'),
(11, 18, '2026-09-17 20:48:55'),
(11, 19, '2026-09-17 20:48:55'),
(11, 20, '2026-09-17 20:48:55'),
(11, 21, '2026-09-17 20:48:55'),
(11, 22, '2026-09-17 20:48:55'),
(11, 23, '2026-09-17 20:48:55'),
(11, 24, '2026-09-17 20:48:55'),
(11, 25, '2026-09-17 20:48:55'),
(11, 26, '2026-09-17 20:48:55'),
(11, 27, '2026-09-17 20:48:55'),
(13, 5, '2026-09-17 20:48:55'),
(13, 7, '2026-09-17 20:48:55'),
(13, 17, '2026-09-17 20:48:55'),
(13, 18, '2026-09-17 20:48:55'),
(13, 19, '2026-09-17 20:48:55'),
(13, 20, '2026-09-17 20:48:55'),
(13, 21, '2026-09-17 20:48:55'),
(13, 22, '2026-09-17 20:48:55'),
(13, 25, '2026-09-17 20:48:55'),
(13, 26, '2026-09-17 20:48:55'),
(13, 27, '2026-09-17 20:48:55'),
(17, 5, '2026-09-17 20:48:55'),
(17, 7, '2026-09-17 20:48:55'),
(17, 17, '2026-09-17 20:48:55'),
(17, 18, '2026-09-17 20:48:55'),
(17, 19, '2026-09-17 20:48:55'),
(17, 20, '2026-09-17 20:48:55'),
(17, 21, '2026-09-17 20:48:55'),
(17, 22, '2026-09-17 20:48:55'),
(17, 25, '2026-09-17 20:48:55'),
(17, 26, '2026-09-17 20:48:55'),
(17, 27, '2026-09-17 20:48:55'),
(18, 5, '2026-09-17 20:48:55'),
(18, 7, '2026-09-17 20:48:55'),
(18, 17, '2026-09-17 20:48:55'),
(18, 18, '2026-09-17 20:48:55'),
(18, 19, '2026-09-17 20:48:55'),
(18, 20, '2026-09-17 20:48:55'),
(18, 21, '2026-09-17 20:48:55'),
(18, 22, '2026-09-17 20:48:55'),
(18, 25, '2026-09-17 20:48:55'),
(18, 26, '2026-09-17 20:48:55'),
(18, 27, '2026-09-17 20:48:55'),
(20, 17, '2026-09-17 20:48:55'),
(20, 18, '2026-09-17 20:48:55'),
(20, 19, '2026-09-17 20:48:55'),
(20, 21, '2026-09-17 20:48:55'),
(20, 25, '2026-09-17 20:48:55'),
(20, 26, '2026-09-17 20:48:55'),
(20, 27, '2026-09-17 20:48:55'),
(24, 17, '2026-09-17 20:48:55'),
(24, 18, '2026-09-17 20:48:55'),
(24, 19, '2026-09-17 20:48:55'),
(24, 21, '2026-09-17 20:48:55'),
(24, 25, '2026-09-17 20:48:55'),
(24, 26, '2026-09-17 20:48:55'),
(24, 27, '2026-09-17 20:48:55'),
(25, 17, '2026-09-17 20:48:55'),
(25, 18, '2026-09-17 20:48:55'),
(25, 19, '2026-09-17 20:48:55'),
(25, 21, '2026-09-17 20:48:55'),
(25, 25, '2026-09-17 20:48:55'),
(25, 26, '2026-09-17 20:48:55'),
(25, 27, '2026-09-17 20:48:55'),
(27, 21, '2026-09-17 20:48:55'),
(27, 27, '2026-09-17 20:48:55'),
(31, 21, '2026-09-17 20:48:55'),
(31, 27, '2026-09-17 20:48:55'),
(32, 21, '2026-09-17 20:48:55'),
(32, 27, '2026-09-17 20:48:55');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `telephony_sources`
--

CREATE TABLE `telephony_sources` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `platform` enum('ASTERISK','VICIDIAL','ISSABEL','GENERIC_API','DATABASE','CSV','EXCEL','MANUAL','OTHER') NOT NULL DEFAULT 'OTHER',
  `connection_mode` enum('API','READONLY_DATABASE','FILE_IMPORT','MANUAL','OTHER') NOT NULL DEFAULT 'MANUAL',
  `host` varchar(255) DEFAULT NULL,
  `port` int(10) UNSIGNED DEFAULT NULL,
  `database_name` varchar(150) DEFAULT NULL,
  `configuration_json` longtext DEFAULT NULL,
  `encrypted_secret` longblob DEFAULT NULL,
  `secret_key_version` varchar(50) DEFAULT NULL,
  `status` enum('ACTIVE','INACTIVE','ERROR','PENDING_TEST') NOT NULL DEFAULT 'PENDING_TEST',
  `last_test_at` datetime DEFAULT NULL,
  `last_error_code` varchar(100) DEFAULT NULL,
  `last_error_at` datetime DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `telephony_sources`
--

INSERT INTO `telephony_sources` (`id`, `tenant_id`, `name`, `platform`, `connection_mode`, `host`, `port`, `database_name`, `configuration_json`, `encrypted_secret`, `secret_key_version`, `status`, `last_test_at`, `last_error_code`, `last_error_at`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 'PBX Prueba CallAudit', 'ASTERISK', 'MANUAL', '192.168.5.6', 5038, 'asterisk', NULL, NULL, NULL, 'PENDING_TEST', NULL, NULL, NULL, 7, '2026-10-22 19:01:12', '2026-10-22 19:01:46');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tenants`
--

CREATE TABLE `tenants` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `slug` varchar(150) NOT NULL,
  `status` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `tenants`
--

INSERT INTO `tenants` (`id`, `name`, `slug`, `status`, `created_at`, `updated_at`) VALUES
(1, 'AGIL BPO SAC', 'empresa-demo', 'ACTIVE', '2026-09-16 16:35:07', '2026-09-16 16:35:07'),
(2, 'IGS', 'call-center-prueba', 'ACTIVE', '2026-09-16 16:35:07', '2026-09-16 16:35:07'),
(6, 'protecta sac', 'protecta-sac', 'ACTIVE', '2026-09-16 17:13:09', '2026-09-16 17:13:09');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `status` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `users`
--

INSERT INTO `users` (`id`, `tenant_id`, `name`, `email`, `password`, `status`, `created_at`, `updated_at`) VALUES
(7, NULL, 'Super Administrador', 'superadmin@callaudit.local', '$2y$10$kjzyAkYy9.DhUGL5AWAVG.kLkCuq3zxjWuXa1udR9uVa3i0ofotse', 'ACTIVE', '2026-09-24 15:45:55', '2026-09-24 16:53:46'),
(8, 6, 'Administrador Protecta', 'admin.protecta@callaudit.local', '$2y$10$PelQGLqZ/GNkuA0QMQf6oOuNGsrWWTZEIa2KB1esdy.34EynuSTqK', 'ACTIVE', '2026-09-24 15:45:55', '2026-09-24 17:31:40'),
(9, 6, 'Supervisor Protecta', 'supervisor.protecta@callaudit.local', '$2y$10$dzk2wuq8J1h0JEOPhwHXmO/f.nB/Q1Q/CMpTPHE1fq5Y9Je12WHO2', 'ACTIVE', '2026-09-24 15:45:55', '2026-10-13 18:40:24'),
(10, 6, 'Auditor Protecta', 'auditor.protecta@callaudit.local', '$2y$10$ZaKK/1WEh1WFkVXE58Mpcucw8LXmHRZ.pNe6fV8Zk88rN2bmoFS9u', 'ACTIVE', '2026-09-24 15:45:56', '2026-10-14 21:30:30'),
(11, 6, 'Prueba Supervisor', 'prueba.supervisor@callaudit.local', '$2y$10$EnJnZefcSzGxAmuIlqBax.uQOYLX/yoib.QjyfcfSC.8Vm0.TfaCy', 'ACTIVE', '2026-09-24 17:10:21', '2026-09-24 17:10:21'),
(12, 6, 'Admin 2', 'prueba.admin2@callaudit.local', '$2y$10$f.h3ofcJByHVBG/tblZyuOadQeJwquMe3JCP2vCzsL3dHB9y4ETHm', 'ACTIVE', '2026-09-24 17:38:01', '2026-09-24 17:38:01'),
(13, 6, 'Supervisor 2', 'prueba.supervisor2@callaudit.local', '$2y$10$YImtiikAe/k.vG/dY1PV4eyzAlUisY64lt2WWn.F4Wmrb66NmSuQ.', 'ACTIVE', '2026-09-24 17:39:26', '2026-09-24 17:39:26'),
(14, 6, 'Auditor 2', 'prueba.auditor2@callaudit.local', '$2y$10$J/aDMcgVuOdNUvJbtwa6cuX4Q9wamsJC8G5gJRVKyAfYibWzqyTBy', 'ACTIVE', '2026-09-24 17:40:07', '2026-09-24 17:40:07'),
(15, 6, 'Agente 2', 'prueba.agente2@callaudit.local', '$2y$10$FN3/8GWB3lnsYzvbUaZj7eNrry/iD1fv9IHWOplRHfP3gq1uWJL0e', 'ACTIVE', '2026-09-24 17:41:17', '2026-10-15 17:14:32'),
(16, 1, 'Usuario Prueba Multiempresa', 'prueba.multempresa@callaudit.local', '$2y$10$AIv.9SSSZPVwTUM1AR.Zz.ixSmzQN5F/W8uxGwvp.XDCaGURffK5G', 'ACTIVE', '2026-10-13 16:09:15', '2026-10-14 20:00:33'),
(17, 2, 'Usuario Prueba Multiempresa', 'prueba.multempresa@callaudit.local', '$2y$10$AIv.9SSSZPVwTUM1AR.Zz.ixSmzQN5F/W8uxGwvp.XDCaGURffK5G', 'ACTIVE', '2026-10-13 16:09:15', '2026-10-14 20:00:33'),
(20, 6, 'Agente Prueba 3', 'prueba.agente3@callaudit.local', '$2y$12$Wo.INFbmWCGXAX2LNzQnoOCz6c3gBENXfzopp07nRQkmo5/lyeZZ2', 'ACTIVE', '2026-10-15 18:14:30', '2026-10-15 18:14:30'),
(21, 6, 'Agente Prueba 4', 'prueba.agente4@callaudit.local', '$2y$12$Wo.INFbmWCGXAX2LNzQnoOCz6c3gBENXfzopp07nRQkmo5/lyeZZ2', 'ACTIVE', '2026-10-15 18:14:30', '2026-10-15 18:14:30'),
(22, 6, 'Agente Prueba 5', 'prueba.agente5@callaudit.local', '$2y$12$Wo.INFbmWCGXAX2LNzQnoOCz6c3gBENXfzopp07nRQkmo5/lyeZZ2', 'ACTIVE', '2026-10-15 18:14:30', '2026-10-15 18:14:30'),
(23, 2, 'Agente Superadmin Prueba', 'agentesuperadmin.prueba@callaudit.local', '$2y$10$dDpqXjlvsJY8h3zmk/Y3NOWWpPH23RGu9lXBPDOgcxF/lX9Cg8Vna', 'ACTIVE', '2026-10-15 18:35:51', '2026-10-15 18:35:51'),
(24, 1, 'agentecreado  desdeadmin', 'prueba.agenteadm@local.com', '$2y$10$RL5kriDlINzNeUPBLYvxdexgqVx4nTsDt.oqe7vrwZxryJN8gr6.m', 'ACTIVE', '2026-10-15 18:36:56', '2026-10-15 18:36:56');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `user_permissions`
--

CREATE TABLE `user_permissions` (
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `permission_id` bigint(20) UNSIGNED NOT NULL,
  `effect` enum('GRANT','DENY') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `user_permissions`
--

INSERT INTO `user_permissions` (`user_id`, `permission_id`, `effect`, `created_at`) VALUES
(9, 6, 'GRANT', '2026-10-13 18:16:50');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `user_roles`
--

CREATE TABLE `user_roles` (
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `role_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `user_roles`
--

INSERT INTO `user_roles` (`user_id`, `role_id`, `created_at`) VALUES
(7, 1, '2026-09-24 15:45:56'),
(8, 11, '2026-09-24 15:45:56'),
(9, 18, '2026-09-24 15:45:56'),
(10, 25, '2026-09-24 15:45:56'),
(11, 18, '2026-09-24 17:10:21'),
(12, 11, '2026-09-24 17:38:01'),
(13, 18, '2026-09-24 17:39:26'),
(14, 25, '2026-09-24 17:40:07'),
(15, 32, '2026-09-24 17:41:17'),
(16, 17, '2026-10-13 16:09:15'),
(17, 13, '2026-10-13 16:09:15'),
(20, 32, '2026-10-15 18:14:30'),
(21, 32, '2026-10-15 18:14:30'),
(22, 32, '2026-10-15 18:14:30'),
(23, 27, '2026-10-15 18:35:51'),
(24, 31, '2026-10-15 18:36:56');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `ai_provider_configs`
--
ALTER TABLE `ai_provider_configs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_ai_provider_tenant` (`tenant_id`,`provider_code`),
  ADD KEY `fk_aip_creator` (`created_by`);

--
-- Indices de la tabla `audit_activity_log`
--
ALTER TABLE `audit_activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_aactivity_tenant_date` (`tenant_id`,`occurred_at`),
  ADD KEY `idx_aactivity_entity` (`tenant_id`,`entity_type`,`entity_id`,`occurred_at`),
  ADD KEY `idx_aactivity_actor` (`tenant_id`,`actor_user_id`,`occurred_at`),
  ADD KEY `fk_aactivity_actor` (`actor_user_id`);

--
-- Indices de la tabla `audit_calls`
--
ALTER TABLE `audit_calls`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_audit_call_tenant_id` (`tenant_id`,`id`),
  ADD UNIQUE KEY `uk_audit_call_external` (`tenant_id`,`source_id`,`external_call_id`),
  ADD KEY `idx_audit_calls_campaign_date` (`tenant_id`,`campaign_id`,`started_at`),
  ADD KEY `idx_audit_calls_processing` (`tenant_id`,`processing_status`,`started_at`),
  ADD KEY `idx_audit_calls_agent` (`tenant_id`,`agent_user_id`,`started_at`),
  ADD KEY `fk_acall_campaign` (`campaign_id`),
  ADD KEY `fk_acall_agent` (`agent_user_id`);

--
-- Indices de la tabla `audit_forms`
--
ALTER TABLE `audit_forms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_audit_forms_tenant_id` (`tenant_id`,`id`),
  ADD KEY `idx_audit_forms_tenant_status` (`tenant_id`,`status`),
  ADD KEY `fk_audit_forms_creator` (`created_by`);

--
-- Indices de la tabla `audit_form_questions`
--
ALTER TABLE `audit_form_questions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_audit_question_position` (`tenant_id`,`form_version_id`,`position`),
  ADD UNIQUE KEY `uk_audit_question_tenant_id` (`tenant_id`,`id`);

--
-- Indices de la tabla `audit_form_versions`
--
ALTER TABLE `audit_form_versions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_audit_form_version_number` (`tenant_id`,`form_id`,`version_number`),
  ADD UNIQUE KEY `uk_audit_form_version_tenant_id` (`tenant_id`,`id`),
  ADD KEY `fk_aformv_publisher` (`published_by`),
  ADD KEY `fk_aformv_creator` (`created_by`);

--
-- Indices de la tabla `audit_jobs`
--
ALTER TABLE `audit_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_audit_job_idempotency` (`tenant_id`,`idempotency_key`),
  ADD KEY `idx_audit_jobs_queue` (`status`,`available_at`,`priority`,`id`),
  ADD KEY `idx_audit_jobs_tenant` (`tenant_id`,`status`,`created_at`);

--
-- Indices de la tabla `audit_results`
--
ALTER TABLE `audit_results`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_audit_result_tenant_id` (`tenant_id`,`id`),
  ADD KEY `idx_audit_results_task` (`tenant_id`,`task_id`,`evaluator_type`,`status`),
  ADD KEY `fk_aresult_evaluator` (`evaluator_user_id`);

--
-- Indices de la tabla `audit_result_answers`
--
ALTER TABLE `audit_result_answers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_aresult_answer` (`tenant_id`,`result_id`,`question_id`),
  ADD KEY `fk_ra_question` (`tenant_id`,`question_id`);

--
-- Indices de la tabla `audit_selection_policies`
--
ALTER TABLE `audit_selection_policies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_selection_policy_tenant_id` (`tenant_id`,`id`),
  ADD KEY `idx_selection_policy_campaign` (`tenant_id`,`campaign_id`,`enabled`,`priority`),
  ADD KEY `fk_spolicy_campaign` (`campaign_id`),
  ADD KEY `fk_spolicy_creator` (`created_by`);

--
-- Indices de la tabla `audit_tasks`
--
ALTER TABLE `audit_tasks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_audit_task_tenant_id` (`tenant_id`,`id`),
  ADD UNIQUE KEY `uk_audit_task_call_stage` (`tenant_id`,`call_id`,`stage_id`),
  ADD KEY `idx_audit_tasks_assignee` (`tenant_id`,`assigned_to`,`status`,`due_at`),
  ADD KEY `idx_audit_tasks_queue` (`tenant_id`,`status`,`priority`,`created_at`),
  ADD KEY `fk_atask_stage` (`tenant_id`,`stage_id`),
  ADD KEY `fk_atask_assignee` (`assigned_to`),
  ADD KEY `fk_atask_assigner` (`assigned_by`);

--
-- Indices de la tabla `audit_tenant_settings`
--
ALTER TABLE `audit_tenant_settings`
  ADD PRIMARY KEY (`tenant_id`),
  ADD KEY `fk_atsettings_updater` (`updated_by`);

--
-- Indices de la tabla `audit_workflow_stages`
--
ALTER TABLE `audit_workflow_stages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_audit_stage_number` (`tenant_id`,`campaign_id`,`stage_number`),
  ADD UNIQUE KEY `uk_audit_stage_tenant_id` (`tenant_id`,`id`),
  ADD KEY `fk_astage_campaign` (`campaign_id`),
  ADD KEY `fk_astage_form_version` (`tenant_id`,`form_version_id`),
  ADD KEY `fk_astage_creator` (`created_by`);

--
-- Indices de la tabla `campaigns`
--
ALTER TABLE `campaigns`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_campaigns_tenant_slug` (`tenant_id`,`slug`),
  ADD KEY `idx_campaigns_tenant` (`tenant_id`),
  ADD KEY `idx_campaigns_created_by` (`created_by`);

--
-- Indices de la tabla `campaign_users`
--
ALTER TABLE `campaign_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_campaign_users_assignment` (`tenant_id`,`campaign_id`,`user_id`),
  ADD KEY `fk_campaign_users_campaign` (`campaign_id`),
  ADD KEY `fk_campaign_users_user` (`user_id`);

--
-- Indices de la tabla `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_permissions_slug` (`slug`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_roles_tenant_slug` (`tenant_id`,`slug`);

--
-- Indices de la tabla `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_id`,`permission_id`),
  ADD KEY `fk_role_permissions_permission` (`permission_id`);

--
-- Indices de la tabla `telephony_sources`
--
ALTER TABLE `telephony_sources`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_telephony_source_tenant_id` (`tenant_id`,`id`),
  ADD KEY `idx_telephony_source_status` (`tenant_id`,`status`),
  ADD KEY `fk_tsource_creator` (`created_by`);

--
-- Indices de la tabla `tenants`
--
ALTER TABLE `tenants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_tenants_slug` (`slug`);

--
-- Indices de la tabla `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_users_tenant_email` (`tenant_id`,`email`);

--
-- Indices de la tabla `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD PRIMARY KEY (`user_id`,`permission_id`),
  ADD KEY `fk_user_permissions_permission` (`permission_id`);

--
-- Indices de la tabla `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`user_id`,`role_id`),
  ADD KEY `fk_user_roles_role` (`role_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `ai_provider_configs`
--
ALTER TABLE `ai_provider_configs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `audit_activity_log`
--
ALTER TABLE `audit_activity_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `audit_calls`
--
ALTER TABLE `audit_calls`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `audit_forms`
--
ALTER TABLE `audit_forms`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `audit_form_questions`
--
ALTER TABLE `audit_form_questions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `audit_form_versions`
--
ALTER TABLE `audit_form_versions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `audit_jobs`
--
ALTER TABLE `audit_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `audit_results`
--
ALTER TABLE `audit_results`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `audit_result_answers`
--
ALTER TABLE `audit_result_answers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `audit_selection_policies`
--
ALTER TABLE `audit_selection_policies`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `audit_tasks`
--
ALTER TABLE `audit_tasks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `audit_workflow_stages`
--
ALTER TABLE `audit_workflow_stages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `campaigns`
--
ALTER TABLE `campaigns`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `campaign_users`
--
ALTER TABLE `campaign_users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT de la tabla `telephony_sources`
--
ALTER TABLE `telephony_sources`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `tenants`
--
ALTER TABLE `tenants`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `ai_provider_configs`
--
ALTER TABLE `ai_provider_configs`
  ADD CONSTRAINT `fk_aip_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_aip_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`);

--
-- Filtros para la tabla `audit_activity_log`
--
ALTER TABLE `audit_activity_log`
  ADD CONSTRAINT `fk_aactivity_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_aactivity_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`);

--
-- Filtros para la tabla `audit_calls`
--
ALTER TABLE `audit_calls`
  ADD CONSTRAINT `fk_acall_agent` FOREIGN KEY (`agent_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_acall_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`),
  ADD CONSTRAINT `fk_acall_source` FOREIGN KEY (`tenant_id`,`source_id`) REFERENCES `telephony_sources` (`tenant_id`, `id`),
  ADD CONSTRAINT `fk_acall_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`);

--
-- Filtros para la tabla `audit_forms`
--
ALTER TABLE `audit_forms`
  ADD CONSTRAINT `fk_audit_forms_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_audit_forms_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`);

--
-- Filtros para la tabla `audit_form_questions`
--
ALTER TABLE `audit_form_questions`
  ADD CONSTRAINT `fk_aquestion_version` FOREIGN KEY (`tenant_id`,`form_version_id`) REFERENCES `audit_form_versions` (`tenant_id`, `id`);

--
-- Filtros para la tabla `audit_form_versions`
--
ALTER TABLE `audit_form_versions`
  ADD CONSTRAINT `fk_aformv_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_aformv_form` FOREIGN KEY (`tenant_id`,`form_id`) REFERENCES `audit_forms` (`tenant_id`, `id`),
  ADD CONSTRAINT `fk_aformv_publisher` FOREIGN KEY (`published_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_aformv_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`);

--
-- Filtros para la tabla `audit_jobs`
--
ALTER TABLE `audit_jobs`
  ADD CONSTRAINT `fk_ajob_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`);

--
-- Filtros para la tabla `audit_results`
--
ALTER TABLE `audit_results`
  ADD CONSTRAINT `fk_aresult_evaluator` FOREIGN KEY (`evaluator_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_aresult_task` FOREIGN KEY (`tenant_id`,`task_id`) REFERENCES `audit_tasks` (`tenant_id`, `id`);

--
-- Filtros para la tabla `audit_result_answers`
--
ALTER TABLE `audit_result_answers`
  ADD CONSTRAINT `fk_ra_question` FOREIGN KEY (`tenant_id`,`question_id`) REFERENCES `audit_form_questions` (`tenant_id`, `id`),
  ADD CONSTRAINT `fk_ra_result` FOREIGN KEY (`tenant_id`,`result_id`) REFERENCES `audit_results` (`tenant_id`, `id`);

--
-- Filtros para la tabla `audit_selection_policies`
--
ALTER TABLE `audit_selection_policies`
  ADD CONSTRAINT `fk_spolicy_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`),
  ADD CONSTRAINT `fk_spolicy_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_spolicy_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`);

--
-- Filtros para la tabla `audit_tasks`
--
ALTER TABLE `audit_tasks`
  ADD CONSTRAINT `fk_atask_assignee` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_atask_assigner` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_atask_call` FOREIGN KEY (`tenant_id`,`call_id`) REFERENCES `audit_calls` (`tenant_id`, `id`),
  ADD CONSTRAINT `fk_atask_stage` FOREIGN KEY (`tenant_id`,`stage_id`) REFERENCES `audit_workflow_stages` (`tenant_id`, `id`),
  ADD CONSTRAINT `fk_atask_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`);

--
-- Filtros para la tabla `audit_tenant_settings`
--
ALTER TABLE `audit_tenant_settings`
  ADD CONSTRAINT `fk_atsettings_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`),
  ADD CONSTRAINT `fk_atsettings_updater` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);

--
-- Filtros para la tabla `audit_workflow_stages`
--
ALTER TABLE `audit_workflow_stages`
  ADD CONSTRAINT `fk_astage_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`),
  ADD CONSTRAINT `fk_astage_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_astage_form_version` FOREIGN KEY (`tenant_id`,`form_version_id`) REFERENCES `audit_form_versions` (`tenant_id`, `id`),
  ADD CONSTRAINT `fk_astage_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`);

--
-- Filtros para la tabla `campaigns`
--
ALTER TABLE `campaigns`
  ADD CONSTRAINT `fk_campaigns_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_campaigns_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`);

--
-- Filtros para la tabla `campaign_users`
--
ALTER TABLE `campaign_users`
  ADD CONSTRAINT `fk_campaign_users_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`),
  ADD CONSTRAINT `fk_campaign_users_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`),
  ADD CONSTRAINT `fk_campaign_users_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Filtros para la tabla `roles`
--
ALTER TABLE `roles`
  ADD CONSTRAINT `fk_roles_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `fk_role_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `telephony_sources`
--
ALTER TABLE `telephony_sources`
  ADD CONSTRAINT `fk_tsource_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_tsource_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`);

--
-- Filtros para la tabla `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD CONSTRAINT `fk_user_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_user_permissions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `user_roles`
--
ALTER TABLE `user_roles`
  ADD CONSTRAINT `fk_user_roles_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_user_roles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
