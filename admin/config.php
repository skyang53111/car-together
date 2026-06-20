<?php
/**
 * 校区拼车平台 - 后台系统配置
 */
require_once __DIR__ . '/../config.php';
requireAdmin();
setPageTitle('系统配置');
$adminActivePage = 'config';

$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf(input('csrf_token'))) {
        flashMessage('表单已过期', 'error');
    } else {
        $configs = [
            'site_name'                => input('site_name'),
            'site_description'         => input('site_description'),
            'max_seats'                => input('max_seats'),
            'announcement'             => input('announcement'),
            'registration_verification' => input('registration_verification', 'post', '0'),
            'email_notifications'      => input('email_notifications', 'post', '0'),
        ];

        foreach ($configs as $key => $value) {
            $stmt = $pdo->prepare("INSERT INTO site_config (config_key, config_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE config_value = ?");
            $stmt->execute([$key, $value, $value]);
        }

        $saved = true;
    }
}

$stmt = $pdo->query("SELECT config_key, config_value FROM site_config");
$currentConfig = [];
while ($row = $stmt->fetch()) {
    $currentConfig[$row['config_key']] = $row['config_value'];
}

include __DIR__ . '/header.php';
?>

<h1 style="margin-bottom:16px;">🔧 系统配置</h1>

<?php if ($saved): ?>
<div class="flash-msg flash-success">✅ 配置已保存</div>
<?php endif; ?>

<div class="card">
    <form method="post" action="config.php">
        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">

        <h3 style="font-size:16px;margin-bottom:12px;">站点设置</h3>
        <div class="form-group">
            <label class="form-label">站点名称</label>
            <input type="text" name="site_name" class="form-input" value="<?php echo h($currentConfig['site_name'] ?? SITE_NAME); ?>">
        </div>
        <div class="form-group">
            <label class="form-label">站点描述</label>
            <input type="text" name="site_description" class="form-input" value="<?php echo h($currentConfig['site_description'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label class="form-label">最大搭载人数</label>
            <input type="number" name="max_seats" class="form-input" value="<?php echo h($currentConfig['max_seats'] ?? '6'); ?>" min="1" max="20">
        </div>
        <div class="form-group">
            <label class="form-label">全站公告（支持HTML）</label>
            <textarea name="announcement" class="form-textarea" rows="3"><?php echo h($currentConfig['announcement'] ?? ''); ?></textarea>
        </div>

        <h3 style="font-size:16px;margin:20px 0 12px;">注册设置</h3>
        <div class="form-group">
            <label class="form-label">注册邮箱验证</label>
            <select name="registration_verification" class="form-select">
                <option value="1" <?php echo ($currentConfig['registration_verification'] ?? '1') === '1' ? 'selected' : ''; ?>>开启（需通过邮箱验证码注册）</option>
                <option value="0" <?php echo ($currentConfig['registration_verification'] ?? '1') === '0' ? 'selected' : ''; ?>>关闭（可直接注册，无需验证码）</option>
            </select>
            <p class="form-hint">关闭后用户注册时不需要输入邮箱验证码</p>
        </div>

        <h3 style="font-size:16px;margin:20px 0 12px;">通知设置</h3>
        <div class="form-group">
            <label class="form-label">邮件通知</label>
            <select name="email_notifications" class="form-select">
                <option value="1" <?php echo ($currentConfig['email_notifications'] ?? '1') === '1' ? 'selected' : ''; ?>>开启（预订、取消、满员时发送邮件通知）</option>
                <option value="0" <?php echo ($currentConfig['email_notifications'] ?? '1') === '0' ? 'selected' : ''; ?>>关闭（不发送业务邮件通知）</option>
            </select>
            <p class="form-hint">控制预订成功、取消预订、拼车满员、拼车取消等场景的邮件通知。验证码邮件不受此开关影响。</p>
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg">保存配置</button>
    </form>
</div>

<?php include __DIR__ . '/footer.php'; ?>
