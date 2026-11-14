-- ============================================================
-- 校区拼车平台 - 数据库迁移脚本 v5
-- 用途：接入统一身份认证（Casdoor / OIDC）
-- 执行前请备份数据库！
-- 将每条 SQL 分开执行，遇到报错跳过继续
-- ============================================================

-- 1. users 表新增：casdoor_id（Casdoor 用户 sub）、auth_source（认证来源）、avatar（头像URL）
ALTER TABLE `users`
  ADD COLUMN `casdoor_id` varchar(64) DEFAULT NULL COMMENT 'Casdoor 用户 sub' AFTER `openid`,
  ADD COLUMN `auth_source` enum('local','casdoor') DEFAULT 'local' COMMENT '认证来源' AFTER `casdoor_id`,
  ADD COLUMN `avatar` varchar(500) DEFAULT NULL COMMENT '头像URL（同步自 Casdoor）' AFTER `nickname`;

-- 2. casdoor_id 唯一索引（NULL 可重复，不影响存量用户）
ALTER TABLE `users` ADD UNIQUE KEY `uk_casdoor_id` (`casdoor_id`);
