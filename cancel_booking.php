<?php
/**
 * 校区拼车平台 - 取消预订
 */
require_once __DIR__ . '/config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['cancel'])) {
    header('Location: /my_bookings.php');
    exit;
}

$bookingId = (int)input('booking_id');
$csrf = input('csrf_token');

if (!verifyCsrf($csrf)) {
    flashMessage('表单已过期', 'error');
    header('Location: /my_bookings.php');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT b.id, b.ride_id FROM bookings b JOIN rides r ON b.ride_id = r.id WHERE b.id = ? AND b.user_id = ? AND b.status = 'confirmed'"
);
$stmt->execute([$bookingId, $_SESSION['user_id']]);
$booking = $stmt->fetch();

if (!$booking) {
    flashMessage('预订记录不存在', 'error');
    header('Location: /my_bookings.php');
    exit;
}

$pdo->beginTransaction();
try {
    $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?")->execute([$bookingId]);
    $pdo->prepare("UPDATE rides SET available_seats = available_seats + 1 WHERE id = ? AND available_seats < capacity")->execute([$booking['ride_id']]);
    $pdo->commit();
    flashMessage('已取消预订', 'info');

    // 发送通知
    notifyOnCancelBooking($booking['ride_id'], $_SESSION['user_id']);
} catch (Exception $e) {
    $pdo->rollBack();
    flashMessage('操作失败', 'error');
}

header('Location: /my_bookings.php');
exit;
