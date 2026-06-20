<?php
/**
 * 校区拼车平台 - 工具函数库
 */

// ==================== 用户相关 ====================

function isLoggedIn(): bool {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] <= 0) {
        return false;
    }

    // 每次请求验证用户状态，确保被禁用/删除的用户立即失效
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, status, role FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || $user['status'] !== 'active') {
        // 用户不存在或已被禁用 → 清除登录态
        unset($_SESSION['user_id'], $_SESSION['user_role']);
        return false;
    }

    // 同步最新角色（管理员升降及时生效）
    $_SESSION['user_role'] = $user['role'];
    return true;
}

function isAdmin(): bool {
    return isLoggedIn() && in_array($_SESSION['user_role'] ?? '', ['admin', 'super_admin'], true);
}

function isSuperAdmin(): bool {
    return isLoggedIn() && ($_SESSION['user_role'] ?? '') === 'super_admin';
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header('Location: /login.php');
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) {
        header('Location: /index.php');
        exit;
    }
}

function getCurrentUser(): ?array {
    if (!isLoggedIn()) return null;
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, phone, email, nickname, gender, campus, role, phone_verified, email_verified, created_at FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

/**
 * 根据手机号或邮箱查找用户
 */
function findUserByLogin(string $login): ?array {
    global $pdo;
    if (strpos($login, '@') !== false) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE phone = ? LIMIT 1");
    }
    $stmt->execute([$login]);
    return $stmt->fetch() ?: null;
}

/**
 * 验证中国大陆手机号格式
 */
function isValidPhone(string $phone): bool {
    return preg_match('/^1[3-9]\d{9}$/', $phone) === 1;
}

/**
 * 获取用户显示名称
 */
function userDisplayName(array $user): string {
    $name = $user['nickname'] ?? '';
    if (!$name) {
        if (!empty($user['phone'])) {
            $p = $user['phone'];
            $name = substr($p, 0, 3) . '****' . substr($p, -4);
        } elseif (!empty($user['email'])) {
            $name = explode('@', $user['email'])[0];
        } else {
            $name = '用户';
        }
    }
    return $name;
}

// ==================== 辅助 ====================

/**
 * 自动将已过期的拼车标记为 completed
 * 应在每次页面加载时调用（轻量级操作）
 */
function autoCompleteRides(): void {
    global $pdo;
    // 出发时间超过2小时的仍为 active 的拼车 → 标记为 completed
    $pdo->exec("UPDATE rides SET status='completed' WHERE status='active' AND ride_time < DATE_SUB(NOW(), INTERVAL 2 HOUR)");
    // 对应预订也标记为 completed
    $pdo->exec("UPDATE bookings b JOIN rides r ON b.ride_id=r.id SET b.status='completed' WHERE b.status='confirmed' AND r.status='completed'");
}

/**
 * 创建站内通知
 */
function createNotification(int $userId, string $msgType, string $title, string $body, string $link = ''): void {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, msg_type, title, body, link) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $msgType, $title, $body, $link ?: null]);
    } catch (Exception $e) {
        error_log("Notification create failed: " . $e->getMessage());
    }
}

/**
 * 获取未读通知数
 */
function getUnreadNotificationCount(int $userId): int {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

/**
 * 获取通知列表
 */
function getNotifications(int $userId, int $limit = 20): array {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$userId, $limit]);
    return $stmt->fetchAll();
}

/**
 * 标记通知为已读
 */
function markNotificationRead(int $notificationId, int $userId): void {
    global $pdo;
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")->execute([$notificationId, $userId]);
}

/**
 * 标记用户所有通知为已读
 */
function markAllNotificationsRead(int $userId): void {
    global $pdo;
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=? AND is_read=0")->execute([$userId]);
}

/**
 * 删除单条通知
 */
function deleteNotification(int $notificationId, int $userId): void {
    global $pdo;
    $pdo->prepare("DELETE FROM notifications WHERE id=? AND user_id=?")->execute([$notificationId, $userId]);
}

/**
 * 删除用户所有已读通知
 */
function deleteAllReadNotifications(int $userId): void {
    global $pdo;
    $pdo->prepare("DELETE FROM notifications WHERE user_id=? AND is_read=1")->execute([$userId]);
}

/**
 * 获取通知列表（支持类型筛选）
 */
function getNotificationsFiltered(int $userId, string $msgType = '', int $limit = 20): array {
    global $pdo;
    if ($msgType) {
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND msg_type = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$userId, $msgType, $limit]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$userId, $limit]);
    }
    return $stmt->fetchAll();
}

/**
 * 记录管理员操作日志
 */
function adminLog(string $action, string $targetType = '', int $targetId = 0, string $detail = ''): void {
    global $pdo;
    if (!isset($_SESSION['user_id'])) return;
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO admin_logs (admin_id, action, target_type, target_id, detail, ip_address) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $_SESSION['user_id'],
            $action,
            $targetType ?: null,
            $targetId ?: null,
            $detail ?: null,
            getClientIP()
        ]);
    } catch (Exception $e) {
        error_log("Admin log failed: " . $e->getMessage());
    }
}

/**
 * 获取管理员操作日志
 */
function getAdminLogs(int $limit = 30, int $adminId = 0): array {
    global $pdo;
    try {
        if ($adminId > 0) {
            $stmt = $pdo->prepare(
                "SELECT l.*, u.nickname FROM admin_logs l LEFT JOIN users u ON l.admin_id = u.id WHERE l.admin_id = ? ORDER BY l.created_at DESC LIMIT ?"
            );
            $stmt->execute([$adminId, $limit]);
        } else {
            $stmt = $pdo->prepare(
                "SELECT l.*, u.nickname FROM admin_logs l LEFT JOIN users u ON l.admin_id = u.id ORDER BY l.created_at DESC LIMIT ?"
            );
            $stmt->execute([$limit]);
        }
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

// ==================== 安全 / 输入处理 ====================

function h(?string $str): string {
    return htmlspecialchars($str ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function input(string $key, string $method = 'post', $default = ''): string {
    $source = strtoupper($method) === 'GET' ? $_GET : $_POST;
    return trim($source[$key] ?? $default);
}

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(string $token): bool {
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function getClientIP(): string {
    // 仅在可信代理后使用 X-Forwarded-For
    if (defined('TRUSTED_PROXY_IP') && !empty(TRUSTED_PROXY_IP)) {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
        $trustedIps = is_array(TRUSTED_PROXY_IP) ? TRUSTED_PROXY_IP : [TRUSTED_PROXY_IP];
        if (in_array($remoteAddr, $trustedIps, true) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

// ==================== 频率限制 ====================

function checkRateLimit(string $action, int $maxAttempts, int $windowSeconds): bool {
    global $pdo;
    $ip = getClientIP();

    $pdo->prepare("DELETE FROM rate_limits WHERE ip_address = ? AND action = ? AND first_attempt < DATE_SUB(NOW(), INTERVAL ? SECOND)")
        ->execute([$ip, $action, $windowSeconds]);

    $stmt = $pdo->prepare("SELECT * FROM rate_limits WHERE ip_address = ? AND action = ?");
    $stmt->execute([$ip, $action]);
    $record = $stmt->fetch();

    if ($record) {
        if ($record['blocked_until'] && strtotime($record['blocked_until']) > time()) {
            return false;
        }
        if ($record['attempts'] >= $maxAttempts) {
            $blockUntil = date('Y-m-d H:i:s', time() + $windowSeconds);
            $pdo->prepare("UPDATE rate_limits SET blocked_until = ? WHERE id = ?")
                ->execute([$blockUntil, $record['id']]);
            return false;
        }
        $pdo->prepare("UPDATE rate_limits SET attempts = attempts + 1 WHERE id = ?")
            ->execute([$record['id']]);
    } else {
        $pdo->prepare("INSERT INTO rate_limits (ip_address, action, attempts) VALUES (?, ?, 1)")
            ->execute([$ip, $action]);
    }

    return true;
}

// ==================== 验证码 ====================

function generateCode(int $length = CODE_LENGTH): string {
    $chars = '0123456789';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

function saveVerificationCode(string $email, string $code, string $type = 'register'): bool {
    global $pdo;
    $expiresAt = date('Y-m-d H:i:s', time() + CODE_EXPIRE);
    $pdo->prepare("UPDATE verification_codes SET used = 1 WHERE email = ? AND type = ? AND used = 0")
        ->execute([$email, $type]);
    $stmt = $pdo->prepare("INSERT INTO verification_codes (email, code, type, expires_at) VALUES (?, ?, ?, ?)");
    return $stmt->execute([$email, $code, $type, $expiresAt]);
}

function verifyCode(string $email, string $code, string $type = 'register'): bool {
    global $pdo;
    $stmt = $pdo->prepare(
        "SELECT * FROM verification_codes WHERE email = ? AND code = ? AND type = ? AND used = 0 AND expires_at > NOW() ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([$email, $code, $type]);
    $record = $stmt->fetch();
    if (!$record) return false;
    $pdo->prepare("UPDATE verification_codes SET used = 1 WHERE id = ?")->execute([$record['id']]);
    return true;
}

// ==================== 站点配置 ====================

function siteConfig(string $key, string $default = ''): string {
    global $pdo;
    static $cache = null;
    if ($cache === null) {
        $stmt = $pdo->query("SELECT config_key, config_value FROM site_config");
        $cache = [];
        while ($row = $stmt->fetch()) {
            $cache[$row['config_key']] = $row['config_value'];
        }
    }
    return $cache[$key] ?? $default;
}

// ==================== 邮件配置与发送 ====================

function getMailConfig(): array {
    $username  = defined('MAIL_USERNAME') ? MAIL_USERNAME : '';
    $password  = defined('MAIL_PASSWORD') ? MAIL_PASSWORD : '';

    return [
        'host'      => defined('MAIL_HOST') ? MAIL_HOST : '',
        'port'      => defined('MAIL_PORT') ? (int)MAIL_PORT : 465,
        'secure'    => defined('MAIL_SECURE') ? MAIL_SECURE : 'ssl',
        'username'  => $username,
        'password'  => $password,
        'fromEmail' => defined('MAIL_FROM_EMAIL') ? MAIL_FROM_EMAIL : $username,
        'fromName'  => defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : SITE_NAME,
    ];
}

function sendMail(string $to, string $subject, string $htmlBody): bool {
    $cfg = getMailConfig();
    $host     = $cfg['host'];
    $port     = $cfg['port'];
    $secure   = $cfg['secure'];
    $username = $cfg['username'];
    $password = $cfg['password'];
    $fromEmail = $cfg['fromEmail'] ?: $username;
    $fromName  = $cfg['fromName'];

    if (empty($username) || empty($password)) {
        error_log("Mail config not set. Cannot send email to {$to}");
        return false;
    }

    $lastResponse = '';
    try {
        $socket = fsockopen(
            ($secure === 'ssl' ? 'ssl://' : '') . $host,
            $port, $errno, $errstr, 10
        );
        if (!$socket) {
            error_log("SMTP connection failed: {$errstr} ({$errno}) to {$host}:{$port}");
            return false;
        }

        $getResponse = function() use ($socket, &$lastResponse) {
            $response = '';
            while ($line = fgets($socket, 512)) {
                $response .= $line;
                if (isset($line[3]) && $line[3] === ' ') break;
            }
            $lastResponse = trim($response);
            return $response;
        };

        $getResponse();
        fwrite($socket, "EHLO " . gethostname() . "\r\n");
        $getResponse();

        if ($secure === 'tls') {
            fwrite($socket, "STARTTLS\r\n");
            $getResponse();
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            fwrite($socket, "EHLO " . gethostname() . "\r\n");
            $getResponse();
        }

        fwrite($socket, "AUTH LOGIN\r\n");
        $getResponse();
        fwrite($socket, base64_encode($username) . "\r\n");
        $getResponse();
        fwrite($socket, base64_encode($password) . "\r\n");
        $authResponse = $getResponse();
        if (strpos($authResponse, '235') === false) {
            error_log("SMTP auth failed for {$username} → {$lastResponse}");
            fclose($socket);
            return false;
        }

        fwrite($socket, "MAIL FROM:<{$fromEmail}>\r\n");
        $getResponse();
        fwrite($socket, "RCPT TO:<{$to}>\r\n");
        $getResponse();
        fwrite($socket, "DATA\r\n");
        $getResponse();

        $messageId = time() . '.' . bin2hex(random_bytes(8)) . '@' . $host;
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>\r\n";
        $headers .= "To: <{$to}>\r\n";
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $headers .= "Message-ID: <{$messageId}>\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

        fwrite($socket, $headers . "\r\n" . $htmlBody . "\r\n.\r\n");
        $dataResponse = $getResponse();
        fwrite($socket, "QUIT\r\n");
        fclose($socket);

        $ok = strpos($dataResponse, '250') !== false;
        if (!$ok) {
            error_log("SMTP send failed for {$to} → {$lastResponse}");
        }
        return $ok;
    } catch (Exception $e) {
        error_log("Mail send exception: " . $e->getMessage() . " → {$lastResponse}");
        return false;
    }
}

function sendVerificationEmail(string $to, string $code): bool {
    $subject = "【" . SITE_NAME . "】邮箱验证码";
    $content = '<p style="font-size:16px;color:#333;">您好！</p>'
        . '<p style="font-size:16px;color:#333;">您的验证码如下：</p>'
        . '<div style="text-align:center;margin:30px 0;">'
        . '<span style="font-size:32px;font-weight:bold;color:#4F46E5;letter-spacing:8px;background:#EEF2FF;padding:12px 30px;border-radius:6px;">' . $code . '</span>'
        . '</div>'
        . '<p style="font-size:14px;color:#888;">验证码有效期10分钟，请勿泄露给他人。</p>'
        . '<p style="font-size:14px;color:#888;">如非本人操作，请忽略此邮件。</p>';
    return sendMail($to, $subject, renderEmailHtml('验证码', $content));
}

function sendResetPasswordEmail(string $to, string $code): bool {
    $subject = "【" . SITE_NAME . "】重置密码验证码";
    $content = '<p style="font-size:16px;color:#333;">您好！</p>'
        . '<p style="font-size:16px;color:#333;">您正在申请重置密码，验证码如下：</p>'
        . '<div style="text-align:center;margin:30px 0;">'
        . '<span style="font-size:32px;font-weight:bold;color:#EF4444;letter-spacing:8px;background:#FEE2E2;padding:12px 30px;border-radius:6px;">' . $code . '</span>'
        . '</div>'
        . '<p style="font-size:14px;color:#888;">验证码有效期10分钟，请勿泄露给他人。</p>'
        . '<p style="font-size:14px;color:#888;">如非本人操作，请忽略此邮件。</p>';
    return sendMail($to, $subject, renderEmailHtml('重置密码', $content, '', '', 'danger'));
}

// ==================== 邮件通知功能 ====================

/**
 * 统一的邮件 HTML 模板
 */
function renderEmailHtml(string $headerTitle, string $contentHtml, string $ctaUrl = '', string $ctaText = '', string $headerColor = 'var(--primary)'): string {
    $color = match($headerColor) {
        'success' => '#10B981', 'warning' => '#F59E0B', 'danger' => '#EF4444', 'cancel' => '#6B7280',
        default => '#4F46E5'
    };
    $ctaHtml = '';
    if ($ctaUrl && $ctaText) {
        $ctaHtml = '<div style="text-align:center;margin:20px 0;">'
            . '<a href="' . $ctaUrl . '" style="display:inline-block;padding:10px 24px;background:#4F46E5;color:#fff;text-decoration:none;border-radius:6px;">' . $ctaText . '</a>'
            . '</div>';
    }
    return '<div style="max-width:600px;margin:0 auto;padding:20px;font-family:Arial,sans-serif;background:#f7f8fa;border-radius:8px;">'
        . '<div style="background:' . $color . ';padding:20px;text-align:center;border-radius:8px 8px 0 0;">'
        . '<h1 style="color:#fff;margin:0;font-size:22px;">' . $headerTitle . '</h1></div>'
        . '<div style="background:#fff;padding:30px;border-radius:0 0 8px 8px;">'
        . $contentHtml
        . $ctaHtml
        . '<hr style="border:none;border-top:1px solid #eee;margin:20px 0;">'
        . '<p style="font-size:12px;color:#bbb;text-align:center;">校区拼车平台 · 自动发送邮件</p></div></div>';
}

/**
 * 检查邮件通知功能是否开启
 */
function isEmailNotificationEnabled(): bool {
    return siteConfig('email_notifications', '1') === '1';
}

/**
 * 获取拼车信息和所有相关用户（用于通知）
 */
function getRideNotifyData(int $rideId): ?array {
    global $pdo;

    $stmt = $pdo->prepare("SELECT * FROM rides WHERE id = ?");
    $stmt->execute([$rideId]);
    $ride = $stmt->fetch();
    if (!$ride) return null;

    $stmt = $pdo->prepare("SELECT id, email, nickname FROM users WHERE id = ?");
    $stmt->execute([$ride['user_id']]);
    $owner = $stmt->fetch();

    $stmt = $pdo->prepare(
        "SELECT u.id, u.email, u.nickname
         FROM bookings b
         JOIN users u ON b.user_id = u.id
         WHERE b.ride_id = ? AND b.status = 'confirmed'"
    );
    $stmt->execute([$rideId]);
    $passengers = $stmt->fetchAll();

    return [
        'ride' => $ride,
        'owner' => $owner,
        'passengers' => $passengers,
    ];
}

/**
 * 预订成功通知 —— 通知乘客和发布者
 */
function notifyOnBooking(int $rideId, int $passengerId): void {
    if (!isEmailNotificationEnabled()) return;

    $data = getRideNotifyData($rideId);
    if (!$data || !$data['owner']) return;

    $ride = $data['ride'];
    $owner = $data['owner'];
    $rideTime = date('Y-m-d H:i', strtotime($ride['ride_time']));
    $siteUrl = SITE_URL;

    global $pdo;
    $stmt = $pdo->prepare("SELECT id, email, nickname FROM users WHERE id = ?");
    $stmt->execute([$passengerId]);
    $passenger = $stmt->fetch();
    if (!$passenger) return;
    $passengerName = h($passenger['nickname'] ?: explode('@', $passenger['email'])[0]);
    $ownerName = h($owner['nickname'] ?: explode('@', $owner['email'])[0]);

    $subject1 = "【" . SITE_NAME . "】拼车预订成功";
    $content1 = '<p style="font-size:16px;color:#333;">' . $passengerName . '，您好！</p>'
        . '<p style="font-size:16px;color:#333;">您已成功预订以下拼车：</p>'
        . '<table style="width:100%;border-collapse:collapse;margin:16px 0;">'
        . '<tr><td style="padding:8px 12px;background:#F9FAFB;color:#6B7280;width:80px;">出发地</td><td style="padding:8px 12px;color:#333;"><strong>' . h($ride['origin']) . '</strong></td></tr>'
        . '<tr><td style="padding:8px 12px;background:#F9FAFB;color:#6B7280;">目的地</td><td style="padding:8px 12px;color:#333;"><strong>' . h($ride['destination']) . '</strong></td></tr>'
        . '<tr><td style="padding:8px 12px;background:#F9FAFB;color:#6B7280;">出发时间</td><td style="padding:8px 12px;color:#333;"><strong>' . $rideTime . '</strong></td></tr>'
        . '<tr><td style="padding:8px 12px;background:#F9FAFB;color:#6B7280;">发布者</td><td style="padding:8px 12px;color:#333;">' . $ownerName . '</td></tr>'
        . '</table>'
        . '<p style="font-size:14px;color:#888;">登录平台进入「我的预订」查看发布者联系方式。</p>';
    sendMail($passenger['email'], $subject1, renderEmailHtml('预订成功', $content1, $siteUrl . '/my_bookings.php', '查看我的预订', 'success'));
    createNotification($passengerId, 'booking_success', $subject1, "预订成功", '/my_bookings.php');

    $subject2 = "【" . SITE_NAME . "】有人预订了您的拼车";
    $content2 = '<p style="font-size:16px;color:#333;">' . $ownerName . '，您好！</p>'
        . '<p style="font-size:16px;color:#333;">' . $passengerName . ' 预订了您的拼车：</p>'
        . '<table style="width:100%;border-collapse:collapse;margin:16px 0;">'
        . '<tr><td style="padding:8px 12px;background:#F9FAFB;color:#6B7280;width:80px;">路线</td><td style="padding:8px 12px;color:#333;"><strong>' . h($ride['origin']) . ' → ' . h($ride['destination']) . '</strong></td></tr>'
        . '<tr><td style="padding:8px 12px;background:#F9FAFB;color:#6B7280;">出发时间</td><td style="padding:8px 12px;color:#333;"><strong>' . $rideTime . '</strong></td></tr>'
        . '<tr><td style="padding:8px 12px;background:#F9FAFB;color:#6B7280;">剩余座位</td><td style="padding:8px 12px;color:#333;"><strong>' . $ride['available_seats'] . '</strong> / ' . $ride['capacity'] . '</td></tr>'
        . '</table>';
    sendMail($owner['email'], $subject2, renderEmailHtml('新的预订', $content2, $siteUrl . '/my_rides.php', '查看我的发布'));
    createNotification($ride['user_id'], 'new_booking', $subject2, $passengerName . " 预订了您的拼车", '/my_rides.php');
}

/**
 * 拼车满员通知 —— 通知发布者和所有乘客
 */
function notifyOnCarpoolFull(int $rideId): void {
    if (!isEmailNotificationEnabled()) return;

    $data = getRideNotifyData($rideId);
    if (!$data || !$data['owner']) return;

    $ride = $data['ride'];
    $owner = $data['owner'];
    $rideTime = date('Y-m-d H:i', strtotime($ride['ride_time']));
    $siteUrl = SITE_URL;
    $ownerName = h($owner['nickname'] ?: explode('@', $owner['email'])[0]);

    $subject1 = "【" . SITE_NAME . "】拼车已满员";
    $content1 = '<p style="font-size:16px;color:#333;">' . $ownerName . '，您好！</p>'
        . '<p style="font-size:16px;color:#333;">您发布的拼车已满员（' . $ride['capacity'] . '/' . $ride['capacity'] . '座）：</p>'
        . '<table style="width:100%;border-collapse:collapse;margin:16px 0;">'
        . '<tr><td style="padding:8px 12px;background:#F9FAFB;color:#6B7280;width:80px;">路线</td><td style="padding:8px 12px;color:#333;"><strong>' . h($ride['origin']) . ' → ' . h($ride['destination']) . '</strong></td></tr>'
        . '<tr><td style="padding:8px 12px;background:#F9FAFB;color:#6B7280;">出发时间</td><td style="padding:8px 12px;color:#333;"><strong>' . $rideTime . '</strong></td></tr>'
        . '</table>'
        . '<p style="font-size:14px;color:#888;">请及时与乘客确认行程安排。</p>';
    sendMail($owner['email'], $subject1, renderEmailHtml('拼车已满员', $content1, $siteUrl . '/my_rides.php', '查看详情', 'warning'));
    createNotification($ride['user_id'], 'carpool_full', $subject1, "您的拼车已满员", '/my_rides.php');

    foreach ($data['passengers'] as $p) {
        $passengerName = h($p['nickname'] ?: explode('@', $p['email'])[0]);
        $subject2 = "【" . SITE_NAME . "】拼车已满员，请确认行程";
        $content2 = '<p style="font-size:16px;color:#333;">' . $passengerName . '，您好！</p>'
            . '<p style="font-size:16px;color:#333;">您预订的拼车已满员：</p>'
            . '<table style="width:100%;border-collapse:collapse;margin:16px 0;">'
            . '<tr><td style="padding:8px 12px;background:#F9FAFB;color:#6B7280;width:80px;">路线</td><td style="padding:8px 12px;color:#333;"><strong>' . h($ride['origin']) . ' → ' . h($ride['destination']) . '</strong></td></tr>'
            . '<tr><td style="padding:8px 12px;background:#F9FAFB;color:#6B7280;">出发时间</td><td style="padding:8px 12px;color:#333;"><strong>' . $rideTime . '</strong></td></tr>'
            . '<tr><td style="padding:8px 12px;background:#F9FAFB;color:#6B7280;">发布者</td><td style="padding:8px 12px;color:#333;">' . $ownerName . '</td></tr>'
            . '</table>'
            . '<p style="font-size:14px;color:#888;">拼车已满员，请与发布者确认行程安排。</p>';
        sendMail($p['email'], $subject2, renderEmailHtml('拼车已满员', $content2, $siteUrl . '/my_bookings.php', '查看我的预订', 'warning'));
        createNotification($p['id'], 'carpool_full', $subject2, "拼车已满员，请确认行程", '/my_bookings.php');
    }
}

/**
 * 取消预订通知 —— 通知乘客和发布者
 */
function notifyOnCancelBooking(int $rideId, int $passengerId): void {
    if (!isEmailNotificationEnabled()) return;

    $data = getRideNotifyData($rideId);
    if (!$data || !$data['owner']) return;

    $ride = $data['ride'];
    $owner = $data['owner'];
    $rideTime = date('Y-m-d H:i', strtotime($ride['ride_time']));
    $siteUrl = SITE_URL;
    $ownerName = h($owner['nickname'] ?: explode('@', $owner['email'])[0]);

    global $pdo;
    $stmt = $pdo->prepare("SELECT id, email, nickname FROM users WHERE id = ?");
    $stmt->execute([$passengerId]);
    $passenger = $stmt->fetch();
    if (!$passenger) return;
    $passengerName = h($passenger['nickname'] ?: explode('@', $passenger['email'])[0]);

    $subject1 = "【" . SITE_NAME . "】预订已取消";
    $content1 = '<p style="font-size:16px;color:#333;">' . $passengerName . '，您好！</p>'
        . '<p style="font-size:16px;color:#333;">您已取消以下拼车预订：</p>'
        . '<table style="width:100%;border-collapse:collapse;margin:16px 0;">'
        . '<tr><td style="padding:8px 12px;background:#F9FAFB;color:#6B7280;width:80px;">路线</td><td style="padding:8px 12px;color:#333;"><strong>' . h($ride['origin']) . ' → ' . h($ride['destination']) . '</strong></td></tr>'
        . '<tr><td style="padding:8px 12px;background:#F9FAFB;color:#6B7280;">出发时间</td><td style="padding:8px 12px;color:#333;"><strong>' . $rideTime . '</strong></td></tr>'
        . '</table>'
        . '<p style="font-size:14px;color:#888;">如有需要，您可以重新预订其他拼车。</p>';
    sendMail($passenger['email'], $subject1, renderEmailHtml('预订已取消', $content1, $siteUrl . '/index.php', '查看拼车列表', 'cancel'));
    createNotification($passengerId, 'cancel_booking', $subject1, "已取消预订", '/index.php');

    $subject2 = "【" . SITE_NAME . "】有人取消了预订";
    $content2 = '<p style="font-size:16px;color:#333;">' . $ownerName . '，您好！</p>'
        . '<p style="font-size:16px;color:#333;">' . $passengerName . ' 已取消对您拼车的预订：</p>'
        . '<table style="width:100%;border-collapse:collapse;margin:16px 0;">'
        . '<tr><td style="padding:8px 12px;background:#F9FAFB;color:#6B7280;width:80px;">路线</td><td style="padding:8px 12px;color:#333;"><strong>' . h($ride['origin']) . ' → ' . h($ride['destination']) . '</strong></td></tr>'
        . '<tr><td style="padding:8px 12px;background:#F9FAFB;color:#6B7280;">出发时间</td><td style="padding:8px 12px;color:#333;"><strong>' . $rideTime . '</strong></td></tr>'
        . '<tr><td style="padding:8px 12px;background:#F9FAFB;color:#6B7280;">剩余座位</td><td style="padding:8px 12px;color:#333;"><strong>' . $ride['available_seats'] . '</strong> / ' . $ride['capacity'] . '</td></tr>'
        . '</table>';
    sendMail($owner['email'], $subject2, renderEmailHtml('预订已取消', $content2, $siteUrl . '/my_rides.php', '查看我的发布', 'cancel'));
    createNotification($ride['user_id'], 'cancel_booking', $subject2, $passengerName . " 取消了预订", '/my_rides.php');
}

/**
 * 拼车取消通知 —— 通知所有已确认乘客
 */
function notifyOnRideCancel(int $rideId): void {
    if (!isEmailNotificationEnabled()) return;

    $data = getRideNotifyData($rideId);
    if (!$data || !$data['owner']) return;

    $ride = $data['ride'];
    $owner = $data['owner'];
    $rideTime = date('Y-m-d H:i', strtotime($ride['ride_time']));
    $siteUrl = SITE_URL;
    $ownerName = h($owner['nickname'] ?: explode('@', $owner['email'])[0]);

    $subject1 = "【" . SITE_NAME . "】您的拼车已取消";
    $content1 = '<p style="font-size:16px;color:#333;">' . $ownerName . '，您好！</p>'
        . '<p style="font-size:16px;color:#333;">您已成功取消以下拼车：</p>'
        . '<table style="width:100%;border-collapse:collapse;margin:16px 0;">'
        . '<tr><td style="padding:8px 12px;background:#F9FAFB;color:#6B7280;width:80px;">路线</td><td style="padding:8px 12px;color:#333;"><strong>' . h($ride['origin']) . ' → ' . h($ride['destination']) . '</strong></td></tr>'
        . '<tr><td style="padding:8px 12px;background:#F9FAFB;color:#6B7280;">出发时间</td><td style="padding:8px 12px;color:#333;"><strong>' . $rideTime . '</strong></td></tr>'
        . '</table>'
        . '<p style="font-size:14px;color:#888;">系统已通知所有预订此拼车的用户。</p>';
    sendMail($owner['email'], $subject1, renderEmailHtml('拼车已取消', $content1, $siteUrl . '/post_ride.php', '重新发布', 'danger'));

    foreach ($data['passengers'] as $p) {
        $passengerName = h($p['nickname'] ?: explode('@', $p['email'])[0]);
        $subject2 = "【" . SITE_NAME . "】您预订的拼车已被取消";
        $content2 = '<p style="font-size:16px;color:#333;">' . $passengerName . '，您好！</p>'
            . '<p style="font-size:16px;color:#333;">很遗憾，发布者 ' . $ownerName . ' 已取消以下拼车：</p>'
            . '<table style="width:100%;border-collapse:collapse;margin:16px 0;">'
            . '<tr><td style="padding:8px 12px;background:#F9FAFB;color:#6B7280;width:80px;">路线</td><td style="padding:8px 12px;color:#333;"><strong>' . h($ride['origin']) . ' → ' . h($ride['destination']) . '</strong></td></tr>'
            . '<tr><td style="padding:8px 12px;background:#F9FAFB;color:#6B7280;">出发时间</td><td style="padding:8px 12px;color:#333;"><strong>' . $rideTime . '</strong></td></tr>'
            . '</table>'
            . '<p style="font-size:14px;color:#888;">您可前往平台查看其他拼车信息。</p>';
        sendMail($p['email'], $subject2, renderEmailHtml('拼车已取消', $content2, $siteUrl . '/index.php', '查看拼车列表', 'danger'));
        createNotification($p['id'], 'ride_cancel', $subject2, "发布者已取消拼车", '/index.php');
    }
}

// ==================== 页面渲染辅助 ====================

function setPageTitle(string $title): void {
    $GLOBALS['page_title'] = $title;
}

function getPageTitle(): string {
    return $GLOBALS['page_title'] ?? SITE_NAME;
}

function getCampuses(): array {
    $campuses = json_decode(CAMPUSES, true);
    return is_array($campuses) ? $campuses : ['南校区', '北校区', '月亮岛校区'];
}

function getHotDestinations(): array {
    return [
        '南校区西门', '南校区北门', '南校区东门',
        '北校区西门', '北校区南门', '北校区东门',
        '月亮岛校区正门', '月亮岛校区侧门',
        '市中心', '火车站', '高铁站', '机场',
        '万达广场', '大学城', '地铁站'
    ];
}

function flashMessage(?string $msg = null, string $type = 'info'): string {
    if ($msg !== null) {
        $_SESSION['flash_message'] = ['msg' => $msg, 'type' => $type];
        return '';
    }
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        $icons = ['success' => '✓', 'error' => '✗', 'warning' => '⚠', 'info' => 'ℹ'];
        $icon = $icons[$flash['type']] ?? $icons['info'];
        return '<div class="flash-msg flash-' . $flash['type'] . '" onclick="this.remove()">'
            . '<span class="flash-icon">' . $icon . '</span> ' . $flash['msg']
            . '</div>';
    }
    return '';
}

function timeAgo(string $datetime): string {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    if ($diff < 60) return '刚刚';
    if ($diff < 3600) return floor($diff / 60) . '分钟前';
    if ($diff < 86400) return floor($diff / 3600) . '小时前';
    if ($diff < 172800) return '昨天';
    if ($diff < 604800) return floor($diff / 86400) . '天前';
    return date('m-d H:i', $timestamp);
}
