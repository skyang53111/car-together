<?php
/**
 * 校区拼车平台 - 登录入口
 *
 * AUTH_MODE = local（默认）：仅本机账号密码登录，无需任何外部服务
 * AUTH_MODE = both          ：同时提供统一认证登录与本机手机号/邮箱+密码登录
 * AUTH_MODE = casdoor       ：完全切换到统一身份认证，本页直接跳转 /oauth/login.php
 */
require_once __DIR__ . '/config.php';
setPageTitle('登录');

// 如果已登录，重定向
if (isLoggedIn()) {
    header('Location: /index.php');
    exit;
}

$redirect = input('redirect', 'get') ?: ($_SESSION['redirect_url'] ?? '');

// ========== 完全切换到统一身份认证 ==========
if (AUTH_MODE === 'casdoor') {
    $url = '/oauth/login.php';
    if ($redirect !== '' && $redirect !== '/oauth/login.php') {
        $url .= '?redirect=' . urlencode($redirect);
    }
    header('Location: ' . $url);
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && AUTH_MODE !== 'casdoor') {
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
                    // 登录成功
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['auth_source'] = 'local';

                    // 更新登录信息
                    $pdo->prepare("UPDATE users SET last_login = NOW(), last_ip = ? WHERE id = ?")
                        ->execute([getClientIP(), $user['id']]);

                    // 清除该IP的登录限制
                    $pdo->prepare("DELETE FROM rate_limits WHERE ip_address = ? AND action = 'login'")
                        ->execute([getClientIP()]);

                    // 安全重定向：仅允许站点内相对路径
                    $target = '/index.php';
                    $parsed = parse_url($redirect);
                    if (empty($parsed['host']) && empty($parsed['scheme'])) {
                        $path = $parsed['path'] ?? $redirect;
                        $target = (str_starts_with($path, '/') && !str_starts_with($path, '//') && strpos($path, '\\') === false) ? $path : '/index.php';
                    }
                    unset($_SESSION['redirect_url']);
                    header('Location: ' . $target);
                    exit;
                }
            } else {
                $error = '手机号/邮箱或密码错误';
                // 记录失败次数（通过rate_limit机制）
                checkRateLimit('login', RATE_LOGIN_MAX, RATE_LOGIN_WINDOW);
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

        <?php if (AUTH_MODE === 'both'): ?>
        <!-- 统一身份认证登录（推荐） -->
        <a href="/oauth/login.php<?php echo $redirect !== '' ? '?redirect=' . urlencode($redirect) : ''; ?>"
           class="btn btn-primary btn-block btn-lg" style="margin-bottom:16px;">
            <svg class="ico"><use href="#i-lock"/></svg>统一身份认证登录
        </a>
        <div style="display:flex;align-items:center;gap:10px;margin:4px 0 16px;color:var(--ink-500);font-size:12.5px;">
            <span style="flex:1;height:1px;background:var(--ink-200);"></span>
            <span>或使用本机账号</span>
            <span style="flex:1;height:1px;background:var(--ink-200);"></span>
        </div>
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

        <?php if (AUTH_MODE !== 'casdoor'): ?>
        <div class="auth-link" style="margin-top:14px;display:flex;justify-content:space-between;font-size:13px;">
            <a href="/forgot_password.php" style="color:var(--ink-500);">忘记密码？</a>
            <a href="/register.php<?php echo $redirect !== '' ? '?redirect=' . urlencode($redirect) : ''; ?>" style="color:var(--brand);font-weight:600;">没有账号？注册</a>
        </div>
        <?php endif; ?>
        <?php if (AUTH_MODE !== 'local'): ?>
        <div class="auth-link" style="margin-top:12px;">
            <a href="/oauth/login.php" style="font-size:13px;color:var(--ink-500);">统一身份认证登录</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
