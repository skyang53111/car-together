<?php
/**
 * 校区拼车平台 - 后台公共头部
 */
if (!isset($pdo)) require_once __DIR__ . '/../config.php';
requireAdmin();

$adminActivePage = $adminActivePage ?? basename($_SERVER['SCRIPT_NAME'], '.php');

$_adminUnreadNotif = 0;
try {
    $_adminUnreadNotif = getUnreadNotificationCount($_SESSION['user_id']);
} catch (Exception $e) {}

$_adminPending = [];
try {
    $_adminPending['expired_rides'] = (int)$pdo->query(
        "SELECT COUNT(*) FROM rides WHERE status='active' AND ride_time < DATE_SUB(NOW(), INTERVAL 2 HOUR)"
    )->fetchColumn();
    $_adminPending['rate_blocked'] = (int)$pdo->query(
        "SELECT COUNT(*) FROM rate_limits WHERE blocked_until IS NOT NULL AND blocked_until > NOW()"
    )->fetchColumn();
} catch (Exception $e) {}
$_adminPendingTotal = array_sum($_adminPending);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h(getPageTitle()); ?> - <?php echo h(SITE_NAME); ?></title>
    <link rel="stylesheet" href="/assets/css/app.css?v=3">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>⚙️</text></svg>">
</head>
<body>
<div class="admin-layout">
    <aside class="admin-sidebar">
        <div class="admin-logo">⚙️ 后台管理</div>
        <div class="admin-nav">
            <a href="index.php" class="<?php echo $adminActivePage === 'index' ? 'active' : ''; ?>">
                📊 仪表盘
                <?php if ($_adminPendingTotal > 0): ?>
                <span class="sidebar-badge"><?php echo $_adminPendingTotal; ?></span>
                <?php endif; ?>
            </a>
            <a href="users.php" class="<?php echo $adminActivePage === 'users' ? 'active' : ''; ?>">👥 用户管理</a>
            <a href="rides.php" class="<?php echo $adminActivePage === 'rides' ? 'active' : ''; ?>">🚗 拼车管理</a>
            <a href="bookings.php" class="<?php echo $adminActivePage === 'bookings' ? 'active' : ''; ?>">📌 预订管理</a>
            <a href="config.php" class="<?php echo $adminActivePage === 'config' ? 'active' : ''; ?>">🔧 系统配置</a>
        </div>
        <div class="admin-footer">
            <a href="../index.php">← 返回前台</a>
            <a href="#" onclick="event.preventDefault();if(confirm('确定退出登录？')){var f=document.createElement('form');f.method='POST';f.action='../logout.php';var t=document.createElement('input');t.type='hidden';t.name='csrf_token';t.value='<?php echo csrfToken(); ?>';f.appendChild(t);document.body.appendChild(f);f.submit();}">🚪 退出登录</a>
        </div>
    </aside>
    <main class="admin-main">
        <?php echo flashMessage(); ?>
