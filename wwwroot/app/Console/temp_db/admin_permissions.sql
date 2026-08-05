/*
 Navicat Premium Dump SQL

 Source Server         : luckpay-local
 Source Server Type    : MySQL
 Source Server Version : 50743 (5.7.43-log)
 Source Host           : 172.26.179.199:3306
 Source Schema         : luckypay

 Target Server Type    : MySQL
 Target Server Version : 50743 (5.7.43-log)
 File Encoding         : 65001

 Date: 04/08/2026 07:30:24
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for admin_permissions
-- ----------------------------
DROP TABLE IF EXISTS `admin_permissions`;
CREATE TABLE `admin_permissions`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `http_method` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `http_path` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `order` int(11) NOT NULL DEFAULT 0,
  `parent_id` bigint(20) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `admin_permissions_slug_unique`(`slug`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 236 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of admin_permissions
-- ----------------------------
INSERT INTO `admin_permissions` VALUES (7, '数据报表', 'data-report', '', '', 7, 0, '2024-06-19 19:28:50', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (8, '商户报表', 'dashborad-report-merchants', 'GET', '/report-merchants', 8, 7, '2024-06-19 19:29:51', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (9, '币种报表', 'dashboard-report-currency', 'GET', '/report-currencys', 9, 7, '2024-06-19 19:30:39', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (10, '渠道报表', 'dashborad-report-channel', 'GET', '/report-channels', 10, 7, '2024-06-19 19:31:22', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (11, '金主报表', 'dashborad-report-users', 'GET', '/report-users', 11, 7, '2024-06-19 19:31:55', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (12, '上号报表', 'dashboard-report-user-bank', 'GET', '/report-user-banks', 12, 7, '2024-06-19 19:32:29', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (13, '商户管理', 'merchant-manager', '', '', 14, 0, '2024-06-19 19:33:00', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (14, '商户信息', 'merchant-info', '', '/merchant/auth/users*', 15, 13, '2024-06-19 19:33:55', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (15, '商户费率', 'merchant-payments', '', '/merchant-payments*', 23, 13, '2024-06-19 19:35:33', '2026-07-22 20:05:49');
INSERT INTO `admin_permissions` VALUES (16, '商户流水', 'merchant-balance-logs', '', '/merchant-balance-logs', 24, 13, '2024-06-19 19:36:13', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (17, '代理功能', 'all-agent', '', '', 27, 0, '2024-06-19 19:39:50', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (18, '商户代理', 'merchant-agent-auth-users', '', '/agent/auth/users*', 28, 17, '2024-06-19 19:41:16', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (19, '商户代理费率', 'merchant-rates-agent-payments', 'GET', '/rates-agent-payments', 30, 17, '2024-06-19 19:41:57', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (20, '商户代理流水', 'merchant-agent-balance-logs', 'GET', '/agent-balance-logs', 31, 17, '2024-06-19 19:43:01', '2026-07-22 20:42:45');
INSERT INTO `admin_permissions` VALUES (21, '商户代理报表', 'merchant-agent-reports', 'GET', '/report-merchant-agents', 32, 17, '2024-06-19 19:43:57', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (22, '金主代理', 'user-agent1', '', '/agents*', 33, 17, '2024-06-19 19:45:23', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (23, '金主代理费率', 'user-agent-rates', 'GET', '/user-agent-rates', 35, 17, '2024-06-19 19:46:04', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (24, '金主代理流水', 'user-agent-balance-logs', 'GET', '/user-agent-balance-logs', 36, 17, '2024-06-19 19:53:24', '2026-07-22 20:44:25');
INSERT INTO `admin_permissions` VALUES (25, '金主代理报表', 'user-agent-reports', 'GET', '/report-user-agents', 37, 17, '2024-06-19 19:53:54', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (26, '渠道管理', 'channel-manager', '', '', 38, 0, '2024-06-19 19:54:18', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (27, '渠道列表', 'channels-index', '', '/channels*', 39, 26, '2024-06-19 19:56:05', '2026-07-22 20:50:33');
INSERT INTO `admin_permissions` VALUES (28, '渠道账号', 'channel-accounts', '', '/channel-accounts*', 40, 26, '2024-06-19 19:56:35', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (29, '商户渠道', 'merchant-channels', '', '/merchant-channels*', 41, 26, '2024-06-19 19:56:59', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (30, '金主管理', 'user-manager', '', '', 43, 0, '2024-06-19 20:05:55', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (32, '金主列表', 'users-index', '', '/tusers*', 44, 30, '2024-06-19 20:07:07', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (33, '金主收款卡', 'bank-users', '', '/bank-users*', 46, 30, '2024-06-19 20:07:46', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (34, '订单管理', 'order-manager', '', '', 49, 0, '2024-06-19 20:08:10', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (35, '商户充值订单', 'deposit-orders', 'GET', '/deposit-orders', 50, 34, '2024-06-19 20:08:50', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (36, '商户代付订单', 'transfer-orders', 'GET', '/transfer-orders', 51, 34, '2024-06-19 20:09:20', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (37, '商户冻结订单', 'freeze-orders', 'GET', '/freeze-orders', 52, 34, '2024-06-19 20:09:52', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (38, '商户结算订单', 'settlement-orders', 'GET', '/settlement-orders', 53, 34, '2024-06-19 20:10:26', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (39, '运维管理', 'system-manager', '', '', 54, 0, '2024-06-19 20:11:11', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (40, '管理员', 'system-auth-users', '', '/auth/users*', 55, 39, '2024-06-19 20:11:56', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (42, '银行代码', 'bank-codes', '', '/bank-codes*', 56, 39, '2024-06-19 20:13:30', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (43, '自营管理', 'selfchannel-manager', '', '', 71, 0, '2024-06-19 20:14:06', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (44, '自营配置', 'selfchannels.config', '', '/selfchannels/config', 72, 43, '2024-06-19 20:14:39', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (45, '自营分组', 'group-users', '', '/group-users*', 73, 43, '2024-06-19 20:15:10', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (46, '日志管理', 'log-manager', 'GET', '', 74, 0, '2024-06-19 20:15:29', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (47, '总后台', 'admin_log-admin', 'GET', '', 75, 46, '2024-06-19 20:15:54', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (48, '商户端', 'admin_log_merchant', 'GET', '', 79, 46, '2024-06-19 20:16:22', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (49, '黑名单', 'black-contents', '', '/black-contents*', 57, 39, '2024-06-27 13:31:48', '2026-07-22 22:14:11');
INSERT INTO `admin_permissions` VALUES (53, 'ApiTest', 'apitest', 'GET', '/apiTest', 58, 39, '2024-07-12 12:04:09', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (54, '角色管理', 'auth_roles', '', '/auth/roles*', 59, 39, '2024-07-15 23:11:34', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (55, '通道编码', 'payment', 'GET', '/payment', 60, 39, '2024-07-22 00:56:59', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (56, '删除商户', 'merchant-user-delete', '', '', 17, 14, '2024-08-05 18:34:45', '2026-07-22 19:51:41');
INSERT INTO `admin_permissions` VALUES (62, '飞机群发', 'telegramQunSend', 'GET', '/telegramQunSend', 61, 39, '2024-08-21 13:41:50', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (63, '金主流水', 'user-balance-logs', 'GET', '/user-balance-logs', 48, 30, '2024-08-24 10:39:31', '2026-07-22 21:45:54');
INSERT INTO `admin_permissions` VALUES (64, '渠道成本', 'rates-channels', '', '/rates-channels*', 42, 26, '2024-11-05 22:06:38', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (65, '通道报表', 'dashboard-report-payment', 'GET', '/report-payments', 13, 7, '2025-06-15 20:41:54', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (66, '配置管理', 'config.manager', '', '', 62, 0, '2025-07-26 09:29:54', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (67, '基础配置', 'config.base', 'GET', '/config/base', 63, 66, '2025-07-26 09:31:04', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (68, '代收配置', 'config.deposit', 'GET', '/config/deposit', 64, 66, '2025-07-26 09:32:38', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (69, '代付配置', 'config.transfer', 'GET', '/config/transfer', 65, 66, '2025-07-26 09:33:28', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (70, '飞机配置', 'config.telegram', 'GET', '/config/telegram', 66, 66, '2025-07-26 09:35:22', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (71, '通知配置', 'config.notice', 'GET', '/config/notice', 67, 66, '2025-07-26 09:36:37', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (72, '商户配置', 'config.merchant', 'GET', '/config/merchant', 68, 66, '2025-07-26 09:37:34', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (73, '风控配置', 'config.risk', 'GET', '/config/risk', 69, 66, '2025-07-27 07:07:25', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (74, '新增商户', 'merchant-user-add', '', '', 16, 14, '2025-07-30 13:33:18', '2026-07-22 19:51:41');
INSERT INTO `admin_permissions` VALUES (75, '编辑商户', 'merchant-user-edit', '', '', 18, 14, '2025-07-30 13:34:04', '2026-07-22 19:51:41');
INSERT INTO `admin_permissions` VALUES (76, '重置密码', 'merchant-user-reset-password', '', '', 19, 14, '2025-07-30 15:08:24', '2026-07-22 19:51:41');
INSERT INTO `admin_permissions` VALUES (77, '重置谷歌验证码', 'merchant-user-reset-googlecode', '', '', 20, 14, '2025-07-30 15:08:57', '2026-07-22 19:51:41');
INSERT INTO `admin_permissions` VALUES (78, '解绑机器人', 'merchant-user-unbind-telegram', '', '', 21, 14, '2025-07-30 15:26:19', '2026-07-22 19:51:41');
INSERT INTO `admin_permissions` VALUES (80, '白名单设置', 'merchant-user-white-ip', '', '', 22, 14, '2025-07-30 15:38:09', '2026-07-22 19:51:41');
INSERT INTO `admin_permissions` VALUES (81, '今日统计', 'today-report', '', '', 1, 0, '2025-09-04 12:23:52', '2025-09-04 12:23:59');
INSERT INTO `admin_permissions` VALUES (82, '商户监控', 'today-index', 'GET', '/today/index', 2, 81, '2025-09-04 12:25:15', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (83, '商户成效', 'today-merchantBenefit', 'GET', '/today/merchantBenefit', 3, 81, '2025-09-04 12:26:12', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (84, '渠道成效', 'today-channelBenefit', 'GET', '/today/channelBenefit', 4, 81, '2025-09-04 12:26:49', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (85, '金主成效', 'today-userBenefit', 'GET', '/today/userBenefit', 5, 81, '2025-09-04 12:27:19', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (86, '上号成效', 'today-bankBenefit', 'GET', '/today/bankBenefit', 6, 81, '2025-09-04 12:27:52', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (87, '欧易配置', 'config.okx', 'GET', '/config/okx', 70, 66, '2026-02-03 12:50:47', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (88, '操作日志', 'admin_log_other', 'GET', '/admin-operation-logs', 76, 47, '2026-02-14 08:58:37', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (89, '登录日志', 'admin_log_login', 'GET', '/admin-login-logs', 77, 47, '2026-02-14 08:59:55', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (90, '操作日志', 'admin_log_merchant_other', 'GET', '/merchant-operation-logs', 80, 48, '2026-02-14 09:01:35', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (91, '登录日志', 'admin_log_merchant_login', 'GET', '/merchant-login-logs', 81, 48, '2026-02-14 09:02:05', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (92, '商户代理', 'admin_log_agent', 'GET', '', 82, 46, '2026-02-14 09:02:58', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (93, '操作日志', 'admin_log_merchant_agent_other', 'GET', '/agent-operation-logs', 83, 92, '2026-02-14 09:03:35', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (94, '登录日志', 'admin_log_merchant_agent_login', 'GET', '/agent-login-logs', 84, 92, '2026-02-14 09:04:23', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (95, '金主(代理)', 'admin_log_user', 'GET', '', 85, 46, '2026-02-14 09:04:50', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (96, '操作日志', 'admin_log_user_other', 'GET', '/user-operation-logs', 86, 95, '2026-02-14 09:05:27', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (97, '登录日志', 'admin_log_user_login', 'GET', '/user-login-logs', 87, 95, '2026-02-14 09:05:54', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (98, '商户日切', 'day-balance-logs', 'GET', '/day-balance-logs', 26, 13, '2026-03-20 16:23:52', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (99, '登陆异常', 'ip-blacklists', '', '/ip-blacklists', 78, 47, '2026-03-26 13:31:20', '2026-04-02 14:32:48');
INSERT INTO `admin_permissions` VALUES (100, '自营面板', 'selfchannels-index', 'GET', '/selfchannels/index', 88, 43, '2026-04-29 12:15:35', '2026-04-29 12:15:35');
INSERT INTO `admin_permissions` VALUES (101, 'API代收测试', 'admin_operation_manager_api_deposit_test', 'GET', '/api/deoisit/test', 89, 53, '2026-05-02 10:43:01', '2026-05-02 10:43:01');
INSERT INTO `admin_permissions` VALUES (102, 'API代付测试', 'admin_operation_manager_api_transfer_test', 'GET', '/api/transfer/test', 90, 53, '2026-05-02 10:43:35', '2026-05-02 10:43:35');
INSERT INTO `admin_permissions` VALUES (103, '安全配置', 'config-security', 'GET', '/config/security', 91, 66, '2026-05-03 10:29:47', '2026-05-03 10:30:42');
INSERT INTO `admin_permissions` VALUES (104, '金主日切', 'user-day-balance-logs', 'GET', '/user-day-balance-logs', 92, 30, '2026-07-19 15:55:27', '2026-07-19 15:55:56');
INSERT INTO `admin_permissions` VALUES (105, '日总报表', 'report-days', 'GET', '/report-days', 93, 7, '2026-07-19 21:37:30', '2026-07-19 21:38:21');
INSERT INTO `admin_permissions` VALUES (108, '解锁商户登录', 'merchant-user-unlock-login', '', '', 94, 14, '2026-07-22 19:54:10', '2026-07-22 19:54:10');
INSERT INTO `admin_permissions` VALUES (109, '删除商户群管理员', 'merchant-user-delete-telegram-admin', '', '', 95, 14, '2026-07-22 19:54:10', '2026-07-22 19:54:10');
INSERT INTO `admin_permissions` VALUES (110, '新增商户费率', 'merchant-payment-create', '', '', 96, 15, '2026-07-22 20:04:18', '2026-07-22 20:04:18');
INSERT INTO `admin_permissions` VALUES (111, '编辑商户费率', 'merchant-payment-edit', '', '', 97, 15, '2026-07-22 20:04:18', '2026-07-22 20:04:18');
INSERT INTO `admin_permissions` VALUES (112, '删除商户费率', 'merchant-payment-delete', '', '', 98, 15, '2026-07-22 20:04:18', '2026-07-22 20:04:18');
INSERT INTO `admin_permissions` VALUES (113, '启用/禁用商户费率', 'merchant-payment-status', '', '', 99, 15, '2026-07-22 20:04:18', '2026-07-22 20:04:18');
INSERT INTO `admin_permissions` VALUES (114, '批量设置商户费率限额', 'merchant-payment-batch-limit', '', '', 100, 15, '2026-07-22 20:04:18', '2026-07-22 20:04:18');
INSERT INTO `admin_permissions` VALUES (115, '批量设置商户费率', 'merchant-payment-batch-rate', '', '', 101, 15, '2026-07-22 20:04:18', '2026-07-22 20:04:18');
INSERT INTO `admin_permissions` VALUES (116, '商户资金加项', 'merchant-balance-log-add', '', '', 102, 16, '2026-07-22 20:11:48', '2026-07-22 20:11:48');
INSERT INTO `admin_permissions` VALUES (117, '商户资金减项', 'merchant-balance-log-reduce', '', '', 103, 16, '2026-07-22 20:11:48', '2026-07-22 20:11:48');
INSERT INTO `admin_permissions` VALUES (118, '商户流水冲正', 'merchant-balance-log-corre', '', '', 104, 16, '2026-07-22 20:11:48', '2026-07-22 20:11:48');
INSERT INTO `admin_permissions` VALUES (119, '新增商户代理', 'merchant-agent-create', '', '', 105, 18, '2026-07-22 20:19:23', '2026-07-22 20:19:23');
INSERT INTO `admin_permissions` VALUES (120, '编辑商户代理', 'merchant-agent-edit', '', '', 106, 18, '2026-07-22 20:19:23', '2026-07-22 20:19:23');
INSERT INTO `admin_permissions` VALUES (121, '删除商户代理', 'merchant-agent-delete', '', '', 107, 18, '2026-07-22 20:19:23', '2026-07-22 20:19:23');
INSERT INTO `admin_permissions` VALUES (122, '重置商户代理 Google 验证', 'merchant-agent-reset-googlecode', '', '', 108, 18, '2026-07-22 20:19:23', '2026-07-22 20:19:23');
INSERT INTO `admin_permissions` VALUES (123, '解锁商户代理登录', 'merchant-agent-unlock-login', '', '', 109, 18, '2026-07-22 20:19:23', '2026-07-22 20:19:23');
INSERT INTO `admin_permissions` VALUES (124, '商户代理资金加项', 'merchant-agent-balance-log-add', '', '', 110, 20, '2026-07-22 20:24:30', '2026-07-22 20:24:30');
INSERT INTO `admin_permissions` VALUES (125, '商户代理资金减项', 'merchant-agent-balance-log-reduce', '', '', 111, 20, '2026-07-22 20:24:30', '2026-07-22 20:24:30');
INSERT INTO `admin_permissions` VALUES (126, '商户代理流水冲正', 'merchant-agent-balance-log-corre', '', '', 112, 20, '2026-07-22 20:24:30', '2026-07-22 20:24:30');
INSERT INTO `admin_permissions` VALUES (127, '新增金主代理', 'user-agent-create', '', '', 113, 22, '2026-07-22 20:28:47', '2026-07-22 20:28:47');
INSERT INTO `admin_permissions` VALUES (128, '编辑金主代理', 'user-agent-edit', '', '', 114, 22, '2026-07-22 20:28:47', '2026-07-22 20:28:47');
INSERT INTO `admin_permissions` VALUES (129, '删除金主代理', 'user-agent-delete', '', '', 115, 22, '2026-07-22 20:28:47', '2026-07-22 20:28:47');
INSERT INTO `admin_permissions` VALUES (130, '重置金主代理 Google 验证', 'user-agent-reset-googlecode', '', '', 116, 22, '2026-07-22 20:28:47', '2026-07-22 20:28:47');
INSERT INTO `admin_permissions` VALUES (131, '解锁金主代理登录', 'user-agent-unlock-login', '', '', 117, 22, '2026-07-22 20:28:47', '2026-07-22 20:28:47');
INSERT INTO `admin_permissions` VALUES (132, '强制金主代理退出', 'user-agent-force-logout', '', '', 118, 22, '2026-07-22 20:28:47', '2026-07-22 20:28:47');
INSERT INTO `admin_permissions` VALUES (133, '金主代理资金加项', 'user-agent-balance-log-add', '', '', 1, 24, '2026-07-22 20:43:54', '2026-07-22 20:43:54');
INSERT INTO `admin_permissions` VALUES (134, '金主代理资金减项', 'user-agent-balance-log-reduce', '', '', 2, 24, '2026-07-22 20:43:54', '2026-07-22 20:43:54');
INSERT INTO `admin_permissions` VALUES (135, '金主代理流水冲正', 'user-agent-balance-log-corre', '', '', 3, 24, '2026-07-22 20:43:54', '2026-07-22 20:43:54');
INSERT INTO `admin_permissions` VALUES (136, '新增渠道', 'channel-create', '', '', 1, 27, '2026-07-22 20:49:46', '2026-07-22 20:49:46');
INSERT INTO `admin_permissions` VALUES (137, '编辑渠道', 'channel-edit', '', '', 2, 27, '2026-07-22 20:49:46', '2026-07-22 20:49:46');
INSERT INTO `admin_permissions` VALUES (138, '删除渠道', 'channel-delete', '', '', 3, 27, '2026-07-22 20:49:46', '2026-07-22 20:49:46');
INSERT INTO `admin_permissions` VALUES (139, '解绑机器人', 'channel-reset-telegram', '', '', 4, 27, '2026-07-22 20:49:46', '2026-07-22 20:49:46');
INSERT INTO `admin_permissions` VALUES (140, '新增渠道账号', 'channel-account-create', '', '', 1, 28, '2026-07-22 20:52:47', '2026-07-22 20:52:47');
INSERT INTO `admin_permissions` VALUES (141, '编辑渠道账号', 'channel-account-edit', '', '', 2, 28, '2026-07-22 20:52:47', '2026-07-22 20:52:47');
INSERT INTO `admin_permissions` VALUES (142, '删除渠道账号', 'channel-account-delete', '', '', 3, 28, '2026-07-22 20:52:47', '2026-07-22 20:52:47');
INSERT INTO `admin_permissions` VALUES (143, '新增商户渠道', 'merchant-channel-create', '', '', 1, 29, '2026-07-22 20:57:00', '2026-07-22 20:57:00');
INSERT INTO `admin_permissions` VALUES (144, '编辑商户渠道', 'merchant-channel-edit', '', '', 2, 29, '2026-07-22 20:57:00', '2026-07-22 20:57:00');
INSERT INTO `admin_permissions` VALUES (145, '删除商户渠道', 'merchant-channel-delete', '', '', 3, 29, '2026-07-22 20:57:00', '2026-07-22 20:57:00');
INSERT INTO `admin_permissions` VALUES (146, '恢复商户渠道', 'merchant-channel-restore', '', '', 4, 29, '2026-07-22 20:57:00', '2026-07-22 20:57:00');
INSERT INTO `admin_permissions` VALUES (147, '批量添加商户渠道', 'merchant-channel-batch-add', '', '', 5, 29, '2026-07-22 20:57:00', '2026-07-22 20:57:00');
INSERT INTO `admin_permissions` VALUES (148, '批量设置代收限额', 'merchant-channel-batch-pay-limit', '', '', 6, 29, '2026-07-22 20:57:00', '2026-07-22 20:57:00');
INSERT INTO `admin_permissions` VALUES (149, '批量设置代付限额', 'merchant-channel-batch-collection-limit', '', '', 7, 29, '2026-07-22 20:57:00', '2026-07-22 20:57:00');
INSERT INTO `admin_permissions` VALUES (150, '批量设置商户渠道费率', 'merchant-channel-batch-rate', '', '', 8, 29, '2026-07-22 20:57:00', '2026-07-22 20:57:00');
INSERT INTO `admin_permissions` VALUES (151, '启用/禁用商户渠道', 'merchant-channel-status', '', '', 9, 29, '2026-07-22 20:57:00', '2026-07-22 20:57:00');
INSERT INTO `admin_permissions` VALUES (152, '启用/禁用商户渠道浮动', 'merchant-channel-float-status', '', '', 10, 29, '2026-07-22 20:57:00', '2026-07-22 20:57:00');
INSERT INTO `admin_permissions` VALUES (153, '新增渠道成本', 'channel-rate-create', '', '', 1, 64, '2026-07-22 21:01:31', '2026-07-22 21:01:31');
INSERT INTO `admin_permissions` VALUES (154, '编辑渠道成本', 'channel-rate-edit', '', '', 2, 64, '2026-07-22 21:01:31', '2026-07-22 21:01:31');
INSERT INTO `admin_permissions` VALUES (155, '删除渠道成本', 'channel-rate-delete', '', '', 3, 64, '2026-07-22 21:01:31', '2026-07-22 21:01:31');
INSERT INTO `admin_permissions` VALUES (156, '编辑金主', 'user-edit', '', '', 1, 32, '2026-07-22 21:08:38', '2026-07-22 21:08:38');
INSERT INTO `admin_permissions` VALUES (157, '启用/禁用金主账号', 'user-status', '', '', 2, 32, '2026-07-22 21:08:38', '2026-07-22 21:08:38');
INSERT INTO `admin_permissions` VALUES (158, '启用/禁用金主收单', 'user-acquisition-status', '', '', 3, 32, '2026-07-22 21:08:38', '2026-07-22 21:08:38');
INSERT INTO `admin_permissions` VALUES (159, '解锁金主登录', 'user-unlock-login', '', '', 4, 32, '2026-07-22 21:08:38', '2026-07-22 21:08:38');
INSERT INTO `admin_permissions` VALUES (160, '强制金主退出', 'user-force-logout', '', '', 5, 32, '2026-07-22 21:08:38', '2026-07-22 21:08:38');
INSERT INTO `admin_permissions` VALUES (161, '重置金主密码', 'user-reset-password', '', '', 6, 32, '2026-07-22 21:08:38', '2026-07-22 21:08:38');
INSERT INTO `admin_permissions` VALUES (162, '修改金主代理', 'user-update-agent', '', '', 7, 32, '2026-07-22 21:08:38', '2026-07-22 21:08:38');
INSERT INTO `admin_permissions` VALUES (163, '清除金主缓存', 'user-clear-cache', '', '', 8, 32, '2026-07-22 21:08:38', '2026-07-22 21:08:38');
INSERT INTO `admin_permissions` VALUES (164, '保证金加项', 'user-deposit-balance-add', '', '', 9, 32, '2026-07-22 21:08:38', '2026-07-22 21:08:38');
INSERT INTO `admin_permissions` VALUES (165, '保证金减项', 'user-deposit-balance-reduce', '', '', 10, 32, '2026-07-22 21:08:38', '2026-07-22 21:08:38');
INSERT INTO `admin_permissions` VALUES (166, '代收账户加项', 'user-collection-balance-add', '', '', 11, 32, '2026-07-22 21:08:38', '2026-07-22 21:08:38');
INSERT INTO `admin_permissions` VALUES (167, '代收账户减项', 'user-collection-balance-reduce', '', '', 12, 32, '2026-07-22 21:08:38', '2026-07-22 21:08:38');
INSERT INTO `admin_permissions` VALUES (168, '代付账户加项', 'user-transfer-balance-add', '', '', 13, 32, '2026-07-22 21:08:38', '2026-07-22 21:08:38');
INSERT INTO `admin_permissions` VALUES (169, '代付账户减项', 'user-transfer-balance-reduce', '', '', 14, 32, '2026-07-22 21:08:38', '2026-07-22 21:08:38');
INSERT INTO `admin_permissions` VALUES (170, '佣金账户加项', 'user-commission-balance-add', '', '', 15, 32, '2026-07-22 21:08:38', '2026-07-22 21:08:38');
INSERT INTO `admin_permissions` VALUES (171, '佣金账户减项', 'user-commission-balance-reduce', '', '', 16, 32, '2026-07-22 21:08:38', '2026-07-22 21:08:38');
INSERT INTO `admin_permissions` VALUES (172, '解绑金主机器人', 'user-unbind-telegram', '', '', 17, 32, '2026-07-22 21:08:38', '2026-07-22 21:08:38');
INSERT INTO `admin_permissions` VALUES (173, '重置金主 Google 验证', 'user-reset-googlecode', '', '', 18, 32, '2026-07-22 21:08:38', '2026-07-22 21:08:38');
INSERT INTO `admin_permissions` VALUES (174, '删除金主', 'user-delete', '', '', 19, 32, '2026-07-22 21:08:38', '2026-07-22 21:08:38');
INSERT INTO `admin_permissions` VALUES (175, '新增金主收款卡', 'user-bank-create', '', '', 1, 33, '2026-07-22 21:32:41', '2026-07-22 21:32:41');
INSERT INTO `admin_permissions` VALUES (176, '编辑金主收款卡', 'user-bank-edit', '', '', 2, 33, '2026-07-22 21:32:41', '2026-07-22 21:32:41');
INSERT INTO `admin_permissions` VALUES (177, '删除金主收款卡', 'user-bank-delete', '', '', 3, 33, '2026-07-22 21:32:41', '2026-07-22 21:32:41');
INSERT INTO `admin_permissions` VALUES (178, '还原金主收款卡', 'user-bank-restore', '', '', 4, 33, '2026-07-22 21:32:41', '2026-07-22 21:32:41');
INSERT INTO `admin_permissions` VALUES (179, '启用/禁用金主收款卡', 'user-bank-status', '', '', 5, 33, '2026-07-22 21:32:41', '2026-07-22 21:32:41');
INSERT INTO `admin_permissions` VALUES (180, '复制金主收款卡', 'user-bank-copy', '', '', 6, 33, '2026-07-22 21:32:41', '2026-07-22 21:32:41');
INSERT INTO `admin_permissions` VALUES (181, '批量复制金主收款卡', 'user-bank-batch-copy', '', '', 7, 33, '2026-07-22 21:32:41', '2026-07-22 21:32:41');
INSERT INTO `admin_permissions` VALUES (182, '批量开启金主收款卡', 'user-bank-batch-open', '', '', 8, 33, '2026-07-22 21:32:41', '2026-07-22 21:32:41');
INSERT INTO `admin_permissions` VALUES (183, '批量关闭金主收款卡', 'user-bank-batch-close', '', '', 9, 33, '2026-07-22 21:32:41', '2026-07-22 21:32:41');
INSERT INTO `admin_permissions` VALUES (184, '批量修改金主收款卡限额', 'user-bank-batch-limit', '', '', 10, 33, '2026-07-22 21:32:41', '2026-07-22 21:32:41');
INSERT INTO `admin_permissions` VALUES (185, '收款卡金额加项', 'user-bank-balance-add', '', '', 11, 33, '2026-07-22 21:32:41', '2026-07-22 21:32:41');
INSERT INTO `admin_permissions` VALUES (186, '收款卡金额减项', 'user-bank-balance-reduce', '', '', 12, 33, '2026-07-22 21:32:41', '2026-07-22 21:32:41');
INSERT INTO `admin_permissions` VALUES (187, '批量回调代收订单', 'deposit-order-batch-callback', '', '', 1, 35, '2026-07-22 21:44:47', '2026-07-22 21:44:47');
INSERT INTO `admin_permissions` VALUES (188, '更新代收订单缓存', 'deposit-order-update-cache', '', '', 2, 35, '2026-07-22 21:44:47', '2026-07-22 21:44:47');
INSERT INTO `admin_permissions` VALUES (189, '人工补单', 'deposit-order-manual-success', '', '', 3, 35, '2026-07-22 21:44:47', '2026-07-22 21:44:47');
INSERT INTO `admin_permissions` VALUES (190, '手动失败代收订单', 'deposit-order-manual-fail', '', '', 4, 35, '2026-07-22 21:44:47', '2026-07-22 21:44:47');
INSERT INTO `admin_permissions` VALUES (191, '手动超时代收订单', 'deposit-order-manual-timeout', '', '', 5, 35, '2026-07-22 21:44:47', '2026-07-22 21:44:47');
INSERT INTO `admin_permissions` VALUES (192, '冻结代收订单', 'deposit-order-freeze', '', '', 6, 35, '2026-07-22 21:44:47', '2026-07-22 21:44:47');
INSERT INTO `admin_permissions` VALUES (193, '推送代收订单回调', 'deposit-order-callback', '', '', 7, 35, '2026-07-22 21:44:47', '2026-07-22 21:44:47');
INSERT INTO `admin_permissions` VALUES (194, '手动查询代收订单状态', 'deposit-order-query-status', '', '', 8, 35, '2026-07-22 21:44:47', '2026-07-22 21:44:47');
INSERT INTO `admin_permissions` VALUES (195, '金主流水冲正', 'user-balance-log-corre', '', '', 1, 63, '2026-07-22 21:47:07', '2026-07-22 21:47:07');
INSERT INTO `admin_permissions` VALUES (196, '批量回调代付订单', 'transfer-order-batch-callback', '', '', 1, 36, '2026-07-22 21:51:57', '2026-07-22 21:51:57');
INSERT INTO `admin_permissions` VALUES (197, '批量提交代付订单', 'transfer-order-batch-submit', '', '', 2, 36, '2026-07-22 21:51:57', '2026-07-22 21:51:57');
INSERT INTO `admin_permissions` VALUES (198, '批量失败代付订单', 'transfer-order-batch-fail', '', '', 3, 36, '2026-07-22 21:51:57', '2026-07-22 21:51:57');
INSERT INTO `admin_permissions` VALUES (200, '手动成功代付订单', 'transfer-order-manual-success', '', '', 5, 36, '2026-07-22 21:51:57', '2026-07-22 21:51:57');
INSERT INTO `admin_permissions` VALUES (201, '手动失败代付订单', 'transfer-order-manual-fail', '', '', 6, 36, '2026-07-22 21:51:57', '2026-07-22 21:51:57');
INSERT INTO `admin_permissions` VALUES (202, '代付订单冲正', 'transfer-order-corre', '', '', 7, 36, '2026-07-22 21:51:57', '2026-07-22 21:51:57');
INSERT INTO `admin_permissions` VALUES (203, '设置/重新提交代付渠道', 'transfer-order-channel', '', '', 8, 36, '2026-07-22 21:51:57', '2026-07-22 21:51:57');
INSERT INTO `admin_permissions` VALUES (204, '推送代付订单回调', 'transfer-order-callback', '', '', 9, 36, '2026-07-22 21:51:57', '2026-07-22 21:51:57');
INSERT INTO `admin_permissions` VALUES (206, '解冻冻结订单', 'freeze-order-unfreeze', '', '', 1, 37, '2026-07-22 21:59:37', '2026-07-22 21:59:37');
INSERT INTO `admin_permissions` VALUES (207, '认领结算订单', 'settlement-order-claim', '', '', 1, 38, '2026-07-22 22:04:53', '2026-07-22 22:04:53');
INSERT INTO `admin_permissions` VALUES (208, '设置/重新提交结算渠道', 'settlement-order-channel', '', '', 2, 38, '2026-07-22 22:04:53', '2026-07-22 22:04:53');
INSERT INTO `admin_permissions` VALUES (209, '手动成功结算订单', 'settlement-order-manual-success', '', '', 3, 38, '2026-07-22 22:04:53', '2026-07-22 22:04:53');
INSERT INTO `admin_permissions` VALUES (210, '手动失败结算订单', 'settlement-order-manual-fail', '', '', 4, 38, '2026-07-22 22:04:53', '2026-07-22 22:04:53');
INSERT INTO `admin_permissions` VALUES (211, '结算订单冲正', 'settlement-order-corre', '', '', 5, 38, '2026-07-22 22:04:53', '2026-07-22 22:04:53');
INSERT INTO `admin_permissions` VALUES (212, '新增管理员', 'admin-user-create', '', '', 1, 40, '2026-07-22 22:07:51', '2026-07-22 22:07:51');
INSERT INTO `admin_permissions` VALUES (213, '编辑管理员', 'admin-user-edit', '', '', 2, 40, '2026-07-22 22:07:51', '2026-07-22 22:07:51');
INSERT INTO `admin_permissions` VALUES (214, '删除管理员', 'admin-user-delete', '', '', 3, 40, '2026-07-22 22:07:51', '2026-07-22 22:07:51');
INSERT INTO `admin_permissions` VALUES (215, '重置管理员 Google 验证', 'admin-user-reset-googlecode', '', '', 4, 40, '2026-07-22 22:07:51', '2026-07-22 22:07:51');
INSERT INTO `admin_permissions` VALUES (217, '设置管理员飞机权限', 'admin-user-telegram-role', '', '', 6, 40, '2026-07-22 22:07:51', '2026-07-22 22:07:51');
INSERT INTO `admin_permissions` VALUES (218, '新增银行代码', 'bank-code-create', '', '', 1, 42, '2026-07-22 22:11:06', '2026-07-22 22:11:06');
INSERT INTO `admin_permissions` VALUES (219, '编辑银行代码', 'bank-code-edit', '', '', 2, 42, '2026-07-22 22:11:06', '2026-07-22 22:11:06');
INSERT INTO `admin_permissions` VALUES (220, '删除银行代码', 'bank-code-delete', '', '', 3, 42, '2026-07-22 22:11:06', '2026-07-22 22:11:06');
INSERT INTO `admin_permissions` VALUES (221, '新增渠道银行代码', 'bank-code-channel-create', '', '', 4, 42, '2026-07-22 22:11:06', '2026-07-22 22:11:06');
INSERT INTO `admin_permissions` VALUES (222, '编辑渠道银行代码', 'bank-code-channel-edit', '', '', 5, 42, '2026-07-22 22:11:06', '2026-07-22 22:11:06');
INSERT INTO `admin_permissions` VALUES (223, '删除渠道银行代码', 'bank-code-channel-delete', '', '', 6, 42, '2026-07-22 22:11:06', '2026-07-22 22:11:06');
INSERT INTO `admin_permissions` VALUES (224, '复制渠道银行代码', 'bank-code-channel-copy', '', '', 7, 42, '2026-07-22 22:11:06', '2026-07-22 22:11:06');
INSERT INTO `admin_permissions` VALUES (225, '新增黑名单', 'black-content-create', '', '', 1, 49, '2026-07-22 22:14:11', '2026-07-22 22:14:11');
INSERT INTO `admin_permissions` VALUES (226, '编辑黑名单', 'black-content-edit', '', '', 2, 49, '2026-07-22 22:14:11', '2026-07-22 22:14:11');
INSERT INTO `admin_permissions` VALUES (227, '启用/禁用黑名单', 'black-content-status', '', '', 3, 49, '2026-07-22 22:14:11', '2026-07-22 22:14:11');
INSERT INTO `admin_permissions` VALUES (228, '删除黑名单', 'black-content-delete', '', '', 4, 49, '2026-07-22 22:14:11', '2026-07-22 22:14:11');
INSERT INTO `admin_permissions` VALUES (229, '解封 IP 黑名单', 'ip-blacklist-unban', '', '', 1, 99, '2026-07-22 22:14:11', '2026-07-22 22:14:11');
INSERT INTO `admin_permissions` VALUES (230, '新增自营分组', 'user-group-create', '', '', 1, 45, '2026-07-22 22:16:48', '2026-07-22 22:16:48');
INSERT INTO `admin_permissions` VALUES (231, '编辑自营分组', 'user-group-edit', '', '', 2, 45, '2026-07-22 22:16:48', '2026-07-22 22:16:48');
INSERT INTO `admin_permissions` VALUES (232, '删除自营分组', 'user-group-delete', '', '', 3, 45, '2026-07-22 22:16:48', '2026-07-22 22:16:48');
INSERT INTO `admin_permissions` VALUES (233, '启用/禁用自营分组', 'user-group-status', '', '', 4, 45, '2026-07-22 22:16:48', '2026-07-22 22:16:48');
INSERT INTO `admin_permissions` VALUES (234, '修改自营分组优先级', 'user-group-priority', '', '', 5, 45, '2026-07-22 22:16:48', '2026-07-22 22:16:48');
INSERT INTO `admin_permissions` VALUES (235, '商户对接', 'merchant-user-detail', 'GET', '/merchant/user/detail', 119, 14, '2026-08-04 07:26:37', '2026-08-04 07:26:37');

SET FOREIGN_KEY_CHECKS = 1;
