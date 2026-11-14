<?php
/**
 * 校区拼车平台 - 行程 API v1
 * GET  /api/v1/rides.php?action=list      — 列表（支持搜索/筛选/分页）
 * GET  /api/v1/rides.php?action=detail    — 详情
 * POST /api/v1/rides.php?action=create    — 发布（需登录）
 * POST /api/v1/rides.php?action=cancel    — 取消（需登录+本人）
 */

require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/subscribe_send.php';

$action = $_REQUEST['action'] ?? '';

if ($action === 'list') {
    global $pdo;

    $search = trim($_GET['search'] ?? '');
    $campus = trim($_GET['campus'] ?? '');
    $date   = trim($_GET['date'] ?? '');
    $today  = trim($_GET['today'] ?? '');
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = min(50, max(10, (int)($_GET['limit'] ?? 12)));
    $offset = ($page - 1) * $limit;

    $where = ["r.status = 'active'", "r.available_seats > 0"];
    $params = [];

    if ($search) {
        $where[] = "(r.destination LIKE ? OR r.origin LIKE ? OR r.notes LIKE ?)";
        $params[] = "%{$search}%"; $params[] = "%{$search}%"; $params[] = "%{$search}%";
    }
    if ($campus) {
        $where[] = "(r.origin LIKE ? OR r.destination LIKE ?)";
        $params[] = "%{$campus}%"; $params[] = "%{$campus}%";
    }
    if ($date) {
        $where[] = "DATE(r.ride_time) = ?";
        $params[] = $date;
    }
    if ($today) {
        $where[] = "DATE(r.ride_time) = CURDATE()";
    }

    $whereClause = implode(' AND ', $where);

    // 总数
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM rides r WHERE {$whereClause}");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    // 列表
    $sql = "SELECT r.id, r.origin, r.destination, r.ride_time, r.contact,
                   r.capacity, r.available_seats, r.notes, r.status, r.created_at,
                   r.user_id, u.nickname
            FROM rides r JOIN users u ON r.user_id = u.id
            WHERE {$whereClause}
            ORDER BY r.ride_time ASC LIMIT {$limit} OFFSET {$offset}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rides = $stmt->fetchAll();

    // 标记当前用户已预订
    $userId = getAuthUserId();
    $bookedIds = [];
    if ($userId > 0) {
        $ids = array_column($rides, 'id');
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $bs = $pdo->prepare("SELECT ride_id FROM bookings WHERE user_id = ? AND ride_id IN ({$placeholders}) AND status = 'confirmed'");
            $bs->execute(array_merge([$userId], $ids));
            $bookedIds = $bs->fetchAll(PDO::FETCH_COLUMN);
        }
    }

    $list = array_map(function ($ride) use ($userId, $bookedIds) {
        return [
            'id' => (int)$ride['id'],
            'origin' => $ride['origin'],
            'destination' => $ride['destination'],
            'ride_time' => $ride['ride_time'],
            'capacity' => (int)$ride['capacity'],
            'available_seats' => (int)$ride['available_seats'],
            'notes' => $ride['notes'],
            'status' => $ride['status'],
            'publisher' => [
                'id' => (int)$ride['user_id'],
                'nickname' => $ride['nickname'],
            ],
            'is_owner' => $userId > 0 && $ride['user_id'] == $userId,
            'has_booked' => in_array($ride['id'], $bookedIds),
            'created_at' => $ride['created_at'],
        ];
    }, $rides);

    success([
        'list' => $list,
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => ceil($total / $limit),
    ]);
}

if ($action === 'detail') {
    $rideId = (int)($_GET['id'] ?? 0);
    if ($rideId <= 0) error('缺少行程ID');

    global $pdo;
    $stmt = $pdo->prepare(
        "SELECT r.*, u.nickname, u.phone, u.email
         FROM rides r JOIN users u ON r.user_id = u.id WHERE r.id = ?"
    );
    $stmt->execute([$rideId]);
    $ride = $stmt->fetch();

    if (!$ride) error('行程信息不存在', -1, 404);

    $userId = getAuthUserId();
    $hasBooked = false;
    if ($userId > 0) {
        $bs = $pdo->prepare("SELECT id FROM bookings WHERE user_id = ? AND ride_id = ? AND status = 'confirmed'");
        $bs->execute([$userId, $rideId]);
        $hasBooked = (bool)$bs->fetch();
    }

    success([
        'id' => (int)$ride['id'],
        'origin' => $ride['origin'],
        'destination' => $ride['destination'],
        'ride_time' => $ride['ride_time'],
        'contact' => $ride['contact'],
        'capacity' => (int)$ride['capacity'],
        'available_seats' => (int)$ride['available_seats'],
        'notes' => $ride['notes'],
        'status' => $ride['status'],
        'publisher' => [
            'id' => (int)$ride['user_id'],
            'nickname' => $ride['nickname'],
            'phone' => $ride['phone'],
            'email' => $ride['email'],
        ],
        'is_owner' => $userId > 0 && $ride['user_id'] == $userId,
        'has_booked' => $hasBooked,
        'created_at' => $ride['created_at'],
    ]);
}

if ($action === 'create') {
    $userId = requireAuth();

    $origin      = trim($_POST['origin'] ?? '');
    $destination = trim($_POST['destination'] ?? '');
    $rideTime    = trim($_POST['ride_time'] ?? '');
    $contact     = trim($_POST['contact'] ?? '');
    $capacity    = (int)($_POST['capacity'] ?? 2);
    $notes       = trim($_POST['notes'] ?? '');

    if (empty($origin)) error('请填写出发地');
    if (empty($destination)) error('请填写目的地');
    if ($origin === $destination) error('出发地和目的地不能相同');
    if (empty($rideTime)) error('请选择出发时间');
    if (strtotime($rideTime) < time() - 3600) error('出发时间不能在过去');
    if (empty($contact)) error('请留下联系方式');
    if ($capacity < 1 || $capacity > 6) error('搭载人数需在1-6之间');

    global $pdo;
    $pdo->prepare(
        "INSERT INTO rides (user_id, origin, destination, ride_time, contact, capacity, available_seats, notes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    )->execute([$userId, $origin, $destination, $rideTime, $contact, $capacity, $capacity, $notes ?: null]);

    success(['id' => (int)$pdo->lastInsertId()], '发布成功');
}

if ($action === 'cancel') {
    $userId = requireAuth();
    $rideId = (int)($_POST['id'] ?? 0);
    if ($rideId <= 0) error('缺少行程ID');

    global $pdo;
    $stmt = $pdo->prepare("SELECT user_id FROM rides WHERE id = ? AND status = 'active'");
    $stmt->execute([$rideId]);
    $ride = $stmt->fetch();

    if (!$ride) error('行程不存在或已取消');
    if ($ride['user_id'] != $userId) error('只能取消自己发布的行程');

    $pdo->prepare("UPDATE rides SET status = 'cancelled' WHERE id = ?")->execute([$rideId]);
    $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE ride_id = ? AND status = 'confirmed'")->execute([$rideId]);

    // 给所有已预订乘客发送订阅通知
    try {
        $rSt = $pdo->prepare("SELECT origin, destination, ride_time FROM rides WHERE id = ?");
        $rSt->execute([$rideId]);
        $rInfo = $rSt->fetch();
        if ($rInfo) {
            $bSt = $pdo->prepare("SELECT user_id FROM bookings WHERE ride_id = ? AND status = 'confirmed'");
            $bSt->execute([$rideId]);
            while ($b = $bSt->fetch()) {
                notifySubscribeCancel((int)$b['user_id'], $rideId, $rInfo['origin'], $rInfo['destination']);
            }
        }
    } catch (Exception $e) { error_log('subscribe_ride_cancel:'.$e->getMessage()); }

    success(null, '行程已取消');
}

error('未知操作');

// VERSION_CHECK: 1782295893
