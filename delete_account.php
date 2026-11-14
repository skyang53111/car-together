<?php
/**
 * 校区拼车平台 - 账号注销
 * 删除用户及其所有关联数据（行程、预订、验证码等）
 *
 * 确认流程：入口在 profile.php 的「注销账号」弹窗里，点「确认注销」即视为二次确认，
 * 不再要求用户输入 DELETE。
 */
require_once __DIR__ . '/config.php';
requireLogin();

setPageTitle('账号注销');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /profile.php');
    exit;
}

// CSRF 校验
if (!verifyCsrf(input('csrf_token'))) {
    flashMessage('表单已过期，请刷新页面重试', 'error');
    header('Location: /profile.php');
    exit;
}

$userId = $_SESSION['user_id'];
$userEmail = '';
$userRole = $_SESSION['user_role'] ?? 'user';

// 先获取用户信息
$stmt = $pdo->prepare("SELECT email, role FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    flashMessage('用户不存在', 'error');
    header('Location: /profile.php');
    exit;
}

// 超级管理员不可注销
if ($user['role'] === 'super_admin') {
    flashMessage('超级管理员账号不可注销', 'error');
    header('Location: /profile.php');
    exit;
}

// 管理员注销时检查是否是最后一个活跃管理员
if (in_array($user['role'], ['admin', 'super_admin'])) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role IN ('admin','super_admin') AND status = 'active' AND id != ?");
    $stmt->execute([$userId]);
    if ($stmt->fetchColumn() < 1) {
        flashMessage('您是唯一的管理员，无法注销。请先将管理员权限授予其他用户。', 'error');
        header('Location: /profile.php');
        exit;
    }
}

$userEmail = $user['email'];

// 事务删除所有关联数据
$pdo->beginTransaction();
try {
    // 外键 CASCADE 会自动处理 rides 和 bookings
    // 但我们需要显式处理没有外键关联的数据

    // 删除验证码记录
    $pdo->prepare("DELETE FROM verification_codes WHERE email = ?")->execute([$userEmail]);

    // 删除用户在 rate_limits 中的记录（使用 last_ip）
    $userIp = getClientIP();
    // 不删除 rate_limits，因为它绑的是 IP 而非用户

    // 删除用户（rides 和 bookings 通过外键 CASCADE 自动删除）
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$userId]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Account deletion failed for user {$userId}: " . $e->getMessage());
    flashMessage('注销失败，请稍后重试', 'error');
    header('Location: /profile.php');
    exit;
}

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

// 重定向到首页并显示消息
session_start();
$_SESSION['flash_message'] = ['msg' => '账号已注销，本平台数据已清空。感谢你使用校区拼车平台。', 'type' => 'info'];
header('Location: /index.php');
exit;
