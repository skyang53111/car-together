<?php
/**
 * 校区拼车平台 - 后台管理首页（仪表盘）
 */
require_once __DIR__ . '/../config.php';
requireAdmin();
setPageTitle('后台管理');
$adminActivePage = 'index';

// ========== 操作处理 ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf(input('csrf_token'))) {
        flashMessage('表单已过期，请刷新重试', 'error');
        header('Location: index.php');
        exit;
    }
    $action = input('action');
    if ($action === 'reset_login_limits') {
        $stmt = $pdo->prepare("DELETE FROM rate_limits WHERE action = 'login'");
        $stmt->execute();
        flashMessage('已清除所有登录限制 (共 ' . $stmt->rowCount() . ' 条)', 'success');
        adminLog('reset_limits', 'rate_limit', 0, '清除登录限制');
        header('Location: index.php'); exit;
    } elseif ($action === 'reset_register_limits') {
        $stmt = $pdo->prepare("DELETE FROM rate_limits WHERE action = 'register'");
        $stmt->execute();
        flashMessage('已清除所有注册限制 (共 ' . $stmt->rowCount() . ' 条)', 'success');
        adminLog('reset_limits', 'rate_limit', 0, '清除注册限制');
        header('Location: index.php'); exit;
    } elseif ($action === 'reset_sendcode_limits') {
        $stmt = $pdo->prepare("DELETE FROM rate_limits WHERE action = 'send_code'");
        $stmt->execute();
        flashMessage('已清除所有验证码发送限制 (共 ' . $stmt->rowCount() . ' 条)', 'success');
        adminLog('reset_limits', 'rate_limit', 0, '清除验证码限制');
        header('Location: index.php'); exit;
    } elseif ($action === 'reset_all_limits') {
        $stmt = $pdo->prepare("DELETE FROM rate_limits");
        $stmt->execute();
        flashMessage('已清除所有频率限制 (共 ' . $stmt->rowCount() . ' 条)', 'success');
        adminLog('reset_limits', 'rate_limit', 0, '清除全部限制');
        header('Location: index.php'); exit;
    } elseif ($action === 'clean_expired_codes') {
        $stmt = $pdo->prepare("DELETE FROM verification_codes WHERE (expires_at < NOW() OR used = 1) AND expires_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stmt->execute();
        flashMessage('已清理过期验证码 (共 ' . $stmt->rowCount() . ' 条)', 'success');
        adminLog('clean_codes', 'verification_code', 0, '清理过期验证码');
        header('Location: index.php'); exit;
    }
}

// ========== 基础统计 ==========
$stats = [];
$stats['total_users']    = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$stats['total_rides']    = (int)$pdo->query("SELECT COUNT(*) FROM rides")->fetchColumn();
$stats['active_rides']   = (int)$pdo->query("SELECT COUNT(*) FROM rides WHERE status = 'active' AND available_seats > 0")->fetchColumn();
$stats['total_bookings'] = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed'")->fetchColumn();
$stats['today_rides']    = (int)$pdo->query("SELECT COUNT(*) FROM rides WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$stats['today_users']    = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$stats['total_completed'] = (int)$pdo->query("SELECT COUNT(*) FROM rides WHERE status = 'completed'")->fetchColumn();

// ========== 7天趋势数据 ==========
$trendUsers = [];
$trendRides = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $label = date('m-d', strtotime("-{$i} days"));
    $cnt = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = '{$date}'")->fetchColumn();
    $trendUsers[] = ['date' => $label, 'count' => $cnt];
    $cnt2 = (int)$pdo->query("SELECT COUNT(*) FROM rides WHERE DATE(created_at) = '{$date}'")->fetchColumn();
    $trendRides[] = ['date' => $label, 'count' => $cnt2];
}
// 7天总计（用于显示趋势箭头）
$weekUsers = array_sum(array_column($trendUsers, 'count'));
$weekRides = array_sum(array_column($trendRides, 'count'));
$maxUsers = max(array_column($trendUsers, 'count')) ?: 1;
$maxRides = max(array_column($trendRides, 'count')) ?: 1;

// 7天活跃用户数（有登录或发布行程的用户）
$stats['active_users_7d'] = (int)$pdo->query(
    "SELECT COUNT(DISTINCT u.id) FROM users u
     LEFT JOIN rides r ON r.user_id = u.id AND r.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
     WHERE u.last_login > DATE_SUB(NOW(), INTERVAL 7 DAY) OR r.id IS NOT NULL"
)->fetchColumn();

// 取消率
$totalBookingsAll = (int)$pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$cancelledBookings = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'cancelled'")->fetchColumn();
$cancelRate = $totalBookingsAll > 0 ? round($cancelledBookings / $totalBookingsAll * 100) : 0;

// ========== 待处理事项 ==========
$pending = [];
$pending['expired_rides'] = (int)$pdo->query(
    "SELECT COUNT(*) FROM rides WHERE status='active' AND ride_time < DATE_SUB(NOW(), INTERVAL 2 HOUR)"
)->fetchColumn();
$pending['rate_blocked'] = (int)$pdo->query(
    "SELECT COUNT(*) FROM rate_limits WHERE blocked_until IS NOT NULL AND blocked_until > NOW()"
)->fetchColumn();
$pending['unread_notif'] = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE is_read = 0")->fetchColumn();
$pending['expired_codes'] = (int)$pdo->query(
    "SELECT COUNT(*) FROM verification_codes WHERE expires_at < NOW() AND used = 0"
)->fetchColumn();

// ========== 热门路线 Top 5 ==========
$hotRoutes = $pdo->query(
    "SELECT origin, destination, COUNT(*) AS cnt FROM rides
     WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
     GROUP BY origin, destination ORDER BY cnt DESC LIMIT 5"
)->fetchAll();

// ========== 系统健康 ==========
$health = [];
try {
    $dbSize = $pdo->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
    $health['db_size'] = $dbSize ? $dbSize . ' MB' : '未知';
} catch (Exception $e) { $health['db_size'] = '未知'; }
$health['smtp_configured'] = defined('MAIL_USERNAME') && !empty(MAIL_USERNAME) && defined('MAIL_PASSWORD') && !empty(MAIL_PASSWORD);
$health['total_notif'] = (int)$pdo->query("SELECT COUNT(*) FROM notifications")->fetchColumn();
$health['total_codes'] = (int)$pdo->query("SELECT COUNT(*) FROM verification_codes")->fetchColumn();

// ========== 频率限制统计 ==========
$limitStats = [];
$limitStats['login_blocked']    = (int)$pdo->query("SELECT COUNT(*) FROM rate_limits WHERE action = 'login' AND blocked_until > NOW()")->fetchColumn();
$limitStats['register_blocked'] = (int)$pdo->query("SELECT COUNT(*) FROM rate_limits WHERE action = 'register' AND blocked_until > NOW()")->fetchColumn();
$limitStats['sendcode_blocked'] = (int)$pdo->query("SELECT COUNT(*) FROM rate_limits WHERE action = 'send_code' AND blocked_until > NOW()")->fetchColumn();
$limitStats['total_records']    = (int)$pdo->query("SELECT COUNT(*) FROM rate_limits")->fetchColumn();

// ========== 最近行程 ==========
$recentRides = $pdo->query(
    "SELECT r.*, u.phone, u.email FROM rides r JOIN users u ON r.user_id = u.id ORDER BY r.created_at DESC LIMIT 10"
)->fetchAll();

include __DIR__ . '/header.php';
?>

<h1 style="margin-bottom:24px;">仪表盘</h1>

<!-- ===== 待处理事项 ===== -->
<?php if (array_sum($pending) > 0): ?>
<div style="margin-bottom:24px;">
    <h3 style="font-size:15px;font-weight:600;color:var(--gray-600);margin-bottom:12px;">⏰ 待处理事项</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;">
        <?php if ($pending['expired_rides'] > 0): ?>
        <div class="todo-card" style="border-left-color:var(--danger);">
            <span class="todo-icon">🚗</span>
            <div class="todo-body">
                <div class="todo-title">过期未关闭行程</div>
                <div class="todo-desc">出发超过2小时仍为active</div>
            </div>
            <span class="todo-count" style="color:var(--danger);"><?php echo $pending['expired_rides']; ?></span>
        </div>
        <?php endif; ?>
        <?php if ($pending['rate_blocked'] > 0): ?>
        <div class="todo-card">
            <span class="todo-icon">🛡️</span>
            <div class="todo-body">
                <div class="todo-title">频率限制被阻IP</div>
                <div class="todo-desc">登录/注册/验证码</div>
            </div>
            <span class="todo-count"><?php echo $pending['rate_blocked']; ?></span>
        </div>
        <?php endif; ?>
        <?php if ($pending['expired_codes'] > 50): ?>
        <div class="todo-card" style="border-left-color:var(--gray-400);">
            <span class="todo-icon">📝</span>
            <div class="todo-body">
                <div class="todo-title">过期验证码堆积</div>
                <div class="todo-desc">建议定期清理</div>
            </div>
            <span class="todo-count" style="color:var(--gray-500);"><?php echo $pending['expired_codes']; ?></span>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- ===== 核心统计 ===== -->
<div class="stats-grid">
    <div class="stat-card stat-card-blue">
        <div class="stat-icon">👥</div>
        <div class="stat-value"><?php echo $stats['total_users']; ?></div>
        <div class="stat-label">用户总数</div>
        <div class="stat-trend">7天新增 <span class="up">+<?php echo $weekUsers; ?></span></div>
    </div>
    <div class="stat-card stat-card-green">
        <div class="stat-icon">🚗</div>
        <div class="stat-value"><?php echo $stats['active_rides']; ?></div>
        <div class="stat-label">有效行程</div>
        <div class="stat-trend">今日新增 <span class="up">+<?php echo $stats['today_rides']; ?></span></div>
    </div>
    <div class="stat-card stat-card-orange">
        <div class="stat-icon">📌</div>
        <div class="stat-value"><?php echo $stats['total_bookings']; ?></div>
        <div class="stat-label">预订记录</div>
        <div class="stat-trend">取消率 <?php echo $cancelRate; ?>%</div>
    </div>
    <div class="stat-card stat-card-blue">
        <div class="stat-icon">📊</div>
        <div class="stat-value"><?php echo $stats['total_rides']; ?></div>
        <div class="stat-label">累计行程</div>
        <div class="stat-trend">已完成 <?php echo $stats['total_completed']; ?></div>
    </div>
    <div class="stat-card stat-card-green">
        <div class="stat-icon">🔥</div>
        <div class="stat-value"><?php echo $stats['active_users_7d']; ?></div>
        <div class="stat-label">7天活跃用户</div>
        <div class="stat-trend">今日新注册 <span class="up">+<?php echo $stats['today_users']; ?></span></div>
    </div>
    <div class="stat-card stat-card-red">
        <div class="stat-icon">🛡️</div>
        <div class="stat-value"><?php echo $limitStats['total_records']; ?></div>
        <div class="stat-label">频率限制记录</div>
        <div class="stat-trend">当前被阻 <?php echo $limitStats['login_blocked'] + $limitStats['register_blocked'] + $limitStats['sendcode_blocked']; ?> 个</div>
    </div>
</div>

<!-- ===== 7天趋势图表 ===== -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">
    <div class="trend-chart-wrap">
        <h3>📈 近7天新增用户（共 <?php echo $weekUsers; ?> 人）</h3>
        <div class="css-bar-chart">
            <?php foreach ($trendUsers as $t): $h = max(8, ($t['count'] / $maxUsers) * 100); ?>
            <div class="bar-col">
                <div class="bar" style="height:<?php echo $h; ?>%;">
                    <?php if ($t['count'] > 0): ?><span class="bar-val"><?php echo $t['count']; ?></span><?php endif; ?>
                </div>
                <span class="bar-label"><?php echo $t['date']; ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="trend-chart-wrap">
        <h3>📈 近7天新增行程（共 <?php echo $weekRides; ?> 条）</h3>
        <div class="css-bar-chart">
            <?php foreach ($trendRides as $t): $h = max(8, ($t['count'] / $maxRides) * 100); ?>
            <div class="bar-col">
                <div class="bar" style="height:<?php echo $h; ?>%;background:var(--success);">
                    <?php if ($t['count'] > 0): ?><span class="bar-val"><?php echo $t['count']; ?></span><?php endif; ?>
                </div>
                <span class="bar-label"><?php echo $t['date']; ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ===== 热门路线 + 系统健康 ===== -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">
    <!-- 热门路线 Top 5 -->
    <div class="card">
        <h3 style="font-size:16px;margin-bottom:12px;">🏆 近30天热门路线</h3>
        <?php if (empty($hotRoutes)): ?>
        <p style="font-size:13px;color:var(--gray-400);text-align:center;padding:16px 0;">暂无数据</p>
        <?php else: ?>
        <?php foreach ($hotRoutes as $idx => $hr): ?>
        <div class="hot-route-item">
            <span class="hot-route-rank <?php echo $idx < 3 ? 'r' . ($idx + 1) : 'rn'; ?>"><?php echo $idx + 1; ?></span>
            <span class="hot-route-name"><?php echo h($hr['origin']); ?> → <?php echo h($hr['destination']); ?></span>
            <span class="hot-route-count"><?php echo $hr['cnt']; ?> 次</span>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- 系统健康 -->
    <div class="card">
        <h3 style="font-size:16px;margin-bottom:12px;">💊 系统健康</h3>
        <div class="health-grid">
            <div class="health-item">
                <div class="health-label">数据库大小</div>
                <div class="health-value <?php echo (float)$health['db_size'] > 100 ? 'warn' : 'ok'; ?>"><?php echo $health['db_size']; ?></div>
            </div>
            <div class="health-item">
                <div class="health-label">SMTP 邮件</div>
                <div class="health-value <?php echo $health['smtp_configured'] ? 'ok' : 'err'; ?>"><?php echo $health['smtp_configured'] ? '✅ 已配置' : '❌ 未配置'; ?></div>
            </div>
            <div class="health-item">
                <div class="health-label">通知总量</div>
                <div class="health-value"><?php echo $health['total_notif']; ?></div>
            </div>
            <div class="health-item">
                <div class="health-label">验证码残留</div>
                <div class="health-value <?php echo $health['total_codes'] > 1000 ? 'warn' : 'ok'; ?>"><?php echo $health['total_codes']; ?></div>
            </div>
        </div>
        <?php if ($pending['expired_codes'] > 50): ?>
        <form method="post" style="margin-top:12px;">
            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
            <button type="submit" name="action" value="clean_expired_codes" class="btn btn-sm btn-outline" onclick="return confirm('确定清理7天前的过期验证码？')">🧹 清理过期验证码</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<!-- ===== 频率限制管理 ===== -->
<div class="card" style="margin-bottom:20px;">
    <h3 style="font-size:16px;margin-bottom:12px;">🛡️ 频率限制管理</h3>
    <p style="font-size:13px;color:var(--gray-500);margin-bottom:12px;">
        当前限制状态：登录被阻 <?php echo $limitStats['login_blocked']; ?> 个 /
        注册被阻 <?php echo $limitStats['register_blocked']; ?> 个 /
        验证码被阻 <?php echo $limitStats['sendcode_blocked']; ?> 个
        （共 <?php echo $limitStats['total_records']; ?> 条记录）
    </p>

    <?php if ($limitStats['total_records'] > 0): ?>
    <div class="admin-table-actions" style="margin-bottom:12px;">
        <form method="post" style="display:inline;"><input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
            <button type="submit" name="action" value="reset_login_limits" class="btn btn-sm btn-outline" onclick="return confirm('确定清除所有登录频率限制？')">清除登录限制</button>
        </form>
        <form method="post" style="display:inline;"><input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
            <button type="submit" name="action" value="reset_register_limits" class="btn btn-sm btn-outline" onclick="return confirm('确定清除所有注册频率限制？')">清除注册限制</button>
        </form>
        <form method="post" style="display:inline;"><input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
            <button type="submit" name="action" value="reset_sendcode_limits" class="btn btn-sm btn-outline" onclick="return confirm('确定清除所有验证码发送限制？')">清除验证码限制</button>
        </form>
        <form method="post" style="display:inline;"><input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
            <button type="submit" name="action" value="reset_all_limits" class="btn btn-sm btn-outline-danger" onclick="return confirm('确定清除所有频率限制记录？')">清除全部</button>
        </form>
    </div>

    <?php
    $blockedRecords = $pdo->query(
        "SELECT ip_address, action, blocked_until, attempts FROM rate_limits
         WHERE blocked_until IS NOT NULL AND blocked_until > NOW()
         ORDER BY blocked_until ASC LIMIT 20"
    )->fetchAll();
    if (!empty($blockedRecords)):
    ?>
    <div class="table-container">
        <table>
            <thead><tr><th>IP地址</th><th>操作</th><th>尝试次数</th><th>解除时间</th></tr></thead>
            <tbody>
                <?php foreach ($blockedRecords as $r): ?>
                <tr>
                    <td><?php echo h($r['ip_address']); ?></td>
                    <td><?php
                        $actionLabels = ['login'=>'登录','register'=>'注册','send_code'=>'发送验证码'];
                        echo $actionLabels[$r['action']] ?? $r['action'];
                    ?></td>
                    <td><?php echo $r['attempts']; ?></td>
                    <td><?php echo date('m-d H:i:s', strtotime($r['blocked_until'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?php else: ?>
    <p style="font-size:13px;color:var(--gray-400);">暂无频率限制记录</p>
    <?php endif; ?>
</div>

<!-- ===== 最近行程信息 ===== -->
<div class="card">
    <h3 style="font-size:16px;margin-bottom:12px;">🚗 最近行程信息</h3>
    <div class="table-container">
        <table>
            <thead>
                <tr><th>ID</th><th>出发地</th><th>目的地</th><th>时间</th><th>发布者</th><th>座位</th><th>状态</th></tr>
            </thead>
            <tbody>
                <?php foreach ($recentRides as $r): ?>
                <tr>
                    <td><?php echo $r['id']; ?></td>
                    <td><?php echo h($r['origin']); ?></td>
                    <td><?php echo h($r['destination']); ?></td>
                    <td><?php echo h(date('m-d H:i', strtotime($r['ride_time']))); ?></td>
                    <td><?php echo h($r['phone'] ?: $r['email']); ?></td>
                    <td><?php echo $r['available_seats']; ?>/<?php echo $r['capacity']; ?></td>
                    <td><span class="tag <?php echo $r['status'] === 'active' ? 'tag-success' : ''; ?>"><?php echo h($r['status']); ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ===== 最近操作日志 ===== -->
<?php
$recentLogs = getAdminLogs(15);
if (!empty($recentLogs)):
?>
<div class="card" style="margin-top:20px;">
    <h3 style="font-size:16px;margin-bottom:12px;">📝 最近操作日志</h3>
    <div class="table-container">
        <table>
            <thead><tr><th>时间</th><th>管理员</th><th>操作</th><th>对象</th><th>详情</th><th>IP</th></tr></thead>
            <tbody>
                <?php foreach ($recentLogs as $log): ?>
                <tr>
                    <td style="white-space:nowrap;"><?php echo date('m-d H:i', strtotime($log['created_at'])); ?></td>
                    <td><?php echo h($log['nickname'] ?: 'ID:' . $log['admin_id']); ?></td>
                    <td><span class="tag"><?php echo h($log['action']); ?></span></td>
                    <td><?php echo $log['target_type'] ? h($log['target_type'] . '#' . $log['target_id']) : '-'; ?></td>
                    <td><?php echo h($log['detail'] ?: '-'); ?></td>
                    <td style="font-size:12px;color:var(--gray-400);"><?php echo h($log['ip_address']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
