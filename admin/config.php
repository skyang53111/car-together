<?php
/**
 * 校区拼车平台 - 后台系统配置
 */
require_once __DIR__ . '/../config.php';
requireAdmin();
setPageTitle('系统配置');
$adminActivePage = 'config';

$saved = false;

// 已下线、前台从不读取的旧配置项：保存时顺手清掉（保留着也不会生效，属历史遗留）
$retiredKeys = ['site_name', 'site_description', 'max_seats', 'announcement', 'registration_verification'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf(input('csrf_token'))) {
        flashMessage('表单已过期', 'error');
    } else {
        // 弹窗频率只接受这三个值，其余一律回落到 once
        $freq = input('announcement_frequency');
        if (!in_array($freq, ['always', 'daily', 'once'], true)) {
            $freq = 'once';
        }

        $configs = [
            'announcement_enabled'   => input('announcement_enabled', 'post', '0') === '1' ? '1' : '0',
            'announcement_title'     => input('announcement_title'),
            'announcement_body'      => input('announcement_body'),   // 允许 HTML，此处不转义
            'announcement_frequency' => $freq,
            'email_notifications'    => input('email_notifications', 'post', '0') === '1' ? '1' : '0',
        ];

        $stmt = $pdo->prepare("INSERT INTO site_config (config_key, config_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE config_value = ?");
        foreach ($configs as $key => $value) {
            $stmt->execute([$key, $value, $value]);
        }

        $ph = implode(',', array_fill(0, count($retiredKeys), '?'));
        $pdo->prepare("DELETE FROM site_config WHERE config_key IN ($ph)")->execute($retiredKeys);

        $saved = true;
    }
}

// 读取当前配置
$stmt = $pdo->query("SELECT config_key, config_value FROM site_config");
$currentConfig = [];
while ($row = $stmt->fetch()) {
    $currentConfig[$row['config_key']] = $row['config_value'];
}

$annEnabled = ($currentConfig['announcement_enabled'] ?? '0') === '1';
$annFreq    = $currentConfig['announcement_frequency'] ?? 'once';
$annTitle   = $currentConfig['announcement_title'] ?? '';
$annBody    = $currentConfig['announcement_body'] ?? '';

include __DIR__ . '/header.php';
?>

<h1 style="margin-bottom:16px;">🔧 系统配置</h1>

<?php if ($saved): ?>
<div class="flash-msg flash-success">✅ 配置已保存</div>
<?php endif; ?>

<form method="post" action="config.php">
    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">

    <div class="card">
        <h3 style="font-size:16px;margin-bottom:6px;">📢 首页公告</h3>
        <p class="form-hint" style="margin-bottom:14px;">
            开启后，用户打开首页会弹出公告。标题与正文都不填时不会弹。
        </p>

        <div class="form-group">
            <label class="form-label">公告开关</label>
            <select name="announcement_enabled" class="form-select">
                <option value="1" <?php echo $annEnabled ? 'selected' : ''; ?>>开启（在首页弹窗提醒）</option>
                <option value="0" <?php echo !$annEnabled ? 'selected' : ''; ?>>关闭（不弹公告）</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">公告标题</label>
            <input type="text" name="announcement_title" class="form-input" maxlength="60"
                   value="<?php echo h($annTitle); ?>" placeholder="例如：国庆假期行程提醒">
        </div>

        <div class="form-group">
            <label class="form-label">公告正文（支持 HTML）</label>
            <textarea name="announcement_body" class="form-textarea" rows="6"><?php echo h($annBody); ?></textarea>
            <p class="form-hint">
                直接写文字即可，回车换行会被保留。需要加粗、链接、列表时可写 HTML，例如：
                <code>&lt;p&gt;假期请提前 &lt;b&gt;1 天&lt;/b&gt; 发布行程&lt;/p&gt;</code>。
                支持 <code>&lt;p&gt;</code> <code>&lt;b&gt;</code> <code>&lt;a&gt;</code>
                <code>&lt;ul&gt;&lt;li&gt;</code> <code>&lt;img&gt;</code> 等标签。
            </p>
        </div>

        <div class="form-group">
            <label class="form-label">弹窗频率</label>
            <select name="announcement_frequency" class="form-select">
                <option value="once"   <?php echo $annFreq === 'once'   ? 'selected' : ''; ?>>每个用户只弹一次（推荐）</option>
                <option value="daily"  <?php echo $annFreq === 'daily'  ? 'selected' : ''; ?>>每个用户每天弹一次</option>
                <option value="always" <?php echo $annFreq === 'always' ? 'selected' : ''; ?>>每次打开首页都弹</option>
            </select>
            <p class="form-hint">
                选「只弹一次」或「每天弹一次」时，用户点过「我知道了」就不再弹；
                你改动标题或正文后，令牌会变，所有人会重新收到一次新公告。
            </p>
        </div>
    </div>

    <div class="card">
        <h3 style="font-size:16px;margin-bottom:12px;">📧 通知设置</h3>
        <div class="form-group">
            <label class="form-label">邮件通知</label>
            <select name="email_notifications" class="form-select">
                <option value="1" <?php echo ($currentConfig['email_notifications'] ?? '1') === '1' ? 'selected' : ''; ?>>开启（预订、取消、满员时发送邮件通知）</option>
                <option value="0" <?php echo ($currentConfig['email_notifications'] ?? '1') === '0' ? 'selected' : ''; ?>>关闭（不发送业务邮件通知）</option>
            </select>
            <p class="form-hint">控制预订成功、取消预订、行程已满、行程取消等场景的邮件通知。验证码邮件不受此开关影响。</p>
        </div>
    </div>

    <button type="submit" class="btn btn-primary btn-block btn-lg">保存配置</button>
</form>

<?php include __DIR__ . '/footer.php'; ?>
