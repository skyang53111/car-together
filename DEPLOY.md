# 部署指南

从零把校区拼车平台跑起来。全程只需要 PHP + MySQL + 一个域名，没有构建步骤、没有 Composer。

## 目录

1. [环境要求](#1-环境要求)
2. [上传代码](#2-上传代码)
3. [文件权限](#3-文件权限最容易踩坑的一步)
4. [创建数据库](#4-创建数据库)
5. [本地配置](#5-本地配置configlocalphp)
6. [站点配置](#6-站点配置configphp)
7. [Web 服务器](#7-web-服务器)
8. [HTTPS](#8-https)
9. [邮件发送](#9-邮件发送)
10. [验收清单](#10-验收清单)
11. [升级与回滚](#11-升级与回滚)
12. [常见问题](#12-常见问题)
13. [安全建议](#13-安全建议)

---

## 1. 环境要求

| 项目 | 要求 |
| --- | --- |
| 操作系统 | Linux（CentOS / Ubuntu / Debian 均可），Windows 也能跑但仅建议本地开发 |
| Web 服务器 | Nginx 1.18+（推荐）/ Apache 2.4+ |
| PHP | 8.0 / 8.1 / 8.2（推荐 8.1）。代码用到 `match`、`str_starts_with`，**PHP 7.4 跑不起来** |
| PHP 扩展 | `pdo_mysql`、`openssl`、`mbstring`（缺 `openssl` 会导致验证码邮件发不出去） |
| 数据库 | MySQL 5.7+ / MariaDB 10.3+ |
| 磁盘 | 50MB 足够 |

---

## 2. 上传代码

把仓库里的全部文件上传到网站根目录（下文以 `/www/wwwroot/your-domain.com` 为例，换成你自己的路径）。
**不要**上传 `config.local.php`——那是服务器本地文件，见第 5 节。

宝塔面板用户：网站 → 根目录 → 上传压缩包 → 解压，然后确认解压出来的文件在根目录下
（`index.php` 与网站根目录同级），而不是多套了一层文件夹。

---

## 3. 文件权限（最容易踩坑的一步）

```bash
cd /www/wwwroot/your-domain.com

# 属主改成 Web 服务用户（宝塔是 www，Debian/Ubuntu 常见 www-data）
chown -R www:www .

# 目录 755、文件 644
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;
```

**为什么强调这个**：如果文件权限是 `700`，PHP 能不能执行取决于 Web 用户是不是属主；
属主不对时，`lib/`、`oauth/` 这类目录会直接返回 **403 Access denied**。
以后每次新增目录，同样要保证「属主 www、目录 755、文件 644」。

---

## 4. 创建数据库

```sql
CREATE DATABASE carpool DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

然后导入结构。**全新安装**用 `setup.sql`（一次建全所有表，含小程序与订阅消息用的表）：

```bash
mysql -u carpool -p carpool < /www/wwwroot/your-domain.com/setup.sql
```

**已有数据的老站点升级**不要用 `setup.sql`（它会 DROP 重建），按顺序执行：

```bash
mysql -u carpool -p carpool < migrate_v2.sql
mysql -u carpool -p carpool < migrate_v3.sql
mysql -u carpool -p carpool < migrate_v4.sql
mysql -u carpool -p carpool < migrate_v5.sql   # 统一认证字段
# 需要小程序 / 订阅消息时再执行 setup_miniprogram.sql、setup_subscribe.sql
```

导入后应有这些表：`users`、`rides`、`bookings`、`notifications`、`verification_codes`、`rate_limits`、
`site_config`，以及小程序相关的 `user_tokens`、`user_subscriptions`、`wechat_tokens`。

默认管理员：`admin@campus.com` / `admin123456`——**登录后第一件事就是改掉它**。

---

## 5. 本地配置（config.local.php）

```bash
cp config.local.example.php config.local.php
```

编辑 `config.local.php`，最少要填数据库这一段：

```php
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'carpool');
define('DB_USER', 'carpool');
define('DB_PASS', '你的数据库密码');
```

这个文件**只存在于服务器**，已在 `.gitignore` 中；因为代码更新不会覆盖它，所以升级时不用重新填。

---

## 6. 站点配置（config.php）

`config.php` 是随版本走的通用配置，需要按自己的站点改两处：

```php
define('SITE_NAME', '校区拼车平台');                         // 站点名，页面标题与邮件落款都用它
define('SITE_URL',  'https://your-domain.com');              // 你的域名，统一认证回调用
```

同一文件里还有几组可调项：

| 常量 | 作用 | 默认 |
| --- | --- | --- |
| `TIMEZONE` | 时区 | `Asia/Shanghai` |
| `CAMPUSES` | 校区列表（JSON 数组），发布/注册/资料页的下拉项 | 南校区 / 北校区 / 东校区 |
| `MAX_SEATS` | 单次行程最多可带人数 | 6 |
| `RIDES_PER_PAGE` | 首页每页条数 | 12 |
| `AUTH_MODE` | 认证模式 `local` / `both` / `casdoor` | `local` |
| `QQ_GROUP_URL` | 帮助与反馈的加群链接，留空则不显示该入口 | 空 |

> 校区与热门目的地：`config.php` 的 `CAMPUSES` 决定校区的下拉项；
> 热门目的地列表在 `functions.php` 的 `getHotDestinations()` 里，按自己学校改。

---

## 7. Web 服务器

### Nginx（推荐）

站点根目录指向项目文件夹，PHP 交给 php-fpm。伪静态 / 站点配置参考：

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /www/wwwroot/your-domain.com;
    index index.php index.html;

    # 本地配置文件只允许 PHP 执行，禁止被直接下载
    location ~ ^/config\.local\.php$ {
        deny all;
    }

    # 静态资源长缓存（引用处带 ?v=N，可放心缓存）
    location ~* \.(css|js|svg|png|jpg|jpeg|webp|ico|woff2?)$ {
        expires 30d;
        access_log off;
        try_files $uri =404;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_pass unix:/tmp/php-cgi-81.sock;   # 换成你的 php-fpm 地址
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

项目以纯 PHP 文件为主，几乎不依赖 URL 重写；上面的 `try_files` 只是兜底。

### Apache

根目录放一个 `.htaccess` 即可（模块 `mod_rewrite`、`mod_headers`）：

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php?$1 [QSA,L]
</IfModule>
```

---

## 8. HTTPS

后台登录、邮箱验证码都建议跑在 HTTPS 下。宝塔面板：网站 → SSL → Let's Encrypt → 申请 →
打开「强制 HTTPS」。证书签发后记得把 `config.php` 里的 `SITE_URL` 改成 `https://`。

如果用了统一认证，回调地址要与 `SITE_URL` 完全一致（含 http/https），否则会报跳转地址不匹配。

---

## 9. 邮件发送

验证码与通知邮件走 SMTP，配置写在 `config.local.php`：

```php
define('MAIL_HOST', 'smtp.office365.com');
define('MAIL_PORT', 587);
define('MAIL_SECURE', 'tls');            // tls / ssl
define('MAIL_USERNAME', 'your@email.com');
define('MAIL_PASSWORD', '应用密码或授权码');
define('MAIL_FROM_EMAIL', 'your@email.com');
define('MAIL_FROM_NAME', '校区拼车平台');
```

常见服务商：

| 邮箱 | SMTP 服务器 | 端口 | 加密 | 密码 |
| --- | --- | --- | --- | --- |
| Outlook / Microsoft 365 | `smtp.office365.com` | 587 | TLS | 应用密码 |
| QQ 邮箱 | `smtp.qq.com` | 465 | SSL | 授权码 |
| 163 邮箱 | `smtp.163.com` | 465 | SSL | 授权码 |
| Gmail | `smtp.gmail.com` | 587 | TLS | 应用专用密码 |

补充说明：

- QQ 邮箱 / 163 邮箱要用「授权码」，不是登录密码，需要在邮箱设置里单独开启 SMTP 并生成。
- Outlook 的「应用密码」需要先开启两步验证才能创建。
- 服务器防火墙要允许对应端口的**出站**连接。
- 暂时不发邮件也行：`config.local.php` 里留空，后台「系统配置」里关掉邮件通知即可，
  站内通知照常工作（注册时的邮箱验证码会收不到，可在后台关闭注册验证）。

测试：打开 `/register.php`，用一个真实邮箱点「获取验证码」，收到即成功。

---

## 10. 验收清单

- [ ] 首页能打开，行程列表 / 筛选 / 分页正常
- [ ] 注册 → 收到验证码 → 注册成功 → 自动登录
- [ ] 发布行程后，首页能看到，卡片信息正确
- [ ] 用另一个账号预订 → 座位数减 1 → 双方都收到站内通知
- [ ] 预订后才能看到发起人联系方式；未预订看不到
- [ ] 取消预订 → 座位数恢复
- [ ] 我的发布 / 我的预订 / 个人中心 / 资料页都能打开，无 500
- [ ] 注销账号：弹窗确认后回首页，数据库里该用户与相关记录已清空
- [ ] 后台 `/admin/`：仪表盘、用户、行程、预订、系统配置均可操作
- [ ] 未登录访问 `/post_ride.php` 会跳登录页；普通用户访问 `/admin/` 会被挡回首页
- [ ] 手机浏览器上看一遍：底部标签栏、预订面板、下拉与输入框都正常
- [ ] 后台发一条公告 → 首页弹出 → 关闭后刷新不再弹 → 改公告后会重新弹一次

---

## 11. 升级与回滚

**升级**：覆盖文件 → 看 `CHANGELOG.md` 里对应版本是否需要执行迁移 → 重载 PHP（清 OPcache）。
`config.local.php` 不会被覆盖，改动只在代码里的配置项（如 `SITE_URL`）需要手动同步。

**静态资源缓存**：改了 `glass.css` / `app.js` 后，记得把 `header.php` 里的 `?v=` 加一，
否则手机端会一直用旧缓存。

**回滚统一认证**：把 `AUTH_MODE` 改回 `local` 即可，本机账号密码登录立刻恢复；
`oauth/` 与 `lib/casdoor.php` 不参与本地登录流程，不需要删文件。

---

## 12. 常见问题

### Q1：验证码收不到邮件？

1. 先看垃圾邮件箱
2. 确认 `config.local.php` 里 SMTP 配置正确（代码只读这个文件，不读数据库）
3. 确认用的是「应用密码 / 授权码」而不是登录密码
4. 看 PHP 错误日志（宝塔：网站 → 设置 → 日志）
5. 测试出站连通性：`telnet smtp.office365.com 587`

### Q2：打开网站 500？

1. 看 PHP 错误日志
2. 确认 `config.local.php` 存在、数据库名/用户/密码正确
3. 确认已导入 `setup.sql`（老站点确认迁移脚本都执行过）
4. 确认 `pdo_mysql` 扩展已启用

### Q3：新上传的文件 403 Access denied？

权限问题，见第 3 节：属主 `www`、目录 `755`、文件 `644`。

### Q4：数据库连不上？

- 确认用户有该库权限（宝塔：数据库 → 权限）
- `localhost` 与 `127.0.0.1` 有区别：MySQL 走 socket 还是 TCP，有时需要改成 `127.0.0.1`
- 云服务器安全组 / 防火墙不要开放 3306 到公网

### Q5：忘记管理员密码？

```bash
# 1. 生成新密码的哈希
php -r "echo password_hash('新密码', PASSWORD_BCRYPT) . PHP_EOL;"

# 2. 更新数据库
mysql -u carpool -p
USE carpool;
UPDATE users SET password_hash = '刚才生成的哈希值' WHERE email = 'admin@campus.com';
```

### Q6：改了代码不生效？

PHP 开了 OPcache 时，重载一次 PHP 或重启 php-fpm 才会加载新代码。
宝塔：软件商店 → PHP → 设置 → 重载配置。

---

## 13. 安全建议

1. 改掉默认管理员账号和密码，数据库密码用强密码
2. 只开放 80 / 443 / SSH 端口，MySQL 不对外
3. 全站 HTTPS，并开启 HSTS
4. 定期备份数据库（宝塔有定时备份）
5. `config.local.php` 不要提交到任何仓库，也不要发给别人
6. 面板端口改掉默认值，条件允许时上 Fail2ban
7. 关注 `CHANGELOG.md` 里标为「修复」的版本，及时覆盖
