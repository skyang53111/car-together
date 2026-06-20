<?php
/**
 * 校区拼车平台 - 我的预订
 */
require_once __DIR__ . '/config.php';
requireLogin();
setPageTitle('我的预订');

$stmt = $pdo->prepare(
    "SELECT b.*, r.origin, r.destination, r.ride_time, r.contact, r.notes, r.capacity, r.available_seats, r.status as ride_status, u.nickname, u.phone, u.email
     FROM bookings b
     JOIN rides r ON b.ride_id = r.id
     JOIN users u ON r.user_id = u.id
     WHERE b.user_id = ?
     ORDER BY r.ride_time DESC"
);
$stmt->execute([$_SESSION['user_id']]);
$bookings = $stmt->fetchAll();
?>
<?php include __DIR__ . '/header.php'; ?>

<h1 style="font-size: 18px; margin-bottom: 12px;">📌 我的预订</h1>

<?php if (empty($bookings)): ?>
<div class="empty-state">
    <div class="empty-icon">📌</div>
    <p>还没有预订任何拼车</p>
    <a href="/index.php" class="btn btn-primary" style="margin-top:12px;">去首页看看</a>
</div>
<?php else: ?>
    <?php foreach ($bookings as $b): ?>
    <?php
        $publisher = ['nickname' => $b['nickname'], 'phone' => $b['phone'], 'email' => $b['email']];
        $statusLabel = match($b['status']) {
            'confirmed' => '<span class="ride-badge ride-badge-available">已确认</span>',
            'cancelled' => '<span class="tag" style="background:#FEE2E2;color:#EF4444;">已取消</span>',
            'completed' => '<span class="ride-badge ride-badge-ended">已完成</span>',
            default => ''
        };
    ?>
    <div class="ride-card">
        <div class="ride-card-top">
            <div class="ride-route">
                <span class="from"><?php echo h($b['origin']); ?></span>
                <span class="arrow">→</span>
                <span class="to"><?php echo h($b['destination']); ?></span>
            </div>
            <?php echo $statusLabel; ?>
        </div>
        <div class="ride-card-meta">
            <span>🕐 <?php echo h(date('Y-m-d H:i', strtotime($b['ride_time']))); ?></span>
            <span>👤 <?php echo h(userDisplayName($publisher)); ?></span>
            <?php if ($b['status'] === 'confirmed'): ?>
            <span>📞 <?php echo h($b['contact']); ?></span>
            <?php endif; ?>
        </div>
        <?php if ($b['status'] === 'confirmed'): ?>
        <div class="ride-card-actions">
            <form action="cancel_booking.php" method="post" style="flex:1;display:flex;">
                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                <input type="hidden" name="booking_id" value="<?php echo (int)$b['id']; ?>">
                <button type="submit" name="cancel" class="btn btn-outline-danger" style="width:100%;border-radius:0 0 var(--radius-lg);" onclick="return confirm('确定取消预订？')">取消预订</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
