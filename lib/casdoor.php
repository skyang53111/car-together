<?php
/**
 * 校区拼车平台 - Casdoor 统一身份认证封装
 *
 * 依赖 config.php 中的 CASDOOR_* 常量，以及 functions.php 的 isValidPhone()/getClientIP()
 * 仅服务端使用：client_secret 不暴露给前端
 */

// ==================== 基础 ====================

function casdoorBase(): string {
    return rtrim(CASDOOR_BASE, '/');
}

function casdoorIsConfigured(): bool {
    return CASDOOR_CLIENT_ID !== '' && CASDOOR_CLIENT_SECRET !== '';
}

function casdoorLog(string $msg): void {
    error_log('[casdoor] ' . $msg);
}

// ==================== HTTP ====================

function casdoorHttpGet(string $url, array $headers = []): ?array {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false) {
        casdoorLog("GET {$url} failed: {$err}");
        return null;
    }
    $json = json_decode($resp, true);
    if (!is_array($json)) {
        casdoorLog("GET {$url} non-JSON (HTTP {$code}): " . substr((string)$resp, 0, 300));
        return null;
    }
    if ($code >= 400) {
        casdoorLog("GET {$url} HTTP {$code}: " . substr((string)$resp, 0, 300));
        return null;
    }
    return $json;
}

function casdoorHttpPostForm(string $url, array $data): ?array {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false) {
        casdoorLog("POST {$url} failed: {$err}");
        return null;
    }
    $json = json_decode($resp, true);
    if (!is_array($json)) {
        casdoorLog("POST {$url} non-JSON (HTTP {$code}): " . substr((string)$resp, 0, 300));
        return null;
    }
    return $json; // 失败时也让调用方看到 error 字段
}

// ==================== OAuth2 / OIDC ====================

/**
 * 构造授权跳转地址（授权码 + PKCE S256）
 */
function casdoorAuthorizeUrl(string $state, string $codeChallenge, bool $silent = false): string {
    $params = [
        'client_id'             => CASDOOR_CLIENT_ID,
        'redirect_uri'          => CASDOOR_REDIRECT_URI,
        'response_type'         => 'code',
        'scope'                 => CASDOOR_SCOPE,
        'state'                 => $state,
        'code_challenge'        => $codeChallenge,
        'code_challenge_method' => 'S256',
    ];
    if ($silent) {
        $params['prompt'] = 'none'; // 已登录时静默返回，不展示登录界面
    }
    return casdoorBase() . '/login/oauth/authorize?' . http_build_query($params);
}

/**
 * 用授权码换取 token
 * @return array|null 含 access_token / id_token / refresh_token ...
 */
function casdoorExchangeCode(string $code, string $codeVerifier): ?array {
    $res = casdoorHttpPostForm(casdoorBase() . '/api/login/oauth/access_token', [
        'grant_type'    => 'authorization_code',
        'client_id'     => CASDOOR_CLIENT_ID,
        'client_secret' => CASDOOR_CLIENT_SECRET,
        'code'          => $code,
        'redirect_uri'  => CASDOOR_REDIRECT_URI,
        'code_verifier' => $codeVerifier,
    ]);
    if (!$res || empty($res['access_token'])) {
        casdoorLog('token exchange failed: ' . json_encode($res, JSON_UNESCAPED_UNICODE));
        return null;
    }
    return $res;
}

/**
 * 解析 JWT 载荷（不校验签名；用于从 id_token 兜底取 claims）
 */
function casdoorDecodeJwtPayload(string $jwt): ?array {
    $parts = explode('.', $jwt);
    if (count($parts) < 2) return null;
    $payload = strtr($parts[1], '-_', '+/');
    $pad = strlen($payload) % 4;
    if ($pad) $payload .= str_repeat('=', 4 - $pad);
    $decoded = base64_decode($payload);
    if ($decoded === false) return null;
    $json = json_decode($decoded, true);
    return is_array($json) ? $json : null;
}

/**
 * 获取用户 claims：优先 userinfo 接口，失败则退回解析 id_token
 */
function casdoorFetchClaims(array $tokenResponse): ?array {
    $claims = null;

    // 1) 优先 userinfo（OIDC 标准命名：name=昵称 / picture=头像）
    $accessToken = $tokenResponse['access_token'] ?? '';
    if ($accessToken !== '') {
        // 1a) Bearer 头
        $info = casdoorHttpGet(casdoorBase() . '/api/userinfo', ['Authorization: Bearer ' . $accessToken]);
        // 1b) accessToken 查询参数（部分版本）——仅在上一种没拿到可用 claims 时才再试
        if (!$info || !(isset($info['sub']) || isset($info['id']) || isset($info['name']))) {
            $info = casdoorHttpGet(casdoorBase() . '/api/userinfo?accessToken=' . urlencode($accessToken));
        }
        if ($info && (isset($info['sub']) || isset($info['id']) || isset($info['name']))) {
            $claims = $info;
        }
    }

    // 2) id_token 兜底 / 补齐。
    //    id_token 里是 User 结构体的原始字段名（displayName / avatar），与 userinfo 不同名。
    //    两份合并（userinfo 优先），由 casdoorExtractProfile() 兼容两套命名取值，
    //    这样即便应用配了「Token 字段」白名单把其中一份的头像过滤掉，另一份仍能补上。
    if (!empty($tokenResponse['id_token'])) {
        $idClaims = casdoorDecodeJwtPayload($tokenResponse['id_token']);
        if ($idClaims) {
            if ($claims === null) {
                $claims = $idClaims;
            } else {
                // userinfo 与 id_token 合并：userinfo 的「非空」值优先，
                // 但为空串 / null 的字段不能盖掉 id_token 里的真实值。
                // （否则 userinfo 里被过滤成空串的 phone 会把 id_token 里的手机号冲没）
                foreach ($idClaims as $k => $v) {
                    if (!array_key_exists($k, $claims) || $claims[$k] === '' || $claims[$k] === null) {
                        $claims[$k] = $v;
                    }
                }
            }
        }
    }

    if (!$claims) {
        casdoorLog('no claims obtained from userinfo / id_token');
        return null;
    }
    return $claims;
}

/**
 * 单点登出地址（带 id_token_hint 可让 Casdoor 准确定位并注销该会话）
 */
function casdoorLogoutUrl(string $postLogoutRedirect = '', string $idTokenHint = ''): string {
    $params = ['client_id' => CASDOOR_CLIENT_ID];
    if ($postLogoutRedirect !== '') {
        $params['post_logout_redirect_uri'] = $postLogoutRedirect;
    }
    if ($idTokenHint !== '') {
        $params['id_token_hint'] = $idTokenHint;
    }
    return casdoorBase() . '/api/logout?' . http_build_query($params);
}

// ==================== 用户关联 / 同步 ====================

/**
 * 手机号归一化：去掉非数字与 +86 国家码
 */
function casdoorNormalizePhone(?string $phone): string {
    if (!$phone) return '';
    $p = preg_replace('/\D+/', '', $phone);
    if (strlen($p) === 13 && strpos($p, '86') === 0) {
        $p = substr($p, 2); // 8613800138000 → 13800138000
    }
    return $p;
}

/**
 * 从 claims 里提取资料字段。
 *
 * ⚠️ 必须同时兼容两套命名，否则会取不到值（2026-10-06 踩过的坑）：
 *
 *   Casdoor /api/userinfo 按 OIDC 标准命名：
 *       name(昵称) / preferred_username(用户名) / picture(头像)
 *   Casdoor id_token 里则是 User 结构体的原始字段名：
 *       displayName(昵称) / avatar(头像)
 *
 * 之前只读 avatar / displayName，而 userinfo 实际返回的是 picture / name，
 * 于是头像永远取到空串、同步不过去（昵称因为 name 兜底所以没露馅）。
 *
 * @return array{sub:string,email:string,phone:string,nick:string,avatar:string}
 */
function casdoorExtractProfile(array $claims): array {
    // 按优先级取第一个「存在且非空」的字符串值
    $pick = function (array $keys) use ($claims): string {
        foreach ($keys as $k) {
            if (isset($claims[$k]) && is_string($claims[$k]) && trim($claims[$k]) !== '') {
                return trim($claims[$k]);
            }
        }
        return '';
    };

    return [
        'sub'    => $pick(['sub', 'id']),
        'email'  => $pick(['email']),
        'phone'  => casdoorNormalizePhone($pick(['phone', 'phone_number'])),
        'nick'   => $pick(['displayName', 'name', 'preferred_username']),
        'avatar' => $pick(['picture', 'avatar']),
    ];
}

/**
 * 把 Casdoor 资料写入本地 users 表。
 *
 * 规则：非空才覆盖（避免把已有值清空）；手机号写入时同时置 phone_verified=1——
 * Casdoor 侧已绑定手机号即视为已验证，否则个人中心会一直显示「未绑手机」。
 *
 * email / phone 都是唯一键，可能与他人冲突：先整条更新，冲突时退化为只同步
 * 非唯一字段（昵称 / 头像），避免因一人冲突导致整个同步失败。
 *
 * @param int    $userId    本地用户 id
 * @param array  $profile   casdoorExtractProfile() 的返回值
 * @param string $casdoorId 需要一并写入的 casdoor_id（传空串则不写，例如 Webhook 更新场景）
 */
function casdoorApplyProfile(int $userId, array $profile, string $casdoorId = ''): void {
    global $pdo;

    $nick   = $profile['nick']   ?? '';
    $avatar = $profile['avatar'] ?? '';
    $email  = $profile['email']  ?? '';
    $phone  = $profile['phone']  ?? '';

    $sets = ["auth_source = 'casdoor'", "updated_at = NOW()"];
    $vals = [];
    if ($casdoorId !== '') { $sets[] = "casdoor_id = ?"; $vals[] = $casdoorId; }
    if ($nick !== '')      { $sets[] = "nickname = ?";   $vals[] = $nick; }
    if ($avatar !== '')    { $sets[] = "avatar = ?";     $vals[] = $avatar; }
    if ($email !== '')     { $sets[] = "email = ?"; $vals[] = $email; $sets[] = "email_verified = 1"; }
    if ($phone !== '' && isValidPhone($phone)) {
        $sets[] = "phone = ?"; $vals[] = $phone;
        $sets[] = "phone_verified = 1";
    }

    $vals[] = $userId;
    try {
        $pdo->prepare("UPDATE users SET " . implode(', ', $sets) . " WHERE id = ?")->execute($vals);
    } catch (Exception $e) {
        // 邮箱/手机号唯一键冲突时，退化为只同步非唯一字段（不再动 casdoor_id / email / phone）
        casdoorLog('apply profile conflict, fallback: ' . $e->getMessage());
        $sets2 = ["auth_source = 'casdoor'", "updated_at = NOW()"];
        $vals2 = [];
        if ($nick !== '')   { $sets2[] = "nickname = ?"; $vals2[] = $nick; }
        if ($avatar !== '') { $sets2[] = "avatar = ?";   $vals2[] = $avatar; }
        $vals2[] = $userId;
        $pdo->prepare("UPDATE users SET " . implode(', ', $sets2) . " WHERE id = ?")->execute($vals2);
    }
}

/**
 * 按 sub → 邮箱 → 手机号 依次匹配本地用户；命中则同步资料，未命中则新建。
 * @return int|null 本地用户 id
 */
function casdoorFindOrCreateUser(array $claims): ?int {
    global $pdo;

    $profile = casdoorExtractProfile($claims);

    $sub = $profile['sub'];
    if ($sub === '') {
        casdoorLog('claims missing sub/id: ' . json_encode($claims, JSON_UNESCAPED_UNICODE));
        return null;
    }
    $email  = $profile['email'];
    $phone  = $profile['phone'];
    $nick   = $profile['nick'];
    $avatar = $profile['avatar'];

    $userId = 0;

    // 1) casdoor_id
    $st = $pdo->prepare("SELECT id FROM users WHERE casdoor_id = ? LIMIT 1");
    $st->execute([$sub]);
    if ($row = $st->fetch()) $userId = (int)$row['id'];

    // 2) 邮箱
    if (!$userId && $email !== '') {
        $st = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $st->execute([$email]);
        if ($row = $st->fetch()) $userId = (int)$row['id'];
    }

    // 3) 手机号
    if (!$userId && $phone !== '' && isValidPhone($phone)) {
        $st = $pdo->prepare("SELECT id FROM users WHERE phone = ? LIMIT 1");
        $st->execute([$phone]);
        if ($row = $st->fetch()) $userId = (int)$row['id'];
    }

    if ($userId > 0) {
        // 同步资料：非空才覆盖，避免清空已有值；手机号写入同时置 phone_verified=1
        casdoorApplyProfile($userId, $profile, $sub);
        return $userId;
    }

    // 新建账号
    $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
    $validPhone = ($phone !== '' && isValidPhone($phone)) ? $phone : null;
    $sql = "INSERT INTO users
                (casdoor_id, auth_source, phone, email, nickname, avatar,
                 password_hash, status, phone_verified, email_verified, last_ip)
            VALUES (?, 'casdoor', ?, ?, ?, ?, ?, 'active', ?, ?, ?)";
    $params = [
        $sub,
        $validPhone,
        $email !== '' ? $email : null,
        $nick !== '' ? $nick : null,
        $avatar !== '' ? $avatar : null,
        $hash,
        $validPhone ? 1 : 0,
        $email !== '' ? 1 : 0,
        getClientIP(),
    ];

    try {
        $pdo->prepare($sql)->execute($params);
        $newId = (int)$pdo->lastInsertId();
        casdoorLog("created local user #{$newId} for sub={$sub}");
        return $newId;
    } catch (Exception $e) {
        casdoorLog('create user failed: ' . $e->getMessage());
        // 并发下可能已被创建，再查一次
        if ($email !== '') {
            $st = $pdo->prepare("SELECT id FROM users WHERE casdoor_id = ? OR email = ? LIMIT 1");
            $st->execute([$sub, $email]);
        } else {
            $st = $pdo->prepare("SELECT id FROM users WHERE casdoor_id = ? LIMIT 1");
            $st->execute([$sub]);
        }
        if ($row = $st->fetch()) return (int)$row['id'];
        return null;
    }
}
