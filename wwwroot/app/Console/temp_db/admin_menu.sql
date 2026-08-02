-- phpMyAdmin SQL Dump
-- version 4.9.5
-- https://www.phpmyadmin.net/
--
-- 主机： localhost
-- 生成日期： 2026-07-23 15:37:57
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
-- 表的结构 `admin_menu`
--

CREATE TABLE `admin_menu` (
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
-- 转存表中的数据 `admin_menu`
--

INSERT INTO `admin_menu` (`id`, `parent_id`, `order`, `title`, `icon`, `uri`, `extension`, `show`, `created_at`, `updated_at`) VALUES
(1, 0, 7, 'admin_data_report', 'feather icon-bar-chart-2', '/', '', 1, '2024-04-09 17:55:57', '2026-04-15 22:06:53'),
(2, 0, 42, 'admin_operation_manager', 'feather icon-settings', NULL, '', 1, '2024-04-09 17:55:57', '2026-04-15 22:06:53'),
(3, 2, 43, 'admin_operation_manager_user', NULL, 'auth/users', '', 1, '2024-04-09 17:55:57', '2026-04-15 22:06:53'),
(14, 0, 63, 'admin_log_manager', 'fa-connectdevelop', 'auth/operation-logs', '', 1, '2024-04-18 22:31:27', '2026-04-15 22:06:53'),
(18, 0, 14, 'admin_merchant_manager', 'fa-address-card', NULL, '', 1, '2024-04-22 17:36:45', '2026-04-15 22:06:53'),
(19, 18, 15, 'admin_merchant_manager_info', NULL, 'merchant/auth/users', '', 1, '2024-04-22 17:45:05', '2026-04-15 22:06:53'),
(20, 18, 16, 'admin_merchant_manager_payment', NULL, 'merchant-payments', '', 1, '2024-04-25 05:02:02', '2026-04-15 22:06:53'),
(21, 0, 19, 'admin_agent_manager', 'fa-american-sign-language-interpreting', NULL, '', 1, '2024-04-25 14:35:49', '2026-04-15 22:06:53'),
(23, 21, 20, 'admin_agent_merchant_index', NULL, 'agent/auth/users', '', 1, '2024-04-25 14:54:41', '2026-04-15 22:06:53'),
(24, 21, 21, 'admin_agent_merchant_payment', NULL, 'rates-agent-payments', '', 1, '2024-04-26 19:36:16', '2026-04-15 22:06:53'),
(27, 0, 33, 'admin_user_manager', 'fa-users', NULL, '', 1, '2024-04-27 01:38:37', '2026-04-15 22:06:53'),
(29, 27, 34, 'admin_user_index', 'fa-address-book', 'tusers', '', 1, '2024-04-27 01:40:00', '2026-04-15 22:06:53'),
(30, 2, 45, 'admin_operation_manager_bank', 'fa-bank', 'bank-codes', '', 1, '2024-05-07 06:33:36', '2026-04-15 22:06:53'),
(31, 0, 28, 'admin_channel_manager', 'fa-cc-paypal', NULL, '', 1, '2024-05-07 10:27:32', '2026-04-15 22:06:53'),
(32, 31, 29, 'admin_channel_index', NULL, 'channels', '', 1, '2024-05-07 10:28:10', '2026-04-15 22:06:53'),
(33, 31, 30, 'admin_channel_account', 'fa-address-book-o', 'channel-accounts', '', 1, '2024-05-09 00:52:20', '2026-04-15 22:06:53'),
(34, 31, 31, 'admin_channel_merchant', 'fa-paypal', 'merchant-channels', '', 1, '2024-05-09 02:37:33', '2026-04-15 22:06:53'),
(35, 0, 59, 'admin_self_manager', 'fa-object-ungroup', NULL, '', 1, '2024-05-09 05:01:27', '2026-04-15 22:06:53'),
(36, 35, 61, 'admin_self_config', NULL, 'selfchannels/config', '', 1, '2024-05-09 05:05:45', '2026-04-15 22:06:53'),
(37, 35, 62, 'admin_self_group', NULL, 'group-users', '', 1, '2024-05-09 05:06:08', '2026-04-15 22:06:53'),
(38, 27, 35, 'admin_user_bank', 'fa-bank', 'bank-users', '', 1, '2024-05-09 11:34:55', '2026-04-15 22:06:53'),
(39, 0, 37, 'admin_order_manager', 'fa-first-order', NULL, '', 1, '2024-05-24 09:42:46', '2026-04-15 22:06:53'),
(40, 39, 38, 'admin_order_recharge', NULL, 'deposit-orders', '', 1, '2024-05-24 09:43:49', '2026-04-15 22:06:53'),
(41, 39, 39, 'admin_order_transfer', NULL, 'transfer-orders', '', 1, '2024-05-28 07:39:19', '2026-04-15 22:06:53'),
(42, 39, 40, 'admin_order_freeze', NULL, 'freeze-orders', '', 1, '2024-06-01 12:46:13', '2026-04-15 22:06:53'),
(43, 39, 41, 'admin_order_settlement', NULL, 'settlement-orders', '', 1, '2024-06-02 03:39:18', '2026-04-15 22:06:53'),
(44, 18, 17, 'admin_merchant_manager_balance_log', NULL, 'merchant-balance-logs', '', 1, '2024-06-02 15:54:54', '2026-04-15 22:06:53'),
(45, 21, 22, 'admin_agent_merchant_balance_log', NULL, 'agent-balance-logs', '', 1, '2024-06-02 16:51:46', '2026-04-15 22:06:53'),
(46, 21, 23, 'admin_agent_merchant_report', NULL, 'report-merchant-agents', '', 1, '2024-06-03 06:12:38', '2026-04-15 22:06:53'),
(47, 21, 24, 'admin_agent_user_index', NULL, 'agents', '', 1, '2024-06-03 07:20:50', '2026-04-15 22:06:53'),
(48, 21, 25, 'admin_agent_user_payment', NULL, 'user-agent-rates', '', 1, '2024-06-03 07:32:30', '2026-04-15 22:06:53'),
(49, 21, 26, 'admin_agent_user_balance_log', NULL, 'user-agent-balance-logs', '', 1, '2024-06-03 09:06:10', '2026-04-15 22:06:53'),
(50, 21, 27, 'admin_agent_user_report', NULL, 'report-user-agents', '', 1, '2024-06-03 09:40:14', '2026-04-15 22:06:53'),
(51, 1, 8, 'admin_data_report_merchant', NULL, 'report-merchants', '', 1, '2024-06-13 15:18:33', '2026-04-15 22:06:53'),
(53, 1, 9, 'admin_data_report_channel', NULL, 'report-channels', '', 1, '2024-06-18 05:24:12', '2026-04-15 22:06:53'),
(54, 1, 10, 'admin_data_report_user', NULL, 'report-users', '', 1, '2024-06-18 05:31:20', '2026-04-15 22:06:53'),
(55, 1, 11, 'admin_data_report_bank', NULL, 'report-user-banks', '', 1, '2024-06-18 05:50:27', '2026-04-15 22:06:53'),
(62, 2, 44, 'admin_operation_manager_black', NULL, 'black-contents', '', 1, '2024-06-27 05:31:17', '2026-04-15 22:06:53'),
(63, 2, 46, 'admin_operation_manager_api', NULL, 'apiTest', '', 1, '2024-07-12 04:03:20', '2026-04-15 22:06:53'),
(64, 2, 47, 'admin_operation_manager_role', NULL, 'auth/roles', '', 1, '2024-07-15 15:10:52', '2026-04-15 22:06:53'),
(65, 2, 48, 'admin_operation_manager_payment', NULL, 'payment', '', 1, '2024-07-21 16:56:03', '2026-04-15 22:06:53'),
(66, 2, 49, 'admin_operation_manager_telegram', NULL, 'telegramQunSend', '', 1, '2024-08-19 14:24:25', '2026-04-15 22:06:53'),
(67, 27, 36, 'admin_user_balance_log', NULL, 'user-balance-logs', '', 1, '2024-08-24 02:37:22', '2026-04-15 22:06:53'),
(68, 31, 32, 'admin_channel_rates', NULL, 'rates-channels', '', 1, '2024-11-05 14:05:59', '2026-04-15 22:06:53'),
(69, 1, 12, 'admin_data_report_currency', NULL, 'report-currencys', '', 1, '2025-06-15 12:30:50', '2026-04-15 22:06:53'),
(70, 1, 13, 'admin_data_report_payment', NULL, 'report-payments', '', 1, '2025-06-15 12:31:12', '2026-04-15 22:06:53'),
(71, 0, 50, 'admin_config_manager', 'fa-gears', NULL, '', 1, '2025-07-26 01:27:23', '2026-04-15 22:06:53'),
(72, 71, 51, 'admin_config_base', NULL, 'config/base', '', 1, '2025-07-26 01:29:01', '2026-04-15 22:06:53'),
(73, 71, 52, 'admin_config_deposit', NULL, 'config/deposit', '', 1, '2025-07-26 01:31:53', '2026-04-15 22:06:53'),
(74, 71, 53, 'admin_config_transfer', NULL, 'config/transfer', '', 1, '2025-07-26 01:33:52', '2026-04-15 22:06:53'),
(75, 71, 54, 'admin_config_telegram', NULL, 'config/telegram', '', 1, '2025-07-26 01:34:23', '2026-04-15 22:06:53'),
(76, 71, 55, 'admin_config_notice', NULL, 'config/notice', '', 1, '2025-07-26 01:35:55', '2026-04-15 22:06:53'),
(77, 71, 56, 'admin_config_merchant', NULL, 'config/merchant', '', 1, '2025-07-26 01:37:05', '2026-04-15 22:06:53'),
(78, 71, 57, 'admin_config_risk', NULL, 'config/risk', '', 1, '2025-07-26 23:06:35', '2026-04-15 22:06:53'),
(79, 0, 1, 'admin_today_cencus', 'fa-area-chart', NULL, '', 1, '2025-09-04 01:21:41', '2025-09-18 02:21:10'),
(80, 79, 2, 'admin_today_cencus_merchant_monitoring', NULL, 'today/index', '', 1, '2025-09-04 01:23:09', '2025-09-18 02:21:19'),
(81, 79, 3, 'admin_today_cencus_merchant_benefit', NULL, 'today/merchantBenefit', '', 1, '2025-09-04 01:23:28', '2025-09-18 02:21:38'),
(82, 79, 4, 'admin_today_cencus_channel_benefit', NULL, 'today/channelBenefit', '', 1, '2025-09-04 01:23:46', '2025-09-18 02:21:46'),
(83, 79, 5, 'admin_today_cencus_user_benefit', NULL, 'today/userBenefit', '', 1, '2025-09-04 01:25:21', '2026-04-15 22:06:53'),
(84, 79, 6, 'admin_today_cencus_bank_benefit', NULL, 'today/bankBenefit', '', 1, '2025-09-04 01:25:48', '2026-04-15 22:06:53'),
(91, 71, 58, 'admin_config_okx', NULL, 'config/okx', '', 1, '2026-02-03 02:55:31', '2026-04-15 22:06:53'),
(92, 14, 64, 'admin_log_admin', 'fa-align-justify', NULL, '', 1, '2026-02-06 02:44:35', '2026-04-15 22:06:53'),
(93, 14, 68, 'admin_log_merchant', 'fa-align-justify', NULL, '', 1, '2026-02-06 02:44:42', '2026-04-15 22:06:53'),
(94, 14, 71, 'admin_log_agent', 'fa-align-justify', NULL, '', 1, '2026-02-06 02:44:49', '2026-04-15 22:06:53'),
(95, 14, 74, 'admin_log_user', 'fa-align-justify', NULL, '', 1, '2026-02-06 02:44:56', '2026-04-15 22:06:53'),
(96, 92, 65, 'admin_log_other', NULL, 'admin-operation-logs', '', 1, '2026-02-06 02:45:23', '2026-04-15 22:06:53'),
(97, 92, 66, 'admin_log_login', NULL, 'admin-login-logs', '', 1, '2026-02-06 02:45:56', '2026-04-15 22:06:53'),
(98, 93, 69, 'admin_log_other', NULL, 'merchant-operation-logs', '', 1, '2026-02-06 02:46:26', '2026-04-15 22:06:53'),
(99, 93, 70, 'admin_log_login', NULL, 'merchant-login-logs', '', 1, '2026-02-06 02:46:50', '2026-04-15 22:06:53'),
(100, 94, 72, 'admin_log_other', NULL, 'agent-operation-logs', '', 1, '2026-02-06 02:47:14', '2026-04-15 22:06:53'),
(101, 94, 73, 'admin_log_login', NULL, 'agent-login-logs', '', 1, '2026-02-06 02:47:31', '2026-04-15 22:06:53'),
(102, 95, 75, 'admin_log_other', NULL, 'user-operation-logs', '', 1, '2026-02-06 02:47:50', '2026-02-06 02:47:50'),
(103, 95, 76, 'admin_log_login', NULL, 'user-login-logs', '', 1, '2026-02-06 02:48:27', '2026-02-14 01:06:13'),
(104, 18, 18, 'admin_day_balance_logs', NULL, 'day-balance-logs', '', 1, '2026-03-20 08:23:12', '2026-04-15 22:06:53'),
(105, 92, 67, 'admin_login_exction_logs', NULL, 'ip-blacklists', '', 1, '2026-03-26 05:29:16', '2026-04-15 22:06:53'),
(106, 35, 60, 'admin_self_channel_index', NULL, 'selfchannels/index', '', 1, '2026-04-15 22:06:44', '2026-04-15 22:06:53'),
(107, 63, 77, 'admin_operation_manager_api_deposit_test', NULL, 'api/deoisit/test', '', 1, '2026-05-02 02:41:15', '2026-05-02 02:41:15'),
(108, 63, 78, 'admin_operation_manager_api_transfer_test', NULL, 'api/transfer/test', '', 1, '2026-05-02 02:41:57', '2026-05-02 02:41:57'),
(109, 71, 79, 'admin_config_security', NULL, 'config/security', '', 1, '2026-05-03 02:27:59', '2026-05-03 02:27:59'),
(110, 27, 80, 'admin_user_day_balance_logs', NULL, 'user-day-balance-logs', '', 1, '2026-07-19 07:52:57', '2026-07-19 07:52:57'),
(111, 1, 81, 'admin_report_days', NULL, 'report-days', '', 1, '2026-07-19 13:36:54', '2026-07-19 13:36:54');

--
-- 转储表的索引
--

--
-- 表的索引 `admin_menu`
--
ALTER TABLE `admin_menu`
  ADD PRIMARY KEY (`id`);

--
-- 在导出的表使用AUTO_INCREMENT
--

--
-- 使用表AUTO_INCREMENT `admin_menu`
--
ALTER TABLE `admin_menu`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=112;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
