<?php
/**
 * 校区拼车平台 - 删除行程信息
 */
require_once __DIR__ . '/config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf(input('csrf_token'))) {
        flashMessage('表单已过期，请重试', 'error');
        header('Location: /my_rides.php');
        exit;
    }

    $rideId = (int)input('ride_id');
    $userId = $_SESSION['user_id'];

    // 获取行程信息（允许用户删除自己的发布，管理员可删除任意）
    if (isAdmin()) {
        $stmt = $pdo->prepare("SELECT * FROM rides WHERE id = ?");
        $stmt->execute([$rideId]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM rides WHERE id = ? AND user_id = ?");
        $stmt->execute([$rideId, $userId]);
    }
    $ride = $stmt->fetch();

    if (!$ride) {
        flashMessage('未找到该行程信息', 'error');
    } else {
        $pdo->beginTransaction();
        try {
            // 先发送邮件通知所有已确认乘客（需要在取消预订之前发送）
            notifyOnRideCancel($rideId);

            // 设为已取消状态（软删除）
            $stmt = $pdo->prepare("UPDATE rides SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$rideId]);

            // 同时取消相关的预订
            $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE ride_id = ? AND status = 'confirmed'")
                ->execute([$rideId]);

            $pdo->commit();
            flashMessage('行程信息已删除', 'success');
        } catch (Exception $e) {
            $pdo->rollBack();
            flashMessage('删除失败，请重试', 'error');
        }
    }
}

header('Location: my_rides.php');
exit;
