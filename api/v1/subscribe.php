<?php
/**
 * 校区拼车平台 - 微信订阅消息 API v1
 */
require_once __DIR__ . '/utils.php';

$action = $_REQUEST['action'] ?? '';

if ($action === 'save') {
    $userId = requireAuth();
    $resultJson = $_POST['subscribe_result'] ?? '';
    if (empty($resultJson)) error('缺少订阅结果');
    $result = json_decode($resultJson, true);
    if (!is_array($result)) error('无效的订阅结果');
    global $pdo;
    foreach ($result as $tmplId => $accepted) {
        $acceptedVal = $accepted ? 1 : 0;
        $stmt = $pdo->prepare(
            "INSERT INTO user_subscriptions (user_id, template_id, subscribed, updated_at)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE subscribed = VALUES(subscribed), updated_at = NOW()"
        );
        $stmt->execute([$userId, $tmplId, $acceptedVal]);
    }
    success(null, '订阅状态已保存');
}

if ($action === 'templates') {
    $templates = [];
    if (defined('SUBSCRIBE_TMPL_BOOKING')) {
        $templates[] = ['id' => SUBSCRIBE_TMPL_BOOKING, 'name' => '预订成功通知', 'desc' => '预订行程后接收确认信息'];
    }
    if (defined('SUBSCRIBE_TMPL_CANCEL')) {
        $templates[] = ['id' => SUBSCRIBE_TMPL_CANCEL, 'name' => '取消通知', 'desc' => '预订被取消时接收通知'];
    }
    success(['templates' => $templates, 'count' => count($templates)]);
}

if ($action === 'user_status') {
    $userId = requireAuth();
    global $pdo;
    $stmt = $pdo->prepare("SELECT template_id, subscribed FROM user_subscriptions WHERE user_id = ?");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();
    $status = [];
    foreach ($rows as $row) {
        $status[$row['template_id']] = (bool)$row['subscribed'];
    }
    success(['status' => $status]);
}

error('未知操作');
