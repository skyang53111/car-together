<?php
/**
 * 校区拼车平台 - 首页
 */
require_once __DIR__ . '/config.php';
setPageTitle('首页');

$search = input('search', 'get');
$filter_campus = input('campus', 'get');
$filter_date = input('date', 'get');
$filter_today = input('today', 'get');
$page = max(1, (int)input('page', 'get', '1'));

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

$countSql = "SELECT COUNT(*) FROM rides r WHERE {$whereClause}";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$total = $stmt->fetchColumn();
$totalPages = ceil($total / RIDES_PER_PAGE);

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

$bookedRideIds = [];
if (isLoggedIn()) {
    $bs = $pdo->prepare("SELECT ride_id FROM bookings WHERE user_id = ? AND status = 'confirmed'");
    $bs->execute([$_SESSION['user_id']]);
    $bookedRideIds = $bs->fetchAll(PDO::FETCH_COLUMN);
}

$todayStmt = $pdo->prepare("SELECT COUNT(*) FROM rides WHERE status = 'active' AND available_seats > 0 AND DATE(ride_time) = CURDATE()");
$todayStmt->execute();
$todayCount = $todayStmt->fetchColumn();

$campuses = getCampuses();

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
?>
<?php include __DIR__ . '/header.php'; ?>

<div class="card" style="background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%); color: #fff; text-align: center; padding: 24px 16px; margin-bottom: 16px;">
    <h2 style="font-size: 20px; margin-bottom: 4px;">让通勤更简单</h2>
    <p style="opacity: .9; font-size: 14px; margin-bottom: 8px;">覆盖南校区 · 北校区 · 月亮岛校区</p>
    <div style="display:inline-flex;gap:16px;font-size:13px;opacity:.8;">
        <span>🚗 今日 <?php echo (int)$todayCount; ?> 趟拼车</span>
        <span>📌 <?php echo (int)$total; ?> 个有效行程</span>
    </div>
</div>

<section class="search-section">
    <form action="index.php" method="get" class="search-bar">
        <input type="text" name="search" class="search-input"
               placeholder="搜索目的地、出发地..."
               value="<?php echo h($search); ?>">
        <?php if ($filter_campus): ?><input type="hidden" name="campus" value="<?php echo h($filter_campus); ?>"><?php endif; ?>
        <?php if ($filter_date): ?><input type="hidden" name="date" value="<?php echo h($filter_date); ?>"><?php endif; ?>
        <?php if ($filter_today): ?><input type="hidden" name="today" value="1"><?php endif; ?>
        <button type="submit" class="btn btn-primary">搜索</button>
    </form>
    <div class="filter-group" style="margin-top:8px;">
        <select name="campus" class="form-select" style="flex:1;min-width:110px;" onchange="location.href='<?php echo filterQuery(['campus' => '__VAL__']); ?>'.replace('__VAL__', this.value)">
            <option value="">全部校区</option>
            <?php foreach ($campuses as $c): ?>
            <option value="<?php echo h($c); ?>" <?php echo $filter_campus === $c ? 'selected' : ''; ?>><?php echo h($c); ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" id="dateFilter" class="form-input" style="flex:1;min-width:110px;"
               value="<?php echo h($filter_date); ?>"
               onchange="location.href='<?php echo filterQuery(['date' => '__VAL__']); ?>'.replace('__VAL__', this.value)">
        <a href="<?php echo $filter_today ? filterQuery(['today' => null]) : filterQuery(['today' => '1']); ?>"
           class="btn btn-sm <?php echo $filter_today ? 'btn-primary' : 'btn-outline'; ?>" style="flex-shrink:0;">
            <?php echo $filter_today ? '✓ 只看今天' : '今天出发'; ?>
        </a>
        <?php if ($search || $filter_campus || $filter_date || $filter_today): ?>
        <a href="/index.php" class="btn btn-sm btn-outline-danger" style="flex-shrink:0;">重置</a>
        <?php endif; ?>
    </div>
</section>

<div class="ride-list">
    <?php if (empty($rides)): ?>
    <div class="empty-state">
        <div class="empty-icon">🚗</div>
        <p>暂无拼车信息</p>
        <p style="font-size:13px;">成为第一个发布拼车的人！</p>
        <a href="/post_ride.php" class="btn btn-primary" style="margin-top:12px;">发布拼车</a>
    </div>
    <?php else: ?>
        <?php foreach ($rides as $ride): ?>
        <?php
            $publisher = ['nickname' => $ride['nickname'], 'phone' => $ride['phone'], 'email' => $ride['email']];
            $publisherName = userDisplayName($publisher);
            $isOwner = isLoggedIn() && $ride['user_id'] == $_SESSION['user_id'];
            $hasBooked = isLoggedIn() && in_array($ride['id'], $bookedRideIds);
            $canSeeContact = $isOwner || $hasBooked;
        ?>
        <div class="ride-card">
            <div class="ride-card-top">
                <div class="ride-route">
                    <span class="from"><?php echo h($ride['origin']); ?></span>
                    <span class="arrow">→</span>
                    <span class="to"><?php echo h($ride['destination']); ?></span>
                </div>
                <span class="ride-badge <?php echo $ride['available_seats'] <= 0 ? 'ride-badge-full' : 'ride-badge-available'; ?>">
                    <?php if ($ride['available_seats'] <= 0): ?>
                        已满员
                    <?php else: ?>
                        余<?php echo (int)$ride['available_seats']; ?>/<?php echo (int)$ride['capacity']; ?>座
                    <?php endif; ?>
                </span>
            </div>
            <div class="ride-card-meta">
                <span>🕐 <?php echo h(date('Y-m-d H:i', strtotime($ride['ride_time']))); ?></span>
                <span>👤 <?php echo h($publisherName); ?></span>
                <?php if ($canSeeContact): ?>
                <span>📞 <?php echo h($ride['contact']); ?></span>
                <?php else: ?>
                <span style="color:var(--gray-400);">🔒 <em>预订后可查看联系方式</em></span>
                <?php endif; ?>
                <?php if ($ride['notes']): ?>
                <span style="width:100%;">💬 <?php echo h($ride['notes']); ?></span>
                <?php endif; ?>
            </div>
            <div class="ride-card-actions">
                <?php if (!isLoggedIn()): ?>
                    <a href="/login.php" class="btn btn-primary">登录后预订</a>
                <?php elseif ($isOwner): ?>
                    <a href="/my_rides.php" class="btn" style="flex:1;background:var(--primary-bg);color:var(--primary);">自己发布的</a>
                <?php else: ?>
                    <form action="book_ride.php" method="post" style="flex:1;display:flex;">
                        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                        <input type="hidden" name="ride_id" value="<?php echo (int)$ride['id']; ?>">
                        <button type="submit" name="book" class="btn btn-primary" style="width:100%;border-radius:0 0 0 var(--radius-lg);">
                            <?php echo $hasBooked ? '✓ 已预订' : '立即预订'; ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php
            $queryParams = $_GET;
            unset($queryParams['page']);
            $baseQuery = http_build_query($queryParams);
            $baseUrl = 'index.php' . ($baseQuery ? '?' . $baseQuery . '&' : '?');

            $prev = $page - 1;
            $next = $page + 1;

            if ($page > 1): ?>
                <a href="<?php echo $baseUrl; ?>page=<?php echo $prev; ?>">‹</a>
            <?php else: ?>
                <span class="disabled">‹</span>
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
                <a href="<?php echo $baseUrl; ?>page=<?php echo $next; ?>">›</a>
            <?php else: ?>
                <span class="disabled">›</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/footer.php'; ?>
