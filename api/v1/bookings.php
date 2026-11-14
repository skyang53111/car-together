<?php
/**
 * 校区拼车平台 - 预订 API v1
 * GET  /api/v1/bookings.php?action=list    — 我的预订列表
 * POST /api/v1/bookings.php?action=book    — 预订行程
 * POST /api/v1/bookings.php?action=cancel  — 取消预订
 */

require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/subscribe_send.php';

$action = $_REQUEST['action'] ?? '';

if ($action === 'list') {
    $userId = requireAuth();
    global $pdo;
    $stmt = $pdo->prepare(
        "SELECT b.id AS booking_id, b.status AS booking_status, b.created_at AS booked_at,
                r.id AS ride_id, r.origin, r.destination, r.ride_time, r.status AS ride_status,
                r.contact, r.available_seats, r.capacity, u.nickname AS publisher_nickname
         FROM bookings b JOIN rides r ON b.ride_id = r.id JOIN users u ON r.user_id = u.id
         WHERE b.user_id = ? ORDER BY r.ride_time DESC"
    );
    $stmt->execute([$userId]);
    $bookings = $stmt->fetchAll();
    $list = array_map(function ($b) {
        return ['booking_id' => (int)$b['booking_id'], 'booking_status' => $b['booking_status'], 'booked_at' => $b['booked_at'],
            'ride' => ['id' => (int)$b['ride_id'], 'origin' => $b['origin'], 'destination' => $b['destination'],
                       'ride_time' => $b['ride_time'], 'contact' => $b['contact'],
                       'available_seats' => (int)$b['available_seats'], 'capacity' => (int)$b['capacity'], 'status' => $b['ride_status']],
            'publisher' => ['nickname' => $b['publisher_nickname']]];
    }, $bookings);
    success(['list' => $list, 'total' => count($list)]);
}

if ($action === 'book') {
    $userId = requireAuth();
    $rideId = (int)($_POST['ride_id'] ?? 0);
    if ($rideId <= 0) error('缺少行程ID');

    global $pdo;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM rides WHERE id = ? AND status = 'active' FOR UPDATE");
        $stmt->execute([$rideId]);
        $ride = $stmt->fetch();
        if (!$ride) { $pdo->rollBack(); error('行程不存在或已取消'); }
        if ($ride['user_id'] == $userId) { $pdo->rollBack(); error('不能预订自己发布的行程'); }
        if ($ride['available_seats'] <= 0) { $pdo->rollBack(); error('该行程已满'); }

        $stmt = $pdo->prepare("SELECT id FROM bookings WHERE user_id = ? AND ride_id = ? AND status = 'confirmed' FOR UPDATE");
        $stmt->execute([$userId, $rideId]);
        if ($stmt->fetch()) { $pdo->rollBack(); error('您已预订过此行程'); }

        $pdo->prepare("INSERT INTO bookings (user_id, ride_id, status) VALUES (?, ?, 'confirmed')")->execute([$userId, $rideId]);
        $pdo->prepare("UPDATE rides SET available_seats = available_seats - 1 WHERE id = ? AND available_seats > 0")->execute([$rideId]);

        $pdo->commit();

        // 站内通知（不依赖邮件配置）
        try {
            $pdo->prepare("INSERT INTO notifications (user_id, msg_type, title, body, link) VALUES (?, 'booking_success', '行程预订成功', '您已成功预订，请在「我的预订」查看', '/my_bookings.php')")->execute([$userId]);
            $os = $pdo->prepare("SELECT user_id FROM rides WHERE id = ?");
            $os->execute([$rideId]);
            if ($oid = $os->fetchColumn()) {
                $pdo->prepare("INSERT INTO notifications (user_id, msg_type, title, body, link) VALUES (?, 'new_booking', '有人预订了您的行程', '点击查看详情', '/my_rides.php')")->execute([$oid]);
            }
        } catch (Exception $e) { error_log('notify_db:'.$e->getMessage()); }

        // 微信订阅消息发送（独立 try-catch，不阻塞主流程）
        try {
            $subscribeResult = notifySubscribeBooking($userId, $rideId, $ride['origin'], $ride['destination'], $ride['ride_time']);
            error_log("[subscribe_debug] book notifySubscribeBooking: userId={$userId}, rideId={$rideId}, result=" . ($subscribeResult ? 'success' : 'skipped_or_failed'));
        } catch (Exception $e) { error_log('subscribe_book:'.$e->getMessage()); }

        // 邮件通知（独立 try-catch）
        try { notifyOnBooking($rideId, $userId); } catch (Exception $e) { error_log('notify_email:'.$e->getMessage()); }
        if ($ride['available_seats'] - 1 <= 0) {
            try { notifyOnCarpoolFull($rideId); } catch (Exception $e) { error_log('full:'.$e->getMessage()); }
        }

        success(['ride_id' => $rideId], '预订成功');
    } catch (Exception $e) {
        $pdo->rollBack();
        error('预订失败，请重试');
    }
}

if ($action === 'cancel') {
    $userId = requireAuth();
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    if ($bookingId <= 0) error('缺少预订ID');

    global $pdo;
    $stmt = $pdo->prepare("SELECT b.id, b.ride_id FROM bookings b WHERE b.id = ? AND b.user_id = ? AND b.status = 'confirmed'");
    $stmt->execute([$bookingId, $userId]);
    $booking = $stmt->fetch();
    if (!$booking) error('预订不存在或已取消');

    $pdo->beginTransaction();
    $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?")->execute([$bookingId]);
    $pdo->prepare("UPDATE rides SET available_seats = available_seats + 1 WHERE id = ?")->execute([$booking['ride_id']]);
    $pdo->commit();

    // 查询发布者ID和行程信息（用于通知）
    $ownerId = null;
    $origin = $destination = '';
    $infoSt = $pdo->prepare("SELECT user_id, origin, destination FROM rides WHERE id = ?");
    $infoSt->execute([$booking['ride_id']]);
    $info = $infoSt->fetch();
    if ($info) {
        $ownerId = $info['user_id'];
        $origin = $info['origin'];
        $destination = $info['destination'];
    }

    // 取消预订站内通知
    try {
        $pdo->prepare("INSERT INTO notifications (user_id, msg_type, title, body, link) VALUES (?, 'cancel_booking', '预订已取消', '您已取消预订', '/index.php')")->execute([$userId]);
        if ($ownerId) {
            $pdo->prepare("INSERT INTO notifications (user_id, msg_type, title, body, link) VALUES (?, 'cancel_booking', '您的行程有人取消了预订', '一名乘客取消了预订', '/my_rides.php')")->execute([$ownerId]);
        }
    } catch (Exception $e) { error_log('cancel_notify:'.$e->getMessage()); }

    // 微信订阅消息发送
    if ($origin && $destination) {
        try { notifySubscribeCancel($userId, $booking['ride_id'], $origin, $destination); } catch (Exception $e) { error_log('subscribe_cancel_user:'.$e->getMessage()); }
        if ($ownerId) {
            try { notifySubscribeCancel($ownerId, $booking['ride_id'], $origin, $destination); } catch (Exception $e) { error_log('subscribe_cancel_owner:'.$e->getMessage()); }
        }
    }

    success(null, '已取消预订');
}

error('未知操作');
