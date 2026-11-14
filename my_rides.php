<?php
/**
 * 校区拼车平台 - 我的发布
 */
require_once __DIR__ . '/config.php';
requireLogin();
setPageTitle('我的发布');

$userId = $_SESSION['user_id'];

// 统计
$activeCount = $pdo->prepare("SELECT COUNT(*) FROM rides WHERE user_id = ? AND status = 'active'");
$activeCount->execute([$userId]);
$activeCount = $activeCount->fetchColumn();
$totalCount = $pdo->prepare("SELECT COUNT(*) FROM rides WHERE user_id = ?");
$totalCount->execute([$userId]);
$totalCount = $totalCount->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT * FROM rides WHERE user_id = ? ORDER BY
     CASE status WHEN 'active' THEN 0 WHEN 'cancelled' THEN 2 ELSE 1 END,
     ride_time DESC"
);
$stmt->execute([$userId]);
$rides = $stmt->fetchAll();

// 获取每条行程的预订者
$passengersMap = [];
if (!empty($rides)) {
    $rideIds = array_column($rides, 'id');
    if (!empty($rideIds)) {
        $placeholders = implode(',', array_fill(0, count($rideIds), '?'));
        $psStmt = $pdo->prepare(
            "SELECT b.ride_id, u.phone, u.email, u.nickname
             FROM bookings b
             JOIN users u ON b.user_id = u.id
             WHERE b.ride_id IN ({$placeholders}) AND b.status = 'confirmed'
             ORDER BY b.created_at ASC"
        );
        $psStmt->execute($rideIds);
        while ($row = $psStmt->fetch()) {
            $passengersMap[$row['ride_id']][] = $row;
        }
    }
}
?>
<?php include __DIR__ . '/header.php'; ?>

<div class="page-head">
    <div>
        <h1>我的发布</h1>
        <p class="page-head-sub">共 <?php echo (int)$totalCount; ?> 条记录</p>
    </div>
    <a href="/post_ride.php" class="btn btn-primary">
        <svg class="ico"><use href="#i-plus"/></svg>发布
    </a>
</div>

<div class="stat-tiles">
    <div class="stat-tile is-primary">
        <div class="stat-tile-val"><?php echo (int)$activeCount; ?></div>
        <div class="stat-tile-lbl">进行中</div>
    </div>
    <div class="stat-tile">
        <div class="stat-tile-val"><?php echo (int)$totalCount; ?></div>
        <div class="stat-tile-lbl">累计发布</div>
    </div>
</div>

<?php if (empty($rides)): ?>
<div class="empty-state">
    <svg class="ico"><use href="#i-car"/></svg>
    <p>还没有发布任何行程信息</p>
    <p style="font-size:13px;">发布一条，让顺路的人找到你</p>
    <a href="/post_ride.php" class="btn btn-primary">立即发布</a>
</div>
<?php else: ?>
    <?php foreach ($rides as $ride): ?>
    <article class="ride-card<?php echo $ride['status'] !== 'active' ? ' is-inactive' : ''; ?>">
        <div class="ride-card-top">
            <div class="ride-route">
                <span class="from"><?php echo h($ride['origin']); ?></span>
                <svg class="ico"><use href="#i-arrow-r"/></svg>
                <span class="to"><?php echo h($ride['destination']); ?></span>
            </div>
            <?php if ($ride['status'] === 'active'): ?>
                <span class="ride-badge ride-badge-available">余 <?php echo (int)$ride['available_seats']; ?>/<?php echo (int)$ride['capacity']; ?> 座</span>
            <?php elseif ($ride['status'] === 'cancelled'): ?>
                <span class="ride-badge ride-badge-cancelled">已取消</span>
            <?php else: ?>
                <span class="ride-badge ride-badge-full">已完成</span>
            <?php endif; ?>
        </div>

        <div class="ride-card-meta">
            <span class="meta-item">
                <svg class="ico"><use href="#i-clock"/></svg><?php echo h(date('m-d H:i', strtotime($ride['ride_time']))); ?>
            </span>
            <span class="meta-item">
                <svg class="ico"><use href="#i-phone"/></svg><?php echo h($ride['contact']); ?>
            </span>
            <?php if ($ride['notes']): ?>
            <span class="meta-note"><?php echo h($ride['notes']); ?></span>
            <?php endif; ?>
        </div>

        <?php if ($ride['status'] === 'active' && !empty($passengersMap[$ride['id']])): ?>
        <div class="people">
            <div class="people-label">
                <svg class="ico"><use href="#i-users"/></svg>已预订 <?php echo count($passengersMap[$ride['id']]); ?> 人
            </div>
            <div class="people-list">
                <?php foreach ($passengersMap[$ride['id']] as $p): ?>
                <span class="person-chip">
                    <?php
                        $pn = $p['nickname'] ?: '';
                        if (!$pn && $p['phone']) {
                            $pn = (strlen($p['phone']) >= 7) ? substr($p['phone'], 0, 3) . '****' . substr($p['phone'], -4) : '****' . substr($p['phone'], -2);
                        }
                        if (!$pn) $pn = explode('@', $p['email'])[0];
                        echo h($pn);
                    ?>
                </span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($ride['status'] === 'active'): ?>
        <div class="ride-card-actions">
            <form method="post" action="delete_ride.php">
                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                <input type="hidden" name="ride_id" value="<?php echo (int)$ride['id']; ?>">
                <button type="submit" class="btn btn-danger-outline btn-block"
                        data-confirm="确定要取消此行程信息吗？已预订的人将收到通知。">取消发布</button>
            </form>
        </div>
        <?php endif; ?>
    </article>
    <?php endforeach; ?>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
