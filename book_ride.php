<?php
/**
 * 校区拼车平台 - 预订拼车
 */
require_once __DIR__ . '/config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['book'])) {
    header('Location: /index.php');
    exit;
}

$rideId = (int)input('ride_id');
$csrf   = input('csrf_token');

if (!verifyCsrf($csrf)) {
    flashMessage('表单已过期，请刷新重试', 'error');
    header('Location: /index.php');
    exit;
}

// 获取拼车信息
$stmt = $pdo->prepare("SELECT * FROM rides WHERE id = ? AND status = 'active' FOR UPDATE");
$stmt->execute([$rideId]);
$ride = $stmt->fetch();

if (!$ride) {
    flashMessage('该拼车不存在或已取消', 'error');
    header('Location: /index.php');
    exit;
}

if ($ride['user_id'] == $_SESSION['user_id']) {
    flashMessage('不能预订自己的拼车', 'error');
    header('Location: /index.php');
    exit;
}

if ($ride['available_seats'] <= 0) {
    flashMessage('该拼车已满员', 'error');
    header('Location: /index.php');
    exit;
}

// 检查是否已预订
$stmt = $pdo->prepare("SELECT id FROM bookings WHERE user_id = ? AND ride_id = ? AND status = 'confirmed'");
$stmt->execute([$_SESSION['user_id'], $rideId]);
if ($stmt->fetch()) {
    flashMessage('您已经预订过此拼车了', 'warning');
    header('Location: /index.php');
    exit;
}

// 事务：创建预订 + 减少座位
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("INSERT INTO bookings (user_id, ride_id) VALUES (?, ?)");
    $stmt->execute([$_SESSION['user_id'], $rideId]);

    $stmt = $pdo->prepare("UPDATE rides SET available_seats = available_seats - 1 WHERE id = ? AND available_seats > 0");
    $stmt->execute([$rideId]);

    $pdo->commit();

    // 发送通知
    notifyOnBooking($rideId, $_SESSION['user_id']);

    // 如果满员了
    $stmt = $pdo->prepare("SELECT available_seats, capacity FROM rides WHERE id = ?");
    $stmt->execute([$rideId]);
    $updated = $stmt->fetch();
    if ($updated && $updated['available_seats'] <= 0) {
        notifyOnCarpoolFull($rideId);
    }

    flashMessage('预订成功！可以查看发布者联系方式了', 'success');
} catch (Exception $e) {
    $pdo->rollBack();
    flashMessage('预订失败，请重试', 'error');
}

header('Location: /index.php');
exit;
