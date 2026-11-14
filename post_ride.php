<?php
/**
 * 校区拼车平台 - 发布行程
 *
 * 版式（v1.5.1）：一张卡 —— 路线是主角（连接线 + 交换），其余「标签左 / 控件右」。
 * 字段名与提交逻辑保持不变：origin / destination / ride_time / contact / capacity / notes
 * （自定义地址走 origin_custom / destination_custom），服务端校验仍在这里。
 */
require_once __DIR__ . '/config.php';
requireLogin();
setPageTitle('发布行程');

$user        = getCurrentUser();
$campuses    = getCampuses();
$hotDests    = getHotDestinations();
$knownPlaces = array_merge($campuses, $hotDests);   // 判断某个地址是不是“已知地点”

$error   = '';
$errs    = [];          // 字段级错误：origin / destination / ride_time / contact / capacity
$prefill = false;       // 是否带出了上次的行程（用于页头一句提示）
$formData = [
    'origin' => '', 'origin_custom' => '',
    'destination' => '', 'destination_custom' => '',
    'ride_time' => '', 'contact' => '', 'capacity' => '2', 'notes' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf(input('csrf_token'))) {
        $error = '表单已过期，请刷新重试';
    } else {
        $formData = [
            'origin' => input('origin'), 'origin_custom' => input('origin_custom'),
            'destination' => input('destination'), 'destination_custom' => input('destination_custom'),
            'ride_time' => input('ride_time'), 'contact' => input('contact'),
            'capacity' => input('capacity'), 'notes' => input('notes'),
        ];

        // 归一化“自定义地址”：有的浏览器提交 __custom__ + 自定义框，有的直接把自定义值放在 origin
        foreach (['origin', 'destination'] as $f) {
            $v = $formData[$f];
            if ($v !== '' && $v !== '__custom__' && !in_array($v, $knownPlaces, true)) {
                $formData[$f . '_custom'] = $v;
                $formData[$f] = '__custom__';
            }
        }

        $origin   = $formData['origin'] === '__custom__'      ? trim($formData['origin_custom'])      : trim($formData['origin']);
        $dest     = $formData['destination'] === '__custom__' ? trim($formData['destination_custom']) : trim($formData['destination']);
        $contact  = trim($formData['contact']);
        $capacity = (int)$formData['capacity'];

        if ($origin === '')                       { $errs['origin'] = '请选择或输入出发地'; }
        if ($dest === '')                         { $errs['destination'] = '请选择或输入目的地'; }
        elseif ($origin !== '' && $origin === $dest) { $errs['destination'] = '出发地和目的地不能一样'; }
        if ($formData['ride_time'] === '')        { $errs['ride_time'] = '请选择出发时间'; }
        elseif (strtotime($formData['ride_time']) < time() - 3600) { $errs['ride_time'] = '出发时间不能在过去'; }
        if ($contact === '')                      { $errs['contact'] = '留一个能联系到你的方式'; }
        if ($capacity < 1 || $capacity > MAX_SEATS) { $errs['capacity'] = '人数需在 1-' . MAX_SEATS . ' 之间'; }

        if (!$errs) {
            $stmt = $pdo->prepare(
                "INSERT INTO rides (user_id, origin, destination, ride_time, contact, capacity, available_seats, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $user['id'], $origin, $dest, $formData['ride_time'], $contact,
                $capacity, $capacity, $formData['notes'] !== '' ? $formData['notes'] : null,
            ]);
            flashMessage('行程已发布！可在「我的发布」里查看', 'success');
            header('Location: /my_rides.php');
            exit;
        }
        $error = '还有 ' . count($errs) . ' 处要改，看下面标红的地方';
    }
} else {
    // ========== GET：预填 ==========
    if (!empty($user['phone'])) { $formData['contact'] = $user['phone']; }

    $stmt = $pdo->prepare(
        "SELECT origin, destination, capacity, ride_time FROM rides
         WHERE user_id = ? ORDER BY created_at DESC LIMIT 1"
    );
    $stmt->execute([$user['id']]);
    $last = $stmt->fetch();

    if ($last) {
        $prefill = true;
        foreach (['origin', 'destination'] as $f) {
            $v = (string)$last[$f];
            if ($v === '' || $v === '__custom__') { continue; }   // 跳过空值 / 历史脏数据
            if (in_array($v, $knownPlaces, true)) {
                $formData[$f] = $v;
            } else {                                   // 上次是自定义地址
                $formData[$f] = '__custom__';
                $formData[$f . '_custom'] = $v;
            }
        }
        $formData['capacity'] = (string)max(1, min(MAX_SEATS, (int)$last['capacity']));
    }

    // 时间：明天，沿用上次的时分，没有就 08:00
    $hm = ($last && !empty($last['ride_time'])) ? date('H:i', strtotime($last['ride_time'])) : '08:00';
    $formData['ride_time'] = date('Y-m-d\T', strtotime('+1 day')) . $hm;
}

// ========== 展示用 ==========
$ts = strtotime($formData['ride_time']);
if ($ts === false) { $ts = strtotime('+1 day 08:00'); }
$week     = ['日', '一', '二', '三', '四', '五', '六'];
$timeText = date('m-d', $ts) . ' 周' . $week[(int)date('w', $ts)] . ' ' . date('H:i', $ts);

$capacity = max(1, min(MAX_SEATS, (int)$formData['capacity']));

/** 输出地址下拉的选项（校区 / 热门地点 / 自定义） */
function prPlaceOptions(array $campuses, array $hotDests, string $current): void {
    echo '<option value="">选择地点</option>';
    echo '<optgroup label="校区">';
    foreach ($campuses as $c) {
        echo '<option value="' . h($c) . '"' . ($current === $c ? ' selected' : '') . '>' . h($c) . '</option>';
    }
    echo '</optgroup><optgroup label="热门地点">';
    foreach ($hotDests as $d) {
        echo '<option value="' . h($d) . '"' . ($current === $d ? ' selected' : '') . '>' . h($d) . '</option>';
    }
    echo '</optgroup>';
    echo '<option value="__custom__"' . ($current === '__custom__' ? ' selected' : '') . '>自定义地址…</option>';
}
?>
<?php include __DIR__ . '/header.php'; ?>

<div class="page-head">
    <div>
        <h1>发布行程</h1>
        <p class="page-head-sub"><?php echo $prefill ? '已按上次的行程填好，改要改的地方就行' : '填好路线，让顺路的人找到你'; ?></p>
    </div>
</div>

<?php if ($error): ?>
<div class="flash-msg flash-error"><?php echo h($error); ?></div>
<?php endif; ?>

<form method="post" action="post_ride.php" id="postRideForm">
    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">

    <div class="card" style="padding:4px 16px;">

        <!-- ===== 路线（主角）：一个位置一个控件，选了「自定义地址…」下拉就地变输入框 ===== -->
        <div class="pr-route<?php echo (isset($errs['origin']) || isset($errs['destination'])) ? ' pr-err' : ''; ?>">
            <div class="rail"><span class="dot"></span><span class="thread"></span><span class="dot hollow"></span></div>

            <div class="cell">
                <select class="pr-sel" id="originSel" name="origin" required
                        <?php echo $formData['origin'] === '__custom__' ? 'style="display:none;"' : ''; ?>>
                    <?php prPlaceOptions($campuses, $hotDests, $formData['origin']); ?>
                </select>
                <input class="pr-sel pr-sel-input" type="text" id="originInput" name="origin_custom" placeholder="输入出发地"
                       value="<?php echo h($formData['origin_custom']); ?>"
                       <?php echo $formData['origin'] !== '__custom__' ? 'style="display:none;"' : ''; ?>>
            </div>

            <button class="pr-swap" type="button" id="swapBtn" aria-label="交换出发地与目的地">
                <svg class="ico"><use href="#i-swap"/></svg>
            </button>

            <div class="cell">
                <select class="pr-sel" id="destinationSel" name="destination" required
                        <?php echo $formData['destination'] === '__custom__' ? 'style="display:none;"' : ''; ?>>
                    <?php prPlaceOptions($campuses, $hotDests, $formData['destination']); ?>
                </select>
                <input class="pr-sel pr-sel-input" type="text" id="destinationInput" name="destination_custom" placeholder="输入目的地"
                       value="<?php echo h($formData['destination_custom']); ?>"
                       <?php echo $formData['destination'] !== '__custom__' ? 'style="display:none;"' : ''; ?>>
            </div>
        </div>
        <?php if (isset($errs['destination']) || isset($errs['origin'])): ?>
        <div class="pr-err-msg">
            <svg class="ico"><use href="#i-x"/></svg><?php echo h($errs['origin'] ?? $errs['destination']); ?>
        </div>
        <?php endif; ?>

        <!-- ===== 设置行：标签左 / 控件右 ===== -->
        <div class="pr-set">

            <div class="pr-row pr-timerow<?php echo isset($errs['ride_time']) ? ' pr-err' : ''; ?>">
                <span class="k">时间</span>
                <span class="v"><span id="timeText"><?php echo h($timeText); ?></span><svg class="ico chev"><use href="#i-chev-r"/></svg></span>
                <input type="datetime-local" class="pr-native" id="rideTime" name="ride_time"
                       value="<?php echo h($formData['ride_time']); ?>" min="<?php echo date('Y-m-d\TH:i'); ?>" required>
            </div>
            <?php if (isset($errs['ride_time'])): ?>
            <div class="pr-err-msg"><svg class="ico"><use href="#i-x"/></svg><?php echo h($errs['ride_time']); ?></div>
            <?php endif; ?>

            <div class="pr-row<?php echo isset($errs['capacity']) ? ' pr-err' : ''; ?>">
                <span class="k">人数</span>
                <span class="v">
                    <span class="pr-step">
                        <button type="button" id="capMinus" aria-label="减少"><svg class="ico" style="width:15px;height:15px"><use href="#i-minus"/></svg></button>
                        <b id="capNum"><?php echo $capacity; ?></b>
                        <button type="button" id="capPlus" aria-label="增加"><svg class="ico" style="width:15px;height:15px"><use href="#i-plus"/></svg></button>
                    </span>
                </span>
                <input type="hidden" name="capacity" id="capacity" value="<?php echo $capacity; ?>">
            </div>
            <?php if (isset($errs['capacity'])): ?>
            <div class="pr-err-msg"><svg class="ico"><use href="#i-x"/></svg><?php echo h($errs['capacity']); ?></div>
            <?php endif; ?>

            <div class="pr-row<?php echo isset($errs['contact']) ? ' pr-err' : ''; ?>" style="border-bottom:none;">
                <span class="k">联系</span>
                <span class="v"><input class="pr-contact" id="contact" name="contact" placeholder="手机号 / 微信 / QQ"
                       value="<?php echo h($formData['contact']); ?>" required></span>
            </div>
            <?php if (isset($errs['contact'])): ?>
            <div class="pr-err-msg" style="margin-top:0;"><svg class="ico"><use href="#i-x"/></svg><?php echo h($errs['contact']); ?></div>
            <?php endif; ?>
            <p class="pr-tip">手机号 / 微信 / QQ 都行，只有预订的人能看到</p>
        </div>

        <!-- ===== 备注：默认收起（无 JS 时直接显示输入框） ===== -->
        <div class="pr-more" id="moreBtn" style="display:none;">
            <svg class="ico"><use href="#i-plus"/></svg>加一句备注（选填）
        </div>
        <div class="pr-note" id="noteWrap">
            <textarea class="form-textarea" id="notes" name="notes" rows="2"
                      placeholder="例如：准时发车、可送到具体地点、行李较多"><?php echo h($formData['notes']); ?></textarea>
        </div>
    </div>

    <button type="submit" class="btn btn-primary btn-block btn-lg">发布行程</button>
</form>

<script>
/* 发布页交互。ES5 写法，单独一个 script 块（老手机内核抛错不会连累别处）。 */
(function () {
    var form = document.getElementById('postRideForm');
    if (!form) return;

    /* ---- 路线：选了「自定义地址…」就把这一路的下拉就地换成输入框
           （一个位置一个控件：不重复、也不会让下拉被长地址撑长） ---- */
    function toggleCell(selId, inputId, focusIt) {
        var sel = document.getElementById(selId);
        var inp = document.getElementById(inputId);
        if (!sel || !inp) return;
        var custom = (sel.value === '__custom__');
        sel.style.display = custom ? 'none' : 'block';
        inp.style.display = custom ? 'block' : 'none';
        inp.required = custom;
        if (custom && focusIt && inp.focus) { inp.focus(); }
    }
    var originSel = document.getElementById('originSel');
    var destSel = document.getElementById('destinationSel');
    if (originSel) { originSel.onchange = function () { toggleCell('originSel', 'originInput', true); }; }
    if (destSel) { destSel.onchange = function () { toggleCell('destinationSel', 'destinationInput', true); }; }

    /* ---- 交换出发地与目的地 ---- */
    var swap = document.getElementById('swapBtn');
    if (swap && originSel && destSel) {
        swap.onclick = function () {
            var so = originSel.value, sd = destSel.value;
            originSel.value = sd; destSel.value = so;
            var oi = document.getElementById('originInput');
            var di = document.getElementById('destinationInput');
            if (oi && di) { var t = oi.value; oi.value = di.value; di.value = t; }
            toggleCell('originSel', 'originInput', false);
            toggleCell('destinationSel', 'destinationInput', false);
        };
    }

    /* ---- 人数步进 ---- */
    var cap = document.getElementById('capacity');
    var capNum = document.getElementById('capNum');
    var MAX = <?php echo (int)MAX_SEATS; ?>;
    function setCap(n) {
        if (isNaN(n)) { n = 2; }
        if (n < 1) { n = 1; }
        if (n > MAX) { n = MAX; }
        cap.value = n;
        if (capNum) { capNum.textContent = n; }
    }
    var mn = document.getElementById('capMinus');
    var pl = document.getElementById('capPlus');
    if (cap) {
        if (mn) { mn.onclick = function () { setCap(parseInt(cap.value, 10) - 1); }; }
        if (pl) { pl.onclick = function () { setCap(parseInt(cap.value, 10) + 1); }; }
        setCap(parseInt(cap.value, 10));
    }

    /* ---- 时间：原生选择器铺在整行上，选完刷新显示 ---- */
    var rt = document.getElementById('rideTime');
    var tt = document.getElementById('timeText');
    var WEEK = ['日', '一', '二', '三', '四', '五', '六'];
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function updTime() {
        if (!rt || !tt || !rt.value) return;
        var d = new Date(rt.value);
        if (isNaN(d.getTime())) return;
        tt.textContent = pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + ' 周' + WEEK[d.getDay()] +
                         ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }
    if (rt) { rt.onchange = updTime; rt.oninput = updTime; }

    /* ---- 备注：有 JS 才收起 ---- */
    var more = document.getElementById('moreBtn');
    var noteWrap = document.getElementById('noteWrap');
    if (more && noteWrap) {
        more.style.display = 'flex';
        noteWrap.style.display = 'none';
        more.onclick = function () {
            noteWrap.style.display = 'block';
            more.style.display = 'none';
            var t = document.getElementById('notes');
            if (t && t.focus) { t.focus(); }
        };
    }

    /* ---- 有字段报错就滚到第一处 ---- */
    var firstErr = form.querySelector('.pr-err');
    if (firstErr && firstErr.scrollIntoView) { firstErr.scrollIntoView({ block: 'center' }); }
})();
</script>

<?php include __DIR__ . '/footer.php'; ?>
