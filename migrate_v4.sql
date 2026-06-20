-- ============================================================
-- 校区拼车平台 - 数据库迁移脚本 v4 (P0/P1/P2 全量优化)
-- 执行前请备份数据库！
-- 将每条 SQL 分开执行，遇到报错跳过继续
-- ============================================================

-- 1. 管理操作日志表（记录管理员关键操作）
CREATE TABLE IF NOT EXISTS `admin_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL COMMENT '操作管理员',
  `action` varchar(50) NOT NULL COMMENT '操作类型',
  `target_type` varchar(30) DEFAULT NULL COMMENT '对象类型(user/ride/booking)',
  `target_id` int(11) DEFAULT NULL COMMENT '对象ID',
  `detail` varchar(300) DEFAULT NULL COMMENT '操作详情',
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_admin` (`admin_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理操作日志';

-- 2. bookings 表 status 字段增加 completed 状态（如果 v3 已执行则跳过）
ALTER TABLE `bookings` MODIFY COLUMN `status` enum('confirmed','cancelled','completed') DEFAULT 'confirmed';

-- 3. rides 表添加复合索引（如果 v3 已执行则跳过）
ALTER TABLE `rides` ADD INDEX `idx_user_status` (`user_id`, `status`);

-- 4. bookings 索引（如果 v3 已执行则跳过）
ALTER TABLE `bookings` ADD INDEX `idx_user_status` (`user_id`, `status`);
