<?php
/**
 * 校区拼车平台 - 本地配置模板
 *
 * 使用方法：
 *   1. 复制本文件为 config.local.php（同目录）
 *   2. 填入数据库密码、邮箱授权码等真实信息
 *   3. config.local.php 已在 .gitignore 中，不会进入版本库，也不会被代码更新覆盖
 *
 * ⚠️ 千万不要把 config.local.php 提交到公开仓库
 */

// ==================== 数据库配置 ====================
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'carpool');
define('DB_USER', 'carpool_user');
define('DB_PASS', 'your_database_password');

// ==================== 邮件配置（注册验证码、预订/取消通知）====================
// 常用组合：
//   Outlook：smtp.office365.com / 587 / tls，密码填「应用密码」
//   QQ 邮箱：smtp.qq.com / 465 / ssl，密码填「授权码」
//   163 邮箱：smtp.163.com / 465 / ssl，密码填「授权码」
// 留空也能跑，只是发不出验证码邮件（可在后台「系统配置」里关闭注册邮箱验证）
define('MAIL_HOST', 'smtp.office365.com');
define('MAIL_PORT', 587);
define('MAIL_SECURE', 'tls');
define('MAIL_USERNAME', 'your-email@outlook.com');
define('MAIL_PASSWORD', 'your_app_password');
define('MAIL_FROM_EMAIL', 'your-email@outlook.com');
define('MAIL_FROM_NAME', '校区拼车平台');

// ==================== 统一身份认证（可选，默认不用）====================
// 不接统一认证就把 config.php 里的 AUTH_MODE 保持为 'local'，以下全部留空即可。
// 要接入 Casdoor / 任何标准 OIDC 服务时：
//   1. 在认证服务后台新建应用，回调地址填 https://你的域名/oauth/callback.php
//   2. 把 AUTH_MODE 改成 'casdoor'（只走统一认证）或 'both'（两种登录都提供）
//   3. 填下面的地址与密钥
// define('CASDOOR_BASE', 'https://your-auth-domain.com');
// define('CASDOOR_CLIENT_ID', '');
// define('CASDOOR_CLIENT_SECRET', '');
// Webhook 共享密钥：认证服务删除/修改用户时会推送到 /oauth/casdoor_webhook.php，
// 用请求头 X-Casdoor-Webhook-Secret 校验来源；留空则关闭该端点。
// define('CASDOOR_WEBHOOK_SECRET', '');

// ==================== 微信小程序（可选，不用小程序就整段留空）====================
// 获取路径：微信公众平台 → 开发管理 → 开发设置
// define('WX_APPID', '');
// define('WX_SECRET', '');

// ==================== 微信订阅消息模板（可选）====================
// 模板字段名跟着你在小程序后台选的模板走，这里只做「字段 → 值」映射。
// define('SUBSCRIBE_TMPL_BOOKING', '');
// define('SUBSCRIBE_BOOKING_FIELDS', json_encode([
//     'thing1' => ['value' => '{origin} → {destination}'],
//     'time2'  => ['value' => '{time}'],
//     'thing3' => ['value' => '已预订成功，请准时到达出发点'],
// ]));
// define('SUBSCRIBE_TMPL_CANCEL', '');
// define('SUBSCRIBE_CANCEL_FIELDS', json_encode([
//     'thing1' => ['value' => '{origin} → {destination}'],
//     'thing2' => ['value' => '用户已取消预订'],
// ]));
// define('SUBSCRIBE_TMPL_REMIND', '');
// define('SUBSCRIBE_REMIND_FIELDS', json_encode([
//     'thing1' => ['value' => '{origin} → {destination}'],
//     'time2'  => ['value' => '{time}'],
// ]));
