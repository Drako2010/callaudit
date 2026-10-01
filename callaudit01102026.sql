-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 01-10-2026 a las 21:48:51
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
CREATE DATABASE IF NOT EXISTS `callaudit` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `callaudit`;

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
(3, 6, 'BARRIDO VENTA', 'BARRIDO-VENTA', 'BARRIDO PRUEBAS', 'ACTIVE', '2026-09-25', '2026-12-30', 12, '2026-09-24 18:57:30', '2026-09-24 18:58:18');

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
(1, 6, 3, 15, 'ACTIVE', '2026-09-24 14:02:25');

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
(2, 9, 'Administrador', 'ADMIN', 'Administrador de la empresa.', 'ACTIVE', '2026-09-17 17:30:26', '2026-09-17 17:30:26'),
(3, 9, 'Supervisor', 'SUPERVISOR', 'Supervisor de la empresa.', 'ACTIVE', '2026-09-17 17:30:26', '2026-09-17 17:30:26'),
(4, 9, 'Auditor', 'AUDITOR', 'Auditor de llamadas y evaluaciones.', 'ACTIVE', '2026-09-17 17:30:26', '2026-09-17 17:30:26'),
(5, 9, 'Agente', 'AGENTE', 'Usuario operativo de la empresa.', 'ACTIVE', '2026-09-17 17:30:26', '2026-09-17 17:30:26'),
(6, 2, 'Administrador', 'ADMIN', 'Administrador de la empresa.', 'ACTIVE', '2026-09-17 20:46:26', '2026-09-17 20:46:26'),
(7, 5, 'Administrador', 'ADMIN', 'Administrador de la empresa.', 'ACTIVE', '2026-09-17 20:46:26', '2026-09-17 20:46:26'),
(8, 3, 'Administrador', 'ADMIN', 'Administrador de la empresa.', 'ACTIVE', '2026-09-17 20:46:26', '2026-09-17 20:46:26'),
(9, 4, 'Administrador', 'ADMIN', 'Administrador de la empresa.', 'ACTIVE', '2026-09-17 20:46:26', '2026-09-17 20:46:26'),
(10, 1, 'Administrador', 'ADMIN', 'Administrador de la empresa.', 'ACTIVE', '2026-09-17 20:46:26', '2026-09-17 20:46:26'),
(11, 6, 'Administrador', 'ADMIN', 'Administrador de la empresa.', 'ACTIVE', '2026-09-17 20:46:26', '2026-09-17 20:46:26'),
(13, 2, 'Supervisor', 'SUPERVISOR', 'Supervisor de la empresa.', 'ACTIVE', '2026-09-17 20:46:26', '2026-09-17 20:46:26'),
(14, 5, 'Supervisor', 'SUPERVISOR', 'Supervisor de la empresa.', 'ACTIVE', '2026-09-17 20:46:26', '2026-09-17 20:46:26'),
(15, 3, 'Supervisor', 'SUPERVISOR', 'Supervisor de la empresa.', 'ACTIVE', '2026-09-17 20:46:26', '2026-09-17 20:46:26'),
(16, 4, 'Supervisor', 'SUPERVISOR', 'Supervisor de la empresa.', 'ACTIVE', '2026-09-17 20:46:26', '2026-09-17 20:46:26'),
(17, 1, 'Supervisor', 'SUPERVISOR', 'Supervisor de la empresa.', 'ACTIVE', '2026-09-17 20:46:26', '2026-09-17 20:46:26'),
(18, 6, 'Supervisor', 'SUPERVISOR', 'Supervisor de la empresa.', 'ACTIVE', '2026-09-17 20:46:26', '2026-09-17 20:46:26'),
(20, 2, 'Auditor', 'AUDITOR', 'Auditor de llamadas y evaluaciones.', 'ACTIVE', '2026-09-17 20:46:26', '2026-09-17 20:46:26'),
(21, 5, 'Auditor', 'AUDITOR', 'Auditor de llamadas y evaluaciones.', 'ACTIVE', '2026-09-17 20:46:26', '2026-09-17 20:46:26'),
(22, 3, 'Auditor', 'AUDITOR', 'Auditor de llamadas y evaluaciones.', 'ACTIVE', '2026-09-17 20:46:26', '2026-09-17 20:46:26'),
(23, 4, 'Auditor', 'AUDITOR', 'Auditor de llamadas y evaluaciones.', 'ACTIVE', '2026-09-17 20:46:26', '2026-09-17 20:46:26'),
(24, 1, 'Auditor', 'AUDITOR', 'Auditor de llamadas y evaluaciones.', 'ACTIVE', '2026-09-17 20:46:26', '2026-09-17 20:46:26'),
(25, 6, 'Auditor', 'AUDITOR', 'Auditor de llamadas y evaluaciones.', 'ACTIVE', '2026-09-17 20:46:26', '2026-09-17 20:46:26'),
(27, 2, 'Agente', 'AGENTE', 'Usuario operativo de la empresa.', 'ACTIVE', '2026-09-17 20:46:26', '2026-09-17 20:46:26'),
(28, 5, 'Agente', 'AGENTE', 'Usuario operativo de la empresa.', 'ACTIVE', '2026-09-17 20:46:26', '2026-09-17 20:46:26'),
(29, 3, 'Agente', 'AGENTE', 'Usuario operativo de la empresa.', 'ACTIVE', '2026-09-17 20:46:26', '2026-09-17 20:46:26'),
(30, 4, 'Agente', 'AGENTE', 'Usuario operativo de la empresa.', 'ACTIVE', '2026-09-17 20:46:26', '2026-09-17 20:46:26'),
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
(2, 5, '2026-09-17 17:53:10'),
(2, 6, '2026-09-17 17:53:10'),
(2, 7, '2026-09-17 17:53:10'),
(2, 8, '2026-09-17 17:53:10'),
(2, 9, '2026-09-17 17:53:10'),
(2, 10, '2026-09-17 17:53:10'),
(2, 11, '2026-09-17 17:53:10'),
(2, 12, '2026-09-17 17:53:10'),
(2, 13, '2026-09-17 17:53:10'),
(2, 14, '2026-09-17 17:53:10'),
(2, 15, '2026-09-17 17:53:10'),
(2, 16, '2026-09-17 17:53:10'),
(2, 17, '2026-09-17 17:53:10'),
(2, 18, '2026-09-17 17:53:10'),
(2, 19, '2026-09-17 17:53:10'),
(2, 20, '2026-09-17 17:53:10'),
(2, 21, '2026-09-17 17:53:10'),
(2, 22, '2026-09-17 17:53:10'),
(2, 23, '2026-09-17 17:53:10'),
(2, 24, '2026-09-17 17:53:10'),
(2, 25, '2026-09-17 17:53:10'),
(2, 26, '2026-09-17 17:53:10'),
(2, 27, '2026-09-17 17:53:10'),
(3, 5, '2026-09-17 17:53:49'),
(3, 7, '2026-09-17 17:53:49'),
(3, 17, '2026-09-17 17:53:49'),
(3, 18, '2026-09-17 17:53:49'),
(3, 19, '2026-09-17 17:53:49'),
(3, 20, '2026-09-17 17:53:49'),
(3, 21, '2026-09-17 17:53:49'),
(3, 22, '2026-09-17 17:53:49'),
(3, 25, '2026-09-17 17:53:49'),
(3, 26, '2026-09-17 17:53:49'),
(3, 27, '2026-09-17 17:53:49'),
(4, 17, '2026-09-17 17:54:04'),
(4, 18, '2026-09-17 17:54:04'),
(4, 19, '2026-09-17 17:54:04'),
(4, 21, '2026-09-17 17:54:04'),
(4, 25, '2026-09-17 17:54:04'),
(4, 26, '2026-09-17 17:54:04'),
(4, 27, '2026-09-17 17:54:04'),
(5, 21, '2026-09-17 17:54:52'),
(5, 27, '2026-09-17 17:54:52'),
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
(7, 5, '2026-09-17 20:48:55'),
(7, 6, '2026-09-17 20:48:55'),
(7, 7, '2026-09-17 20:48:55'),
(7, 8, '2026-09-17 20:48:55'),
(7, 9, '2026-09-17 20:48:55'),
(7, 10, '2026-09-17 20:48:55'),
(7, 11, '2026-09-17 20:48:55'),
(7, 12, '2026-09-17 20:48:55'),
(7, 13, '2026-09-17 20:48:55'),
(7, 14, '2026-09-17 20:48:55'),
(7, 15, '2026-09-17 20:48:55'),
(7, 16, '2026-09-17 20:48:55'),
(7, 17, '2026-09-17 20:48:55'),
(7, 18, '2026-09-17 20:48:55'),
(7, 19, '2026-09-17 20:48:55'),
(7, 20, '2026-09-17 20:48:55'),
(7, 21, '2026-09-17 20:48:55'),
(7, 22, '2026-09-17 20:48:55'),
(7, 23, '2026-09-17 20:48:55'),
(7, 24, '2026-09-17 20:48:55'),
(7, 25, '2026-09-17 20:48:55'),
(7, 26, '2026-09-17 20:48:55'),
(7, 27, '2026-09-17 20:48:55'),
(8, 5, '2026-09-17 20:48:55'),
(8, 6, '2026-09-17 20:48:55'),
(8, 7, '2026-09-17 20:48:55'),
(8, 8, '2026-09-17 20:48:55'),
(8, 9, '2026-09-17 20:48:55'),
(8, 10, '2026-09-17 20:48:55'),
(8, 11, '2026-09-17 20:48:55'),
(8, 12, '2026-09-17 20:48:55'),
(8, 13, '2026-09-17 20:48:55'),
(8, 14, '2026-09-17 20:48:55'),
(8, 15, '2026-09-17 20:48:55'),
(8, 16, '2026-09-17 20:48:55'),
(8, 17, '2026-09-17 20:48:55'),
(8, 18, '2026-09-17 20:48:55'),
(8, 19, '2026-09-17 20:48:55'),
(8, 20, '2026-09-17 20:48:55'),
(8, 21, '2026-09-17 20:48:55'),
(8, 22, '2026-09-17 20:48:55'),
(8, 23, '2026-09-17 20:48:55'),
(8, 24, '2026-09-17 20:48:55'),
(8, 25, '2026-09-17 20:48:55'),
(8, 26, '2026-09-17 20:48:55'),
(8, 27, '2026-09-17 20:48:55'),
(9, 5, '2026-09-17 20:48:55'),
(9, 6, '2026-09-17 20:48:55'),
(9, 7, '2026-09-17 20:48:55'),
(9, 8, '2026-09-17 20:48:55'),
(9, 9, '2026-09-17 20:48:55'),
(9, 10, '2026-09-17 20:48:55'),
(9, 11, '2026-09-17 20:48:55'),
(9, 12, '2026-09-17 20:48:55'),
(9, 13, '2026-09-17 20:48:55'),
(9, 14, '2026-09-17 20:48:55'),
(9, 15, '2026-09-17 20:48:55'),
(9, 16, '2026-09-17 20:48:55'),
(9, 17, '2026-09-17 20:48:55'),
(9, 18, '2026-09-17 20:48:55'),
(9, 19, '2026-09-17 20:48:55'),
(9, 20, '2026-09-17 20:48:55'),
(9, 21, '2026-09-17 20:48:55'),
(9, 22, '2026-09-17 20:48:55'),
(9, 23, '2026-09-17 20:48:55'),
(9, 24, '2026-09-17 20:48:55'),
(9, 25, '2026-09-17 20:48:55'),
(9, 26, '2026-09-17 20:48:55'),
(9, 27, '2026-09-17 20:48:55'),
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
(14, 5, '2026-09-17 20:48:55'),
(14, 7, '2026-09-17 20:48:55'),
(14, 17, '2026-09-17 20:48:55'),
(14, 18, '2026-09-17 20:48:55'),
(14, 19, '2026-09-17 20:48:55'),
(14, 20, '2026-09-17 20:48:55'),
(14, 21, '2026-09-17 20:48:55'),
(14, 22, '2026-09-17 20:48:55'),
(14, 25, '2026-09-17 20:48:55'),
(14, 26, '2026-09-17 20:48:55'),
(14, 27, '2026-09-17 20:48:55'),
(15, 5, '2026-09-17 20:48:55'),
(15, 7, '2026-09-17 20:48:55'),
(15, 17, '2026-09-17 20:48:55'),
(15, 18, '2026-09-17 20:48:55'),
(15, 19, '2026-09-17 20:48:55'),
(15, 20, '2026-09-17 20:48:55'),
(15, 21, '2026-09-17 20:48:55'),
(15, 22, '2026-09-17 20:48:55'),
(15, 25, '2026-09-17 20:48:55'),
(15, 26, '2026-09-17 20:48:55'),
(15, 27, '2026-09-17 20:48:55'),
(16, 5, '2026-09-17 20:48:55'),
(16, 7, '2026-09-17 20:48:55'),
(16, 17, '2026-09-17 20:48:55'),
(16, 18, '2026-09-17 20:48:55'),
(16, 19, '2026-09-17 20:48:55'),
(16, 20, '2026-09-17 20:48:55'),
(16, 21, '2026-09-17 20:48:55'),
(16, 22, '2026-09-17 20:48:55'),
(16, 25, '2026-09-17 20:48:55'),
(16, 26, '2026-09-17 20:48:55'),
(16, 27, '2026-09-17 20:48:55'),
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
(21, 17, '2026-09-17 20:48:55'),
(21, 18, '2026-09-17 20:48:55'),
(21, 19, '2026-09-17 20:48:55'),
(21, 21, '2026-09-17 20:48:55'),
(21, 25, '2026-09-17 20:48:55'),
(21, 26, '2026-09-17 20:48:55'),
(21, 27, '2026-09-17 20:48:55'),
(22, 17, '2026-09-17 20:48:55'),
(22, 18, '2026-09-17 20:48:55'),
(22, 19, '2026-09-17 20:48:55'),
(22, 21, '2026-09-17 20:48:55'),
(22, 25, '2026-09-17 20:48:55'),
(22, 26, '2026-09-17 20:48:55'),
(22, 27, '2026-09-17 20:48:55'),
(23, 17, '2026-09-17 20:48:55'),
(23, 18, '2026-09-17 20:48:55'),
(23, 19, '2026-09-17 20:48:55'),
(23, 21, '2026-09-17 20:48:55'),
(23, 25, '2026-09-17 20:48:55'),
(23, 26, '2026-09-17 20:48:55'),
(23, 27, '2026-09-17 20:48:55'),
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
(28, 21, '2026-09-17 20:48:55'),
(28, 27, '2026-09-17 20:48:55'),
(29, 21, '2026-09-17 20:48:55'),
(29, 27, '2026-09-17 20:48:55'),
(30, 21, '2026-09-17 20:48:55'),
(30, 27, '2026-09-17 20:48:55'),
(31, 21, '2026-09-17 20:48:55'),
(31, 27, '2026-09-17 20:48:55'),
(32, 21, '2026-09-17 20:48:55'),
(32, 27, '2026-09-17 20:48:55');

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
(3, 'CallAudit', 'Demo SAC', 'ACTIVE', '2026-09-16 16:55:48', '2026-09-16 16:55:48'),
(4, 'recupera sac', 'Demos SAC', 'ACTIVE', '2026-09-16 17:00:59', '2026-09-16 17:00:59'),
(5, 'CallAudit Demo SAC', 'callaudit-demo-sac', 'ACTIVE', '2026-09-16 17:02:19', '2026-09-16 17:02:19'),
(6, 'protecta sac', 'protecta-sac', 'ACTIVE', '2026-09-16 17:13:09', '2026-09-16 17:13:09'),
(9, 'Empresa Roles Prueba', 'empresa-roles-prueba', 'ACTIVE', '2026-09-17 17:30:26', '2026-09-17 17:30:26');

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
(9, 6, 'Supervisor Protecta', 'supervisor.protecta@callaudit.local', '$2y$10$wvK8UTvcZtRFpNWSKEsvKOHJPyGHUJbtn7x.5xU3A7ccu5mvAtqqi', 'ACTIVE', '2026-09-24 15:45:55', '2026-09-24 15:45:55'),
(10, 6, 'Auditor Protecta', 'auditor.protecta@callaudit.local', '$2y$10$wvK8UTvcZtRFpNWSKEsvKOHJPyGHUJbtn7x.5xU3A7ccu5mvAtqqi', 'ACTIVE', '2026-09-24 15:45:56', '2026-09-24 15:45:56'),
(11, 6, 'Prueba Supervisor', 'prueba.supervisor@callaudit.local', '$2y$10$EnJnZefcSzGxAmuIlqBax.uQOYLX/yoib.QjyfcfSC.8Vm0.TfaCy', 'ACTIVE', '2026-09-24 17:10:21', '2026-09-24 17:10:21'),
(12, 6, 'Admin 2', 'prueba.admin2@callaudit.local', '$2y$10$f.h3ofcJByHVBG/tblZyuOadQeJwquMe3JCP2vCzsL3dHB9y4ETHm', 'ACTIVE', '2026-09-24 17:38:01', '2026-09-24 17:38:01'),
(13, 6, 'Supervisor 2', 'prueba.supervisor2@callaudit.local', '$2y$10$YImtiikAe/k.vG/dY1PV4eyzAlUisY64lt2WWn.F4Wmrb66NmSuQ.', 'ACTIVE', '2026-09-24 17:39:26', '2026-09-24 17:39:26'),
(14, 6, 'Auditor 2', 'prueba.auditor2@callaudit.local', '$2y$10$J/aDMcgVuOdNUvJbtwa6cuX4Q9wamsJC8G5gJRVKyAfYibWzqyTBy', 'ACTIVE', '2026-09-24 17:40:07', '2026-09-24 17:40:07'),
(15, 6, 'Agente 2', 'prueba.agente2@callaudit.local', '$2y$10$D1waHIBbPBShhbrghDNkMeUGkJ9QUDeiepdqi4BWDGOz6fzSElg5W', 'ACTIVE', '2026-09-24 17:41:17', '2026-09-24 17:41:17');

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
(15, 32, '2026-09-24 17:41:17');

--
-- Índices para tablas volcadas
--

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
-- AUTO_INCREMENT de la tabla `campaigns`
--
ALTER TABLE `campaigns`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `campaign_users`
--
ALTER TABLE `campaign_users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT de la tabla `tenants`
--
ALTER TABLE `tenants`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Restricciones para tablas volcadas
--

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
