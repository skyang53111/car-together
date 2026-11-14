<?php
/**
 * 校区拼车平台 - 退出登录
 * 仅接受 POST 请求并验证 CSRF token，防止跨站请求伪造攻击
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/casdoor.php';

// 仅接受 POST 请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /index.php');
    exit;
}

// CSRF 保护
if (!verifyCsrf(input('csrf_token'))) {
    header('Location: /index.php');
    exit;
}

// 记录来源：若本次是通过统一身份认证登录的，退出后一并注销 Casdoor 会话（单点登出）
$fromCasdoor = ($_SESSION['auth_source'] ?? '') === 'casdoor';
$idTokenHint = $_SESSION['casdoor_id_token'] ?? '';

// 清除会话
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// 单点登出：跳转 Casdoor 注销，再回到本平台首页
if ($fromCasdoor && casdoorIsConfigured()) {
    header('Location: ' . casdoorLogoutUrl(CASDOOR_LOGOUT_REDIRECT, $idTokenHint));
    exit;
}

header('Location: /index.php');
exit;
