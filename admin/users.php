<?php
/**
 * 校区拼车平台 - 后台用户管理
 */
require_once __DIR__ . '/../config.php';
requireAdmin();
setPageTitle('用户管理');
$adminActivePage = 'users';

// 操作处理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf(input('csrf_token'))) {
        flashMessage('表单已过期，请刷新重试', 'error');
        header('Location: users.php'); exit;
    }
    $action = input('action');
    $targetId = (int)input('user_id');

    if ($targetId == $_SESSION['user_id']) {
        flashMessage('不能操作自己的账号', 'warning');
    } else {
        $stmt = $pdo->prepare("SELECT id, role, email FROM users WHERE id = ?");
        $stmt->execute([$targetId]);
        $targetUser = $stmt->fetch();

        if (!$targetUser) {
            flashMessage('用户不存在', 'error');
        } elseif ($targetUser['role'] === 'super_admin' && !isSuperAdmin()) {
            flashMessage('权限不足：无法操作系统管理员', 'error');
        } elseif ($action === 'toggle_status') {
            if (in_array($targetUser['role'], ['admin', 'super_admin'], true)) {
                flashMessage('无法禁用管理员', 'warning');
            } else {
                $stmt = $pdo->prepare("UPDATE users SET status = IF(status='active','disabled','active') WHERE id = ?");
                $stmt->execute([$targetId]);
                if ($stmt->rowCount() > 0) { flashMessage('用户状态已更新', 'success'); adminLog('toggle_status', 'user', $targetId); }
            }
        } elseif ($action === 'make_admin') {
            if ($targetUser['role'] === 'super_admin') {
                flashMessage('超级管理员权限不可更改', 'error');
            } else {
                $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ? AND role NOT IN ('admin','super_admin')")->execute([$targetId]);
                flashMessage('已设为管理员', 'success');
                adminLog('make_admin', 'user', $targetId);
            }
        } elseif ($action === 'make_super_admin') {
            if (!isSuperAdmin()) {
                flashMessage('权限不足', 'error');
            } elseif ($targetUser['role'] === 'super_admin') {
                flashMessage('该用户已是超级管理员', 'info');
            } else {
                $pdo->prepare("UPDATE users SET role = 'super_admin' WHERE id = ?")->execute([$targetId]);
                flashMessage('已设为超级管理员', 'success');
            }
        } elseif ($action === 'remove_admin') {
            if ($targetUser['role'] === 'super_admin' && !isSuperAdmin()) {
                flashMessage('权限不足：无法取消超级管理员', 'error');
            } elseif ($targetUser['role'] === 'super_admin' && isSuperAdmin()) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'super_admin' AND status = 'active' AND id != ?");
                $stmt->execute([$targetId]);
                if ($stmt->fetchColumn() < 1) {
                    flashMessage('无法取消：至少保留一个超级管理员', 'warning');
                } else {
                    $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ?")->execute([$targetId]);
                    flashMessage('已降级为普通管理员', 'success');
                }
            } else {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role IN ('admin','super_admin') AND status = 'active' AND id != ?");
                $stmt->execute([$targetId]);
                if ($stmt->fetchColumn() < 1) {
                    flashMessage('无法取消：至少保留一个活跃管理员', 'warning');
                } else {
                    $pdo->prepare("UPDATE users SET role = 'user' WHERE id = ?")->execute([$targetId]);
                    flashMessage('已取消管理员权限', 'success');
                }
            }
        } elseif ($action === 'delete_account') {
            if ($targetUser['role'] === 'super_admin') {
                flashMessage('权限不足：无法注销超级管理员', 'error');
            } elseif (in_array($targetUser['role'], ['admin', 'super_admin'], true)) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role IN ('admin','super_admin') AND status = 'active' AND id != ?");
                $stmt->execute([$targetId]);
                if ($stmt->fetchColumn() < 1) {
                    flashMessage('无法注销：至少保留一个活跃管理员', 'warning');
                } else {
                    goto do_delete;
                }
            } else {
                do_delete:
                $pdo->beginTransaction();
                try {
                    $pdo->prepare("DELETE FROM verification_codes WHERE email = ?")->execute([$targetUser['email']]);
                    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$targetId]);
                    $pdo->commit();
                    flashMessage('用户已注销并删除所有数据', 'success');
                    adminLog('delete_account', 'user', $targetId, '永久注销用户');
                } catch (Exception $e) {
                    $pdo->rollBack();
                    error_log("Admin delete user {$targetId} failed: " . $e->getMessage());
                    flashMessage('注销失败，请稍后重试', 'error');
                }
            }
        }
    }
    header('Location: users.php' . (isset($_GET['page']) ? '?page=' . (int)$_GET['page'] : ''));
    exit;
}

$page = max(1, (int)input('page', 'get', '1'));
$search = input('search', 'get');
$roleFilter = input('role', 'get');
$statusFilter = input('status', 'get');

$where = [];
$params = [];
if ($search) {
    $where[] = "(phone LIKE ? OR email LIKE ? OR nickname LIKE ?)";
    $params = ["%{$search}%", "%{$search}%", "%{$search}%"];
}
if ($roleFilter) { $where[] = "role = ?"; $params[] = $roleFilter; }
if ($statusFilter) { $where[] = "status = ?"; $params[] = $statusFilter; }
$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = $pdo->prepare("SELECT COUNT(*) FROM users {$whereClause}");
$total->execute($params);
$total = $total->fetchColumn();
$totalPages = ceil($total / 20);
$offset = ($page - 1) * 20;

$stmt = $pdo->prepare("SELECT * FROM users {$whereClause} ORDER BY created_at DESC LIMIT 20 OFFSET {$offset}");
$stmt->execute($params);
$users = $stmt->fetchAll();

include __DIR__ . '/header.php';
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:8px;">
    <h1>👥 用户管理 <span style="font-size:14px;color:var(--gray-400);font-weight:400;">共 <?php echo $total; ?> 人</span></h1>
</div>

<form action="users.php" method="get" style="margin-bottom:16px;display:flex;gap:8px;flex-wrap:wrap;">
    <input type="text" name="search" class="form-input" placeholder="搜索手机号/邮箱/昵称..." value="<?php echo h($search); ?>" style="flex:1;min-width:150px;">
    <select name="role" class="form-select" style="width:auto;">
        <option value="">全部角色</option>
        <option value="user" <?php echo $roleFilter === 'user' ? 'selected' : ''; ?>>普通用户</option>
        <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>管理员</option>
        <option value="super_admin" <?php echo $roleFilter === 'super_admin' ? 'selected' : ''; ?>>超级管理员</option>
    </select>
    <select name="status" class="form-select" style="width:auto;">
        <option value="">全部状态</option>
        <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>正常</option>
        <option value="disabled" <?php echo $statusFilter === 'disabled' ? 'selected' : ''; ?>>已禁用</option>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">筛选</button>
    <?php if ($search || $roleFilter || $statusFilter): ?>
    <a href="users.php" class="btn btn-sm btn-outline">重置</a>
    <?php endif; ?>
</form>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>手机号</th><th>邮箱</th><th>昵称</th><th>角色</th><th>状态</th>
                <th>注册时间</th><th>最后登录</th><th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td><?php echo h($u['phone'] ?: '-'); ?></td>
                <td><?php echo h($u['email'] ?: '-'); ?></td>
                <td><?php echo h($u['nickname'] ?: '-'); ?></td>
                <td><span class="tag <?php echo in_array($u['role'], ['admin','super_admin']) ? 'tag-primary' : ''; ?>"><?php
                    $roleLabels = ['user'=>'用户','admin'=>'管理员','super_admin'=>'超级管理员'];
                    echo $roleLabels[$u['role']] ?? $u['role'];
                ?></span></td>
                <td><span class="tag <?php echo $u['status'] === 'active' ? 'tag-success' : ''; ?>"><?php echo $u['status']; ?></span></td>
                <td><?php echo date('m-d H:i', strtotime($u['created_at'])); ?></td>
                <td><?php echo $u['last_login'] ? date('m-d H:i', strtotime($u['last_login'])) : '-'; ?></td>
                <td>
                    <?php if ($u['id'] != $_SESSION['user_id']):
                        $canOperate = !($u['role'] === 'super_admin' && !isSuperAdmin());
                    ?>
                    <?php if ($canOperate): ?>
                    <div class="action-dropdown">
                        <button class="action-btn" type="button">操作 ▾</button>
                        <div class="action-menu">
                            <?php if (!in_array($u['role'], ['admin', 'super_admin'])): ?>
                            <form method="post" onsubmit="return confirm('确认<?php echo $u['status'] === 'active' ? '禁用' : '启用'; ?>该用户？')">
                                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                <button type="submit" name="action" value="toggle_status"><?php echo $u['status'] === 'active' ? '🚫 禁用' : '✅ 启用'; ?></button>
                            </form>
                            <form method="post" onsubmit="return confirm('确定升为管理员？')">
                                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                <button type="submit" name="action" value="make_admin">⬆️ 升为管理员</button>
                            </form>
                            <?php elseif ($u['role'] === 'admin'): ?>
                            <form method="post" onsubmit="return confirm('确定取消管理员权限？')">
                                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                <button type="submit" name="action" value="remove_admin">⬇️ 取消管理</button>
                            </form>
                            <?php elseif ($u['role'] === 'super_admin' && isSuperAdmin()): ?>
                            <form method="post" onsubmit="return confirm('确定降级为普通管理员？')">
                                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                <button type="submit" name="action" value="remove_admin">⬇️ 降为管理</button>
                            </form>
                            <?php endif; ?>
                            <?php if (isSuperAdmin() && $u['role'] !== 'super_admin'): ?>
                            <form method="post" onsubmit="return confirm('确定升为超级管理员？')">
                                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                <button type="submit" name="action" value="make_super_admin">👑 升为超级管理</button>
                            </form>
                            <?php endif; ?>
                            <form method="post" onsubmit="return confirm('确定永久注销该用户？\n\n所有数据（行程、预订、资料）将被永久删除且无法恢复。')">
                                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                <button type="submit" name="action" value="delete_account" class="danger">🗑️ 注销账号</button>
                            </form>
                        </div>
                    </div>
                    <?php else: ?>
                    <span style="color:var(--gray-400);font-size:12px;">超级管理员</span>
                    <?php endif; ?>
                    <?php else: ?>
                    <span style="color:var(--gray-400);font-size:12px;">当前账号</span>
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
    $queryParams = $_GET;
    unset($queryParams['page']);
    $baseQ = http_build_query($queryParams);
    $base = 'users.php' . ($baseQ ? '?' . $baseQ . '&' : '?');
    if ($page > 1): ?><a href="<?php echo $base; ?>page=<?php echo $page - 1; ?>">‹</a><?php endif; ?>
    <?php for ($i = max(1, $page - 3); $i <= min($totalPages, $page + 3); $i++): ?>
        <a href="<?php echo $base; ?>page=<?php echo $i; ?>" class="<?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
    <?php endfor; ?>
    <?php if ($page < $totalPages): ?><a href="<?php echo $base; ?>page=<?php echo $page + 1; ?>">›</a><?php endif; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
