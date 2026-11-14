<?php
/**
 * 校区拼车平台 - 认证 API
 * POST /api/v1/auth_v2.php
 *
 * 支持的操作：
 *   wx_login       — 微信登录（返回 need_bind 标记）
 *   password_login — 手机号+密码登录
 *   check          — 检查/刷新登录态
 *   bind_phone     — 微信登录后关联已有手机号账号
 *   bind_status    — 查询当前账号是否已绑定手机号
 */
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/../../lib/casdoor.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// AUTH_MODE 非 local 时统一走认证服务，旧的微信登录 / 密码登录不再开放
if (AUTH_MODE !== 'local' && in_array($action, ['wx_login', 'password_login'], true)) {
    jsonResponse(['code' => -1, 'message' => '请使用统一身份认证登录'], 200);
}

// ============================================================
// 微信登录
// ============================================================
if ($action === 'wx_login') {
    $code = $_POST['code'] ?? '';
    if (empty($code)) jsonResponse(['code' => -1, 'message' => '缺少 code 参数'], 200);

    $appid  = defined('WX_APPID') ? WX_APPID : '';
    $secret = defined('WX_SECRET') ? WX_SECRET : '';
    if (empty($appid) || empty($secret)) jsonResponse(['code' => -1, 'message' => '小程序未配置'], 200);

    $url = "https://api.weixin.qq.com/sns/jscode2session?appid={$appid}&secret={$secret}&js_code={$code}&grant_type=authorization_code";
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8, CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($resp === false) jsonResponse(['code' => -2, 'message' => '微信连接失败: ' . $err], 200);
    $wx = json_decode($resp, true);
    if (!empty($wx['errcode'])) jsonResponse(['code' => -2, 'message' => $wx['errmsg'] ?? '微信错误'], 200);
    if (empty($wx['openid'])) jsonResponse(['code' => -2, 'message' => '微信返回异常'], 200);

    $openid = $wx['openid'];
    global $pdo;
    $st = $pdo->prepare("SELECT id, phone, email, nickname, role, status FROM users WHERE openid = ? LIMIT 1");
    $st->execute([$openid]);
    $user = $st->fetch();

    $isNew = false;
    if (!$user) {
        $defaultHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
        $pdo->prepare("INSERT INTO users (openid, nickname, password_hash, status, created_at, updated_at) VALUES (?, '微信用户', ?, 'active', NOW(), NOW())")->execute([$openid, $defaultHash]);
        $uid = (int)$pdo->lastInsertId();
        $user = ['id' => $uid, 'phone' => null, 'email' => null, 'nickname' => '微信用户', 'role' => 'user', 'status' => 'active'];
        $isNew = true;
    } elseif ($user['status'] !== 'active') {
        jsonResponse(['code' => -1, 'message' => '账号已禁用'], 200);
    } else {
        $uid = (int)$user['id'];
    }

    $token = generateToken($uid);
    $needBind = empty($user['phone']);

    success([
        'token' => $token,
        'need_bind' => $needBind,
        'user' => [
            'id' => $uid, 'phone' => $user['phone'], 'email' => $user['email'],
            'nickname' => $user['nickname'], 'role' => $user['role'], 'is_new' => $isNew,
        ],
    ]);
}

// ============================================================
// 手机号+密码登录
// ============================================================
if ($action === 'password_login') {
    $phone = $_POST['phone'] ?? '';
    $pwd   = $_POST['password'] ?? '';
    if (empty($phone) || empty($pwd)) jsonResponse(['code' => -1, 'message' => '请输入手机号和密码'], 200);

    global $pdo;
    $st = $pdo->prepare("SELECT * FROM users WHERE phone = ? LIMIT 1");
    $st->execute([$phone]);
    $user = $st->fetch();
    if (!$user || !password_verify($pwd, $user['password_hash'])) jsonResponse(['code' => -1, 'message' => '手机号或密码错误'], 200);
    if ($user['status'] !== 'active') jsonResponse(['code' => -1, 'message' => '账号已禁用'], 200);

    $token = generateToken((int)$user['id']);
    success([
        'token' => $token,
        'need_bind' => false,
        'user' => ['id' => (int)$user['id'], 'phone' => $user['phone'], 'email' => $user['email'],
                   'nickname' => $user['nickname'], 'role' => $user['role']],
    ]);
}

// ============================================================
// 检查/刷新登录态
// ============================================================
if ($action === 'check') {
    $uid = getAuthUserId();
    if ($uid <= 0) { success(null, '未登录'); }
    global $pdo;
    $st = $pdo->prepare("SELECT id, phone, email, nickname, role, phone_verified, email_verified, campus FROM users WHERE id = ? AND status = 'active'");
    $st->execute([$uid]);
    $u = $st->fetch();
    if (!$u) { success(null, '未登录'); }
    success([
        'id' => (int)$u['id'], 'phone' => $u['phone'], 'email' => $u['email'],
        'nickname' => $u['nickname'], 'role' => $u['role'],
        'phone_verified' => (bool)$u['phone_verified'],
        'email_verified' => (bool)$u['email_verified'], 'campus' => $u['campus'],
    ]);
}

// ============================================================
// 绑定手机号：微信登录后关联已有网页端账号
// ============================================================
if ($action === 'bind_phone') {
    $uid = requireAuth();
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($phone) || strlen($phone) !== 11) {
        error('请输入正确的手机号');
    }
    if (empty($password)) {
        error('请输入密码');
    }

    global $pdo;

    $st = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $st->execute([$uid]);
    $currentUser = $st->fetch();

    if (!$currentUser) {
        error('用户不存在，请重新登录');
    }
    if (!empty($currentUser['phone'])) {
        error('当前账号已绑定手机号，无需重复绑定');
    }

    $st = $pdo->prepare("SELECT * FROM users WHERE phone = ? LIMIT 1");
    $st->execute([$phone]);
    $targetUser = $st->fetch();

    if ($targetUser) {
        if (!password_verify($password, $targetUser['password_hash'])) {
            error('手机号或密码错误');
        }
        if ($targetUser['status'] !== 'active') {
            error('该账号已被禁用');
        }

        $targetId = (int)$targetUser['id'];
        $currentOpenid = $currentUser['openid'];

        if (!empty($targetUser['openid']) && $targetUser['openid'] !== $currentOpenid) {
            error('该手机号已关联其他微信账号');
        }

        $pdo->beginTransaction();
        try {
            // ★ 先清空源用户的 openid，再用 DELETE 删除，避免 uk_openid 唯一键冲突
            if (!empty($currentOpenid)) {
                $pdo->prepare("UPDATE users SET openid = NULL WHERE id = ?")->execute([$uid]);
            }
            if (empty($targetUser['openid']) && !empty($currentOpenid)) {
                $pdo->prepare("UPDATE users SET openid = ? WHERE id = ?")->execute([$currentOpenid, $targetId]);
            }

            // 数据转移前，先删除源用户中与目标用户冲突的 bookings（uk_user_ride 唯一键）
            $conflictSt = $pdo->prepare("SELECT ride_id FROM bookings WHERE user_id = ?");
            $conflictSt->execute([$targetId]);
            $conflictRideIds = $conflictSt->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($conflictRideIds)) {
                $placeholders = implode(',', array_fill(0, count($conflictRideIds), '?'));
                $pdo->prepare("DELETE FROM bookings WHERE user_id = ? AND ride_id IN ($placeholders)")
                    ->execute(array_merge([$uid], $conflictRideIds));
            }

            $pdo->prepare("UPDATE rides SET user_id = ? WHERE user_id = ?")->execute([$targetId, $uid]);
            $pdo->prepare("UPDATE bookings SET user_id = ? WHERE user_id = ?")->execute([$targetId, $uid]);
            $pdo->prepare("UPDATE notifications SET user_id = ? WHERE user_id = ?")->execute([$targetId, $uid]);
            $pdo->prepare("UPDATE user_tokens SET user_id = ? WHERE user_id = ?")->execute([$targetId, $uid]);
            $pdo->prepare("DELETE FROM users WHERE id = ? AND phone IS NULL")->execute([$uid]);

            $pdo->commit();

            $newToken = generateToken($targetId);

            $st = $pdo->prepare("SELECT id, phone, email, nickname, role, phone_verified, email_verified, campus FROM users WHERE id = ?");
            $st->execute([$targetId]);
            $mergedUser = $st->fetch();

            success([
                'token' => $newToken,
                'user' => [
                    'id' => (int)$mergedUser['id'],
                    'phone' => $mergedUser['phone'],
                    'email' => $mergedUser['email'],
                    'nickname' => $mergedUser['nickname'] ?: $currentUser['nickname'],
                    'role' => $mergedUser['role'],
                    'phone_verified' => (bool)$mergedUser['phone_verified'],
                    'email_verified' => (bool)$mergedUser['email_verified'],
                    'campus' => $mergedUser['campus'],
                ],
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('[bind_phone_error] ' . $e->getMessage());
            error('账号合并失败，请稍后重试');
        }
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE users SET phone = ?, password_hash = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$phone, $hash, $uid]);

        success([
            'token' => null,
            'user' => null,
        ]);
    }
}

// ============================================================
// 查询当前账号是否已绑定手机号
// ============================================================
if ($action === 'bind_status') {
    $uid = getAuthUserId();
    $bound = false;

    if ($uid > 0) {
        global $pdo;
        $st = $pdo->prepare("SELECT phone FROM users WHERE id = ? LIMIT 1");
        $st->execute([$uid]);
        $u = $st->fetch();
        $bound = $u && !empty($u['phone']);
    }

    success(['bound' => $bound]);
}

// ============================================================
// 统一身份认证登录（Casdoor）—— 客户端用授权码换取本地 token
// 客户端流程：打开 Casdoor 授权页 → 拿到 code → POST 本接口
// 参数：code（必填）、code_verifier（若授权时用了 PKCE）
// 返回：本地 token（与其它接口一致的 Bearer 令牌）
// ============================================================
if ($action === 'casdoor_login') {
    $code     = $_POST['code'] ?? $_GET['code'] ?? '';
    $verifier = $_POST['code_verifier'] ?? $_GET['code_verifier'] ?? '';

    if (empty($code)) {
        jsonResponse(['code' => -1, 'message' => '缺少 code 参数'], 200);
    }
    if (!casdoorIsConfigured()) {
        jsonResponse(['code' => -1, 'message' => '统一身份认证未配置'], 200);
    }
    if (!checkRateLimit('casdoor_login', RATE_LOGIN_MAX, RATE_LOGIN_WINDOW)) {
        jsonResponse(['code' => -1, 'message' => '请求过于频繁，请稍后再试'], 200);
    }

    $token = casdoorExchangeCode($code, $verifier);
    if (!$token) {
        jsonResponse(['code' => -2, 'message' => '换取令牌失败'], 200);
    }

    $claims = casdoorFetchClaims($token);
    if (!$claims) {
        jsonResponse(['code' => -2, 'message' => '获取用户信息失败'], 200);
    }

    $uid = casdoorFindOrCreateUser($claims);
    if (!$uid) {
        jsonResponse(['code' => -3, 'message' => '账号关联失败'], 200);
    }

    global $pdo;
    $st = $pdo->prepare("SELECT id, phone, email, nickname, role, status FROM users WHERE id = ? LIMIT 1");
    $st->execute([$uid]);
    $user = $st->fetch();
    if (!$user || $user['status'] !== 'active') {
        jsonResponse(['code' => -1, 'message' => '账号已禁用'], 200);
    }

    $localToken = generateToken((int)$user['id']);
    success([
        'token'     => $localToken,
        'need_bind' => false,
        'user'      => [
            'id'       => (int)$user['id'],
            'phone'    => $user['phone'],
            'email'    => $user['email'],
            'nickname' => $user['nickname'],
            'role'     => $user['role'],
        ],
    ]);
}

jsonResponse(['code' => -1, 'message' => '未知操作'], 200);
