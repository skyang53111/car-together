-- ============================================================
-- 校区拼车平台 - 数据库初始化脚本（全新安装用这一个文件即可）
-- 适用于: MySQL 5.7+ / MariaDB 10.3+
--
-- ⚠️ 本脚本会 DROP 并重建所有表，只用于全新安装。
--    已有数据的老站点请改用 migrate_v2.sql → migrate_v5.sql 逐个升级，
--    需要小程序 / 微信订阅消息时再执行 setup_miniprogram.sql、setup_subscribe.sql。
-- ============================================================

-- 创建数据库（在宝塔面板 / phpMyAdmin 中已建库可跳过这两行）
-- CREATE DATABASE IF NOT EXISTS `carpool` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE `carpool`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- 用户表
-- ----------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `openid` varchar(64) DEFAULT NULL COMMENT '微信 openid（小程序登录用）',
  `casdoor_id` varchar(64) DEFAULT NULL COMMENT '统一身份认证用户 sub',
  `auth_source` enum('local','casdoor') DEFAULT 'local' COMMENT '认证来源',
  `avatar` varchar(500) DEFAULT NULL COMMENT '头像 URL',
  `phone` varchar(20) DEFAULT NULL COMMENT '手机号（登录凭据）',
  `email` varchar(100) DEFAULT NULL COMMENT '邮箱（用于接收验证码和通知）',
  `password_hash` varchar(255) NOT NULL,
  `nickname` varchar(50) DEFAULT NULL COMMENT '昵称',
  `gender` enum('male','female','other') DEFAULT NULL COMMENT '性别',
  `campus` varchar(50) DEFAULT NULL COMMENT '所属校区',
  `role` enum('user','admin','super_admin') DEFAULT 'user' COMMENT '角色',
  `status` enum('active','disabled') DEFAULT 'active' COMMENT '状态',
  `phone_verified` tinyint(1) DEFAULT 0 COMMENT '手机号已验证',
  `email_verified` tinyint(1) DEFAULT 0 COMMENT '邮箱已验证',
  `last_login` datetime DEFAULT NULL,
  `last_ip` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_phone` (`phone`),
  UNIQUE KEY `uk_email` (`email`),
  UNIQUE KEY `uk_openid` (`openid`),
  UNIQUE KEY `uk_casdoor_id` (`casdoor_id`),
  KEY `idx_campus` (`campus`),
  KEY `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户表';

-- ----------------------------
-- 行程信息表
-- ----------------------------
DROP TABLE IF EXISTS `rides`;
CREATE TABLE `rides` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `origin` varchar(150) NOT NULL COMMENT '出发地',
  `destination` varchar(150) NOT NULL COMMENT '目的地',
  `ride_time` datetime NOT NULL COMMENT '出发时间',
  `contact` varchar(100) NOT NULL COMMENT '联系方式（微信/QQ/手机）',
  `capacity` int(11) NOT NULL DEFAULT 1 COMMENT '总座位数',
  `available_seats` int(11) NOT NULL DEFAULT 1 COMMENT '剩余座位',
  `notes` text COMMENT '备注说明',
  `status` enum('active','cancelled','completed') DEFAULT 'active',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_user_status` (`user_id`, `status`),
  KEY `idx_ride_time` (`ride_time`),
  KEY `idx_status` (`status`),
  KEY `idx_destination` (`destination`),
  CONSTRAINT `fk_rides_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='行程信息表';

-- ----------------------------
-- 预订记录表
-- ----------------------------
DROP TABLE IF EXISTS `bookings`;
CREATE TABLE `bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `ride_id` int(11) NOT NULL,
  `status` enum('confirmed','cancelled') DEFAULT 'confirmed',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_ride` (`user_id`,`ride_id`),
  KEY `idx_user_status` (`user_id`, `status`),
  KEY `idx_ride` (`ride_id`),
  CONSTRAINT `fk_bookings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bookings_ride` FOREIGN KEY (`ride_id`) REFERENCES `rides` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='预订记录表';

-- ----------------------------
-- 站内通知表
-- ----------------------------
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '接收者',
  `msg_type` varchar(30) NOT NULL COMMENT '类型',
  `title` varchar(200) NOT NULL COMMENT '通知标题',
  `body` text COMMENT '通知正文',
  `link` varchar(300) DEFAULT NULL COMMENT '跳转链接',
  `is_read` tinyint(1) DEFAULT 0 COMMENT '是否已读',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_read` (`user_id`, `is_read`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='站内通知表';

-- ----------------------------
-- 验证码表
-- ----------------------------
DROP TABLE IF EXISTS `verification_codes`;
CREATE TABLE `verification_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `code` varchar(10) NOT NULL COMMENT '6位验证码',
  `type` enum('register','login','reset') DEFAULT 'register',
  `expires_at` datetime NOT NULL COMMENT '过期时间',
  `used` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email_code` (`email`,`code`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='验证码表';

-- ----------------------------
-- 频率限制表（防刷）
-- ----------------------------
DROP TABLE IF EXISTS `rate_limits`;
CREATE TABLE `rate_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `action` varchar(50) NOT NULL COMMENT '操作类型(register/send_code/login)',
  `attempts` int(11) DEFAULT 1,
  `first_attempt` datetime DEFAULT CURRENT_TIMESTAMP,
  `blocked_until` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ip_action` (`ip_address`,`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='频率限制表';

-- ----------------------------
-- 站点配置表
-- ----------------------------
DROP TABLE IF EXISTS `site_config`;
CREATE TABLE `site_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `config_key` varchar(50) NOT NULL,
  `config_value` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_key` (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='站点配置表';

-- ----------------------------
-- 以下三张表仅小程序 / 微信订阅消息需要（不接小程序可以忽略）
-- ----------------------------
DROP TABLE IF EXISTS `user_tokens`;
CREATE TABLE `user_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `token` varchar(64) NOT NULL COMMENT '登录token',
  `expires_at` datetime NOT NULL COMMENT '过期时间',
  `is_revoked` tinyint(1) DEFAULT 0 COMMENT '是否已撤销',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_token` (`token`),
  KEY `idx_user` (`user_id`),
  KEY `idx_expires` (`expires_at`),
  CONSTRAINT `fk_token_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='小程序登录token表';

DROP TABLE IF EXISTS `user_subscriptions`;
CREATE TABLE `user_subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `template_id` varchar(64) NOT NULL COMMENT '微信模板ID',
  `subscribed` tinyint(1) DEFAULT 0 COMMENT '是否已订阅',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_template` (`user_id`,`template_id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `fk_sub_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户订阅消息表';

DROP TABLE IF EXISTS `wechat_tokens`;
CREATE TABLE `wechat_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `access_token` varchar(512) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='微信access_token缓存';

-- ----------------------------
-- 插入默认管理员（密码: admin123456，请登录后立即修改）
-- 密码使用 PHP password_hash('admin123456', PASSWORD_BCRYPT) 生成
INSERT INTO `users` (`phone`, `email`, `password_hash`, `nickname`, `role`, `email_verified`, `phone_verified`, `status`) VALUES
('13800000000', 'admin@campus.com', '$2y$10$wh38OJUJ0Di1F58jZUM3DO4XbcIcFIxBvFgKitWmDaNtB4spQ0BzG', '系统管理员', 'super_admin', 1, 1, 'active');

-- 插入默认站点配置
INSERT INTO `site_config` (`config_key`, `config_value`) VALUES
('announcement_enabled', '0'),
('announcement_title', ''),
('announcement_body', ''),
('announcement_frequency', 'once'),
('email_notifications', '1'),
('smtp_host', 'smtp.163.com'),
('smtp_port', '465'),
('smtp_secure', 'ssl'),
('smtp_username', ''),
('smtp_password', ''),
('smtp_from_email', ''),
('smtp_from_name', '校区拼车平台');

SET FOREIGN_KEY_CHECKS = 1;
