<?php
/**
 * 校区拼车平台 - 忘记密码
 */
require_once __DIR__ . '/config.php';
setPageTitle('找回密码');

if (isLoggedIn()) {
    header('Location: /index.php');
    exit;
}

$step = 1;
$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf(input('csrf_token'))) {
        $error = '表单已过期，请刷新页面重试';
    } else {
        $action = input('action');

        if ($action === 'send_code') {
            $email = input('email');
            if (empty($email)) {
                $error = '请输入邮箱地址';
            } elseif (!checkRateLimit('reset_code', 3, 3600)) {
                $error = '操作过于频繁，请1小时后再试';
            } else {
                $user = findUserByLogin($email);
                if (!$user) {
                    $error = '未找到该账号';
                } else {
                    $code = generateCode();
                    saveVerificationCode($email, $code, 'reset');
                    sendResetPasswordEmail($email, $code);
                    $step = 2;
                    $_SESSION['reset_email'] = $email;
                }
            }
        } elseif ($action === 'reset_password') {
            $code = input('code');
            $newPwd = input('password');
            $confirmPwd = input('confirm_password');
            $email = $_SESSION['reset_email'] ?? '';

            if (empty($email)) {
                $error = '请重新开始找回密码流程';
            } elseif (empty($code)) {
                $error = '请输入验证码';
            } elseif (strlen($newPwd) < 8) {
                $error = '密码至少需要8位';
            } elseif ($newPwd !== $confirmPwd) {
                $error = '两次输入的密码不一致';
            } elseif (!verifyCode($email, $code, 'reset')) {
                $error = '验证码错误或已过期';
            } else {
                $hash = password_hash($newPwd, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
                $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?")->execute([$hash, $email]);
                unset($_SESSION['reset_email']);
                flashMessage('密码已重置，请用新密码登录', 'success');
                header('Location: /login.php');
                exit;
            }
        }
    }
}
?>
<?php include __DIR__ . '/header.php'; ?>

<div class="auth-container">
    <div class="auth-card">
        <h1 class="auth-title">找回密码</h1>
        <p class="auth-subtitle">通过邮箱验证重置密码</p>

        <?php if ($error): ?>
        <div class="flash-msg flash-error"><?php echo h($error); ?></div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
        <form method="post" action="forgot_password.php">
            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
            <input type="hidden" name="action" value="send_code">

            <div class="form-group">
                <label class="form-label" for="email">邮箱地址</label>
                <input type="email" id="email" name="email" class="form-input" placeholder="输入注册时使用的邮箱"
                       value="<?php echo h($email); ?>" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg">发送验证码</button>
        </form>

        <?php else: ?>
        <form method="post" action="forgot_password.php">
            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
            <input type="hidden" name="action" value="reset_password">

            <div class="form-group">
                <label class="form-label">邮箱</label>
                <input type="text" class="form-input" value="<?php echo h($email); ?>" disabled>
            </div>

            <div class="form-group">
                <label class="form-label" for="code">验证码</label>
                <input type="text" id="code" name="code" class="form-input" placeholder="输入6位验证码" maxlength="6" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">新密码</label>
                <input type="password" id="password" name="password" class="form-input" placeholder="至少8位，包含字母和数字" minlength="8" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="confirm_password">确认新密码</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-input" minlength="8" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg">重置密码</button>
        </form>
        <?php endif; ?>

        <div class="auth-link"><a href="/login.php">返回登录</a></div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
