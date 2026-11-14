<?php
/**
 * 校区拼车平台 - 订阅消息发送器
 *
 * 字段名跟着你选的微信模板走，在 config.local.php 中配置映射
 */
// 本文件被 bookings.php require，utils.php 已在 bookings.php 中加载

// ==================== access_token 管理 ====================

function getWechatAccessToken(): string {
    global $pdo;
    $tableExists = false;
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'wechat_tokens'");
        $tableExists = (bool)$stmt->fetch();
    } catch (Exception $e) {}
    if ($tableExists) {
        $stmt = $pdo->prepare("SELECT access_token FROM wechat_tokens WHERE expires_at > DATE_ADD(NOW(), INTERVAL 5 MINUTE) ORDER BY id DESC LIMIT 1");
        $stmt->execute();
        $cached = $stmt->fetchColumn();
        if ($cached) return $cached;
    }

    $appid  = defined('WX_APPID') ? WX_APPID : '';
    $secret = defined('WX_SECRET') ? WX_SECRET : '';
    if (empty($appid) || empty($secret)) return '';

    $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appid}&secret={$secret}";
    $ch = curl_init();
    curl_setopt_array($ch, [CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_SSL_VERIFYPEER => true]);
    $resp = curl_exec($ch);
    curl_close($ch);

    if ($resp === false) return '';
    $data = json_decode($resp, true);
    if (empty($data['access_token'])) return '';

    $token = $data['access_token'];
    if ($tableExists) {
        $expiresAt = date('Y-m-d H:i:s', time() + (int)($data['expires_in'] ?? 7200) - 300);
        $pdo->prepare("INSERT INTO wechat_tokens (access_token, expires_at) VALUES (?, ?)")->execute([$token, $expiresAt]);
    }
    return $token;
}

// ==================== 发送订阅消息 ====================

function sendSubscribeMessage(int $userId, string $templateId, array $data, string $page = ''): bool {
    global $pdo;
    $stmt = $pdo->prepare("SELECT openid FROM users WHERE id = ? AND openid IS NOT NULL");
    $stmt->execute([$userId]);
    $openid = $stmt->fetchColumn();
    if (empty($openid)) return false;

    $stmt = $pdo->prepare("SELECT subscribed FROM user_subscriptions WHERE user_id = ? AND template_id = ? AND subscribed = 1");
    $stmt->execute([$userId, $templateId]);
    if (!$stmt->fetch()) return false;

    $accessToken = getWechatAccessToken();
    if (empty($accessToken)) return false;

    $body = [
        'touser' => $openid,
        'template_id' => $templateId,
        'page' => $page ?: 'pages/index/index',
        'data' => $data,
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api.weixin.qq.com/cgi-bin/message/subscribe/send?access_token={$accessToken}",
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($resp, true);
    if (($result['errcode'] ?? -1) !== 0) {
        error_log("Subscribe send fail: {$resp}, user={$userId}, tmpl={$templateId}");
    }
    return ($result['errcode'] ?? -1) === 0;
}

// ==================== 从配置构建消息数据 ====================

function buildSubscribeData(string $configKey, array $vars = []): array {
    $json = defined($configKey) ? constant($configKey) : '';
    if (empty($json)) return [];
    $fields = json_decode($json, true);
    if (!is_array($fields)) return [];

    $result = [];
    foreach ($fields as $key => $template) {
        $value = $template['value'] ?? (is_string($template) ? $template : '');
        foreach ($vars as $vk => $vv) {
            $value = str_replace('{' . $vk . '}', $vv, $value);
        }
        $result[$key] = ['value' => $value];
    }
    return $result;
}

// ==================== 业务通知函数 ====================

function notifySubscribeBooking(int $userId, int $rideId, string $origin, string $destination, string $rideTime): void {
    $tmplId = defined('SUBSCRIBE_TMPL_BOOKING') ? SUBSCRIBE_TMPL_BOOKING : '';
    if (empty($tmplId)) return;
    $data = buildSubscribeData('SUBSCRIBE_BOOKING_FIELDS', [
        'origin' => $origin, 'destination' => $destination, 'time' => $rideTime,
    ]);
    if (!empty($data)) sendSubscribeMessage($userId, $tmplId, $data, 'pages/mybookings/mybookings');
}

function notifySubscribeCancel(int $userId, int $rideId, string $origin, string $destination): void {
    $tmplId = defined('SUBSCRIBE_TMPL_CANCEL') ? SUBSCRIBE_TMPL_CANCEL : '';
    if (empty($tmplId)) return;
    $data = buildSubscribeData('SUBSCRIBE_CANCEL_FIELDS', [
        'origin' => $origin, 'destination' => $destination,
    ]);
    if (!empty($data)) sendSubscribeMessage($userId, $tmplId, $data, 'pages/index/index');
}

function notifySubscribeRemind(int $userId, string $origin, string $destination, string $rideTime): void {
    $tmplId = defined('SUBSCRIBE_TMPL_REMIND') ? SUBSCRIBE_TMPL_REMIND : '';
    if (empty($tmplId)) return;
    $data = buildSubscribeData('SUBSCRIBE_REMIND_FIELDS', [
        'origin' => $origin, 'destination' => $destination, 'time' => $rideTime,
    ]);
    if (!empty($data)) sendSubscribeMessage($userId, $tmplId, $data, 'pages/mybookings/mybookings');
}
