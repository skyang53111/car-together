<?php
/**
 * 校区拼车平台 - 后台行程管理
 */
require_once __DIR__ . '/../config.php';
requireAdmin();
setPageTitle('行程管理');
$adminActivePage = 'rides';

// 操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf(input('csrf_token'))) {
        flashMessage('表单已过期，请刷新重试', 'error');
        header('Location: rides.php'); exit;
    }
    $action = input('action');
    $rideId = (int)input('ride_id');
    if ($action === 'cancel') {
        notifyOnRideCancel($rideId);
        $pdo->prepare("UPDATE rides SET status = 'cancelled' WHERE id = ?")->execute([$rideId]);
        $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE ride_id = ? AND status = 'confirmed'")->execute([$rideId]);
        flashMessage('行程已取消', 'success');
        adminLog('cancel_ride', 'ride', $rideId);
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM bookings WHERE ride_id = ?")->execute([$rideId]);
        $pdo->prepare("DELETE FROM rides WHERE id = ?")->execute([$rideId]);
        flashMessage('行程已删除', 'success');
        adminLog('delete_ride', 'ride', $rideId);
    }
    header('Location: rides.php?page=' . max(1, (int)input('page', 'get', '1')));
    exit;
}

$page = max(1, (int)input('page', 'get', '1'));
$search = input('search', 'get');
$status = input('status', 'get');
$dateFrom = input('date_from', 'get');
$dateTo = input('date_to', 'get');

$where = [];
$params = [];
if ($search) { $where[] = "(r.origin LIKE ? OR r.destination LIKE ?)"; $params[] = "%{$search}%"; $params[] = "%{$search}%"; }
if ($status) { $where[] = "r.status = ?"; $params[] = $status; }
if ($dateFrom) { $where[] = "r.created_at >= ?"; $params[] = $dateFrom . ' 00:00:00'; }
if ($dateTo) { $where[] = "r.created_at <= ?"; $params[] = $dateTo . ' 23:59:59'; }
$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countSql = "SELECT COUNT(*) FROM rides r {$whereClause}";
$listSql  = "SELECT r.*, u.phone, u.email, u.nickname FROM rides r JOIN users u ON r.user_id = u.id {$whereClause} ORDER BY r.created_at DESC";

$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$total = $stmt->fetchColumn();
$totalPages = ceil($total / 20);
$offset = ($page - 1) * 20;

$stmt = $pdo->prepare($listSql . " LIMIT 20 OFFSET {$offset}");
$stmt->execute($params);
$rides = $stmt->fetchAll();

include __DIR__ . '/header.php';
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:8px;">
    <h1>🚗 行程管理 <span style="font-size:14px;color:var(--gray-400);font-weight:400;">共 <?php echo $total; ?> 条</span></h1>
</div>

<form action="rides.php" method="get" style="margin-bottom:16px;display:flex;gap:8px;flex-wrap:wrap;">
    <input type="text" name="search" class="form-input" placeholder="搜索出发地/目的地..." value="<?php echo h($search); ?>" style="flex:1;min-width:150px;">
    <select name="status" class="form-select" style="width:auto;">
        <option value="">全部状态</option>
        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>进行中</option>
        <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>已取消</option>
        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>已完成</option>
    </select>
    <input type="date" name="date_from" class="form-input" value="<?php echo h($dateFrom); ?>" style="width:auto;" placeholder="开始日期">
    <input type="date" name="date_to" class="form-input" value="<?php echo h($dateTo); ?>" style="width:auto;" placeholder="结束日期">
    <button type="submit" class="btn btn-primary btn-sm">筛选</button>
    <?php if ($search || $status || $dateFrom || $dateTo): ?>
    <a href="rides.php" class="btn btn-sm btn-outline">重置</a>
    <?php endif; ?>
</form>

<div class="table-container">
    <table>
        <thead>
            <tr><th>ID</th><th>发布者</th><th>出发地</th><th>目的地</th><th>时间</th><th>座位</th><th>状态</th><th>操作</th></tr>
        </thead>
        <tbody>
            <?php foreach ($rides as $r): ?>
            <tr>
                <td><?php echo $r['id']; ?></td>
                <td><?php echo h($r['nickname'] ?: ($r['phone'] ?: $r['email'])); ?></td>
                <td><?php echo h($r['origin']); ?></td>
                <td><?php echo h($r['destination']); ?></td>
                <td><?php echo h(date('m-d H:i', strtotime($r['ride_time']))); ?></td>
                <td><?php echo $r['available_seats']; ?>/<?php echo $r['capacity']; ?></td>
                <td><span class="tag <?php echo $r['status'] === 'active' ? 'tag-success' : ''; ?>"><?php echo $r['status']; ?></span></td>
                <td>
                    <div class="action-dropdown">
                        <button class="action-btn" type="button">操作 ▾</button>
                        <div class="action-menu">
                            <?php if ($r['status'] === 'active'): ?>
                            <form method="post" onsubmit="return confirm('确定取消此行程？将通知所有已预订乘客。')">
                                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                                <input type="hidden" name="ride_id" value="<?php echo $r['id']; ?>">
                                <button type="submit" name="action" value="cancel">🚫 取消行程</button>
                            </form>
                            <?php endif; ?>
                            <form method="post" onsubmit="return confirm('确定永久删除？此操作不可恢复。')">
                                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                                <input type="hidden" name="ride_id" value="<?php echo $r['id']; ?>">
                                <button type="submit" name="action" value="delete" class="danger">🗑️ 删除</button>
                            </form>
                        </div>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
<div class="pagination" style="margin-top:16px;">
    <?php
    $qp = $_GET; unset($qp['page']); $bq = http_build_query($qp);
    $b = 'rides.php' . ($bq ? '?' . $bq . '&' : '?');
    if ($page > 1): ?><a href="<?php echo $b; ?>page=<?php echo $page - 1; ?>">‹</a><?php endif; ?>
    <?php for ($i = max(1, $page - 3); $i <= min($totalPages, $page + 3); $i++): ?>
        <a href="<?php echo $b; ?>page=<?php echo $i; ?>" class="<?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
    <?php endfor; ?>
    <?php if ($page < $totalPages): ?><a href="<?php echo $b; ?>page=<?php echo $page + 1; ?>">›</a><?php endif; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
