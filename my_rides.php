<?php
/**
 * 校区拼车平台 - 我的发布
 */
require_once __DIR__ . '/config.php';
requireLogin();
setPageTitle('我的发布');

$stmt = $pdo->prepare(
    "SELECT r.*, (SELECT COUNT(*) FROM bookings WHERE ride_id = r.id AND status = 'confirmed') as passenger_count
     FROM rides r WHERE r.user_id = ? ORDER BY r.ride_time DESC"
);
$stmt->execute([$_SESSION['user_id']]);
$rides = $stmt->fetchAll();
?>
<?php include __DIR__ . '/header.php'; ?>

<h1 style="font-size: 18px; margin-bottom: 12px;">🚗 我的发布</h1>

<?php if (empty($rides)): ?>
<div class="empty-state">
    <div class="empty-icon">🚗</div>
    <p>还没有发布过拼车</p>
    <a href="/post_ride.php" class="btn btn-primary" style="margin-top:12px;">发布第一条拼车</a>
</div>
<?php else: ?>
    <?php foreach ($rides as $ride): ?>
    <div class="ride-card">
        <div class="ride-card-top">
            <div class="ride-route">
                <span class="from"><?php echo h($ride['origin']); ?></span>
                <span class="arrow">→</span>
                <span class="to"><?php echo h($ride['destination']); ?></span>
            </div>
            <?php
            $statusLabel = match($ride['status']) {
                'active' => '<span class="ride-badge ride-badge-available">进行中</span>',
                'completed' => '<span class="ride-badge ride-badge-ended">已完成</span>',
                'cancelled' => '<span class="tag" style="background:#FEE2E2;color:#EF4444;">已取消</span>',
                default => ''
            };
            echo $statusLabel;
            ?>
        </div>
        <div class="ride-card-meta">
            <span>🕐 <?php echo h(date('Y-m-d H:i', strtotime($ride['ride_time']))); ?></span>
            <span>👥 <?php echo (int)$ride['passenger_count']; ?>/<?php echo (int)$ride['capacity']; ?>人</span>
            <?php if ($ride['status'] === 'active'): ?>
            <form action="delete_ride.php" method="post" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                <input type="hidden" name="ride_id" value="<?php echo (int)$ride['id']; ?>">
                <button type="submit" name="cancel" class="btn btn-sm btn-outline-danger" onclick="return confirm('取消后已预订的用户将收到通知，确定取消？')">取消拼车</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
