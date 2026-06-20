<?php
/**
 * 校区拼车平台 - 退出登录
 */
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf(input('csrf_token'))) {
    session_destroy();
}

header('Location: /login.php');
exit;
