-- phpMyAdmin SQL Dump
-- version 4.9.5
-- https://www.phpmyadmin.net/
--
-- 主机： localhost
-- 生成日期： 2025-09-05 15:44:22
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
-- 表的结构 `merchant_menu`
--

CREATE TABLE `merchant_menu` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `parent_id` bigint(20) NOT NULL DEFAULT '0',
  `order` int(11) NOT NULL DEFAULT '0',
  `title` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uri` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `extension` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `show` tinyint(4) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- 转存表中的数据 `merchant_menu`
--

INSERT INTO `merchant_menu` (`id`, `parent_id`, `order`, `title`, `icon`, `uri`, `extension`, `show`, `created_at`, `updated_at`) VALUES
(15, 0, 1, 'index', 'fa-dashboard', '/', '', 1, '2024-04-22 09:28:45', '2025-06-27 10:06:22'),
(16, 0, 12, 'merchant_logs', 'fa-connectdevelop', 'login-logs', '', 1, '2024-05-05 13:54:48', '2025-09-04 11:25:19'),
(18, 0, 11, 'bank_codes', 'fa-bank', 'bank-codes', '', 1, '2024-05-07 06:41:34', '2025-09-04 11:25:19'),
(19, 0, 2, 'information', 'fa-address-card', 'information', '', 1, '2024-05-10 11:01:03', '2025-06-30 03:38:45'),
(20, 0, 3, 'merchant_deposit_order', 'fa-align-justify', 'deposit-orders', '', 1, '2024-06-02 03:27:02', '2025-06-30 05:38:01'),
(21, 0, 4, 'merchant_transfer_order', 'fa-align-justify', 'transfer-orders', '', 1, '2024-06-02 03:27:13', '2025-06-30 05:38:10'),
(22, 0, 5, 'merchant_settlement_order', 'fa-align-justify', 'settlement-orders', '', 1, '2024-06-02 09:47:33', '2025-06-30 05:38:20'),
(23, 0, 6, 'merchant_balance_logs', 'fa-align-justify', 'balance-logs', '', 1, '2024-06-15 15:14:54', '2025-06-30 05:38:32'),
(24, 0, 8, 'report_payment', 'fa-area-chart', 'report-payments', '', 1, '2024-08-31 04:39:10', '2025-06-30 03:36:57'),
(25, 0, 7, 'merchant_reposts', 'fa-area-chart', 'report-merchants', '', 1, '2025-06-30 03:36:51', '2025-06-30 13:19:20'),
(26, 0, 9, 'merchant_users', 'fa-address-book', 'musers', '', 1, '2025-09-04 11:11:41', '2025-09-05 07:08:37'),
(27, 0, 10, 'merchant_roles', 'fa-address-card-o', 'mroles', '', 1, '2025-09-04 11:12:04', '2025-09-05 07:08:26');

--
-- 转储表的索引
--

--
-- 表的索引 `merchant_menu`
--
ALTER TABLE `merchant_menu`
  ADD PRIMARY KEY (`id`);

--
-- 在导出的表使用AUTO_INCREMENT
--

--
-- 使用表AUTO_INCREMENT `merchant_menu`
--
ALTER TABLE `merchant_menu`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
