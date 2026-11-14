<?php
/**
 * 校区拼车平台 - 退出登录后的落地页
 * 单点登出后 Casdoor 会跳回这里（该地址需登记在 Casdoor 应用的「许可跳转列表」中）
 */
require_once __DIR__ . '/../config.php';
setPageTitle('已退出登录');

include __DIR__ . '/../header.php';
?>

<div class="auth-container">
    <div class="auth-card" style="text-align:center;">
        <div style="width:60px;height:60px;border-radius:999px;margin:0 auto 14px;display:grid;place-items:center;background:var(--ok-soft);color:var(--ok);">
            <svg class="ico" style="width:28px;height:28px;"><use href="#i-check"/></svg>
        </div>
        <h1 class="auth-title">已安全退出</h1>
        <p class="auth-subtitle" style="margin-bottom:20px;">
            您已退出本平台，统一身份认证的登录会话也已一并注销。
        </p>
        <a href="/index.php" class="btn btn-primary btn-block btn-lg">返回首页</a>
        <div class="auth-link" style="margin-top:12px;">
            <a href="/login.php">重新登录</a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../footer.php'; ?>
