<?php
/**
 * 校区拼车平台 - 后台用户管理
 */
require_once __DIR__ . '/header.php';
$adminActivePage = 'users';
setPageTitle('用户管理');

$action = input('action');
if ($action === 'toggle_status' && verifyCsrf(input('csrf_token'))) {
    $userId = (int)input('user_id');
    $stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $u = $stmt->fetch();
    if ($u) {
        $newStatus = $u['status'] === 'active' ? 'disabled' : 'active';
        $pdo->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$newStatus, $userId]);
        adminLog('toggle_user_status', 'user', $userId, "用户状态变更为: {$newStatus}");
        flashMessage('用户状态已更新', 'success');
    }
    header('Location: users.php');
    exit;
}

$page = max(1, (int)input('page', 'get', '1'));
$limit = 30;
$offset = ($page - 1) * $limit;
$total = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalPages = ceil($total / $limit);

$users = $pdo->prepare("SELECT * FROM users ORDER BY created_at DESC LIMIT ? OFFSET ?");
$users->execute([$limit, $offset]);
$users = $users->fetchAll();
?>
<h1>👥 用户管理</h1><br>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>手机号</th>
                <th>邮箱</th>
                <th>昵称</th>
                <th>角色</th>
                <th>状态</th>
                <th>注册时间</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td><?php echo (int)$u['id']; ?></td>
                <td><?php echo h($u['phone'] ?: '-'); ?></td>
                <td><?php echo h($u['email'] ?: '-'); ?></td>
                <td><?php echo h($u['nickname'] ?: '-'); ?></td>
                <td>
                    <?php
                    echo match($u['role']) {
                        'super_admin' => '<span class="tag tag-primary">超管</span>',
                        'admin' => '<span class="tag tag-primary">管理员</span>',
                        default => '用户'
                    };
                    ?>
                </td>
                <td>
                    <span class="tag <?php echo $u['status'] === 'active' ? 'tag-success' : ''; ?>" style="<?php echo $u['status'] === 'disabled' ? 'background:#FEE2E2;color:#EF4444;' : ''; ?>">
                        <?php echo $u['status'] === 'active' ? '正常' : '禁用'; ?>
                    </span>
                </td>
                <td><?php echo h($u['created_at']); ?></td>
                <td>
                    <?php if ($u['role'] !== 'super_admin'): ?>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                        <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                        <input type="hidden" name="action" value="toggle_status">
                        <button type="submit" class="btn btn-sm <?php echo $u['status'] === 'active' ? 'btn-outline-danger' : 'btn-success'; ?>" onclick="return confirm('确定<?php echo $u['status'] === 'active' ? '禁用' : '启用'; ?>此用户？')">
                            <?php echo $u['status'] === 'active' ? '禁用' : '启用'; ?>
                        </button>
                    </form>
                    <?php endif; ?>
                </td>
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
