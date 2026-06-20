<?php
/**
 * 校区拼车平台 - 后台拼车管理
 */
require_once __DIR__ . '/header.php';
$adminActivePage = 'rides';
setPageTitle('拼车管理');

$page = max(1, (int)input('page', 'get', '1'));
$limit = 30;
$offset = ($page - 1) * $limit;
$total = (int)$pdo->query("SELECT COUNT(*) FROM rides")->fetchColumn();
$totalPages = ceil($total / $limit);

$rides = $pdo->prepare(
    "SELECT r.*, u.nickname, u.phone FROM rides r JOIN users u ON r.user_id = u.id ORDER BY r.created_at DESC LIMIT ? OFFSET ?"
);
$rides->execute([$limit, $offset]);
$rides = $rides->fetchAll();
?>
<h1>🚗 拼车管理</h1><br>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>发布者</th>
                <th>出发地</th>
                <th>目的地</th>
                <th>出发时间</th>
                <th>座位</th>
                <th>状态</th>
                <th>发布时间</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rides as $r): ?>
            <tr>
                <td><?php echo (int)$r['id']; ?></td>
                <td><?php echo h($r['nickname'] ?: $r['phone']); ?></td>
                <td><?php echo h($r['origin']); ?></td>
                <td><?php echo h($r['destination']); ?></td>
                <td><?php echo h($r['ride_time']); ?></td>
                <td><?php echo (int)$r['available_seats']; ?>/<?php echo (int)$r['capacity']; ?></td>
                <td>
                    <?php
                    echo match($r['status']) {
                        'active' => '<span class="tag tag-success">进行中</span>',
                        'cancelled' => '<span style="background:#FEE2E2;color:#EF4444;padding:2px 8px;border-radius:4px;font-size:12px;">已取消</span>',
                        'completed' => '<span style="background:#F3F4F6;color:#9CA3AF;padding:2px 8px;border-radius:4px;font-size:12px;">已完成</span>',
                        default => $r['status']
                    };
                    ?>
                </td>
                <td><?php echo h($r['created_at']); ?></td>
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
