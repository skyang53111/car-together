<?php
/**
 * 校区拼车平台 - 统一验证码发送 API
 * 参数: type=register|reset|change_email
 *       email (目标邮箱)
 *       csrf_token (可选GET请求无需)
 */
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'message' => '仅支持 POST 请求'], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$type  = input('type');
$email = input('email');
$userId = isLoggedIn() ? $_SESSION['user_id'] : null;

// 基本校验
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '请输入有效的邮箱地址'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 频率限制
if (!checkRateLimit('send_code', RATE_SEND_CODE_MAX, RATE_SEND_CODE_WINDOW)) {
    echo json_encode(['success' => false, 'message' => '操作过于频繁，请1小时后再试'], JSON_UNESCAPED_UNICODE);
    exit;
}

// CSRF 保护：已登录操作（change_email）严格校验
if ($type === 'change_email' && !verifyCsrf(input('csrf_token'))) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '表单已过期，请刷新重试'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 根据类型执行不同逻辑
switch ($type) {
    case 'register':
        // 注册：检查邮箱未被绑定
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => '该邮箱已被绑定，请使用其他邮箱'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $code = generateCode();
        saveVerificationCode($email, $code, 'register');
        $sent = sendVerificationEmail($email, $code);
        break;

    case 'reset':
        // 找回密码：email 参数可能是手机号或邮箱，需兼容
        $queryEmail = $email;
        if (strpos($email, '@') === false) {
            // 手机号 → 查邮箱
            $stmt = $pdo->prepare("SELECT email FROM users WHERE phone = ?");
            $stmt->execute([$email]);
            $u = $stmt->fetch();
            if (!$u) {
                echo json_encode(['success' => false, 'message' => '未找到该账号，请检查输入'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $queryEmail = $u['email'];
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$queryEmail]);
            if (!$stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => '未找到该账号'], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
        $code = generateCode();
        saveVerificationCode($queryEmail, $code, 'reset');
        $sent = sendResetPasswordEmail($queryEmail, $code);
        break;

    case 'change_email':
        // 修改邮箱（需登录）：检查新邮箱未被绑定
        if (!$userId) {
            echo json_encode(['success' => false, 'message' => '请先登录'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $userId]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => '该邮箱已被其他账号绑定'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $code = generateCode();
        saveVerificationCode($email, $code, 'register');
        $sent = sendVerificationEmail($email, $code);
        break;

    default:
        echo json_encode(['success' => false, 'message' => '未知的验证码类型'], JSON_UNESCAPED_UNICODE);
        exit;
}

// 返回结果
if ($sent) {
    echo json_encode(['success' => true, 'message' => '验证码已发送，请查收邮件'], JSON_UNESCAPED_UNICODE);
} else {
    $debugMode = empty(MAIL_USERNAME) || empty(MAIL_PASSWORD);
    echo json_encode([
        'success' => true,
        'message' => $debugMode ? "开发模式验证码: {$code}" : '验证码已发送，如未收到请检查垃圾邮件箱',
    ], JSON_UNESCAPED_UNICODE);
}
