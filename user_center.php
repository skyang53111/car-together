<?php
/**
 * 校区拼车平台 - 个人中心
 */
require_once __DIR__ . '/config.php';
requireLogin();
setPageTitle('个人中心');

$user = getCurrentUser();
if (!$user) {
    header('Location: /logout.php');
    exit;
}

$error = '';
$success = '';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = input('action');

    if ($action === 'update_profile') {
        if (!verifyCsrf(input('csrf_token'))) {
            $error = '表单已过期，请刷新重试';
        } else {
            $nickname = input('nickname');
            $gender   = input('gender');
            $campus   = input('campus');

            $stmt = $pdo->prepare("UPDATE users SET nickname = ?, gender = ?, campus = ? WHERE id = ?");
            $stmt->execute([
                $nickname ?: null,
                $gender ?: null,
                $campus ?: null,
                $user['id']
            ]);
            $success = '个人资料已更新';
            $user['nickname'] = $nickname;
            $user['gender'] = $gender;
            $user['campus'] = $campus;
        }
    } elseif ($action === 'change_password') {
        if (!verifyCsrf(input('csrf_token'))) {
            $error = '表单已过期';
        } else {
            $oldPwd = input('old_password');
            $newPwd = input('new_password');
            $confirmPwd = input('confirm_password');

            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$user['id']]);
            $row = $stmt->fetch();

            if (!password_verify($oldPwd, $row['password_hash'])) {
                $error = '原密码错误';
            } elseif (strlen($newPwd) < 8) {
                $error = '新密码至少需要8位';
            } elseif ($newPwd !== $confirmPwd) {
                $error = '两次输入的密码不一致';
            } else {
                $hash = password_hash($newPwd, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
                $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $user['id']]);
                $success = '密码已修改';
            }
        }
    }
}

// 统计数据
$stmt = $pdo->prepare("SELECT COUNT(*) FROM rides WHERE user_id = ?");
$stmt->execute([$user['id']]);
$rideCount = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ?");
$stmt->execute([$user['id']]);
$bookingCount = $stmt->fetchColumn();

$campuses = getCampuses();
?>
<?php include __DIR__ . '/header.php'; ?>

<div class="user-center-header">
    <div class="user-avatar"><?php echo h(mb_substr(userDisplayName($user), 0, 1)); ?></div>
    <h2 style="font-size:18px;"><?php echo h(userDisplayName($user)); ?></h2>
    <div class="user-badges">
        <?php if ($user['phone_verified']): ?>
        <span class="user-badge user-badge-verified">📱 手机已验证</span>
        <?php endif; ?>
        <?php if ($user['email_verified']): ?>
        <span class="user-badge user-badge-verified">📧 邮箱已验证</span>
        <?php endif; ?>
        <?php if ($user['campus']): ?>
        <span class="user-badge user-badge-info">🎓 <?php echo h($user['campus']); ?></span>
        <?php endif; ?>
        <?php if ($user['role'] === 'super_admin'): ?>
        <span class="user-badge user-badge-warn">👑 超级管理员</span>
        <?php elseif ($user['role'] === 'admin'): ?>
        <span class="user-badge user-badge-warn">⚙️ 管理员</span>
        <?php endif; ?>
    </div>
</div>

<div class="uc-stats-grid">
    <div class="uc-stat-card"><div class="uc-stat-icon">🚗</div><div class="uc-stat-val"><?php echo (int)$rideCount; ?></div><div class="uc-stat-lbl">发布</div></div>
    <div class="uc-stat-card"><div class="uc-stat-icon">📌</div><div class="uc-stat-val"><?php echo (int)$bookingCount; ?></div><div class="uc-stat-lbl">预订</div></div>
</div>

<div class="quick-actions">
    <a href="/post_ride.php" class="quick-action-btn"><span class="qa-icon">🚗</span>发布拼车</a>
    <a href="/my_rides.php" class="quick-action-btn"><span class="qa-icon">📋</span>我的发布</a>
    <a href="/my_bookings.php" class="quick-action-btn"><span class="qa-icon">📌</span>我的预订</a>
    <?php if (isAdmin()): ?>
    <a href="/admin/index.php" class="quick-action-btn"><span class="qa-icon">⚙️</span>后台管理</a>
    <?php endif; ?>
</div>

<?php if ($error): ?>
<div class="flash-msg flash-error"><?php echo h($error); ?></div>
<?php endif; ?>
<?php if ($success): ?>
<div class="flash-msg flash-success"><?php echo h($success); ?></div>
<?php endif; ?>

<!-- 个人资料 -->
<div class="card">
    <div class="card-header">📋 个人资料</div>
    <form method="post" action="user_center.php">
        <input type="hidden" name="action" value="update_profile">
        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">

        <div class="form-group">
            <label class="form-label">手机号</label>
            <input type="text" class="form-input" value="<?php echo h($user['phone']); ?>" disabled>
        </div>
        <div class="form-group">
            <label class="form-label">邮箱</label>
            <input type="email" class="form-input" value="<?php echo h($user['email']); ?>" disabled>
        </div>
        <div class="form-group">
            <label class="form-label" for="nickname">昵称</label>
            <input type="text" id="nickname" name="nickname" class="form-input" value="<?php echo h($user['nickname'] ?? ''); ?>" placeholder="设置一个昵称">
        </div>
        <div class="form-group">
            <label class="form-label">性别</label>
            <div style="display:flex;gap:12px;padding-top:4px;">
                <label style="display:flex;align-items:center;gap:4px;cursor:pointer;">
                    <input type="radio" name="gender" value="male" <?php echo $user['gender'] === 'male' ? 'checked' : ''; ?>> 男
                </label>
                <label style="display:flex;align-items:center;gap:4px;cursor:pointer;">
                    <input type="radio" name="gender" value="female" <?php echo $user['gender'] === 'female' ? 'checked' : ''; ?>> 女
                </label>
                <label style="display:flex;align-items:center;gap:4px;cursor:pointer;">
                    <input type="radio" name="gender" value="other" <?php echo $user['gender'] === 'other' || !$user['gender'] ? 'checked' : ''; ?>> 保密
                </label>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label" for="campus">所属校区</label>
            <select id="campus" name="campus" class="form-select">
                <option value="">不选择</option>
                <?php foreach ($campuses as $c): ?>
                <option value="<?php echo h($c); ?>" <?php echo $user['campus'] === $c ? 'selected' : ''; ?>><?php echo h($c); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-block">保存资料</button>
    </form>
</div>

<!-- 修改密码 -->
<div class="card">
    <div class="card-header">🔒 修改密码</div>
    <form method="post" action="user_center.php">
        <input type="hidden" name="action" value="change_password">
        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">

        <div class="form-group">
            <label class="form-label" for="old_password">原密码</label>
            <input type="password" id="old_password" name="old_password" class="form-input" required>
        </div>
        <div class="form-group">
            <label class="form-label" for="new_password">新密码</label>
            <input type="password" id="new_password" name="new_password" class="form-input" minlength="8" required>
            <p class="form-hint">至少8位，包含字母和数字</p>
        </div>
        <div class="form-group">
            <label class="form-label" for="confirm_password">确认新密码</label>
            <input type="password" id="confirm_password" name="confirm_password" class="form-input" minlength="8" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">修改密码</button>
    </form>
</div>

<!-- 通知中心 -->
<div class="card" id="notifications">
    <div class="card-header">🔔 通知中心</div>
    <?php
    $notifications = getNotifications($user['id'], 20);
    if (empty($notifications)): ?>
    <p style="color:var(--gray-400);text-align:center;padding:16px 0;font-size:14px;">暂无通知</p>
    <?php else: ?>
        <?php foreach ($notifications as $n): ?>
        <div class="my-ride-mini" style="<?php echo $n['is_read'] ? '' : 'background:var(--primary-bg);border-radius:6px;padding:10px;margin:4px 0;'; ?>">
            <div class="ride-info">
                <div class="ride-route-text"><?php echo h($n['title']); ?></div>
                <div class="ride-meta-text"><?php echo h($n['body']); ?> · <?php echo timeAgo($n['created_at']); ?></div>
            </div>
            <?php if (!$n['is_read']): ?>
            <a href="?mark_read=<?php echo (int)$n['id']; ?>" style="font-size:12px;color:var(--primary);flex-shrink:0;">已读</a>
            <?php endif; ?>
            <?php if ($n['link']): ?>
            <a href="<?php echo h($n['link']); ?>" style="font-size:12px;color:var(--primary);flex-shrink:0;">查看</a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <form method="post" style="margin-top:8px;">
            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
            <button type="submit" formaction="?mark_all_read=1" class="btn btn-sm btn-outline" style="width:100%;">全部标记已读</button>
        </form>
    <?php endif; ?>
</div>

<?php
// 处理标记已读
if (isset($_GET['mark_read'])) {
    markNotificationRead((int)$_GET['mark_read'], $user['id']);
    header('Location: /user_center.php#notifications');
    exit;
}
if (isset($_GET['mark_all_read'])) {
    markAllNotificationsRead($user['id']);
    header('Location: /user_center.php#notifications');
    exit;
}
?>

<?php include __DIR__ . '/footer.php'; ?>
