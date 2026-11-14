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

// ==================== 统一身份认证（Casdoor / OIDC，可选）====================
// 不接统一认证时保持 AUTH_MODE = 'local' 即可，本机账号密码/邮箱验证码登录，无外部依赖。
// 想接入 Casdoor（或任何标准 OIDC 服务）时：把下面几项写到 config.local.php，
// 并把 AUTH_MODE 改成 'casdoor'（只走统一认证）或 'both'（两种登录都提供）。
// AUTH_MODE: casdoor = 只走统一认证 | both = 两种登录都提供 | local = 仅本地登录
defined('AUTH_MODE')             or define('AUTH_MODE', 'local');
defined('CASDOOR_BASE')          or define('CASDOOR_BASE', 'https://your-auth-domain.com');
defined('CASDOOR_CLIENT_ID')     or define('CASDOOR_CLIENT_ID', '');      // 实际值放 config.local.php
defined('CASDOOR_CLIENT_SECRET') or define('CASDOOR_CLIENT_SECRET', '');  // 实际值放 config.local.php
defined('CASDOOR_REDIRECT_URI')  or define('CASDOOR_REDIRECT_URI', SITE_URL . '/oauth/callback.php');
defined('CASDOOR_SCOPE')         or define('CASDOOR_SCOPE', 'openid profile email phone');
defined('CASDOOR_ACCOUNT_URL')   or define('CASDOOR_ACCOUNT_URL', rtrim(CASDOOR_BASE, '/') . '/account');
// 单点登出后回跳的地址；必须登记在 Casdoor 应用的「许可跳转列表」中。
// 若暂未登记，可在 config.local.php 里把它定义为空串 ''，则登出时不带该参数（就不会报"跳转列表未找到"）。
defined('CASDOOR_LOGOUT_REDIRECT') or define('CASDOOR_LOGOUT_REDIRECT', SITE_URL . '/oauth/logged_out.php');
// Webhook 共享密钥：Casdoor 删除/修改用户时会主动推送事件到 /oauth/casdoor_webhook.php，用它校验来源。
// 留空 = 关闭该端点（一律返回 403）。实际值请写在 config.local.php，不要写死在这里。
defined('CASDOOR_WEBHOOK_SECRET') or define('CASDOOR_WEBHOOK_SECRET', '');

// ==================== 帮助与反馈 ====================
// QQ 反馈群（加群分享链接）。资料页的「帮助与反馈」入口指向它。
// 链接失效或想换群时改这一行即可；留空则资料页不显示该入口。
defined('QQ_GROUP_URL') or define('QQ_GROUP_URL', '');   // 例：'https://qun.qq.com/...#加群分享链接'

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
    '东校区'
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

// 自动完成已过期的行程（每请求轻量操作）
autoCompleteRides();
