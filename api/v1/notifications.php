<?php
/**
 * 校区拼车平台 - 通知 API v1
 * GET  /api/v1/notifications.php?action=list         — 通知列表
 * POST /api/v1/notifications.php?action=read          — 标记已读
 * POST /api/v1/notifications.php?action=read_all      — 全部已读
 * GET  /api/v1/notifications.php?action=unread_count  — 未读数
 */

require_once __DIR__ . '/utils.php';

$action = $_REQUEST['action'] ?? '';

if ($action === 'list') {
    $userId = requireAuth();
    $type = trim($_GET['type'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;

    global $pdo;
    if ($type) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND msg_type = ?");
        $stmt->execute([$userId, $type]);
        $total = (int)$stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND msg_type = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->execute([$userId, $type, $limit, $offset]);
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
        $stmt->execute([$userId]);
        $total = (int)$stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->execute([$userId, $limit, $offset]);
    }

    $list = $stmt->fetchAll();

    $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt2->execute([$userId]);
    $unreadCount = (int)$stmt2->fetchColumn();

    success([
        'list' => $list,
        'total' => $total,
        'unread_count' => $unreadCount,
        'page' => $page,
        'limit' => $limit,
    ]);
}

if ($action === 'read') {
    $userId = requireAuth();
    $notifId = (int)($_POST['id'] ?? 0);
    if ($notifId <= 0) error('缺少通知ID');
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")->execute([$notifId, $userId]);
    success(null, '已标记已读');
}

if ($action === 'read_all') {
    $userId = requireAuth();
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=? AND is_read=0")->execute([$userId]);
    success(null, '已全部标记已读');
}

if ($action === 'unread_count') {
    $userId = requireAuth();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    success(['count' => (int)$stmt->fetchColumn()]);
}

if ($action === 'delete') {
    $userId = requireAuth();
    $notifId = (int)($_POST['id'] ?? 0);
    if ($notifId <= 0) error('缺少通知ID');
    $pdo->prepare("DELETE FROM notifications WHERE id=? AND user_id=?")->execute([$notifId, $userId]);
    success(null, '已删除');
}

if ($action === 'clear_read') {
    $userId = requireAuth();
    $pdo->prepare("DELETE FROM notifications WHERE user_id=? AND is_read=1")->execute([$userId]);
    success(null, '已清理');
}

error('未知操作');
