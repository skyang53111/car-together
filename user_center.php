<?php
/**
 * 校区拼车平台 - 个人中心（看板）
 *
 * 结构：身份顶卡 → 缺资料提示 → 分段内容（即将出行 / 我的发布 / 消息）
 * 资料与账号设置（基本资料、账号与安全、帮助与反馈、注销）在 profile.php。
 */
require_once __DIR__ . '/config.php';
requireLogin();
setPageTitle('个人中心');

$user = getCurrentUser();

/** JSON 响应（通知 AJAX 用） */
function ucJsonResponse(bool $success, string $message): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => $success, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

/** 距出发还有多久（给「下一次出行」用） */
function ucCountdown(string $rideTime): string {
    $diff = strtotime($rideTime) - time();
    if ($diff <= 0) return '';
    $days = (int)floor($diff / 86400);
    if ($days >= 1) return $days . ' 天后';
    $hours = (int)floor($diff / 3600);
    if ($hours >= 1) return $hours . ' 小时后';
    return max(1, (int)floor($diff / 60)) . ' 分钟后';
}

// ========== 通知操作（AJAX） ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && input('action') === 'read_notif') {
    if (verifyCsrf(input('csrf_token'))) {
        markNotificationRead((int)input('id'), $user['id']);
    }
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && input('action') === 'read_all_notif') {
    if (verifyCsrf(input('csrf_token'))) {
        markAllNotificationsRead($user['id']);
    }
    ucJsonResponse(true, '已全部标记为已读');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && input('action') === 'delete_notif') {
    if (verifyCsrf(input('csrf_token'))) {
        deleteNotification((int)input('id'), $user['id']);
    }
    ucJsonResponse(true, '已删除');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && input('action') === 'delete_read_notif') {
    if (verifyCsrf(input('csrf_token'))) {
        deleteAllReadNotifications($user['id']);
    }
    ucJsonResponse(true, '已清理所有已读通知');
}

// ========== 数据准备 ==========
$displayName = userDisplayName($user);
$regDays = max(1, (int)floor((time() - strtotime($user['created_at'])) / 86400));

// 当前分段（?tab=），默认「即将出行」
$tab = input('tab', 'get');
if (!in_array($tab, ['trips', 'rides', 'msg'], true)) { $tab = 'trips'; }
$notifTypeFilter = input('ntype', 'get');

// 统计
$stmt = $pdo->prepare("SELECT COUNT(*) FROM rides WHERE user_id = ?");
$stmt->execute([$user['id']]);
$myRidesCount = (int)$stmt->fetchColumn();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM rides WHERE user_id = ? AND status = 'active'");
$stmt->execute([$user['id']]);
$myActiveRides = (int)$stmt->fetchColumn();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM rides WHERE user_id = ? AND status = 'completed'");
$stmt->execute([$user['id']]);
$myCompletedRides = (int)$stmt->fetchColumn();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ? AND status = 'confirmed'");
$stmt->execute([$user['id']]);
$myBookingsCount = (int)$stmt->fetchColumn();

// 安全评分
$securityScore = 0;
if ($user['phone'] && $user['phone_verified']) { $securityScore += 30; }
if ($user['email'] && $user['email_verified']) { $securityScore += 30; }
if ($user['nickname']) { $securityScore += 10; }
if ($user['gender'])   { $securityScore += 10; }
if ($user['campus'])   { $securityScore += 10; }
$securityScore += 10;                                   // 统一认证已设置
$securityScore = min(100, $securityScore);
$scoreClass = $securityScore >= 70 ? 'high' : ($securityScore >= 40 ? 'mid' : 'low');

// 缺资料提示：邮箱优先（才是发通知的通道）；手机号影响发布时自动带出联系方式
$missingKeys = [];
$missingWhy  = '';
if (empty($user['email'])) { $missingKeys[] = '邮箱'; }
if (empty($user['phone'])) { $missingKeys[] = '手机号'; }
if (count($missingKeys) === 1) {
    $missingWhy = $missingKeys[0] === '邮箱' ? '会收不到预订、取消等通知' : '发布行程时无法自动带出联系方式';
} elseif (count($missingKeys) === 2) {
    $missingWhy = '邮箱用来收通知，手机号用来在发布时自动带出联系方式';
}

// 即将出行（已确认、行程仍有效，按时间升序）
$stmt = $pdo->prepare(
    "SELECT b.id AS booking_id, r.origin, r.destination, r.ride_time,
            u.nickname AS owner_name, u.email AS owner_email
     FROM bookings b
     JOIN rides r ON b.ride_id = r.id
     JOIN users u ON r.user_id = u.id
     WHERE b.user_id = ? AND b.status = 'confirmed' AND r.status = 'active'
     ORDER BY r.ride_time ASC LIMIT 4"
);
$stmt->execute([$user['id']]);
$upcoming  = $stmt->fetchAll();
$nextTrip  = $upcoming ? $upcoming[0] : null;
$restTrips = $upcoming ? array_slice($upcoming, 1) : [];

// 我的最新发布
$stmt = $pdo->prepare(
    "SELECT r.id, r.origin, r.destination, r.ride_time, r.capacity, r.available_seats, r.status,
            (r.capacity - r.available_seats) AS booked_count
     FROM rides r
     WHERE r.user_id = ?
     ORDER BY r.created_at DESC LIMIT 5"
);
$stmt->execute([$user['id']]);
$myRides = $stmt->fetchAll();

// 通知
$notifications = getNotificationsFiltered($user['id'], $notifTypeFilter, 15);
$unreadCount   = getUnreadNotificationCount($user['id']);

include __DIR__ . '/header.php';
?>

<!-- ===== 身份顶卡 ===== -->
<section class="uc-idcard">
    <div class="top">
        <div class="uc-av">
            <?php if (!empty($user['avatar'])): ?>
                <img src="<?php echo h($user['avatar']); ?>" alt="">
            <?php else: ?>
                <?php echo h(mb_substr($displayName, 0, 1)); ?>
            <?php endif; ?>
        </div>
        <div class="who">
            <div class="nm"><?php echo h($displayName); ?></div>
            <div class="ct">
                <?php if ($user['phone']): ?>
                    <svg class="ico"><use href="#i-phone"/></svg><span><?php echo h($user['phone']); ?></span>
                <?php elseif ($user['email']): ?>
                    <svg class="ico"><use href="#i-mail"/></svg><span><?php echo h($user['email']); ?></span>
                <?php else: ?>
                    <svg class="ico"><use href="#i-mail"/></svg><span>未绑定联系方式</span>
                <?php endif; ?>
            </div>
        </div>
        <a href="/profile.php" class="btn btn-outline" style="height:34px;padding:0 12px;font-size:13px;flex:none;">
            <svg class="ico"><use href="#i-settings"/></svg>资料
        </a>
    </div>

    <div class="bd">
        <?php if ($user['campus']): ?>
        <span class="badge badge-info"><svg class="ico"><use href="#i-pin"/></svg><?php echo h($user['campus']); ?></span>
        <?php endif; ?>
        <span class="badge badge-muted">注册 <?php echo $regDays; ?> 天</span>
        <?php if ($user['phone_verified']): ?>
        <span class="badge badge-ok"><svg class="ico"><use href="#i-check"/></svg>手机</span>
        <?php else: ?>
        <span class="badge badge-warn">未绑手机</span>
        <?php endif; ?>
        <?php if ($user['email'] && $user['email_verified']): ?>
        <span class="badge badge-ok"><svg class="ico"><use href="#i-check"/></svg>邮箱</span>
        <?php endif; ?>
    </div>

    <div class="uc-mstrip">
        <div class="m pri"><div class="v"><?php echo $myActiveRides; ?></div><div class="l">进行中</div></div>
        <div class="m"><div class="v"><?php echo $myRidesCount; ?></div><div class="l">发布</div></div>
        <div class="m"><div class="v"><?php echo $myCompletedRides; ?></div><div class="l">已完成</div></div>
        <div class="m"><div class="v"><?php echo $myBookingsCount; ?></div><div class="l">预订</div></div>
    </div>

    <a class="uc-score <?php echo $scoreClass; ?>" href="/profile.php">
        <svg class="ico"><use href="#i-shield"/></svg>
        <span class="t">账号安全</span>
        <span class="bar"><i style="width:<?php echo $securityScore; ?>%"></i></span>
        <span class="n"><?php echo $securityScore; ?></span>
        <svg class="ico chev"><use href="#i-chev-r"/></svg>
    </a>
</section>

<?php if ($missingKeys): ?>
<!-- ===== 缺资料提示 =====
     本机模式下（AUTH_MODE=local）注册即要求手机号 + 邮箱，正常不会缺；
     真缺了也没有外部认证平台可跳，回资料页看当前绑定情况即可。 -->
<a class="uc-remind" href="<?php echo AUTH_MODE === 'local' ? '/profile.php' : h(CASDOOR_ACCOUNT_URL); ?>"
   <?php echo AUTH_MODE === 'local' ? '' : 'target="_blank" rel="noopener"'; ?>>
    <svg class="ico"><use href="#i-bell"/></svg>
    <div class="tx">还差 <b><?php echo h(implode('、', $missingKeys)); ?></b> 没填，<?php echo h($missingWhy); ?></div>
    <span class="go">去查看 ›</span>
</a>
<?php endif; ?>

<!-- ===== 分段器 ===== -->
<div class="uc-seg" id="ucSeg" role="tablist">
    <button type="button" data-tab="trips" class="<?php echo $tab === 'trips' ? 'is-on' : ''; ?>">
        即将出行<span class="n"><?php echo count($upcoming); ?></span>
    </button>
    <button type="button" data-tab="rides" class="<?php echo $tab === 'rides' ? 'is-on' : ''; ?>">
        我的发布<span class="n"><?php echo $myRidesCount; ?></span>
    </button>
    <button type="button" data-tab="msg" class="<?php echo $tab === 'msg' ? 'is-on' : ''; ?>">
        消息<span class="n<?php echo $unreadCount > 0 ? ' alert' : ''; ?>"><?php echo $unreadCount > 99 ? '99+' : $unreadCount; ?></span>
    </button>
</div>

<!-- ===== 分段 1：即将出行 ===== -->
<div class="uc-panel<?php echo $tab === 'trips' ? ' is-active' : ''; ?>" data-panel="trips">
    <?php if ($nextTrip): ?>
    <div class="uc-trip">
        <span class="lbl">下一次出行</span>
        <div class="rt">
            <div class="route">
                <span><?php echo h($nextTrip['origin']); ?></span>
                <svg class="ico"><use href="#i-arrow-r"/></svg>
                <span><?php echo h($nextTrip['destination']); ?></span>
            </div>
            <?php $cd = ucCountdown($nextTrip['ride_time']); if ($cd !== ''): ?>
            <span class="cd"><?php echo h($cd); ?></span>
            <?php endif; ?>
        </div>
        <div class="meta">
            <span><svg class="ico"><use href="#i-cal"/></svg><?php echo h(date('m-d H:i', strtotime($nextTrip['ride_time']))); ?></span>
            <span><svg class="ico"><use href="#i-user"/></svg>发起人 <?php echo h($nextTrip['owner_name'] ?: explode('@', $nextTrip['owner_email'])[0]); ?></span>
        </div>
        <div class="acts">
            <a class="btn btn-primary" href="/my_bookings.php"><svg class="ico"><use href="#i-phone"/></svg>查看联系方式</a>
        </div>
    </div>
    <?php if ($restTrips): ?>
    <div class="card" style="padding:2px 16px 10px;">
        <?php foreach ($restTrips as $b): ?>
        <div class="list-row">
            <div class="list-row-main">
                <div class="list-row-title"><?php echo h($b['origin']); ?> → <?php echo h($b['destination']); ?></div>
                <div class="list-row-meta">
                    <span><svg class="ico"><use href="#i-clock"/></svg><?php echo h(date('m-d H:i', strtotime($b['ride_time']))); ?></span>
                    <span><svg class="ico"><use href="#i-user"/></svg><?php echo h($b['owner_name'] ?: explode('@', $b['owner_email'])[0]); ?></span>
                </div>
            </div>
            <span class="badge badge-ok">已确认</span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <a class="btn btn-outline btn-block" href="/my_bookings.php" style="margin-top:4px;">查看全部预订</a>
    <?php else: ?>
    <div class="card" style="text-align:center;padding:26px 16px;">
        <svg class="ico" style="width:34px;height:34px;margin:0 auto 10px;color:var(--ink-300);stroke-width:1.6;"><use href="#i-car"/></svg>
        <p style="font-size:13.5px;color:var(--ink-500);margin:0 0 12px;">还没有即将出行的行程</p>
        <a class="btn btn-outline" href="/index.php">去首页找车</a>
    </div>
    <?php endif; ?>
</div>

<!-- ===== 分段 2：我的发布 ===== -->
<div class="uc-panel<?php echo $tab === 'rides' ? ' is-active' : ''; ?>" data-panel="rides">
    <?php if ($myRides): ?>
    <div class="card" style="padding:2px 16px 10px;">
        <?php foreach ($myRides as $mr): ?>
        <div class="list-row">
            <div class="list-row-main">
                <div class="list-row-title"><?php echo h($mr['origin']); ?> → <?php echo h($mr['destination']); ?></div>
                <div class="list-row-meta">
                    <span><svg class="ico"><use href="#i-clock"/></svg><?php echo h(date('m-d H:i', strtotime($mr['ride_time']))); ?></span>
                    <span><svg class="ico"><use href="#i-users"/></svg><?php echo (int)$mr['booked_count']; ?> 人已预订</span>
                </div>
            </div>
            <?php if ($mr['status'] === 'active'): ?>
            <span class="badge <?php echo $mr['available_seats'] > 0 ? 'badge-ok' : 'badge-warn'; ?>">
                <?php echo (int)$mr['available_seats']; ?>/<?php echo (int)$mr['capacity']; ?> 座
            </span>
            <?php else: ?>
            <span class="badge badge-muted"><?php echo $mr['status'] === 'cancelled' ? '已取消' : '已完成'; ?></span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <a class="btn btn-outline btn-block" href="/my_rides.php" style="margin-top:4px;">查看全部发布</a>
    <?php else: ?>
    <div class="card" style="text-align:center;padding:26px 16px;">
        <svg class="ico" style="width:34px;height:34px;margin:0 auto 10px;color:var(--ink-300);stroke-width:1.6;"><use href="#i-car"/></svg>
        <p style="font-size:13.5px;color:var(--ink-500);margin:0 0 12px;">还没有发布过行程</p>
        <a class="btn btn-outline" href="/post_ride.php">去发布行程</a>
    </div>
    <?php endif; ?>
</div>

<!-- ===== 分段 3：消息 ===== -->
<div class="uc-panel<?php echo $tab === 'msg' ? ' is-active' : ''; ?>" data-panel="msg" id="notifications">
    <div class="card">
        <div class="notif-toolbar">
            <div class="notif-filter">
                <a href="/user_center.php?tab=msg" class="<?php echo !$notifTypeFilter ? 'active' : ''; ?>">全部</a>
                <a href="/user_center.php?tab=msg&amp;ntype=booking_success" class="<?php echo $notifTypeFilter === 'booking_success' ? 'active' : ''; ?>">预订</a>
                <a href="/user_center.php?tab=msg&amp;ntype=ride_cancel" class="<?php echo $notifTypeFilter === 'ride_cancel' ? 'active' : ''; ?>">取消</a>
                <a href="/user_center.php?tab=msg&amp;ntype=carpool_full" class="<?php echo $notifTypeFilter === 'carpool_full' ? 'active' : ''; ?>">满员</a>
            </div>
            <div style="margin-left:auto;display:flex;gap:6px;">
                <?php if ($unreadCount > 0): ?>
                <button type="button" class="btn btn-outline" style="height:32px;padding:0 12px;font-size:12.5px;" onclick="ucMarkAllRead()">全部已读</button>
                <?php endif; ?>
                <button type="button" class="btn btn-outline" style="height:32px;padding:0 12px;font-size:12.5px;" onclick="ucDeleteRead()">清理已读</button>
            </div>
        </div>

        <?php if (empty($notifications)): ?>
        <p style="font-size:13.5px;color:var(--ink-500);text-align:center;padding:18px 0;">暂无消息</p>
        <?php else: ?>
        <div id="notifList">
        <?php foreach ($notifications as $n): ?>
            <div class="notif-item<?php echo !$n['is_read'] ? ' is-unread' : ''; ?>" id="notif-<?php echo (int)$n['id']; ?>">
                <a class="notif-body" href="<?php echo h($n['link'] ?: '#'); ?>" onclick="ucReadNotif(<?php echo (int)$n['id']; ?>)">
                    <div class="notif-title" style="font-weight:<?php echo !$n['is_read'] ? '700' : '400'; ?>;"><?php echo h($n['title']); ?></div>
                    <div class="notif-meta">
                        <span><?php echo h(mb_substr($n['body'], 0, 50)); ?><?php echo mb_strlen($n['body']) > 50 ? '…' : ''; ?></span>
                        <span class="time"><?php echo h(timeAgo($n['created_at'])); ?></span>
                    </div>
                </a>
                <button type="button" class="notif-del" onclick="ucDeleteNotif(<?php echo (int)$n['id']; ?>)" aria-label="删除通知">
                    <svg class="ico" style="width:16px;height:16px;"><use href="#i-x"/></svg>
                </button>
            </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
/* 个人中心：分段切换 + 通知操作。
   刻意写成 ES5 并单独放一个 script 块：老手机内核缺少某些 API 时
   只会中断本块，不会连带别处的脚本（2026-10-07 踩过 NodeList.forEach 的坑）。 */
(function () {
    var csrf = <?php echo json_encode(csrfToken()); ?>;

    /* ---- 分段切换 ---- */
    var seg = document.getElementById('ucSeg');
    if (seg) {
        var btns = seg.getElementsByTagName('button');
        var panels = document.querySelectorAll('.uc-panel');
        function show(tab) {
            var i, el, cls;
            for (i = 0; i < btns.length; i++) {
                cls = (' ' + btns[i].className + ' ').replace(/\s+is-on\s+/, ' ');
                btns[i].className = trim(cls) + (btns[i].getAttribute('data-tab') === tab ? ' is-on' : '');
            }
            for (i = 0; i < panels.length; i++) {
                el = panels[i];
                cls = (' ' + el.className + ' ').replace(/\s+is-active\s+/, ' ');
                el.className = trim(cls) + (el.getAttribute('data-panel') === tab ? ' is-active' : '');
            }
        }
        function trim(s) { return s.replace(/^\s+|\s+$/g, ''); }
        for (var k = 0; k < btns.length; k++) {
            (function (btn) {
                btn.onclick = function () { show(btn.getAttribute('data-tab')); };
            })(btns[k]);
        }
    }

    /* ---- 通知操作 ---- */
    function post(action, data) {
        var body = 'action=' + encodeURIComponent(action) + '&csrf_token=' + encodeURIComponent(csrf);
        for (var key in data) {
            if (data.hasOwnProperty(key)) { body += '&' + key + '=' + encodeURIComponent(data[key]); }
        }
        return fetch('user_center.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        });
    }
    window.ucReadNotif = function (id) { post('read_notif', { id: id }); };
    window.ucDeleteNotif = function (id) {
        post('delete_notif', { id: id }).then(function () {
            var el = document.getElementById('notif-' + id);
            if (el && el.parentNode) { el.parentNode.removeChild(el); }
        });
    };
    window.ucMarkAllRead = function () {
        post('read_all_notif', {}).then(function () { location.reload(); });
    };
    window.ucDeleteRead = function () {
        if (!confirm('确定清理所有已读通知？')) return;
        post('delete_read_notif', {}).then(function () { location.reload(); });
    };
})();
</script>

<?php include __DIR__ . '/footer.php'; ?>
