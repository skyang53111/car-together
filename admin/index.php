<?php
/**
 * 校区拼车平台 - 后台仪表盘
 */
require_once __DIR__ . '/header.php';
$adminActivePage = 'index';
setPageTitle('仪表盘');

// 统计
$stats = [];
try {
    $stats['users'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
    $stats['total_rides'] = (int)$pdo->query("SELECT COUNT(*) FROM rides")->fetchColumn();
    $stats['active_rides'] = (int)$pdo->query("SELECT COUNT(*) FROM rides WHERE status='active'")->fetchColumn();
    $stats['bookings'] = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status='confirmed'")->fetchColumn();
    $stats['new_today'] = (int)$pdo->query("SELECT COUNT(*) FROM rides WHERE DATE(created_at)=CURDATE()")->fetchColumn();
} catch (Exception $e) {}
?>
<h1>📊 仪表盘</h1><br>

<div class="stats-grid">
    <div class="stat-card stat-card-blue">
        <div class="stat-icon">👥</div>
        <div class="stat-value"><?php echo $stats['users'] ?? 0; ?></div>
        <div class="stat-label">普通用户</div>
    </div>
    <div class="stat-card stat-card-green">
        <div class="stat-icon">🚗</div>
        <div class="stat-value"><?php echo $stats['active_rides'] ?? 0; ?></div>
        <div class="stat-label">进行中拼车</div>
    </div>
    <div class="stat-card stat-card-orange">
        <div class="stat-icon">📌</div>
        <div class="stat-value"><?php echo $stats['bookings'] ?? 0; ?></div>
        <div class="stat-label">有效预订</div>
    </div>
    <div class="stat-card stat-card-red">
        <div class="stat-icon">🆕</div>
        <div class="stat-value"><?php echo $stats['new_today'] ?? 0; ?></div>
        <div class="stat-label">今日新增</div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
