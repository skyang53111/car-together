<?php
/**
 * 校区拼车平台 - 接收 Casdoor 统一认证平台的 Webhook 通知
 *
 * 作用：Casdoor 里「删除用户 / 修改用户」时主动推送事件，让本地 users 表跟着更新，
 *      避免「Casdoor 已删、本平台还留着脏数据」的问题。
 *
 * 配置位置（Casdoor 后台 → 设置 → Webhook）：
 *   URL     ： http://your-domain.com/oauth/casdoor_webhook.php
 *   Events  ： delete-user（必选） / update-user（可选，用于手机号等资料变更即时同步）
 *   Headers ： X-Casdoor-Webhook-Secret: <与 config.local.php 里一致的密钥>
 *   建议：ObjectFields 留空（或 All），并勾选 IsUserExtended / 扩展用户信息（可选）
 *
 * 安全：必须携带正确密钥，否则一律 403。密钥在 config.local.php 里配置
 *      （define('CASDOOR_WEBHOOK_SECRET', '...');），留空则整个端点关闭。
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/casdoor.php';

header('Content-Type: application/json; charset=utf-8');

/** 统一返回 */
function casdoorWebhookReply(int $httpCode, string $status, string $msg = ''): void {
    http_response_code($httpCode);
    echo json_encode(['status' => $status, 'msg' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

// ==================== 1. 校验共享密钥 ====================
$secret = defined('CASDOOR_WEBHOOK_SECRET') ? CASDOOR_WEBHOOK_SECRET : '';
if ($secret === '') {
    casdoorLog('webhook rejected: CASDOOR_WEBHOOK_SECRET not configured');
    casdoorWebhookReply(403, 'error', 'webhook not configured');
}

$got = '';
foreach (['HTTP_X_CASDOOR_WEBHOOK_SECRET', 'HTTP_X_WEBHOOK_SECRET', 'HTTP_AUTHORIZATION'] as $k) {
    if (!empty($_SERVER[$k])) { $got = $_SERVER[$k]; break; }
}
$got = preg_replace('/^Bearer\s+/i', '', trim((string)$got));
if ($got === '' || !hash_equals($secret, $got)) {
    casdoorLog('webhook rejected: bad secret');
    casdoorWebhookReply(403, 'error', 'forbidden');
}

// ==================== 2. 读取事件与载荷 ====================
$raw  = file_get_contents('php://input');
$body = json_decode((string)$raw, true);
if (!is_array($body)) {
    casdoorLog('webhook: non-JSON body: ' . substr((string)$raw, 0, 300));
    casdoorWebhookReply(200, 'ignored', 'non-json');
}

$event = '';
foreach (['event', 'action', 'eventType'] as $k) {
    if (!empty($body[$k]) && is_string($body[$k])) { $event = $body[$k]; break; }
}
casdoorLog('webhook received event=' . $event);

// 只处理「删除用户」与「更新用户」，其余直接忽略（返回 200，避免 Casdoor 反复重试）
if ($event !== 'delete-user' && $event !== 'update-user') {
    casdoorWebhookReply(200, 'ignored', 'event not handled: ' . $event);
}

// ==================== 3. 从载荷中定位「被操作的用户」====================
$target = casdoorWebhookExtractTargetUser($body);
if (!$target) {
    casdoorLog('webhook: cannot locate target user in payload');
    casdoorWebhookReply(200, 'ignored', 'no target user');
}

// ==================== 4. 执行 ====================
if ($event === 'delete-user') {
    $ok = casdoorWebhookDeleteLocalUser($target);
    casdoorWebhookReply(200, 'ok', $ok ? 'deleted local user' : 'no local match');
}

$ok = casdoorWebhookUpdateLocalUser($target);   // update-user
casdoorWebhookReply(200, 'ok', $ok ? 'synced local user' : 'no local match');

// ==========================================================================
// 以下为处理函数（PHP 顶层函数会被提升，可在上方调用）
// ==========================================================================

/**
 * 从 webhook 载荷里取出「被删除 / 被修改的用户」。
 *
 * Casdoor 两种送法都要兼容：
 *   ① 记录格式（Casdoor 实际发送）：{action:"delete-user", object:"<用户JSON字符串>", user:"<操作的管理员>"}
 *      —— 注意这里的 user / organization 是「执行操作的管理员」，不是目标用户；
 *         真正被删/被改的用户在 object 里（是 JSON 字符串，需再解析一层）。
 *   ② 文档格式：{event:"delete-user", user:{...}}
 *
 * 因此只认两处：object（字符串或对象）→ user（仅当它是对象时）。
 * 绝不使用 extendedUser / 顶层字段：在记录格式里它们指向「执行操作的管理员」，
 * 拿来当目标用户会误删管理员。
 *
 * @return array|null 目标用户的原始字段数组
 */
function casdoorWebhookExtractTargetUser(array $body): ?array {
    $candidates = [];

    // ① object：记录格式里是「请求体字符串」（被删/被改用户的 JSON），也可能是对象
    if (isset($body['object'])) {
        if (is_string($body['object'])) {
            $decoded = json_decode($body['object'], true);
            if (is_array($decoded)) $candidates[] = $decoded;
        } elseif (is_array($body['object'])) {
            $candidates[] = $body['object'];
        }
    }

    // ② user：文档格式里是对象；记录格式里是字符串（管理员），必须排除
    if (isset($body['user']) && is_array($body['user'])) {
        $candidates[] = $body['user'];
    }

    foreach ($candidates as $u) {
        $id    = isset($u['id'])    && is_scalar($u['id'])    ? trim((string)$u['id'])    : '';
        $name  = isset($u['name'])  && is_scalar($u['name'])  ? trim((string)$u['name'])  : '';
        $email = isset($u['email']) && is_scalar($u['email']) ? trim((string)$u['email']) : '';
        $phone = isset($u['phone']) && is_scalar($u['phone']) ? casdoorNormalizePhone((string)$u['phone']) : '';
        if ($id !== '' || $name !== '' || $email !== '' || $phone !== '') {
            return $u;
        }
    }
    return null;
}

/**
 * 按 casdoor_id → 邮箱 → 手机号 依次匹配本地用户。
 * @return array|null 本地用户行（含 id / email / role）
 */
function casdoorWebhookFindLocalUser(array $u): ?array {
    global $pdo;

    $id    = isset($u['id'])    && is_scalar($u['id'])    ? trim((string)$u['id'])    : '';
    $name  = isset($u['name'])  && is_scalar($u['name'])  ? trim((string)$u['name'])  : '';
    $owner = isset($u['owner']) && is_scalar($u['owner']) ? trim((string)$u['owner']) : '';
    $email = isset($u['email']) && is_scalar($u['email']) ? trim((string)$u['email']) : '';
    $phone = isset($u['phone']) && is_scalar($u['phone']) ? casdoorNormalizePhone((string)$u['phone']) : '';

    $find = function (string $column, string $value) use ($pdo) {
        $st = $pdo->prepare("SELECT id, email, role FROM users WHERE {$column} = ? LIMIT 1");
        $st->execute([$value]);
        return $st->fetch() ?: null;
    };

    // 1) casdoor_id = 用户 id（Casdoor 的 sub 就是 User.Id，最可靠）
    if ($id !== '') {
        if ($r = $find('casdoor_id', $id)) return $r;
    }
    // 2) casdoor_id = owner/name（个别版本 sub 用这个形式）
    if ($owner !== '' && $name !== '') {
        if ($r = $find('casdoor_id', $owner . '/' . $name)) return $r;
    }
    // 3) casdoor_id = 用户名
    if ($name !== '') {
        if ($r = $find('casdoor_id', $name)) return $r;
    }
    // 4) 邮箱
    if ($email !== '') {
        if ($r = $find('email', $email)) return $r;
    }
    // 5) 手机号
    if ($phone !== '' && isValidPhone($phone)) {
        if ($r = $find('phone', $phone)) return $r;
    }
    return null;
}

/**
 * delete-user：彻底删除本地用户及其数据。
 * rides / bookings / notifications / user_tokens / user_subscriptions 由外键 ON DELETE CASCADE 自动清理，
 * 只有 verification_codes 是按邮箱存的，需要显式删。
 */
function casdoorWebhookDeleteLocalUser(array $u): bool {
    global $pdo;

    $local = casdoorWebhookFindLocalUser($u);
    if (!$local) {
        casdoorLog('webhook delete: no local user matched');
        return false;
    }
    $uid = (int)$local['id'];

    $pdo->beginTransaction();
    try {
        if (!empty($local['email'])) {
            $pdo->prepare("DELETE FROM verification_codes WHERE email = ?")->execute([$local['email']]);
        }
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        casdoorLog('webhook delete: DB error for local user #' . $uid . ': ' . $e->getMessage());
        return false;
    }

    casdoorLog('webhook delete: removed local user #' . $uid
        . ' role=' . ($local['role'] ?? '?')
        . ' email=' . ($local['email'] ?: '-'));
    return true;
}

/**
 * update-user：按 casdoor_id 精确匹配后同步资料（手机号/邮箱/昵称/头像）。
 * 只匹配 casdoor_id，不新建、不做邮箱/手机兜底匹配，避免误伤他人。
 */
function casdoorWebhookUpdateLocalUser(array $u): bool {
    global $pdo;

    $id = isset($u['id']) && is_scalar($u['id']) ? trim((string)$u['id']) : '';
    if ($id === '') {
        casdoorLog('webhook update: payload has no user id, skip');
        return false;
    }

    $st = $pdo->prepare("SELECT id FROM users WHERE casdoor_id = ? LIMIT 1");
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) {
        casdoorLog('webhook update: no local user with casdoor_id=' . $id);
        return false;
    }

    // Casdoor 用户对象的字段名（id/displayName/avatar/email/phone）正好被 casdoorExtractProfile 兼容
    casdoorApplyProfile((int)$row['id'], casdoorExtractProfile($u));

    casdoorLog('webhook update: synced local user #' . $row['id']);
    return true;
}
