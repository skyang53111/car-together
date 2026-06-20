<?php
/**
 * 校区拼车平台 - 注册页面
 */
require_once __DIR__ . '/config.php';
setPageTitle('注册');

if (isLoggedIn()) {
    header('Location: /index.php');
    exit;
}

$error = '';
$requireVerification = (siteConfig('registration_verification', '1') === '1');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = input('action');

    if ($action === 'register') {
        $phone      = input('phone');
        $email      = input('email');
        $password   = input('password');
        $confirmPwd = input('confirm_password');
        $code       = input('code');
        $nickname   = input('nickname');
        $gender     = input('gender');
        $campus     = input('campus');

        if (empty($phone) || !isValidPhone($phone)) {
            $error = '请输入有效的中国大陆手机号';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = '请输入有效的邮箱地址';
        } elseif (strlen($password) < 8) {
            $error = '密码至少需要8位';
        } elseif ($password !== $confirmPwd) {
            $error = '两次输入的密码不一致';
        } elseif (!preg_match('/^(?=.*[a-zA-Z])(?=.*\d)/', $password)) {
            $error = '密码须包含字母和数字';
        } elseif ($requireVerification && (empty($code) || strlen($code) !== (int)CODE_LENGTH)) {
            $error = '请输入有效的验证码';
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
            $stmt->execute([$phone]);
            if ($stmt->fetch()) {
                $error = '该手机号已被注册';
            } else {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = '该邮箱已被绑定，请使用其他邮箱';
                } elseif ($requireVerification && !verifyCode($email, $code, 'register')) {
                    $error = '验证码错误或已过期';
                } elseif (!checkRateLimit('register', RATE_REGISTER_MAX, RATE_REGISTER_WINDOW)) {
                    $error = '此设备注册次数已达上限，请24小时后重试';
                } else {
                    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
                    $stmt = $pdo->prepare(
                        "INSERT INTO users (phone, email, password_hash, nickname, gender, campus, email_verified, phone_verified, last_ip) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    $stmt->execute([$phone, $email, $hash, $nickname ?: null, $gender ?: null, $campus ?: null, $requireVerification ? 1 : 0, 1, getClientIP()]);
                    $userId = $pdo->lastInsertId();
                    $_SESSION['user_id'] = $userId;
                    $_SESSION['user_role'] = 'user';
                    flashMessage('注册成功！欢迎加入校区拼车平台', 'success');
                    header('Location: /index.php');
                    exit;
                }
            }
        }
    }
}

$campuses = getCampuses();
?>
<?php include __DIR__ . '/header.php'; ?>

<div class="auth-container">
    <div class="auth-card">
        <h1 class="auth-title">创建账号</h1>
        <p class="auth-subtitle">加入校区拼车平台，出行更便捷</p>

        <?php if ($error): ?>
        <div class="flash-msg flash-error"><?php echo h($error); ?></div>
        <?php endif; ?>

        <form id="registerForm" method="post" action="register.php">
            <input type="hidden" name="action" value="register">
            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">

            <div class="form-group">
                <label class="form-label" for="phone">手机号 <span class="required">*</span></label>
                <input type="tel" id="phone" name="phone" class="form-input" placeholder="输入11位手机号"
                       value="<?php echo h(input('phone')); ?>" required maxlength="11" pattern="1[3-9]\d{9}" inputmode="numeric" autocomplete="tel">
                <p class="form-hint">中国大陆手机号，用于登录</p>
            </div>

            <div class="form-group">
                <label class="form-label" for="email">邮箱地址 <span class="required">*</span></label>
                <input type="email" id="email" name="email" class="form-input" placeholder="验证码将发送到此邮箱"
                       value="<?php echo h(input('email')); ?>" required autocomplete="email">
            </div>

            <?php if ($requireVerification): ?>
            <div class="form-group">
                <label class="form-label" for="code">验证码 <span class="required">*</span></label>
                <div class="code-input-group">
                    <input type="text" id="code" name="code" class="form-input" placeholder="输入6位验证码"
                           maxlength="6" required pattern="[0-9]{6}" inputmode="numeric">
                    <button type="button" id="sendCodeBtn" class="send-code-btn">获取验证码</button>
                </div>
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label class="form-label" for="nickname">昵称</label>
                <input type="text" id="nickname" name="nickname" class="form-input" placeholder="在平台上显示的名称"
                       value="<?php echo h(input('nickname')); ?>">
            </div>

            <div class="form-group">
                <label class="form-label">性别</label>
                <div style="display:flex;gap:12px;padding-top:4px;">
                    <label style="display:flex;align-items:center;gap:4px;cursor:pointer;">
                        <input type="radio" name="gender" value="male" <?php echo input('gender') === 'male' ? 'checked' : ''; ?>> 男
                    </label>
                    <label style="display:flex;align-items:center;gap:4px;cursor:pointer;">
                        <input type="radio" name="gender" value="female" <?php echo input('gender') === 'female' ? 'checked' : ''; ?>> 女
                    </label>
                    <label style="display:flex;align-items:center;gap:4px;cursor:pointer;">
                        <input type="radio" name="gender" value="other" <?php echo input('gender') === 'other' ? 'checked' : ''; ?>> 保密
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="campus">所属校区</label>
                <select id="campus" name="campus" class="form-select">
                    <option value="">不选择</option>
                    <?php foreach ($campuses as $c): ?>
                    <option value="<?php echo h($c); ?>" <?php echo input('campus') === $c ? 'selected' : ''; ?>><?php echo h($c); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">密码 <span class="required">*</span></label>
                <input type="password" id="password" name="password" class="form-input" placeholder="至少8位，包含字母和数字"
                       required minlength="8" autocomplete="new-password">
                <p class="form-hint">至少8位，需包含字母和数字</p>
            </div>

            <div class="form-group">
                <label class="form-label" for="confirm_password">确认密码 <span class="required">*</span></label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-input" placeholder="再次输入密码"
                       required minlength="8" autocomplete="new-password">
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg">注册</button>
        </form>

        <div class="auth-link">已有账号？<a href="/login.php">立即登录</a></div>
    </div>
</div>

<script>
document.getElementById('sendCodeBtn').addEventListener('click', function() {
    var email = document.getElementById('email').value.trim();
    if (!email || email.indexOf('@') === -1) {
        alert('请先填写有效的邮箱地址');
        document.getElementById('email').focus();
        return;
    }
    var btn = this;
    btn.disabled = true;
    btn.textContent = '发送中...';
    fetch('/api/send_code.php', {
        method: 'POST',
        body: new URLSearchParams({ type: 'register', email: email })
    })
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
