<?php
/**
 * 校区拼车平台 - 我的预订
 */
require_once __DIR__ . '/config.php';
requireLogin();
setPageTitle('我的预订');

$userId = $_SESSION['user_id'];
// 统计
$totalConfirmed = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ? AND status = 'confirmed'");
$totalConfirmed->execute([$userId]);
$totalConfirmed = $totalConfirmed->fetchColumn();
$activeCount = $pdo->prepare("
    SELECT COUNT(*) FROM bookings b
    JOIN rides r ON b.ride_id = r.id
    WHERE b.user_id = ? AND b.status = 'confirmed' AND r.status = 'active'
");
$activeCount->execute([$userId]);
$activeCount = $activeCount->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT b.id AS booking_id, b.status AS booking_status, b.created_at AS booked_at,
            r.*, u.nickname AS owner_name, u.phone AS owner_phone, u.email AS owner_email
     FROM bookings b
     JOIN rides r ON b.ride_id = r.id
     JOIN users u ON r.user_id = u.id
     WHERE b.user_id = ?
     ORDER BY b.created_at DESC"
);
$stmt->execute([$userId]);
$bookings = $stmt->fetchAll();
?>
<?php include __DIR__ . '/header.php'; ?>

<div class="page-head">
    <div>
        <h1>我的预订</h1>
        <p class="page-head-sub">共 <?php echo (int)$totalConfirmed; ?> 条有效预订</p>
    </div>
    <a href="/index.php" class="btn btn-outline">
        <svg class="ico"><use href="#i-search"/></svg>找车
    </a>
</div>

<div class="stat-tiles">
    <div class="stat-tile is-primary">
        <div class="stat-tile-val"><?php echo (int)$activeCount; ?></div>
        <div class="stat-tile-lbl">进行中</div>
    </div>
    <div class="stat-tile">
        <div class="stat-tile-val"><?php echo (int)$totalConfirmed; ?></div>
        <div class="stat-tile-lbl">累计预订</div>
    </div>
</div>

<?php if (empty($bookings)): ?>
<div class="empty-state">
    <svg class="ico"><use href="#i-ticket"/></svg>
    <p>还没有预订任何行程</p>
    <p style="font-size:13px;">看看有没有顺路的行程</p>
    <a href="/index.php" class="btn btn-primary">去看看行程信息</a>
</div>
<?php else: ?>
    <?php foreach ($bookings as $b): ?>
    <?php
        $ownerName = $b['owner_name'] ?: explode('@', $b['owner_email'])[0];
        $isCancelled = $b['booking_status'] === 'cancelled';
    ?>
    <article class="ride-card<?php echo $isCancelled ? ' is-inactive' : ''; ?>">
        <div class="ride-card-top">
            <div class="ride-route">
                <span class="from"><?php echo h($b['origin']); ?></span>
                <svg class="ico"><use href="#i-arrow-r"/></svg>
                <span class="to"><?php echo h($b['destination']); ?></span>
            </div>
            <span class="ride-badge <?php echo $isCancelled ? 'ride-badge-cancelled' : 'ride-badge-available'; ?>">
                <?php echo $isCancelled ? '已取消' : '已确认'; ?>
            </span>
        </div>

        <div class="ride-card-meta">
            <span class="meta-item">
                <svg class="ico"><use href="#i-clock"/></svg><?php echo h(date('m-d H:i', strtotime($b['ride_time']))); ?>
            </span>
            <span class="meta-item">
                <svg class="ico"><use href="#i-user"/></svg><?php echo h($ownerName); ?>
            </span>
            <?php if ($b['owner_phone'] && strlen($b['owner_phone']) >= 7): ?>
            <span class="meta-item">
                <svg class="ico"><use href="#i-phone"/></svg><?php echo h(substr($b['owner_phone'], 0, 3) . '****' . substr($b['owner_phone'], -4)); ?>
            </span>
            <?php endif; ?>
            <span class="meta-note">发起人联系方式：<?php echo h($b['contact']); ?></span>
            <span class="meta-item" style="font-size:12.5px;">
                <svg class="ico" style="width:14px;height:14px;"><use href="#i-cal"/></svg>预订于 <?php echo h(date('m-d H:i', strtotime($b['booked_at']))); ?>
            </span>
        </div>

        <?php if ($b['booking_status'] === 'confirmed' && $b['status'] === 'active'): ?>
        <div class="ride-card-actions">
            <form method="post" action="cancel_booking.php">
                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                <input type="hidden" name="booking_id" value="<?php echo (int)$b['booking_id']; ?>">
                <button type="submit" class="btn btn-danger-outline btn-block"
                        data-confirm="确定要取消此预订吗？">取消预订</button>
            </form>
        </div>
        <?php endif; ?>
    </article>
    <?php endforeach; ?>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
