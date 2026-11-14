<?php
/**
 * 校区拼车平台 - 资料页
 *
 * 承接从个人中心挪过来的设置项：
 *   基本资料（性别 / 校区，选完即存）· 账号与安全 · 帮助与反馈 · 注销账号
 */
require_once __DIR__ . '/config.php';
requireLogin();
setPageTitle('资料页');

$user = getCurrentUser();

// AUTH_MODE=local 时账号完全在本机，不提供「去认证平台修改」的跳转
$authIsLocal = (AUTH_MODE === 'local');

// ========== 保存基本资料（性别 / 校区）==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && input('action') === 'save_basic') {
    $isAjax = input('ajax') === '1';

    if (!verifyCsrf(input('csrf_token'))) {
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'msg' => '表单已过期，请刷新重试'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        flashMessage('表单已过期，请重试', 'error');
        header('Location: /profile.php');
        exit;
    }

    $gender = input('gender');
    $campus = input('campus');
    if (!in_array($gender, ['male', 'female', 'other'], true)) { $gender = ''; }
    if ($campus !== '' && !in_array($campus, getCampuses(), true)) { $campus = ''; }

    $pdo->prepare("UPDATE users SET gender = ?, campus = ?, updated_at = NOW() WHERE id = ?")
        ->execute([$gender ?: null, $campus ?: null, $user['id']]);

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'msg' => '已保存'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    flashMessage('资料已保存', 'success');
    header('Location: /profile.php');
    exit;
}

$displayName = userDisplayName($user);
$campuses    = getCampuses();
// 无 JS 回退：?del=1 时直接把注销弹窗渲染成打开状态
$delOpen     = input('del', 'get') === '1';

include __DIR__ . '/header.php';
?>

<div class="page-head">
    <div>
        <h1>资料页</h1>
        <div class="page-head-sub">账号资料与安全设置</div>
    </div>
    <a href="/user_center.php" class="btn btn-outline" style="height:36px;padding:0 12px;font-size:13.5px;">
        <svg class="ico"><use href="#i-chev-l"/></svg>返回
    </a>
</div>

<!-- ===== 资料 + 账号与安全（一张卡装完） ===== -->
<div class="card" style="padding:6px 16px;">

    <!-- 身份行：昵称 / 头像在统一认证平台改 -->
    <div class="pf-row">
        <span class="pf-av">
            <?php if (!empty($user['avatar'])): ?>
                <img src="<?php echo h($user['avatar']); ?>" alt="">
            <?php else: ?>
                <?php echo h(mb_substr($displayName, 0, 1)); ?>
            <?php endif; ?>
        </span>
        <span class="k"><?php echo h($displayName); ?></span>
        <?php if (!$authIsLocal): ?>
        <a class="v" href="<?php echo h(CASDOOR_ACCOUNT_URL); ?>" target="_blank" rel="noopener"
           style="margin-left:auto;color:var(--brand);font-weight:600;">去认证平台修改<svg class="ico chev"><use href="#i-chev-r"/></svg></a>
        <?php endif; ?>
    </div>

    <form method="post" action="profile.php" id="basicForm">
        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
        <input type="hidden" name="action" value="save_basic">

        <div class="pf-row">
            <svg class="ico"><use href="#i-user"/></svg><span class="k">性别</span>
            <select name="gender" class="pf-mini">
                <option value="">不设置</option>
                <option value="male"   <?php echo ($user['gender'] ?? '') === 'male'   ? 'selected' : ''; ?>>男</option>
                <option value="female" <?php echo ($user['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>女</option>
                <option value="other"  <?php echo ($user['gender'] ?? '') === 'other'  ? 'selected' : ''; ?>>保密</option>
            </select>
        </div>

        <div class="pf-row">
            <svg class="ico"><use href="#i-pin"/></svg><span class="k">所属校区</span>
            <select name="campus" class="pf-mini">
                <option value="">不选择</option>
                <?php foreach ($campuses as $c): ?>
                <option value="<?php echo h($c); ?>" <?php echo ($user['campus'] ?? '') === $c ? 'selected' : ''; ?>><?php echo h($c); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- 无 JS 时的回退保存按钮（有 JS 会隐藏，并把下面那行提示显示出来） -->
        <div id="pfSaveRow" style="padding:12px 0 6px;">
            <button type="submit" class="btn btn-primary btn-block" style="height:38px;">保存资料</button>
        </div>
    </form>

    <div class="pf-grp">登录与安全<?php echo $authIsLocal ? '' : ' · 由统一认证平台管理'; ?></div>

    <?php
    // 一行「标签 + 值」的账号信息。接入统一认证时整行可点（跳认证平台），本机模式下只是展示。
    $pfRow = function (string $icon, string $label, string $valueHtml, string $extraHtml = '', string $vClass = '') use ($authIsLocal): string {
        $inner = '<svg class="ico"><use href="#' . $icon . '"/></svg><span class="k">' . $label . '</span>'
               . '<span class="v' . ($vClass ? ' ' . $vClass : '') . '">' . $valueHtml . $extraHtml
               . ($authIsLocal ? '' : '<svg class="ico chev"><use href="#i-chev-r"/></svg>')
               . '</span>';
        return $authIsLocal
            ? '<div class="pf-row">' . $inner . '</div>'
            : '<a class="pf-row" href="' . h(CASDOOR_ACCOUNT_URL) . '" target="_blank" rel="noopener">' . $inner . '</a>';
    };
    ?>

    <?php echo $pfRow('i-phone', '手机号',
        '<span class="vv">' . h($user['phone'] ?: '未绑定') . '</span>', '', $user['phone'] ? '' : 'warn'); ?>

    <?php echo $pfRow('i-mail', '邮箱',
        '<span class="vv">' . h($user['email'] ?: '未绑定') . '</span>',
        ($user['email'] && $user['email_verified'])
            ? '<span class="badge badge-ok" style="padding:2px 8px;"><svg class="ico"><use href="#i-check"/></svg>已验证</span>'
            : ''); ?>

    <?php if ($authIsLocal): ?>
    <a class="pf-row" href="/forgot_password.php">
        <svg class="ico"><use href="#i-lock"/></svg><span class="k">密码</span>
        <span class="v"><span class="vv">通过「忘记密码」重置</span><svg class="ico chev"><use href="#i-chev-r"/></svg></span>
    </a>
    <?php else: ?>
    <?php echo $pfRow('i-lock', '密码', '<span class="vv">统一认证平台管理</span>'); ?>
    <?php endif; ?>
</div>

<p class="form-hint" id="pfAutoHint" style="display:none;margin:-4px 4px 14px;">性别、校区选完即保存</p>

<?php if (defined('QQ_GROUP_URL') && QQ_GROUP_URL !== ''): ?>
<a class="pf-link" href="<?php echo h(QQ_GROUP_URL); ?>" target="_blank" rel="noopener">
    <svg class="ico"><use href="#i-chat"/></svg>帮助与反馈<svg class="ico chev"><use href="#i-chev-r"/></svg>
</a>
<?php endif; ?>

<a class="pf-danger" href="/profile.php?del=1" id="pfDelOpen">
    <svg class="ico"><use href="#i-trash"/></svg>注销账号<svg class="ico chev"><use href="#i-chev-r"/></svg>
</a>

<!-- ===== 注销确认弹窗 ===== -->
<div class="pf-scrim<?php echo $delOpen ? ' is-open' : ''; ?>" id="pfDelScrim"></div>
<div class="pf-dialog<?php echo $delOpen ? ' is-open' : ''; ?>" id="pfDelModal" role="dialog" aria-modal="true" aria-labelledby="pfDelTitle">
    <div class="dt" id="pfDelTitle"><svg class="ico"><use href="#i-trash"/></svg>注销账号</div>
    <p class="db">
        注销只删除你在<b>本平台（校区拼车平台）</b>的数据，包括发布的行程、预订记录和个人资料，<b>不可恢复</b>。<br>
        统一认证平台的账号会保留——该账号还用于登录其他接入的网站，一并删除会影响它们；如需一并删除，请联系管理员。
    </p>
    <div class="da">
        <a class="btn btn-outline" href="/profile.php" id="pfDelCancel">取消</a>
        <form method="post" action="delete_account.php" style="flex:1;display:flex;">
            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
            <button type="submit" class="btn btn-danger-solid" style="flex:1;">确认注销</button>
        </form>
    </div>
</div>

<script>
/* 资料页：选完即存 + 注销弹窗。ES5 写法，单独一个 script 块。 */
(function () {
    var form     = document.getElementById('basicForm');
    var saveRow  = document.getElementById('pfSaveRow');
    var hint     = document.getElementById('pfAutoHint');
    var hasFetch = (typeof window.fetch === 'function');

    /* ---- 选完即存：有 fetch 才启用，并藏掉回退按钮、亮出提示 ---- */
    if (form && saveRow && hasFetch) {
        saveRow.style.display = 'none';
        if (hint) { hint.style.display = ''; }

        var busy = false;
        function save() {
            if (busy) { return; }
            busy = true;
            var fd = new FormData(form);
            fd.append('ajax', '1');
            fetch('profile.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    busy = false;
                    if (hint) { hint.textContent = (res && res.ok) ? '已保存' : ((res && res.msg) || '保存失败'); }
                    setTimeout(function () { if (hint) { hint.textContent = '性别、校区选完即保存'; } }, 1600);
                })['catch'](function () {
                    busy = false;
                    if (hint) { hint.textContent = '保存失败，请重试'; }
                });
        }
        var selects = form.getElementsByTagName('select');
        for (var i = 0; i < selects.length; i++) {
            selects[i].onchange = save;
        }
    }

    /* ---- 注销弹窗 ---- */
    var openBtn = document.getElementById('pfDelOpen');
    var modal   = document.getElementById('pfDelModal');
    var scrim   = document.getElementById('pfDelScrim');
    var cancel  = document.getElementById('pfDelCancel');
    if (openBtn && modal) {
        var lastFocus = null;
        function openModal() {
            lastFocus = document.activeElement;
            modal.className += ' is-open';
            if (scrim) { scrim.className += ' is-open'; }
            var c = modal.querySelector('button');
            if (c && c.focus) { c.focus(); }
        }
        function closeModal() {
            modal.className = modal.className.replace(/\s*is-open/, '');
            if (scrim) { scrim.className = scrim.className.replace(/\s*is-open/, ''); }
            if (lastFocus && lastFocus.focus) { lastFocus.focus(); }
        }
        openBtn.onclick = function (e) { if (e && e.preventDefault) { e.preventDefault(); } openModal(); };
        if (cancel) { cancel.onclick = function (e) { if (e && e.preventDefault) { e.preventDefault(); } closeModal(); }; }
        if (scrim)  { scrim.onclick = closeModal; }
        document.addEventListener('keydown', function (e) { if (e && e.key === 'Escape') { closeModal(); } });
    }
})();
</script>

<?php include __DIR__ . '/footer.php'; ?>
