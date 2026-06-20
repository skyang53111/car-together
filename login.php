<?php
/**
 * 校区拼车平台 - 登录页面
 */
require_once __DIR__ . '/config.php';
setPageTitle('登录');

if (isLoggedIn()) {
    header('Location: /index.php');
    exit;
}

$error = '';
$redirect = input('redirect', 'get') ?: ($_SESSION['redirect_url'] ?? '/index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf(input('csrf_token'))) {
        $error = '表单已过期，请刷新页面重试';
    } else {
        $email = input('email');
        $password = input('password');

        if (empty($email) || empty($password)) {
            $error = '请输入手机号/邮箱和密码';
        } elseif (!checkRateLimit('login', RATE_LOGIN_MAX, RATE_LOGIN_WINDOW)) {
            $error = '登录尝试次数过多，请1小时后再试';
        } else {
            $user = findUserByLogin($email);

            if ($user && password_verify($password, $user['password_hash'])) {
                if ($user['status'] === 'disabled') {
                    $error = '该账号已被禁用，请联系管理员';
                } else {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_role'] = $user['role'];

                    $pdo->prepare("UPDATE users SET last_login = NOW(), last_ip = ? WHERE id = ?")
                        ->execute([getClientIP(), $user['id']]);

                    $pdo->prepare("DELETE FROM rate_limits WHERE ip_address = ? AND action = 'login'")
                        ->execute([getClientIP()]);

                    $target = '/index.php';
                    $parsed = parse_url($redirect);
                    if (empty($parsed['host']) && empty($parsed['scheme'])) {
                        $path = $parsed['path'] ?? $redirect;
                        $target = (str_starts_with($path, '/') && !str_starts_with($path, '//')) ? $path : '/index.php';
                    }
                    unset($_SESSION['redirect_url']);
                    header('Location: ' . $target);
                    exit;
                }
            } else {
                $error = '手机号/邮箱或密码错误';
            }
        }
    }
}
?>
<?php include __DIR__ . '/header.php'; ?>

<div class="auth-container">
    <div class="auth-card">
        <h1 class="auth-title">欢迎回来</h1>
        <p class="auth-subtitle">登录校区拼车平台</p>

        <?php if ($error): ?>
        <div class="flash-msg flash-error"><?php echo h($error); ?></div>
        <?php endif; ?>

        <form method="post" action="login.php">
            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">

            <div class="form-group">
                <label class="form-label" for="email">手机号 / 邮箱</label>
                <input type="text" id="email" name="email" class="form-input"
                       placeholder="输入手机号或邮箱"
                       value="<?php echo h(input('email')); ?>" required autocomplete="username">
            </div>

            <div class="form-group">
                <label class="form-label" for="password">密码</label>
                <input type="password" id="password" name="password" class="form-input"
                       placeholder="输入密码" required autocomplete="current-password">
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg">登录</button>
        </form>

        <div class="auth-link">
            还没有账号？<a href="/register.php">立即注册</a>
        </div>
        <div class="auth-link" style="margin-top:4px;">
            <a href="/forgot_password.php" style="font-size:13px;color:var(--gray-500);">忘记密码？</a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
