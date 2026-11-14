<?php
/**
 * 校区拼车平台 - 统一身份认证回调（Casdoor / OIDC）
 *
 * 流程：校验 state → 用 code 换 token → 取 claims → 关联/新建本地用户 → 写 session
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/casdoor.php';

/** 安全跳转：仅允许站内相对路径 */
function oauthSafeTarget(string $target): string {
    $parsed = parse_url($target);
    if (!empty($parsed['host']) || !empty($parsed['scheme'])) {
        return '/index.php';
    }
    $path = $parsed['path'] ?? $target;
    if (str_starts_with($path, '/') && !str_starts_with($path, '//') && strpos($path, '\\') === false) {
        return $path;
    }
    return '/index.php';
}

$silent = !empty($_SESSION['casdoor_silent']);
$error  = $_GET['error'] ?? '';
$code   = $_GET['code'] ?? '';
$state  = $_GET['state'] ?? '';

$expected = $_SESSION['casdoor_state'] ?? '';
$verifier = $_SESSION['casdoor_verifier'] ?? '';
unset($_SESSION['casdoor_state'], $_SESSION['casdoor_verifier'], $_SESSION['casdoor_silent']);

// ========== 授权失败 ==========
if ($error !== '') {
    casdoorLog('authorize error: ' . $error . ' / ' . ($_GET['error_description'] ?? ''));
    if ($silent) {
        // 静默刷新失败（如 Casdoor 会话已过期）→ 提示并回到原页面
        flashMessage('资料刷新失败，请退出后重新登录再试', 'warning');
        header('Location: ' . oauthSafeTarget($_SESSION['redirect_url'] ?? '/user_center.php'));
        exit;
    }
    flashMessage('登录失败：' . $error, 'error');
    header('Location: /login.php');
    exit;
}

// ========== 校验 state ==========
if ($code === '' || $expected === '' || !hash_equals($expected, $state)) {
    casdoorLog('state mismatch or missing code');
    flashMessage('登录校验失败，请重试', 'error');
    header('Location: /login.php');
    exit;
}

// ========== 换取 token ==========
$token = casdoorExchangeCode($code, $verifier);
if (!$token) {
    flashMessage('登录失败：无法与统一认证平台通信，请稍后重试', 'error');
    header('Location: /login.php');
    exit;
}

// ========== 取用户信息 ==========
$claims = casdoorFetchClaims($token);
if (!$claims) {
    flashMessage('登录失败：未能获取用户信息', 'error');
    header('Location: /login.php');
    exit;
}

// ========== 关联 / 新建本地用户 ==========
$userId = casdoorFindOrCreateUser($claims);
if (!$userId) {
    flashMessage('登录失败：账号关联异常，请联系管理员', 'error');
    header('Location: /login.php');
    exit;
}

// ========== 校验账号状态 ==========
$st = $pdo->prepare("SELECT role, status FROM users WHERE id = ? LIMIT 1");
$st->execute([$userId]);
$u = $st->fetch();
if (!$u || $u['status'] !== 'active') {
    flashMessage('该账号已被禁用，请联系管理员', 'error');
    header('Location: /login.php');
    exit;
}

// ========== 写入登录态 ==========
session_regenerate_id(true);
$_SESSION['user_id']      = $userId;
$_SESSION['user_role']    = $u['role'];
$_SESSION['auth_source']  = 'casdoor';
if (!empty($token['id_token'])) {
    $_SESSION['casdoor_id_token'] = $token['id_token']; // 供单点登出使用
}

$pdo->prepare("UPDATE users SET last_login = NOW(), last_ip = ? WHERE id = ?")
    ->execute([getClientIP(), $userId]);

// ========== 跳转 ==========
$target = oauthSafeTarget($_SESSION['redirect_url'] ?? '/index.php');
unset($_SESSION['redirect_url']);

if (!$silent) {
    flashMessage('登录成功，欢迎回来！', 'success');
}

header('Location: ' . $target);
exit;
