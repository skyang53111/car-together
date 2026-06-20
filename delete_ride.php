<?php
/**
 * 校区拼车平台 - 删除/取消拼车
 */
require_once __DIR__ . '/config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['cancel'])) {
    header('Location: /my_rides.php');
    exit;
}

$rideId = (int)input('ride_id');
$csrf = input('csrf_token');

if (!verifyCsrf($csrf)) {
    flashMessage('表单已过期', 'error');
    header('Location: /my_rides.php');
    exit;
}

// 验证是发布者
$stmt = $pdo->prepare("SELECT id, user_id FROM rides WHERE id = ? AND user_id = ?");
$stmt->execute([$rideId, $_SESSION['user_id']]);
$ride = $stmt->fetch();

if (!$ride) {
    flashMessage('无权操作', 'error');
    header('Location: /my_rides.php');
    exit;
}

$pdo->prepare("UPDATE rides SET status = 'cancelled' WHERE id = ?")->execute([$rideId]);
$pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE ride_id = ? AND status = 'confirmed'")->execute([$rideId]);
flashMessage('拼车已取消', 'info');

notifyOnRideCancel($rideId);
header('Location: /my_rides.php');
exit;
