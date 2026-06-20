<?php
/**
 * 校区拼车平台 - 订单/我的订单页面
 */
require_once __DIR__ . '/config.php';
requireLogin();
setPageTitle('我的订单');

$page = max(1, (int)input('page', 'get', '1'));
$limit = 20;
$offset = ($page - 1) * $limit;

$stmt = $pdo->prepare(
    "SELECT b.*, r.origin, r.destination, r.ride_time, r.contact, r.status as ride_status,
            u.nickname, u.phone
     FROM bookings b
     JOIN rides r ON b.ride_id = r.id
     JOIN users u ON r.user_id = u.id
     WHERE b.user_id = ?
     ORDER BY b.created_at DESC LIMIT ? OFFSET ?"
);
$stmt->execute([$_SESSION['user_id'], $limit, $offset]);
$bookings = $stmt->fetchAll();

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ?");
$countStmt->execute([$_SESSION['user_id']]);
$total = $countStmt->fetchColumn();
$totalPages = ceil($total / $limit);
?>
<?php include __DIR__ . '/header.php'; ?>

<h1 style="font-size:18px;margin-bottom:12px;">📋 我的订单</h1>

<?php if (empty($bookings)): ?>
<div class="empty-state">
    <div class="empty-icon">📋</div>
    <p>暂无订单记录</p>
    <a href="/index.php" class="btn btn-primary" style="margin-top:12px;">去看看拼车</a>
</div>
<?php else: ?>
    <?php foreach ($bookings as $b): ?>
    <div class="ride-card">
        <div class="ride-card-top">
            <div class="ride-route">
                <span class="from"><?php echo h($b['origin']); ?></span>
                <span class="arrow">→</span>
                <span class="to"><?php echo h($b['destination']); ?></span>
            </div>
            <?php
            $badge = match($b['status']) {
                'confirmed' => '<span class="ride-badge ride-badge-available">已确认</span>',
                'cancelled' => '<span class="tag" style="background:#FEE2E2;color:#EF4444;">已取消</span>',
                'completed' => '<span class="ride-badge ride-badge-ended">已完成</span>',
                default => ''
            };
            echo $badge;
            ?>
        </div>
        <div class="ride-card-meta">
            <span>🕐 <?php echo h(date('Y-m-d H:i', strtotime($b['ride_time']))); ?></span>
            <span>👤 <?php echo h($b['nickname'] ?: $b['phone']); ?></span>
            <?php if ($b['status'] === 'confirmed'): ?>
            <span>📞 <?php echo h($b['contact']); ?></span>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="?page=<?php echo $i; ?>" class="<?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
