<?php
/**
 * 校区拼车平台 - 忘记密码
 * 步骤1：输入手机号/邮箱 → 验证码发到邮箱
 * 步骤2：输入验证码和新密码完成重置
 */
require_once __DIR__ . '/config.php';
setPageTitle('忘记密码');

// 仅 AUTH_MODE=local（默认）时走本机找回流程；接入统一认证后密码由认证服务统一管理，这里直接跳登录。
if (AUTH_MODE !== 'local') {
    header('Location: /oauth/login.php');
    exit;
}

if (isLoggedIn()) {
    header('Location: /index.php');
    exit;
}

$step = input('step', 'get', '1');
$error = '';
$success = '';

// ========== AJAX 发送验证码（统一调 api/send_code.php） ==========
// 前端 fetch 直接调 /api/send_code.php，此处不再重复实现
// 手机号→邮箱的查找前置在 api/send_code.php 的 'reset' case 中

// ========== 验证并重置密码 ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && input('action') === 'reset') {
    $login    = input('email');
    $code     = input('code');
    $password = input('password');
    $confirm  = input('confirm_password');
    $email    = '';

    if (empty($login)) {
        $error = '请输入手机号或邮箱';
    } elseif (strpos($login, '@') !== false) {
        if (!filter_var($login, FILTER_VALIDATE_EMAIL)) {
            $error = '请输入有效的邮箱地址';
        }
        $email = $login;
    } else {
        $stmt = $pdo->prepare("SELECT email FROM users WHERE phone = ?");
        $stmt->execute([$login]);
        $r = $stmt->fetch();
        $email = $r ? $r['email'] : '';
    }

    if (empty($email)) {
        $error = '未找到该账号';
    } elseif (strlen($password) < 8) {
        $error = '新密码至少8位';
    } elseif ($password !== $confirm) {
        $error = '两次密码不一致';
    } elseif (!preg_match('/^(?=.*[a-zA-Z])(?=.*\d)/', $password)) {
        $error = '密码须包含字母和数字';
    } elseif (!verifyCode($email, $code, 'reset')) {
        $error = '验证码错误或已过期';
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
        $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?")->execute([$hash, $email]);
        $success = '密码重置成功！';

        $stmt = $pdo->prepare("SELECT id, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
        }
    }
}
?>
<?php include __DIR__ . '/header.php'; ?>

<div class="auth-container">
    <div class="auth-card">
        <h1 class="auth-title">忘记密码</h1>
        <p class="auth-subtitle">通过邮箱验证码重置密码</p>

        <?php if ($error): ?>
        <div class="flash-msg flash-error"><?php echo h($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="flash-msg flash-success"><?php echo h($success); ?></div>
        <p style="text-align:center;margin-top:8px;">
            <a href="/index.php" class="btn btn-primary" style="margin-top:12px;">进入首页</a>
        </p>
        <?php else: ?>

        <form method="post" action="forgot_password.php">
            <input type="hidden" name="action" value="reset">
            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">

            <div class="form-group">
                <label class="form-label" for="email">手机号 / 注册邮箱 <span class="required">*</span></label>
                <input type="text" id="email" name="email" class="form-input"
                       placeholder="输入手机号或注册邮箱"
                       value="<?php echo h(input('email')); ?>" required autocomplete="username">
            </div>

            <div class="form-group">
                <label class="form-label" for="code">验证码 <span class="required">*</span></label>
                <div class="code-input-group">
                    <input type="text" id="code" name="code" class="form-input"
                           placeholder="输入6位验证码" maxlength="6" required
                           pattern="[0-9]{6}" inputmode="numeric">
                    <button type="button" id="sendCodeBtn" class="send-code-btn">获取验证码</button>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">新密码 <span class="required">*</span></label>
                <input type="password" id="password" name="password" class="form-input"
                       placeholder="至少8位，包含字母和数字" required minlength="8" autocomplete="new-password">
            </div>

            <div class="form-group">
                <label class="form-label" for="confirm_password">确认新密码 <span class="required">*</span></label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-input"
                       placeholder="再次输入新密码" required minlength="8" autocomplete="new-password">
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg">重置密码</button>
        </form>

        <?php endif; ?>

        <div class="auth-link">
            <a href="/login.php"><svg class="ico" style="width:15px;height:15px;display:inline-block;vertical-align:-2px;"><use href="#i-chev-l"/></svg> 返回登录</a>
        </div>
    </div>
</div>

<script>
document.getElementById('sendCodeBtn').addEventListener('click', function() {
    var email = document.getElementById('email').value.trim();
    if (!email) {
        alert('请输入手机号或邮箱');
        document.getElementById('email').focus();
        return;
    }
    var btn = this;
    btn.disabled = true;
    btn.textContent = '发送中...';
    var formData = new FormData();
    formData.append('type', 'reset');
    formData.append('email', email);
    formData.append('csrf_token', '<?php echo csrfToken(); ?>');
    fetch('/api/send_code.php', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            alert(data.message);
            if (data.success) {
                var sec = 60;
                btn.textContent = sec + 's后重发';
                var timer = setInterval(function() {
                    sec--;
                    btn.textContent = sec + 's后重发';
                    if (sec <= 0) {
                        clearInterval(timer);
                        btn.disabled = false;
                        btn.textContent = '获取验证码';
                    }
                }, 1000);
            } else {
                btn.disabled = false;
                btn.textContent = '获取验证码';
            }
        })
        .catch(function() {
            alert('网络错误，请重试');
            btn.disabled = false;
            btn.textContent = '获取验证码';
        });
});
</script>
<?php include __DIR__ . '/footer.php'; ?>
