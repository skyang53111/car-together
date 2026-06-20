<?php
/**
 * 校区拼车平台 - 发布拼车
 */
require_once __DIR__ . '/config.php';
requireLogin();
setPageTitle('发布拼车');

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf(input('csrf_token'))) {
        $error = '表单已过期，请刷新页面重试';
    } else {
        $origin   = input('origin');
        $dest     = input('destination');
        $rideTime = input('ride_time');
        $contact  = input('contact');
        $capacity = (int)input('capacity');
        $notes    = input('notes');

        if (empty($origin) || empty($dest) || empty($rideTime) || empty($contact)) {
            $error = '请填写所有必填项';
        } elseif ($capacity < 1 || $capacity > MAX_SEATS) {
            $error = '座位数必须在1-' . MAX_SEATS . '之间';
        } elseif (strtotime($rideTime) < time()) {
            $error = '出发时间不能是过去的时间';
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO rides (user_id, origin, destination, ride_time, contact, capacity, available_seats, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $_SESSION['user_id'],
                $origin, $dest, $rideTime, $contact,
                $capacity, $capacity,
                $notes ?: null
            ]);
            $success = true;
        }
    }
}

$campuses = getCampuses();
$hotDest = getHotDestinations();
?>
<?php include __DIR__ . '/header.php'; ?>

<div class="auth-container" style="max-width:600px;">
    <div class="auth-card">
        <h1 class="auth-title">发布拼车</h1>
        <p class="auth-subtitle">填写拼车信息，快速找到同行人</p>

        <?php if ($error): ?>
        <div class="flash-msg flash-error"><?php echo h($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="flash-msg flash-success">✅ 拼车发布成功！</div>
        <div class="auth-link"><a href="/index.php">返回首页查看</a> · <a href="/post_ride.php">再发一条</a></div>
        <?php else: ?>
        <form method="post" action="post_ride.php">
            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">

            <div class="form-group">
                <label class="form-label" for="origin">出发地 <span class="required">*</span></label>
                <input type="text" id="origin" name="origin" class="form-input" placeholder="例如：南校区西门" value="<?php echo h(input('origin')); ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="destination">目的地 <span class="required">*</span></label>
                <input type="text" id="destination" name="destination" class="form-input" placeholder="例如：火车站" list="hot-dest" value="<?php echo h(input('destination')); ?>" required>
                <datalist id="hot-dest">
                    <?php foreach ($hotDest as $d): ?>
                    <option value="<?php echo h($d); ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>

            <div class="form-group">
                <label class="form-label" for="ride_time">出发时间 <span class="required">*</span></label>
                <input type="datetime-local" id="ride_time" name="ride_time" class="form-input" value="<?php echo h(input('ride_time')); ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="contact">联系方式 <span class="required">*</span></label>
                <input type="text" id="contact" name="contact" class="form-input" placeholder="微信/QQ/手机号" value="<?php echo h(input('contact')); ?>" required>
                <p class="form-hint">预订成功后对方才可查看</p>
            </div>

            <div class="form-group">
                <label class="form-label" for="capacity">可搭载人数</label>
                <select id="capacity" name="capacity" class="form-select">
                    <?php for ($i = 1; $i <= MAX_SEATS; $i++): ?>
                    <option value="<?php echo $i; ?>" <?php echo (input('capacity') ?: '3') == $i ? 'selected' : ''; ?>><?php echo $i; ?>人座</option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="notes">备注说明</label>
                <textarea id="notes" name="notes" class="form-textarea" placeholder="例如：需要帮忙搬行李、拼车费用AA等" rows="3"><?php echo h(input('notes')); ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg">发布拼车</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
