-- ============================================================
-- 校区拼车平台 - 数据库初始化脚本
-- 适用于: MySQL 5.7+ / MariaDB 10.3+
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- 用户表
-- ----------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  KEY `idx_campus` (`campus`),
  KEY `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户表';

-- ----------------------------
-- 拼车信息表
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='拼车信息表';

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
-- 插入默认管理员（部署后请立即修改密码）
-- ⚠️ 部署后请立即登录后台修改管理员邮箱和密码！
-- ----------------------------
INSERT INTO `users` (`phone`, `email`, `password_hash`, `nickname`, `role`, `email_verified`, `phone_verified`, `status`) VALUES
('13800000000', 'admin@example.com', '$2y$10$wh38OJUJ0Di1F58jZUM3DO4XbcIcFIxBvFgKitWmDaNtB4spQ0BzG', '系统管理员', 'super_admin', 1, 1, 'active');

-- 插入默认站点配置
INSERT INTO `site_config` (`config_key`, `config_value`) VALUES
('site_name', '校区拼车平台'),
('site_description', '校园通勤拼车信息共享平台'),
('max_seats', '6'),
('announcement', ''),
('registration_verification', '1'),
('email_notifications', '1'),
('smtp_host', ''),
('smtp_port', '465'),
('smtp_secure', 'ssl'),
('smtp_username', ''),
('smtp_password', ''),
('smtp_from_email', ''),
('smtp_from_name', '校区拼车平台');

SET FOREIGN_KEY_CHECKS = 1;
