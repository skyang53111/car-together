<?php
/**
 * 校区拼车平台 - 首页
 * 展示行程信息列表，支持搜索和筛选
 */
require_once __DIR__ . '/config.php';
setPageTitle('首页');

// 获取筛选参数
$search = input('search', 'get');
$filter_campus = input('campus', 'get');
$filter_date = input('date', 'get');
$filter_today = input('today', 'get'); // 快捷筛选：仅看今天
$page = max(1, (int)input('page', 'get', '1'));

// 构建查询
$where = ["r.status = 'active'", "r.available_seats > 0"];
$params = [];

if ($search) {
    $where[] = "(r.destination LIKE ? OR r.origin LIKE ? OR r.notes LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
if ($filter_campus) {
    $where[] = "(r.origin LIKE ? OR r.destination LIKE ?)";
    $params[] = "%{$filter_campus}%";
    $params[] = "%{$filter_campus}%";
}
if ($filter_date) {
    $where[] = "DATE(r.ride_time) = ?";
    $params[] = $filter_date;
}
if ($filter_today) {
    $where[] = "DATE(r.ride_time) = CURDATE()";
}

$whereClause = implode(' AND ', $where);

// 计算总数
$countSql = "SELECT COUNT(*) FROM rides r WHERE {$whereClause}";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$total = $stmt->fetchColumn();
$totalPages = ceil($total / RIDES_PER_PAGE);

// 查询行程列表
$offset = ($page - 1) * RIDES_PER_PAGE;
$sql = "SELECT r.*, u.nickname, u.phone, u.email
        FROM rides r
        JOIN users u ON r.user_id = u.id
        WHERE {$whereClause}
        ORDER BY r.ride_time ASC
        LIMIT " . RIDES_PER_PAGE . " OFFSET {$offset}";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rides = $stmt->fetchAll();

// 获取当前用户已预订的 ride_id 列表（用于判断是否可看联系方式）
$bookedRideIds = [];
if (isLoggedIn()) {
    $bs = $pdo->prepare("SELECT ride_id FROM bookings WHERE user_id = ? AND status = 'confirmed'");
    $bs->execute([$_SESSION['user_id']]);
    $bookedRideIds = $bs->fetchAll(PDO::FETCH_COLUMN);
}

// ---- Hero 概况：三个对用户有决策价值的数字 ----
// 今日出发
$todayStmt = $pdo->prepare("SELECT COUNT(*) FROM rides WHERE status = 'active' AND available_seats > 0 AND DATE(ride_time) = CURDATE()");
$todayStmt->execute();
$todayCount = (int)$todayStmt->fetchColumn();

// 还有空座（可预订座位总数）
$seatStmt = $pdo->prepare("SELECT COALESCE(SUM(available_seats), 0) FROM rides WHERE status = 'active' AND available_seats > 0");
$seatStmt->execute();
$seatCount = (int)$seatStmt->fetchColumn();

// 最早一班
$nextStmt = $pdo->prepare("SELECT MIN(ride_time) FROM rides WHERE status = 'active' AND available_seats > 0 AND ride_time >= NOW()");
$nextStmt->execute();
$nextRide = $nextStmt->fetchColumn();
$nextTime = $nextRide ? date('H:i', strtotime($nextRide)) : '—';

$campuses = getCampuses();

// 构建筛选链接（保留当前其他筛选参数）
function filterQuery(array $overrides): string {
    $params = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === null || $v === '') {
            unset($params[$k]);
        } else {
            $params[$k] = $v;
        }
    }
    unset($params['page']);
    $q = http_build_query($params);
    return 'index.php' . ($q ? '?' . $q : '');
}

$hasFilter = ($search || $filter_campus || $filter_date || $filter_today);
?>
<?php include __DIR__ . '/header.php'; ?>

<!-- ===== Hero：今日概况 ===== -->
<section class="hero glass">
    <div class="hero-inner">
        <div class="hero-text">
            <h1 class="hero-title">让通勤更简单</h1>
            <p class="hero-sub">覆盖<?php echo h(implode('、', $campuses)); ?></p>
        </div>
        <div class="hero-stats">
            <div class="stat">
                <span class="stat-num"><?php echo $todayCount; ?></span>
                <span class="stat-lbl">今日出发</span>
            </div>
            <div class="stat">
                <span class="stat-num"><?php echo $seatCount; ?></span>
                <span class="stat-lbl">还有空座</span>
            </div>
            <div class="stat">
                <span class="stat-num"><?php echo h($nextTime); ?></span>
                <span class="stat-lbl">最早一班</span>
            </div>
        </div>
    </div>
</section>

<div class="sentinel" id="searchSentinel"></div>

<!-- ===== 搜索与筛选（滑动后吸附在顶部） ===== -->
<section class="search-sticky" id="searchSticky">
    <form action="index.php" method="get" class="search-bar glass">
        <input type="text" name="search" class="search-input"
               placeholder="搜索目的地、出发地..."
               value="<?php echo h($search); ?>">
        <?php if ($filter_campus): ?><input type="hidden" name="campus" value="<?php echo h($filter_campus); ?>"><?php endif; ?>
        <?php if ($filter_date): ?><input type="hidden" name="date" value="<?php echo h($filter_date); ?>"><?php endif; ?>
        <?php if ($filter_today): ?><input type="hidden" name="today" value="1"><?php endif; ?>
        <button type="submit" class="btn btn-primary" aria-label="搜索">
            <svg class="ico"><use href="#i-search"/></svg>
        </button>
    </form>

    <div class="chip-row">
        <label class="chip<?php echo $filter_campus ? ' is-active' : ''; ?>">
            <svg class="ico"><use href="#i-pin"/></svg>
            <select name="campus" aria-label="选择校区"
                    onchange="location.href='<?php echo filterQuery(['campus' => '__VAL__']); ?>'.replace('__VAL__', encodeURIComponent(this.value))">
                <option value="">全部校区</option>
                <?php foreach ($campuses as $c): ?>
                <option value="<?php echo h($c); ?>" <?php echo $filter_campus === $c ? 'selected' : ''; ?>><?php echo h($c); ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="chip<?php echo $filter_date ? ' is-active' : ''; ?>">
            <svg class="ico"><use href="#i-cal"/></svg>
            <input type="date" aria-label="选择日期" value="<?php echo h($filter_date); ?>"
                   onchange="location.href='<?php echo filterQuery(['date' => '__VAL__']); ?>'.replace('__VAL__', this.value)">
        </label>

        <a href="<?php echo $filter_today ? filterQuery(['today' => null]) : filterQuery(['today' => '1']); ?>"
           class="chip<?php echo $filter_today ? ' is-active' : ''; ?>">
            <svg class="ico"><use href="#i-check"/></svg>只看今天
        </a>

        <?php if ($hasFilter): ?>
        <a href="/index.php" class="chip">
            <svg class="ico"><use href="#i-x"/></svg>重置
        </a>
        <?php endif; ?>
    </div>
</section>

<!-- ===== 行程列表 ===== -->
<div class="ride-list">
    <?php if (empty($rides)): ?>
    <div class="empty-state">
        <svg class="ico"><use href="#i-car"/></svg>
        <p>暂无行程信息</p>
        <p style="font-size:13px;">成为第一个发布行程的人</p>
        <a href="/post_ride.php" class="btn btn-primary">发布行程</a>
    </div>
    <?php else: ?>
        <?php foreach ($rides as $ride): ?>
        <?php
            $publisher = ['nickname' => $ride['nickname'], 'phone' => $ride['phone'], 'email' => $ride['email']];
            $publisherName = userDisplayName($publisher);
            $isOwner = isLoggedIn() && $ride['user_id'] == $_SESSION['user_id'];
            $hasBooked = isLoggedIn() && in_array($ride['id'], $bookedRideIds);
            $canSeeContact = $isOwner || $hasBooked;
            $seatsLeft = (int)$ride['available_seats'];
        ?>
        <article class="ride-card">
            <div class="ride-card-top">
                <div class="ride-route">
                    <span class="from"><?php echo h($ride['origin']); ?></span>
                    <svg class="ico"><use href="#i-arrow-r"/></svg>
                    <span class="to"><?php echo h($ride['destination']); ?></span>
                </div>
                <span class="ride-badge <?php echo $seatsLeft <= 0 ? 'ride-badge-full' : 'ride-badge-available'; ?>">
                    <?php if ($seatsLeft <= 0): ?>
                        已满员
                    <?php else: ?>
                        余 <?php echo $seatsLeft; ?>/<?php echo (int)$ride['capacity']; ?> 座
                    <?php endif; ?>
                </span>
            </div>

            <div class="ride-card-meta">
                <span class="meta-item">
                    <svg class="ico"><use href="#i-clock"/></svg><?php echo h(date('m-d H:i', strtotime($ride['ride_time']))); ?>
                </span>
                <span class="meta-item">
                    <svg class="ico"><use href="#i-user"/></svg><?php echo h($publisherName); ?>
                </span>
                <?php if ($canSeeContact): ?>
                <span class="meta-item">
                    <svg class="ico"><use href="#i-phone"/></svg><?php echo h($ride['contact']); ?>
                </span>
                <?php else: ?>
                <span class="meta-item is-locked">
                    <svg class="ico"><use href="#i-lock"/></svg>预订后可查看联系方式
                </span>
                <?php endif; ?>
                <?php if ($ride['notes']): ?>
                <span class="meta-note"><?php echo h($ride['notes']); ?></span>
                <?php endif; ?>
            </div>

            <div class="ride-card-actions">
                <?php if (!isLoggedIn()): ?>
                    <a href="/login.php?redirect=<?php echo urlencode('/index.php'); ?>" class="btn btn-primary btn-block">登录后预订</a>
                <?php elseif ($isOwner): ?>
                    <span class="owner-chip">自己发布的</span>
                <?php elseif ($hasBooked): ?>
                    <span class="owner-chip is-ok"><svg class="ico"><use href="#i-check"/></svg>已预订</span>
                <?php elseif ($seatsLeft <= 0): ?>
                    <span class="owner-chip">已满员，看看其他行程</span>
                <?php else: ?>
                    <!-- 无 JS 时该表单直接提交到 book_ride.php（与改造前行为一致）。
                         book=1 必须用隐藏字段而不是按钮的 name：底部面板走的是原生 form.submit()，
                         原生提交不会带上提交按钮的 name/value。 -->
                    <form class="book-form" method="post" action="book_ride.php"
                          data-route="<?php echo h($ride['origin'] . ' → ' . $ride['destination']); ?>"
                          data-time="<?php echo h(date('m-d H:i', strtotime($ride['ride_time']))); ?>"
                          data-owner="<?php echo h($publisherName); ?>"
                          data-seats="余 <?php echo $seatsLeft; ?>/<?php echo (int)$ride['capacity']; ?> 座">
                        <input type="hidden" name="book" value="1">
                        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                        <input type="hidden" name="ride_id" value="<?php echo (int)$ride['id']; ?>">
                        <button type="submit" class="btn btn-primary btn-block">立即预订</button>
                    </form>
                <?php endif; ?>
            </div>
        </article>
        <?php endforeach; ?>

        <!-- 分页 -->
        <?php if ($totalPages > 1): ?>
        <nav class="pagination" aria-label="分页">
            <?php
            $queryParams = $_GET;
            unset($queryParams['page']);
            $baseQuery = http_build_query($queryParams);
            $baseUrl = 'index.php' . ($baseQuery ? '?' . $baseQuery . '&' : '?');

            $prev = $page - 1;
            $next = $page + 1;

            if ($page > 1): ?>
                <a href="<?php echo $baseUrl; ?>page=<?php echo $prev; ?>" aria-label="上一页"><svg class="ico"><use href="#i-chev-l"/></svg></a>
            <?php else: ?>
                <span class="disabled"><svg class="ico"><use href="#i-chev-l"/></svg></span>
            <?php endif;

            $start = max(1, $page - 2);
            $end = min($totalPages, $page + 2);
            if ($start > 1): ?>
                <a href="<?php echo $baseUrl; ?>page=1">1</a>
                <?php if ($start > 2): ?><span class="dots">…</span><?php endif; ?>
            <?php endif;

            for ($i = $start; $i <= $end; $i++): ?>
                <a href="<?php echo $baseUrl; ?>page=<?php echo $i; ?>" class="<?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor;

            if ($end < $totalPages): ?>
                <?php if ($end < $totalPages - 1): ?><span class="dots">…</span><?php endif; ?>
                <a href="<?php echo $baseUrl; ?>page=<?php echo $totalPages; ?>"><?php echo $totalPages; ?></a>
            <?php endif;

            if ($page < $totalPages): ?>
                <a href="<?php echo $baseUrl; ?>page=<?php echo $next; ?>" aria-label="下一页"><svg class="ico"><use href="#i-chev-r"/></svg></a>
            <?php else: ?>
                <span class="disabled"><svg class="ico"><use href="#i-chev-r"/></svg></span>
            <?php endif; ?>
        </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<div class="page-end"></div>

<!-- ===== 预订确认面板（移动端从底部滑出） ===== -->
<div class="sheet-backdrop" id="bookBackdrop"></div>
<div class="sheet" id="bookSheet" role="dialog" aria-modal="true" aria-labelledby="sheetTitle">
    <div class="sheet-handle"></div>
    <button type="button" class="sheet-close" id="sheetClose" aria-label="关闭">
        <svg class="ico"><use href="#i-x"/></svg>
    </button>
    <h2 class="sheet-title" id="sheetTitle">确认预订</h2>
    <dl>
        <div class="sheet-row"><dt>路线</dt><dd id="sRoute"></dd></div>
        <div class="sheet-row"><dt>出发时间</dt><dd id="sTime"></dd></div>
        <div class="sheet-row"><dt>发布者</dt><dd id="sOwner"></dd></div>
        <div class="sheet-row"><dt>座位</dt><dd id="sSeats"></dd></div>
    </dl>
    <div class="sheet-actions">
        <button type="button" class="btn btn-ghost" id="sheetCancel">再想想</button>
        <button type="button" class="btn btn-primary btn-block" id="sheetConfirm">确认预订</button>
    </div>
</div>

<?php
// ===== 首页公告弹窗 =====
// getAnnouncement() 已按「弹窗频率」判断过本次该不该弹（靠 cookie 令牌），null 表示不弹。
$ann = getAnnouncement();
if ($ann):
?>
<div class="ann-backdrop" id="annBackdrop"></div>
<div class="ann-modal" id="annModal" role="dialog" aria-modal="true" aria-labelledby="annTitle"
     data-ann-token="<?php echo h($ann['token']); ?>"
     data-ann-maxage="<?php echo (int)$ann['maxage']; ?>">
    <button type="button" class="ann-close" id="annClose" aria-label="关闭公告">
        <svg class="ico"><use href="#i-x"/></svg>
    </button>
    <?php if ($ann['title'] !== ''): ?>
    <h2 class="ann-title" id="annTitle"><?php echo h($ann['title']); ?></h2>
    <?php else: ?>
    <h2 class="ann-title sr" id="annTitle">公告</h2>
    <?php endif; ?>
    <div class="ann-body"><?php echo $ann['body_html']; ?></div>
    <div class="ann-foot">
        <button type="button" class="btn btn-primary btn-block" id="annOk">我知道了</button>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
