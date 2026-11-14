<?php
/**
 * 校区拼车平台 - 后台预订管理
 */
require_once __DIR__ . '/../config.php';
requireAdmin();
setPageTitle('预订管理');
$adminActivePage = 'bookings';

// 操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf(input('csrf_token'))) {
        flashMessage('表单已过期，请刷新重试', 'error');
        header('Location: bookings.php'); exit;
    }
    $action = input('action');
    $bookingId = (int)input('booking_id');
    if ($action === 'cancel') {
        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ? AND status = 'confirmed'");
        $stmt->execute([$bookingId]);
        $book = $stmt->fetch();
        if ($book) {
            $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?")->execute([$bookingId]);
            $pdo->prepare("UPDATE rides SET available_seats = available_seats + 1 WHERE id = ? AND available_seats < capacity")->execute([$book['ride_id']]);
            notifyOnCancelBooking($book['ride_id'], $book['user_id']);
            flashMessage('预订已取消', 'success');
        }
    }
    header('Location: bookings.php?page=' . max(1, (int)input('page', 'get', '1')));
    exit;
}

// 筛选
$page = max(1, (int)input('page', 'get', '1'));
$search = input('search', 'get');
$statusFilter = input('status', 'get');

$where = [];
$params = [];
if ($search) {
    $where[] = "(r.origin LIKE ? OR r.destination LIKE ? OR u.phone LIKE ? OR u.email LIKE ? OR o.phone LIKE ? OR o.email LIKE ?)";
    $s = "%{$search}%";
    $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s;
}
if ($statusFilter) { $where[] = "b.status = ?"; $params[] = $statusFilter; }
$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countSql = "SELECT COUNT(*) FROM bookings b JOIN users u ON b.user_id = u.id JOIN rides r ON b.ride_id = r.id JOIN users o ON r.user_id = o.id {$whereClause}";
$listSql  = "SELECT b.*, u.phone AS booker_phone, u.email AS booker_email, u.nickname AS booker_name,
                    r.origin, r.destination, r.ride_time,
                    o.phone AS owner_phone, o.email AS owner_email, o.nickname AS owner_name
             FROM bookings b
             JOIN users u ON b.user_id = u.id
             JOIN rides r ON b.ride_id = r.id
             JOIN users o ON r.user_id = o.id
             {$whereClause}
             ORDER BY b.created_at DESC";

$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$total = $stmt->fetchColumn();
$totalPages = ceil($total / 20);
$offset = ($page - 1) * 20;

$stmt = $pdo->prepare($listSql . " LIMIT 20 OFFSET {$offset}");
$stmt->execute($params);
$bookings = $stmt->fetchAll();

include __DIR__ . '/header.php';
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:8px;">
    <h1>📌 预订管理 <span style="font-size:14px;color:var(--gray-400);font-weight:400;">共 <?php echo $total; ?> 条</span></h1>
</div>

<form action="bookings.php" method="get" style="margin-bottom:16px;display:flex;gap:8px;flex-wrap:wrap;">
    <input type="text" name="search" class="form-input" placeholder="搜索路线/用户手机/邮箱..." value="<?php echo h($search); ?>" style="flex:1;min-width:150px;">
    <select name="status" class="form-select" style="width:auto;">
        <option value="">全部状态</option>
        <option value="confirmed" <?php echo $statusFilter === 'confirmed' ? 'selected' : ''; ?>>已确认</option>
        <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>已取消</option>
        <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>已完成</option>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">筛选</button>
    <?php if ($search || $statusFilter): ?>
    <a href="bookings.php" class="btn btn-sm btn-outline">重置</a>
    <?php endif; ?>
</form>

<div class="table-container">
    <table>
        <thead>
            <tr><th>ID</th><th>预订者</th><th>路线</th><th>出发时间</th><th>发布者</th><th>状态</th><th>预订时间</th><th>操作</th></tr>
        </thead>
        <tbody>
            <?php foreach ($bookings as $b): ?>
            <tr>
                <td><?php echo $b['id']; ?></td>
                <td><?php echo h($b['booker_name'] ?: ($b['booker_phone'] ?: $b['booker_email'])); ?></td>
                <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo h($b['origin'] . ' → ' . $b['destination']); ?>">
                    <?php echo h($b['origin']); ?> → <?php echo h($b['destination']); ?>
                </td>
                <td><?php echo h(date('m-d H:i', strtotime($b['ride_time']))); ?></td>
                <td><?php echo h($b['owner_name'] ?: ($b['owner_phone'] ?: $b['owner_email'])); ?></td>
                <td><span class="tag <?php echo $b['status'] === 'confirmed' ? 'tag-success' : ''; ?>"><?php echo $b['status']; ?></span></td>
                <td><?php echo h(date('m-d H:i', strtotime($b['created_at']))); ?></td>
                <td>
                    <?php if ($b['status'] === 'confirmed'): ?>
                    <form method="post" style="display:inline;" onsubmit="return confirm('确认取消此预订？')">
                        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                        <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                        <button type="submit" name="action" value="cancel" class="btn btn-sm btn-outline-danger">取消</button>
                    </form>
                    <?php else: ?>
                    <span style="color:var(--gray-400);font-size:12px;">-</span>
                    <?php endif; ?>
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
    $b = 'bookings.php' . ($bq ? '?' . $bq . '&' : '?');
    if ($page > 1): ?><a href="<?php echo $b; ?>page=<?php echo $page - 1; ?>">‹</a><?php endif; ?>
    <?php for ($i = max(1, $page - 3); $i <= min($totalPages, $page + 3); $i++): ?>
        <a href="<?php echo $b; ?>page=<?php echo $i; ?>" class="<?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
    <?php endfor; ?>
    <?php if ($page < $totalPages): ?><a href="<?php echo $b; ?>page=<?php echo $page + 1; ?>">›</a><?php endif; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
