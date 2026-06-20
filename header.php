<?php
/**
 * 校区拼车平台 - 公共头部
 */
if (!isset($pdo)) require_once __DIR__ . '/config.php';
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#4F46E5">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title><?php echo h(getPageTitle()); ?> - <?php echo h(SITE_NAME); ?></title>
    <link rel="stylesheet" href="/assets/css/app.css?v=3">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🚗</text></svg>">
</head>
<body>
    <!-- 顶部导航栏 -->
    <nav class="top-nav" id="topNav">
        <div class="nav-inner">
            <a href="/index.php" class="nav-logo">
                <span class="logo-icon">🚗</span>
                <span class="logo-text">校区拼车</span>
            </a>
            <div class="nav-actions">
                <?php if (isLoggedIn()): ?>
                    <?php $user = getCurrentUser(); $unread = getUnreadNotificationCount($user['id']); ?>
                    <!-- 通知铃铛 -->
                    <a href="/user_center.php#notifications" style="position:relative;display:flex;align-items:center;padding:6px;color:var(--gray-600);font-size:18px;text-decoration:none;">
                        🔔
                        <?php if ($unread > 0): ?>
                        <span style="position:absolute;top:2px;right:0;background:var(--danger);color:#fff;font-size:10px;font-weight:700;width:16px;height:16px;border-radius:50%;display:flex;align-items:center;justify-content:center;"><?php echo $unread > 9 ? '9+' : $unread; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="/post_ride.php" class="nav-btn nav-btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        <span>发布</span>
                    </a>
                    <div class="nav-user-menu" id="userMenu">
                        <button class="nav-avatar-btn" id="userMenuBtn" aria-label="用户菜单">
                        <?php $displayName = userDisplayName($user); ?>
                            <span class="avatar"><?php echo h(mb_substr($displayName, 0, 1)); ?></span>
                        </button>
                        <div class="nav-dropdown" id="userDropdown" style="display:none;">
                            <div class="dropdown-header">
                                <strong><?php echo h($displayName); ?></strong>
                                <small><?php echo h($user['phone'] ?: $user['email']); ?></small>
                            </div>
                            <a href="/user_center.php">📋 个人中心</a>
                            <a href="/my_rides.php">🚗 我的发布</a>
                            <a href="/my_bookings.php">📌 我的预订</a>
                            <?php if (isAdmin()): ?>
                            <a href="/admin/index.php">⚙️ 后台管理</a>
                            <?php endif; ?>
                            <a href="#" onclick="event.preventDefault();if(confirm('确定退出登录？')){var f=document.createElement('form');f.method='POST';f.action='/logout.php';var t=document.createElement('input');t.type='hidden';t.name='csrf_token';t.value='<?php echo csrfToken(); ?>';f.appendChild(t);document.body.appendChild(f);f.submit();}" style="display:flex;align-items:center;gap:8px;">🚪 退出登录</a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="/login.php" class="nav-btn">登录</a>
                    <a href="/register.php" class="nav-btn nav-btn-primary">注册</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- 主内容 -->
    <main class="main-content">
        <?php echo flashMessage(); ?>
