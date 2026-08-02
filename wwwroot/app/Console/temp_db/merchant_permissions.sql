-- phpMyAdmin SQL Dump
-- version 4.9.5
-- https://www.phpmyadmin.net/
--
-- 主机： localhost
-- 生成日期： 2026-07-24 20:50:01
-- 服务器版本： 5.7.43-log
-- PHP 版本： 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 数据库： `luckypay`
--

-- --------------------------------------------------------

--
-- 表的结构 `merchant_permissions`
--

CREATE TABLE `merchant_permissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `http_method` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `http_path` text COLLATE utf8mb4_unicode_ci,
  `order` int(11) NOT NULL DEFAULT '0',
  `parent_id` bigint(20) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- 转存表中的数据 `merchant_permissions`
--

INSERT INTO `merchant_permissions` (`id`, `name`, `slug`, `http_method`, `http_path`, `order`, `parent_id`, `created_at`, `updated_at`) VALUES
(1, 'base_auth', 'base.auth', 'GET,POST', '/auth/setting*,/auth/verify*,/auth/login,/auth/logout,/auth/captcha/*', 1, 0, '2024-05-05 14:27:10', '2025-09-05 02:02:47'),
(2, 'merchant_logs', 'logs', 'GET,POST', '/login-logs', 3, 0, '2024-05-05 14:28:45', '2025-09-05 01:40:08'),
(4, 'bank_codes', 'bank-codes', 'GET', '/bank-codes', 5, 0, '2024-05-07 06:42:50', '2025-09-05 01:39:54'),
(5, 'information', 'information', 'GET', '/information', 2, 0, '2024-05-10 11:01:50', '2025-09-05 01:41:26'),
(6, 'merchant_deposit_order', 'deposit-orders', 'GET', '/deposit-orders', 6, 0, '2024-06-02 09:23:00', '2025-09-05 01:39:39'),
(7, 'merchant_transfer_order', 'transfer-orders', 'GET', '/transfer-orders', 7, 0, '2024-06-02 09:23:45', '2025-09-05 01:38:47'),
(8, 'merchant_settlement_order', 'settlement-orders', '', '/settlement-orders*', 8, 0, '2024-06-02 09:48:16', '2025-09-05 01:38:36'),
(9, 'merchant_balance_logs', 'balance-logs', '', '/balance-logs', 9, 0, '2024-06-15 15:15:40', '2025-09-05 01:37:40'),
(10, 'report_payment', 'report_payment', 'GET', '/report-payments', 10, 0, '2024-08-31 04:39:37', '2025-09-05 01:37:26'),
(11, 'merchant_reposts', 'report-merchants', 'GET', '/report-merchants', 11, 0, '2025-06-30 13:19:38', '2025-09-05 01:37:13'),
(12, 'merchant_users', 'musers', '', '/musers*', 12, 0, '2025-09-04 11:13:23', '2025-09-05 07:09:58'),
(13, 'merchant_roles', 'mroles', '', '/mroles*', 13, 0, '2025-09-04 11:14:51', '2025-09-05 07:10:14'),
(14, 'request_settlement', 'merchant-settlement-order-add', '', '', 14, 8, '2025-09-04 11:16:55', '2026-07-24 12:24:12'),
(16, 'merchant_reset_secret', 'merchant_reset_secret', '', '', 16, 5, '2025-09-05 06:58:59', '2026-07-24 12:25:09');

--
-- 转储表的索引
--

--
-- 表的索引 `merchant_permissions`
--
ALTER TABLE `merchant_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `admin_permissions_slug_unique` (`slug`);

--
-- 在导出的表使用AUTO_INCREMENT
--

--
-- 使用表AUTO_INCREMENT `merchant_permissions`
--
ALTER TABLE `merchant_permissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
