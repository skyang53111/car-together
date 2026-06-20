<?php
/**
 * 校区拼车平台 - 统一验证码发送 API
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

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '请输入有效的邮箱地址'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!checkRateLimit('send_code', RATE_SEND_CODE_MAX, RATE_SEND_CODE_WINDOW)) {
    echo json_encode(['success' => false, 'message' => '操作过于频繁，请1小时后再试'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($type === 'change_email' && !verifyCsrf(input('csrf_token'))) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '表单已过期，请刷新重试'], JSON_UNESCAPED_UNICODE);
    exit;
}

switch ($type) {
    case 'register':
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
        $queryEmail = $email;
        if (strpos($email, '@') === false) {
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

if ($sent) {
    echo json_encode(['success' => true, 'message' => '验证码已发送，请查收邮件'], JSON_UNESCAPED_UNICODE);
} else {
    $debugMode = empty(MAIL_USERNAME) || empty(MAIL_PASSWORD);
    echo json_encode([
        'success' => true,
        'message' => $debugMode ? "开发模式验证码: {$code}" : '验证码已发送，如未收到请检查垃圾邮件箱',
    ], JSON_UNESCAPED_UNICODE);
}
