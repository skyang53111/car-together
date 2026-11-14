<?php
/**
 * 校区拼车平台 - 预订行程
 */
require_once __DIR__ . '/config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book'])) {
    if (!verifyCsrf(input('csrf_token'))) {
        flashMessage('表单已过期，请重试', 'error');
        header('Location: /index.php');
        exit;
    }

    $rideId = (int)input('ride_id');
    $userId = $_SESSION['user_id'];

    // 开启事务并用 FOR UPDATE 锁行防止竞态条件
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM rides WHERE id = ? AND status = 'active' FOR UPDATE");
        $stmt->execute([$rideId]);
        $ride = $stmt->fetch();

        if (!$ride) {
            $pdo->rollBack();
            flashMessage('行程信息不存在或已取消', 'error');
        } elseif ($ride['user_id'] == $userId) {
            $pdo->rollBack();
            flashMessage('不能预订自己发布的行程信息', 'warning');
        } elseif ($ride['available_seats'] <= 0) {
            $pdo->rollBack();
            flashMessage('该行程已满', 'warning');
        } else {
            // 检查是否已预订
            $stmt = $pdo->prepare("SELECT id FROM bookings WHERE user_id = ? AND ride_id = ? AND status = 'confirmed' FOR UPDATE");
            $stmt->execute([$userId, $rideId]);
            if ($stmt->fetch()) {
                $pdo->rollBack();
                flashMessage('您已预订过此行程信息', 'warning');
            } else {
                $stmt = $pdo->prepare("INSERT INTO bookings (user_id, ride_id, status) VALUES (?, ?, 'confirmed')");
                $stmt->execute([$userId, $rideId]);

                $stmt = $pdo->prepare("UPDATE rides SET available_seats = available_seats - 1 WHERE id = ? AND available_seats > 0");
                $stmt->execute([$rideId]);
                if ($stmt->rowCount() === 0) {
                    $pdo->rollBack();
                    flashMessage('该行程已满', 'warning');
                } else {
                    $pdo->commit();

                    // 发送邮件通知
                    $remainingSeats = $ride['available_seats'] - 1;
                    notifyOnBooking($rideId, $userId);
                    if ($remainingSeats <= 0) {
                        notifyOnCarpoolFull($rideId);
                    }

                    flashMessage('预订成功！可在"我的预订"中查看详情', 'success');
                }
            }
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Booking failed: " . $e->getMessage());
        flashMessage('预订失败，请重试', 'error');
    }
}

header('Location: /index.php');
exit;
