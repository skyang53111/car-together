-- ============================================================
-- 校区拼车平台 - 微信小程序数据库变更
-- 在宝塔面板数据库管理或 phpMyAdmin 中执行
-- ============================================================

-- 1. 创建 token 表（小程序登录态）
CREATE TABLE IF NOT EXISTS `user_tokens` (
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

-- 2. 用户表增加 openid 字段（微信登录绑定）
-- 先判断字段是否已存在，避免重复执行报错
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'users'
  AND COLUMN_NAME = 'openid';

SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `users` ADD COLUMN `openid` varchar(64) DEFAULT NULL AFTER `id`, ADD UNIQUE KEY `uk_openid` (`openid`)',
  'SELECT "openid column already exists, skip" AS msg');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
