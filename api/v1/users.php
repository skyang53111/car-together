<?php
/**
 * 校区拼车平台 - 用户 API v1
 * GET  /api/v1/users.php?action=profile   — 个人资料
 * POST /api/v1/users.php?action=update    — 更新资料
 * GET  /api/v1/users.php?action=stats     — 统计数据
 * GET  /api/v1/users.php?action=my_rides  — 我的发布列表
 * GET  /api/v1/users.php?action=campuses  — 校区列表
 */

require_once __DIR__ . '/utils.php';

$action = $_REQUEST['action'] ?? '';

if ($action === 'profile') {
    $userId = requireAuth();

    global $pdo;
    $stmt = $pdo->prepare("SELECT id, phone, email, nickname, gender, campus, role, phone_verified, email_verified, created_at FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) error('用户不存在');
    success($user);
}

if ($action === 'update') {
    $userId = requireAuth();

    $nickname = trim($_POST['nickname'] ?? '');
    $gender   = trim($_POST['gender'] ?? '');
    $campus   = trim($_POST['campus'] ?? '');

    $fields = [];
    $params = [];

    if ($nickname !== '') { $fields[] = "nickname = ?"; $params[] = $nickname; }
    if ($gender !== '')   { $fields[] = "gender = ?";   $params[] = $gender; }
    if ($campus !== '')   { $fields[] = "campus = ?";   $params[] = $campus; }

    if (empty($fields)) error('没有需要更新的字段');

    $fields[] = "updated_at = NOW()";
    $params[] = $userId;

    $pdo->prepare("UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
    success(null, '更新成功');
}

if ($action === 'stats') {
    $userId = requireAuth();

    global $pdo;

    $r = $pdo->prepare("SELECT COUNT(*) FROM rides WHERE user_id = ?");
    $r->execute([$userId]); $totalRides = (int)$r->fetchColumn();

    $r = $pdo->prepare("SELECT COUNT(*) FROM rides WHERE user_id = ? AND status = 'active'");
    $r->execute([$userId]); $activeRides = (int)$r->fetchColumn();

    $r = $pdo->prepare("SELECT COUNT(*) FROM rides WHERE user_id = ? AND status = 'completed'");
    $r->execute([$userId]); $completedRides = (int)$r->fetchColumn();

    $r = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ? AND status = 'confirmed'");
    $r->execute([$userId]); $totalBookings = (int)$r->fetchColumn();

    $r = $pdo->prepare("SELECT COUNT(*) FROM bookings b JOIN rides r2 ON b.ride_id = r2.id WHERE b.user_id = ? AND b.status = 'confirmed' AND r2.status = 'active'");
    $r->execute([$userId]); $activeBookings = (int)$r->fetchColumn();

    success([
        'total_rides' => $totalRides,
        'active_rides' => $activeRides,
        'completed_rides' => $completedRides,
        'total_bookings' => $totalBookings,
        'active_bookings' => $activeBookings,
    ]);
}

if ($action === 'my_rides') {
    $userId = requireAuth();

    global $pdo;
    $stmt = $pdo->prepare(
        "SELECT r.*, (r.capacity - r.available_seats) AS booked_count
         FROM rides r WHERE r.user_id = ?
         ORDER BY
           CASE r.status WHEN 'active' THEN 0 WHEN 'cancelled' THEN 2 ELSE 1 END,
           r.ride_time DESC"
    );
    $stmt->execute([$userId]);
    $rides = $stmt->fetchAll();

    $list = array_map(function ($r) {
        return [
            'id' => (int)$r['id'],
            'origin' => $r['origin'],
            'destination' => $r['destination'],
            'ride_time' => $r['ride_time'],
            'contact' => $r['contact'],
            'capacity' => (int)$r['capacity'],
            'available_seats' => (int)$r['available_seats'],
            'booked_count' => (int)$r['booked_count'],
            'notes' => $r['notes'],
            'status' => $r['status'],
            'created_at' => $r['created_at'],
        ];
    }, $rides);

    success(['list' => $list, 'total' => count($list)]);
}

if ($action === 'campuses') {
    $campuses = json_decode(CAMPUSES, true);
    success(is_array($campuses) ? $campuses : ['南校区', '北校区', '东校区']);
}

error('未知操作');
