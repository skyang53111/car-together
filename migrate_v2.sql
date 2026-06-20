-- ============================================================
-- 校区拼车平台 - 数据库迁移脚本 v2
-- 用途：从邮箱登录迁移到手机号+邮箱双登录模式
-- 执行前请备份数据库！
-- ============================================================

-- 1. 修改 users 表：phone 改为可 NULL 的唯一键，email 改为可 NULL
ALTER TABLE `users`
    MODIFY COLUMN `phone` varchar(20) DEFAULT NULL COMMENT '手机号（登录凭据）'
    AFTER `id`,
    MODIFY COLUMN `email` varchar(100) DEFAULT NULL COMMENT '邮箱（用于接收验证码和通知）'
    AFTER `phone`,
    ADD COLUMN `phone_verified` tinyint(1) DEFAULT 0 COMMENT '手机号已验证'
    AFTER `email_verified`;

-- 2. 添加 phone 唯一索引
ALTER TABLE `users` ADD UNIQUE KEY `uk_phone` (`phone`);

-- 3. 设置管理员默认手机号（请改为实际手机号）
-- UPDATE `users` SET `phone` = '13800000000', `phone_verified` = 1 WHERE `email` = 'admin@example.com' AND `phone` IS NULL;

-- 4. 插入 email_notifications 配置（如果不存在）
INSERT INTO `site_config` (`config_key`, `config_value`) VALUES ('email_notifications', '1')
ON DUPLICATE KEY UPDATE `config_value` = `config_value`;
