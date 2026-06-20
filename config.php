<?php
/**
 * 校区拼车平台 - 全局配置（通用框架配置）
 *
 * ⚠️ 敏感信息已移至 config.local.php（该文件不会随版本更新覆盖）
 */

// ==================== 加载本地配置（数据库、邮件等敏感信息）====================
$localConfigFile = __DIR__ . '/config.local.php';
if (file_exists($localConfigFile)) {
    require_once $localConfigFile;
}

// ==================== 站点配置 ====================
defined('SITE_NAME')    or define('SITE_NAME', '校区拼车平台');
defined('SITE_URL')     or define('SITE_URL', 'http://your-domain.com');
defined('TIMEZONE')     or define('TIMEZONE', 'Asia/Shanghai');
defined('DB_CHARSET')   or define('DB_CHARSET', 'utf8mb4');

// ==================== 安全配置 ====================
defined('SESSION_LIFETIME') or define('SESSION_LIFETIME', 86400);
defined('BCRYPT_COST')      or define('BCRYPT_COST', 10);

// ==================== 验证码配置 ====================
defined('CODE_EXPIRE')  or define('CODE_EXPIRE', 600);
defined('CODE_LENGTH')  or define('CODE_LENGTH', 6);

// ==================== 频率限制配置 ====================
defined('RATE_SEND_CODE_MAX')    or define('RATE_SEND_CODE_MAX', 3);
defined('RATE_SEND_CODE_WINDOW') or define('RATE_SEND_CODE_WINDOW', 3600);
defined('RATE_REGISTER_MAX')     or define('RATE_REGISTER_MAX', 5);
defined('RATE_REGISTER_WINDOW')  or define('RATE_REGISTER_WINDOW', 86400);
defined('RATE_LOGIN_MAX')        or define('RATE_LOGIN_MAX', 10);
defined('RATE_LOGIN_WINDOW')     or define('RATE_LOGIN_WINDOW', 3600);

// ==================== 业务配置 ====================
defined('MAX_SEATS')       or define('MAX_SEATS', 6);
defined('RIDES_PER_PAGE')  or define('RIDES_PER_PAGE', 12);

// ==================== 校区列表 ====================
defined('CAMPUSES') or define('CAMPUSES', json_encode([
    '南校区',
    '北校区',
    '月亮岛校区'
]));

// ==================== 初始化 ====================
date_default_timezone_set(TIMEZONE);
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
ini_set('session.cookie_lifetime', SESSION_LIFETIME);

// 数据库连接
try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    if (php_sapi_name() === 'cli') {
        die("数据库连接失败: " . $e->getMessage() . "\n");
    }
    error_log("Database connection failed: " . $e->getMessage());
    die("系统维护中，请稍后再试。");
}

// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 自动加载函数库
require_once __DIR__ . '/functions.php';

// 自动完成已过期的拼车（每请求轻量操作）
autoCompleteRides();
