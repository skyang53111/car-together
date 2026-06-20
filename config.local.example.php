<?php
/**
 * 校区拼车平台 - 本地敏感配置（模板）
 *
 * 使用方法：
 *   1. 将此文件复制为 config.local.php
 *   2. 填入你的数据库密码、邮箱密码等敏感信息
 *   3. config.local.php 不会被版本更新覆盖
 *
 * ⚠️ 不要将 config.local.php 提交到 Git！
 */

// ==================== 数据库配置 ====================
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'carpool');
define('DB_USER', 'carpool_user');
define('DB_PASS', 'your_database_password');

// ==================== 邮件配置（推荐使用 Outlook SMTP）====================
define('MAIL_HOST', 'smtp.office365.com');
define('MAIL_PORT', 587);
define('MAIL_SECURE', 'tls');
define('MAIL_USERNAME', 'your-email@outlook.com');
define('MAIL_PASSWORD', 'your_app_password');
define('MAIL_FROM_EMAIL', 'your-email@outlook.com');
define('MAIL_FROM_NAME', '校区拼车平台');
