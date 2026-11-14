<?php
/**
 * 校区拼车平台 - API v1 工具函数
 */

require_once __DIR__ . '/../../config.php';

function jsonResponse($data, int $httpCode = 200): void {
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function success($data = null, string $message = 'ok'): void {
    jsonResponse(['code' => 0, 'message' => $message, 'data' => $data]);
}

function error(string $message, int $code = -1, int $httpCode = 200): void {
    jsonResponse(['code' => $code, 'message' => $message], $httpCode);
}

function needAuth(): void {
    jsonResponse(['code' => 401, 'message' => '未登录或登录已过期'], 200);
}

// ==================== Token 管理 ====================

define('TOKEN_EXPIRE', 86400 * 30);

function generateToken(int $userId): string {
    global $pdo;
    // 先使该用户旧 token 过期
    $pdo->prepare("UPDATE user_tokens SET is_revoked = 1 WHERE user_id = ? AND is_revoked = 0")->execute([$userId]);
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + TOKEN_EXPIRE);
    $pdo->prepare(
        "INSERT INTO user_tokens (user_id, token, expires_at) VALUES (?, ?, ?)"
    )->execute([$userId, $token, $expiresAt]);
    return $token;
}

function verifyToken(string $token): int {
    global $pdo;
    $stmt = $pdo->prepare(
        "SELECT user_id FROM user_tokens WHERE token = ? AND expires_at > NOW() AND is_revoked = 0 LIMIT 1"
    );
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    return $row ? (int)$row['user_id'] : 0;
}

function getAuthUserId(): int {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    // 部分 Apache / CGI 环境不会把 Authorization 透传到 $_SERVER，补一条取值路径
    if ($authHeader === '' && function_exists('apache_request_headers')) {
        foreach (apache_request_headers() as $k => $v) {
            if (strcasecmp($k, 'Authorization') === 0) { $authHeader = $v; break; }
        }
    }

    if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
        return verifyToken(trim($m[1]));
    }

    // 不再接受 ?token= 形式的凭据：URL 会进访问日志、Referer、浏览器历史，等同泄露。
    // 客户端请统一放到请求头：Authorization: Bearer <token>
    return 0;
}

function requireAuth(): int {
    $userId = getAuthUserId();
    if ($userId <= 0) needAuth();
    return $userId;
}
