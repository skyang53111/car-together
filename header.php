<?php
/**
 * 校区拼车平台 - 公共头部
 *
 * 页面可在 include 之前设置 $bodyClass 追加 body 类名（当前无页面使用，
 * 保留作为通用钩子）。样式见 assets/css/glass.css。
 */
if (!isset($pdo)) require_once __DIR__ . '/config.php';
$bodyClass = $bodyClass ?? '';
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#4F46E5">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title><?php echo h(getPageTitle()); ?> - <?php echo h(SITE_NAME); ?></title>
    <link rel="stylesheet" href="/assets/css/app.css?v=3">
    <link rel="stylesheet" href="/assets/css/glass.css?v=5">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🚗</text></svg>">
</head>
<body<?php echo $bodyClass !== '' ? ' class="' . h($bodyClass) . '"' : ''; ?>>

<!-- ===== 图标精灵（Lucide · ISC 协议，内联，无外部请求）===== -->
<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">
    <symbol id="i-car" viewBox="0 0 24 24"><path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><path d="M9 17h6"/><circle cx="17" cy="17" r="2"/></symbol>
    <symbol id="i-bell" viewBox="0 0 24 24"><path d="M10.268 21a2 2 0 0 0 3.464 0"/><path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326"/></symbol>
    <symbol id="i-plus" viewBox="0 0 24 24"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M8 12h8"/><path d="M12 8v8"/></symbol>
    <symbol id="i-house" viewBox="0 0 24 24"><path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10a2 2 0 0 1 .709-1.528l7-6a2 2 0 0 1 2.582 0l7 6A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></symbol>
    <symbol id="i-ticket" viewBox="0 0 24 24"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2"/><path d="M13 17v2"/><path d="M13 11v2"/></symbol>
    <symbol id="i-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 0 0-16 0"/></symbol>
    <symbol id="i-users" viewBox="0 0 24 24"><path d="M18 21a8 8 0 0 0-16 0"/><circle cx="10" cy="8" r="5"/><path d="M22 20c0-3.37-2-6.5-4-8a5 5 0 0 0-.45-8.3"/></symbol>
    <symbol id="i-search" viewBox="0 0 24 24"><path d="m21 21-4.34-4.34"/><circle cx="11" cy="11" r="8"/></symbol>
    <symbol id="i-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></symbol>
    <symbol id="i-pin" viewBox="0 0 24 24"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></symbol>
    <symbol id="i-lock" viewBox="0 0 24 24"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></symbol>
    <symbol id="i-phone" viewBox="0 0 24 24"><path d="M13.832 16.568a1 1 0 0 0 1.213-.303l.355-.465A2 2 0 0 1 17 15h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2A18 18 0 0 1 2 4a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v3a2 2 0 0 1-.8 1.6l-.468.351a1 1 0 0 0-.292 1.233 14 14 0 0 0 6.392 6.384"/></symbol>
    <symbol id="i-cal" viewBox="0 0 24 24"><path d="M8 2v3"/><path d="M16 2v3"/><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M8 13h.01"/><path d="M12 13h.01"/><path d="M16 13h.01"/><path d="M8 17h.01"/><path d="M12 17h.01"/><path d="M16 17h.01"/></symbol>
    <symbol id="i-arrow-r" viewBox="0 0 24 24"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></symbol>
    <symbol id="i-chev-r" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></symbol>
    <symbol id="i-chev-l" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></symbol>
    <symbol id="i-x" viewBox="0 0 24 24"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></symbol>
    <symbol id="i-check" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></symbol>
    <symbol id="i-settings" viewBox="0 0 24 24"><path d="M9.671 4.136a2.34 2.34 0 0 1 4.659 0 2.34 2.34 0 0 0 3.319 1.915 2.34 2.34 0 0 1 2.33 4.033 2.34 2.34 0 0 0 0 3.831 2.34 2.34 0 0 1-2.33 4.033 2.34 2.34 0 0 0-3.319 1.915 2.34 2.34 0 0 1-4.659 0 2.34 2.34 0 0 0-3.32-1.915 2.34 2.34 0 0 1-2.33-4.033 2.34 2.34 0 0 0 0-3.831A2.34 2.34 0 0 1 6.35 6.051a2.34 2.34 0 0 0 3.319-1.915"/><circle cx="12" cy="12" r="3"/></symbol>
    <symbol id="i-logout" viewBox="0 0 24 24"><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/></symbol>
    <symbol id="i-mail" viewBox="0 0 24 24"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></symbol>
    <symbol id="i-shield" viewBox="0 0 24 24"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/></symbol>
    <symbol id="i-chat" viewBox="0 0 24 24"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/></symbol>
    <symbol id="i-trash" viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></symbol>
    <symbol id="i-edit" viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></symbol>
    <symbol id="i-minus" viewBox="0 0 24 24"><path d="M5 12h14"/></symbol>
    <symbol id="i-swap" viewBox="0 0 24 24"><path d="m21 16-4 4-4-4"/><path d="M17 20V4"/><path d="m3 8 4-4 4 4"/><path d="M7 4v16"/></symbol>
</svg>

    <!-- 顶部导航栏 -->
    <nav class="top-nav" id="topNav">
        <div class="nav-inner">
            <a href="/index.php" class="nav-logo">
                <span class="logo-mark"><svg class="ico"><use href="#i-car"/></svg></span>
                <span class="logo-text">校区拼车</span>
            </a>
            <div class="nav-actions">
                <?php if (isLoggedIn()): ?>
                    <?php $user = getCurrentUser(); $unread = getUnreadNotificationCount($user['id']); ?>
                    <!-- 通知铃铛 -->
                    <a href="/user_center.php?tab=msg#notifications" class="icon-btn" aria-label="消息通知<?php echo $unread > 0 ? "（{$unread} 条未读）" : ''; ?>">
                        <svg class="ico"><use href="#i-bell"/></svg>
                        <?php if ($unread > 0): ?>
                        <span class="dot-badge"><?php echo $unread > 9 ? '9+' : (int)$unread; ?></span>
                        <?php endif; ?>
                    </a>
                    <!-- 发布：手机端隐藏，主操作在底部标签栏 -->
                    <a href="/post_ride.php" class="nav-cta">
                        <svg class="ico"><use href="#i-plus"/></svg><span>发布</span>
                    </a>
                    <div class="nav-user-menu" id="userMenu">
                        <button class="avatar-btn" id="userMenuBtn" aria-label="用户菜单" aria-haspopup="true" aria-expanded="false">
                        <?php $displayName = userDisplayName($user); ?>
                            <?php if (!empty($user['avatar'])): ?>
                                <img src="<?php echo h($user['avatar']); ?>" alt="">
                            <?php else: ?>
                                <?php echo h(mb_substr($displayName, 0, 1)); ?>
                            <?php endif; ?>
                        </button>
                        <div class="nav-dropdown" id="userDropdown" style="display:none;">
                            <div class="dropdown-header">
                                <strong><?php echo h($displayName); ?></strong>
                                <small><?php echo h($user['phone'] ?: $user['email']); ?></small>
                            </div>
                            <a href="/user_center.php"><svg class="ico"><use href="#i-user"/></svg>个人中心</a>
                            <a href="/my_rides.php"><svg class="ico"><use href="#i-car"/></svg>我的发布</a>
                            <a href="/my_bookings.php"><svg class="ico"><use href="#i-ticket"/></svg>我的预订</a>
                            <?php if (isAdmin()): ?>
                            <a href="/admin/index.php"><svg class="ico"><use href="#i-settings"/></svg>后台管理</a>
                            <?php endif; ?>
                            <a href="#" onclick="event.preventDefault();if(confirm('确定退出登录？')){var f=document.createElement('form');f.method='POST';f.action='/logout.php';var t=document.createElement('input');t.type='hidden';t.name='csrf_token';t.value='<?php echo csrfToken(); ?>';f.appendChild(t);document.body.appendChild(f);f.submit();}"><svg class="ico"><use href="#i-logout"/></svg>退出登录</a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="/login.php" class="nav-login">登录 / 注册</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- 主内容 -->
    <main class="main-content">
        <?php echo flashMessage(); ?>
