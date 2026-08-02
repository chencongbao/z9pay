-- phpMyAdmin SQL Dump
-- version 4.9.5
-- https://www.phpmyadmin.net/
--
-- 主机： localhost
-- 生成日期： 2025-12-02 11:26:01
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
-- 表的结构 `agent_menu`
--

CREATE TABLE `agent_menu` (
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
-- 转存表中的数据 `agent_menu`
--

INSERT INTO `agent_menu` (`id`, `parent_id`, `order`, `title`, `icon`, `uri`, `extension`, `show`, `created_at`, `updated_at`) VALUES
(15, 0, 1, 'Index', 'fa-dashboard', '/', '', 1, '2024-04-22 09:28:45', '2024-04-25 14:53:32'),
(16, 0, 6, 'admin_merchant_manager_payment', 'fa-align-justify', 'payment-rates', '', 1, '2024-06-03 16:50:40', '2025-12-02 03:25:27'),
(17, 0, 7, 'merchant_balance_logs', 'fa-align-justify', 'balance-logs', '', 1, '2024-06-03 16:51:04', '2025-12-02 03:25:27'),
(18, 0, 3, 'merchant_deposit_order', 'fa-cart-plus', 'deposit-orders', '', 1, '2024-08-28 01:38:28', '2025-09-19 03:50:56'),
(19, 0, 4, 'merchant_transfer_order', 'fa-cart-plus', 'transfer-orders', '', 1, '2024-08-28 01:38:45', '2025-09-19 03:51:04'),
(20, 0, 8, 'agent_reposts', 'fa-area-chart', 'reports-merchant-agents', '', 1, '2024-08-28 01:39:55', '2025-12-02 03:25:27'),
(21, 0, 9, 'merchant_reposts', 'fa-area-chart', 'report-merchants', '', 1, '2025-08-07 03:50:49', '2025-12-02 03:25:27'),
(22, 0, 2, 'information', 'fa-address-book', 'merchant-users', '', 1, '2025-09-09 02:17:50', '2025-09-19 03:50:13'),
(23, 0, 5, 'merchant_settlement_order', 'fa-align-justify', 'settlement-orders', '', 1, '2025-12-02 03:25:11', '2025-12-02 03:25:27');

--
-- 转储表的索引
--

--
-- 表的索引 `agent_menu`
--
ALTER TABLE `agent_menu`
  ADD PRIMARY KEY (`id`);

--
-- 在导出的表使用AUTO_INCREMENT
--

--
-- 使用表AUTO_INCREMENT `agent_menu`
--
ALTER TABLE `agent_menu`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
