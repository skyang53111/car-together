<?php
/**
 * 校区拼车平台 - 后台预订管理
 */
require_once __DIR__ . '/header.php';
$adminActivePage = 'bookings';
setPageTitle('预订管理');

$page = max(1, (int)input('page', 'get', '1'));
$limit = 30;
$offset = ($page - 1) * $limit;
$total = (int)$pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$totalPages = ceil($total / $limit);

$bookings = $pdo->prepare(
    "SELECT b.*, u1.nickname as passenger_name, u1.phone as passenger_phone,
            r.origin, r.destination, r.ride_time,
            u2.nickname as owner_name
     FROM bookings b
     JOIN users u1 ON b.user_id = u1.id
     JOIN rides r ON b.ride_id = r.id
     JOIN users u2 ON r.user_id = u2.id
     ORDER BY b.created_at DESC LIMIT ? OFFSET ?"
);
$bookings->execute([$limit, $offset]);
$bookings = $bookings->fetchAll();
?>
<h1>📌 预订管理</h1><br>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>乘客</th>
                <th>路线</th>
                <th>出发时间</th>
                <th>发布者</th>
                <th>状态</th>
                <th>预订时间</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($bookings as $b): ?>
            <tr>
                <td><?php echo (int)$b['id']; ?></td>
                <td><?php echo h($b['passenger_name'] ?: $b['passenger_phone']); ?></td>
                <td><?php echo h($b['origin']); ?> → <?php echo h($b['destination']); ?></td>
                <td><?php echo h($b['ride_time']); ?></td>
                <td><?php echo h($b['owner_name'] ?: '-'); ?></td>
                <td>
                    <?php
                    echo match($b['status']) {
                        'confirmed' => '<span class="tag tag-success">已确认</span>',
                        'cancelled' => '<span style="background:#FEE2E2;color:#EF4444;padding:2px 8px;border-radius:4px;font-size:12px;">已取消</span>',
                        'completed' => '<span style="background:#F3F4F6;color:#9CA3AF;padding:2px 8px;border-radius:4px;font-size:12px;">已完成</span>',
                        default => $b['status']
                    };
                    ?>
                </td>
                <td><?php echo h($b['created_at']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
<div class="pagination">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
    <a href="?page=<?php echo $i; ?>" class="<?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
