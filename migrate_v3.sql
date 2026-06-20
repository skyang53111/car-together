-- ============================================================
-- 校区拼车平台 - 数据库迁移脚本 v3 (P1 优化)
-- 执行前请备份数据库！
-- 将每条 SQL 分开执行，遇到报错跳过继续
-- ============================================================

-- 1. rides 表添加复合索引（如果失败说明已存在，跳过即可）
ALTER TABLE `rides` ADD INDEX `idx_user_status` (`user_id`, `status`);

-- 2. bookings 索引（单独执行）
ALTER TABLE `bookings` ADD INDEX `idx_user_status` (`user_id`, `status`);

-- 3. bookings 表 status 字段增加 completed 状态
ALTER TABLE `bookings` MODIFY COLUMN `status` enum('confirmed','cancelled','completed') DEFAULT 'confirmed';

-- 4. 创建站内通知表
CREATE TABLE IF NOT EXISTS `notifications` (
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
