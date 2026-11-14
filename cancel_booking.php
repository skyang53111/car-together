<?php
/**
 * 校区拼车平台 - 取消预订
 */
require_once __DIR__ . '/config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf(input('csrf_token'))) {
        flashMessage('表单已过期，请重试', 'error');
        header('Location: /my_bookings.php');
        exit;
    }

    $bookingId = (int)input('booking_id');
    $userId = $_SESSION['user_id'];

    // 获取预订信息
    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ? AND user_id = ? AND status = 'confirmed'");
    $stmt->execute([$bookingId, $userId]);
    $booking = $stmt->fetch();

    if (!$booking) {
        flashMessage('未找到该预订记录', 'error');
    } else {
        $pdo->beginTransaction();
        try {
            // 取消预订
            $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$bookingId]);

            // 恢复座位
            $stmt = $pdo->prepare("UPDATE rides SET available_seats = available_seats + 1 WHERE id = ? AND available_seats < capacity");
            $stmt->execute([$booking['ride_id']]);

            $pdo->commit();

            // 发送邮件通知
            notifyOnCancelBooking($booking['ride_id'], $userId);

            flashMessage('已取消预订', 'success');
        } catch (Exception $e) {
            $pdo->rollBack();
            flashMessage('取消失败，请重试', 'error');
        }
    }
}

header('Location: /my_bookings.php');
exit;
