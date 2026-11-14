<?php
/**
 * 校区拼车平台 - 发起统一身份认证登录（Casdoor / OIDC 授权码 + PKCE）
 *
 * 用法：
 *   /oauth/login.php                       普通登录
 *   /oauth/login.php?redirect=/my_rides.php 登录后回到指定站内页面
 *   /oauth/login.php?silent=1&redirect=..   静默刷新（已登录 Casdoor 时无感返回，用于"刷新我的资料"）
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/casdoor.php';

if (!casdoorIsConfigured()) {
    http_response_code(500);
    die('统一身份认证尚未配置：请在 config.local.php 中填写 CASDOOR_CLIENT_ID / CASDOOR_CLIENT_SECRET。');
}

$silent = isset($_GET['silent']) && $_GET['silent'] !== '0' && $_GET['silent'] !== '';

// 记录登录后要回到的页面（仅允许站内相对路径，防开放重定向）
$redirect = input('redirect', 'get');
if ($redirect !== '') {
    $parsed = parse_url($redirect);
    if (empty($parsed['host']) && empty($parsed['scheme'])) {
        $path = $parsed['path'] ?? $redirect;
        if (str_starts_with($path, '/') && !str_starts_with($path, '//') && strpos($path, '\\') === false) {
            $_SESSION['redirect_url'] = $path;
        }
    }
}

// 已登录且非静默刷新 → 直接回首页
if (!$silent && isLoggedIn()) {
    header('Location: /index.php');
    exit;
}

// 生成 state 与 PKCE
$state    = bin2hex(random_bytes(16));
$verifier = bin2hex(random_bytes(32)); // 64 字符，符合 PKCE 规范
$challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

$_SESSION['casdoor_state']    = $state;
$_SESSION['casdoor_verifier'] = $verifier;
$_SESSION['casdoor_silent']   = $silent;

header('Location: ' . casdoorAuthorizeUrl($state, $challenge, $silent));
exit;
