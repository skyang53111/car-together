# 版本记录（CHANGELOG）

本项目从一套本地账号体系起步，中途接入统一身份认证（可选），随后完成了一次前台全站视觉改版。
下面按版本倒序记录每一版做了什么、为什么这么做，以及踩过的坑——留作开发笔记，也方便别人理解代码为什么长这样。

**版本号规则**：`v主版本.次版本.修订号`。主版本是不兼容的大改动，次版本是新功能，修订号是修 bug 与小调整。

---

## 未发布 · 安全加固与仓库规范

这一轮是代码审查后补的加固，**行为上有几处变化**，同步回线上前请过一遍。

**安全**

- 找回密码增加失败限流（10 次/小时）：6 位验证码只有一百万种可能，原来可以无限次尝试
- 找回密码不再区分「账号不存在」与「验证码错误」，接口层与页面层都改成统一提示，避免被用来探测邮箱是否注册过
- `api/v1/` 三处 `curl` 关闭证书校验（`CURLOPT_SSL_VERIFYPEER => false`）改为校验，
  涉及 `auth_v2.php` 的微信登录与 `subscribe_send.php` 的两处微信接口调用
- 会话 cookie 增加 `HttpOnly`、`SameSite=Lax`，`SITE_URL` 为 https 时自动带 `Secure`
- 小程序接口的登录 token **只从 `Authorization: Bearer` 读取**，不再接受 `?token=`：
  URL 会进访问日志、Referer 和浏览器历史，等同于泄露。**小程序端需要同步改成请求头**
- 本机账号模式（`AUTH_MODE=local`）下注销账号需再输一次登录密码，弹窗里加了密码框
- 未配置 `config.local.php` 时给出可读提示，不再抛 `Undefined constant DB_HOST` 白屏

**工程与仓库规范**

- 后台仪表盘的 7 天趋势统计改为参数化查询（原先是内部生成的日期字符串拼进 SQL）
- 新增 `.gitattributes`（统一 LF）、`.editorconfig`、`SECURITY.md`、`robots.txt`
- 新增 GitHub Actions：对 PHP 8.0 / 8.1 / 8.2 跑 `php -l`，并检查 `config.local.php` 没被提交、
  `setup.sql` 覆盖了代码用到的所有表（防止再出现 v1.5.2 那种「缺表导致全新安装 500」）
- 清掉未使用的 `i-edit` 图标与 `.gitignore` 里早期遗留的 `poo.txt`

---

## v1.5.2 — 修复自定义地址的显示

**问题**：发布页选「自定义地址…」后，路线下面会**多出一个输入框**，和上面下拉里的文字重复；
而且那个下拉会被地址文字撑长，不像移动端该有的样子。

**改法**（不是新增控件，而是**就地替换**）

- 选了「自定义地址…」，这一路的**下拉就地换成输入框**（同一位置、同一行），下面不再另起一个框——
  **一个位置永远只有一个控件**，也就不会重复了。
- 下拉里那一项恢复成固定的「自定义地址…」，不会被长地址撑长；地址只在输入框里显示。
- 刷新 / 提交出错回显时，服务端按状态直接渲染成「下拉」或「输入框」，两边显示一致。
- 顺手加了预填保护：上次行程若存的是历史脏数据 `__custom__`，跳过、不拿来预填。

**改动**：`post_ride.php`、`assets/css/glass.css`、`header.php`。无需数据库迁移。

---

## v1.5.1 — 发布页改版

**问题**：发布页是一张卡里平铺 6 个字段，几种排法混在一起（有的靠左有的靠右、中间一排日期 chip、
散着好几处小灰字和分隔线），看着乱；日期只写「今天/明天/后天」，既有歧义、又让人以为只能选这三天。

**这一版做了什么**

1. **版式统一**：整页一张卡 —— **路线是主角**（左侧竖线串起起终点 + 一键交换），
   其余一律「**标签在左 / 控件在右**」。去掉混排、日期 chip、多余的小灰字与分隔线。
2. **时间收成一行**「10-11 周日 08:00 ▾」，点开就是系统的日期时间选择器——
   看得清是几号、也能选任意日期，不再有"只能选近三天"的误会。
3. **默认带出上次的行程**：路线、人数沿用上次发布；时间默认明天同一时刻（没有就明早 8 点）；
   联系方式用绑定手机号。常发的人基本不用输。
4. **就地报错**：哪个字段有问题标红哪块、红字贴着它，并自动滚到第一处（不再只在顶部飘一条红字）。
5. **联系方式写明**「手机号 / 微信 / QQ 都行，只有预订的人能看到」。
6. **备注**：占位里的「分摊油费」与「通勤互助」定位冲突，换成中性示例；备注默认收起成一行入口。

**兼容与降级**：字段名与提交逻辑**不变**（`origin / destination / ride_time / contact / capacity / notes`，
自定义地址仍走 `*_custom`），服务端校验原样保留。无 JS 时降级为：备注输入框直接显示、人数用默认值、
时间走原生控件照常提交。

**改动**：`post_ride.php`、`assets/css/glass.css`（新增 `pr-` 组件）、`header.php`。

---

## v1.5.0 — 个人中心改版 + 新增资料页 + 注销简化

**这一版做了什么**

1. **个人中心改成「看板」**：一张身份顶卡（头像 + 昵称 + 四个统计 + 账号安全评分条）打头，
   下面把「即将出行 / 我的发布 / 消息」收进一个**分段切换器**，整页不再是一长串卡片。
   「即将出行」里把最近的一趟做成**高亮卡**（带「N 天后」倒计时、查看联系方式）。
2. **新增资料页 `profile.php`**：原个人中心里的「基本资料 / 账号与安全 / 注销账号」整块迁到这里。
   - 基本资料：性别、校区用**内联下拉、选完即存**（无 JS 时回退成普通表单 + 保存按钮）；
   - 账号与安全：手机号 / 邮箱 / 密码做成**可点行**，点任意一行跳到统一身份认证平台；
   - 帮助与反馈：跳转到 `config.php` 里的 `QQ_GROUP_URL`（留空则整行不显示）；
   - 注销账号：一条红色入口，点开**弹窗**里说明 + 确认。
3. **注销简化**：不再要求输入 `DELETE`，弹窗里点「确认注销」即完成（仅二次确认）。
4. **措辞**：把「车主」改成「发起人」——这是同学之间找同行同伴，不是叫车。
5. **全站措辞统一**：文案里的「拼车」统一说成**行程**（发布行程 / 行程已满 / 行程取消 / 行程列表）。

**主要实现**

- `user_center.php` 重写为看板；分段切换与通知操作写成 **ES5** 并单独放一个 `<script>` 块
  （老手机内核缺 API 时不会连累别处，见 v1.3.1 的教训）。
- `profile.php` 为**新增文件**；保存基本资料走 `POST profile.php`（带 CSRF），
  支持 `ajax=1` 静默保存；注销弹窗有无 JS 两种路径都能走（`?del=1` 为无 JS 回退）。
- `delete_account.php` 去掉 `delete_confirm === 'DELETE'` 校验，提示与跳转改到 `profile.php`。
- `assets/css/glass.css` 追加 `uc-`（看板）/ `pf-`（资料页）组件样式，**不改动上面的全站组件**。
- `header.php` 补 5 个图标（`i-mail/i-shield/i-chat/i-trash/i-edit`），通知铃改指向 `?tab=msg`。
- 无需数据库迁移。

---

## v1.4.0 — 手机号同步修复 + 资料不完整提醒 + 删除用户同步（Webhook）

### 1. 修复：认证平台绑了手机号，个人中心仍显示「未绑定」

**根因**：同步逻辑把手机号写进了 `users.phone`，但**漏写了 `phone_verified`**（邮箱是同时写
`email_verified` 的）。而个人中心的「手机」徽标、安全评分里的「手机已绑定」都是看
`phone_verified`，所以手机号其实已经同步过来，界面却一直显示「未绑手机」。

**修复**（`lib/casdoor.php`）

- 新增 `casdoorApplyProfile()`：手机号写入时**同时置 `phone_verified = 1`**；新建用户时同理。
- `casdoorFetchClaims()` 的 userinfo / id_token 合并改为「只用**非空值**覆盖」，
  避免 userinfo 里被过滤成空串的 `phone` 把 id_token 里的真手机号冲掉。

### 2. 新增：个人中心「请完善账号信息」提醒

用 QQ 等快捷方式登录时，账号可能没有邮箱/手机号。缺其中任一项时，个人中心顶部会出现醒目提醒，
附「前往统一认证平台完善」按钮，并说明补完后点「刷新资料」即可同步。（`user_center.php`）

### 3. 新增：认证平台删除用户 → 本平台同步删除（Webhook）

以前在认证平台删了用户，本平台数据库里还留着。现在用 **Webhook** 事件驱动同步：

- 新增 `oauth/casdoor_webhook.php`，接收推送。
- `delete-user` → 按 `casdoor_id` → 邮箱 → 手机号依次匹配本地用户，**彻底删除**；
  行程、预订、通知、API token 等由外键 `ON DELETE CASCADE` 自动清理，验证码按邮箱显式删除。
- `update-user` → 按 `casdoor_id` 精确匹配后同步资料，手机号等变更**即时生效**，不用等下次登录。
- **安全**：必须携带共享密钥（请求头 `X-Casdoor-Webhook-Secret`），否则一律 403；
  密钥在 `config.local.php` 的 `CASDOOR_WEBHOOK_SECRET` 里配，**留空则整个端点关闭**。
- 兼容两种 payload 格式；为防误删，只认载荷里的 `object`（被删用户），
  **绝不采用代表「操作管理员」的字段**（`user` / `extendedUser` 指的是管理员，不能用）。

### 4. 改进：注销账号的说明文案

明确说明：**只删除本平台的数据**；统一认证平台的账号会**保留**
（该账号还用于登录其他接入统一认证的网站，一并删除会导致那些网站也用不了）；
如确需删除认证平台账号，**请联系管理员**。

**改动**：`lib/casdoor.php`、`oauth/casdoor_webhook.php`（新增）、`user_center.php`、`config.php`。

---

## v1.3.1 — 修复手机端公告不弹窗、点了关不掉

**问题现象**：手机打开首页，公告**完全不弹**；桌面端一切正常。

**根因**：v1.3.0 把公告 JS 追加在**同一个 `<script>` 块的末尾**，而那个块前面有 `NodeList.forEach`。

`NodeList.prototype.forEach` 要 Chrome 51+ / Safari 10+。**部分手机内核（老 Android WebView、
微信/QQ 浏览器旧内核）没有这个方法**，一执行就抛 `TypeError: ...forEach is not a function`。
而浏览器的规则是：**一个 `<script>` 块里抛了未捕获的错误，就中断这个块**，后面的代码全部不执行。
公告 IIFE 恰好在这个块的最后 —— 所以它**从来没运行过**，`.is-open` 永远加不上，弹窗就一直停在
`opacity:0; pointer-events:none`：**看不见，也点不动**。

（顺带一提：预订面板的绑定也在同一个块里，所以那类手机上「立即预订」同样是坏的。
桌面端内核新、有 forEach，所以一切正常——这解释了为什么「只有手机端有问题」。）

**修复**

| 文件 | 改动 |
| --- | --- |
| `footer.php` | ① 两处 `NodeList.forEach` 改成**下标 `for` 循环**（老内核也认）② 公告 JS **拆成独立的 `<script>` 块**——一个块抛错只中断当前块 |
| `assets/css/glass.css` | `.ann-*` 去掉老内核不支持的 `inset`（要 Chrome 87+）和 `min()`，改用 `top/right/bottom/left`、`max-width`；`.ann-body` 补 `min-height:0`（正文一长会把「我知道了」顶出弹窗）；弹窗补 `overflow:hidden` |
| `header.php` | `glass.css?v=1` → **`?v=2`**。v1.3.0 改了 CSS 却没动版本号，手机浏览器会一直用缓存的旧样式 |

**怎么确认是它**：把 v1.3.0 的代码原样复刻成一份本地页面，在真浏览器里删掉
`NodeList.prototype.forEach` 再跑 —— 精确复现出「弹窗不出现、点了没反应」；改完之后，
同样条件下弹窗正常弹出、正常关闭。

**经验**：给老手机内核写 JS，一条 `forEach` 就能带走后面整块代码；
**不同功能的脚本分块写**，比什么都保险。

---

## v1.3.0 — 后台清掉无效配置 + 新增首页公告弹窗

### 一、清掉后台的无效配置

后台「系统配置」页原来有一批**填了也不生效**的项——前台根本不读它们：

| 旧配置项 | 为什么没用 |
| --- | --- |
| 站点名称 / 站点描述 | 前台标题用的是 `config.php` 里的 `SITE_NAME` 常量，不读数据库 |
| 最大搭载人数 | 用的是 `MAX_SEATS` 常量 |
| 全站公告（支持HTML） | 后台能存，但**前台任何地方都没显示过** |
| 注册邮箱验证 | 只在 `register.php` 里读，接入统一认证后该页会直接跳转 |

现在配置页只剩两块：**首页公告**（新增）和**通知设置**（邮件通知，这个是真在用的）。
保存时会把上面这些旧记录从 `site_config` 表里删掉。

### 二、新增首页公告弹窗

后台「系统配置 → 首页公告」可设置开关、标题、正文（支持 HTML，纯文本则转义并把换行变 `<br>`）、
以及**弹窗频率**：每个用户只弹一次（推荐） / 每个用户每天弹一次 / 每次打开首页都弹。

用户打开首页后弹出居中卡片，点「我知道了」、右上角 ×、点遮罩、按 ESC 都能关闭。

**「只弹一次」是怎么记住的**：弹窗带一个**内容指纹**（标题 + 正文的 md5 前 12 位），
用户关闭时写进 cookie `carpool_ann`。下次访问指纹一致就不弹；**一改公告内容指纹就变，
看过的用户会重新看到一次新公告**，不需要手动清缓存。判断在服务端做，**未登录用户同样有效**。

**改动**：`admin/config.php`、`functions.php`（新增 `getAnnouncement()`）、`index.php`、
`assets/css/glass.css`（`.ann-*`）、`footer.php`、`setup.sql`。

---

## v1.2.2 — 修复认证平台改的头像同步不过来

**问题现象**：在认证平台换了头像，回到平台点「刷新资料」，昵称能更新，**头像始终不变**。

**根因**：两边字段名不一样，代码只认了其中一套。

| 来源 | 头像字段 | 昵称字段 |
| --- | --- | --- |
| `/api/userinfo`（OIDC 标准命名，**实际走的就是这条**） | `picture` | `name` |
| `id_token`（User 结构体的原始命名） | `avatar` | `displayName` |

原先只读 `$claims['avatar']`，而真正调用的 userinfo 接口返回的是 `picture`，所以**永远取到空串**；
同步逻辑里 `if ($avatar !== '')` 判断为假，头像那一列从来没被写过。昵称写的是
`displayName ?? name`，正好命中 userinfo 的 `name`，于是表现为「昵称正常、只有头像不动」。

**修复**（都在 `lib/casdoor.php`）

- 新增 `casdoorExtractProfile()`：把 claim 取值收口成一个纯函数，按优先级兼容两套命名——
  头像 `picture` → `avatar`，昵称 `displayName` → `name` → `preferred_username`，
  手机号 `phone` → `phone_number`；且只取「存在、且去掉首尾空格后非空」的值。
- `casdoorFindOrCreateUser()` 改用它，不再各自直接读 claim。
- `casdoorFetchClaims()` 改为 **userinfo 与 id_token 合并**（userinfo 优先，id_token 补齐）。

**排查项（属配置问题）**：认证平台应用里的「Token 字段」如果填了白名单且不含 `Avatar`，
返回里就不会有头像。该字段为空时返回全部字段。

---

## v1.2.1 — 修复首页预订功能失效

**问题现象**：首页点「立即预订」没有反应，什么都不提示，直接回到首页。

**根因**：`book_ride.php` 要求 `isset($_POST['book'])` 才处理预订。改造前提交按钮是
`<button type="submit" name="book">`，v1.1.0 重写首页时把这个 `name="book"` 弄丢了，
于是表单提交了、但服务端整段逻辑被跳过，**静默失败**。

**修复**：改用隐藏字段 `<input type="hidden" name="book" value="1">`。
**注意不能改回按钮的 `name`**：底部确认面板走的是原生 `form.submit()`，
原生提交**不会带上提交按钮的 name/value**，用按钮 name 的话只有「禁用 JS 直接提交」那条路径能成。

**顺带修复**：已预订的行程原本仍显示可点的「立即预订」按钮，改为不可点的「已预订」状态标签；
补上 `.meta-item.is-locked` 样式。

---

## v1.2.0 — 前台其余页面全部改版 + glass.css 去作用域

- `assets/css/glass.css` 去掉 `body.page-home` 作用域，改为**前台全站生效**；补齐表单 / 认证页 /
  个人中心 / 统计磁贴 / 列表行 / 通知 / 折叠区等组件。
- `user_center.php`、`my_rides.php`、`my_bookings.php`、`post_ride.php` 全部按新设计重写。
- `login.php`、`forgot_password.php`、`oauth/logged_out.php`、`404.html` 换图标、对齐配色。
- 全文 emoji 图标换成 Lucide 内联图标。

**敢全站生效的前提**：后台（`admin/`）**根本不引入 glass.css**，用的是自己的
`admin/header.php` + `app.css`，所以后台完全不受影响；本次也没有动后台。

---

## v1.1.0 — 首页改版为玻璃质感 + 移动端交互重做

| 文件 | 改动 |
| --- | --- |
| `assets/css/glass.css` | **新增**。玻璃质感样式表，在 app.css 之后加载 |
| `header.php` | 顶部导航重做（图标 logo、Lucide 图标精灵、发布按钮桌面端才显示） |
| `footer.php` | 底部导航改为 `.tabbar` 标签栏（发布移入拇指区）；新增粘性搜索 / 卡片入场 / 预订确认面板脚本 |
| `index.php` | 首页重写：Hero 改成今日概况、筛选改横向滑动条、卡片与预订面板 |

**设计与交互要点**

- 图标换成 **Lucide**（ISC 协议），20 个内联成 sprite，**没有任何外部 CDN 请求**。
- Hero 从静态标语改成**今日概况**：今日出发 / 还有空座 / 最早一班，三个数都用现有数据算出来。
- 「立即预订」不再跳页，改为**底部滑出的确认面板**。
- 排序与筛选：**横向滑动筛选条** + 右缘渐隐提示可滑动。
- 所有点击目标 ≥ **44px**；`touch-action:manipulation` 去掉双击缩放延迟；输入框 16px 防止 iOS 聚焦放大。
- 悬停效果全部包在 `@media (hover:hover)` 内，触屏只用 `:active`。
- 支持刘海屏安全区（`env(safe-area-inset-*)`）。
- 动效只用 `transform`/`opacity`，用 `IntersectionObserver` 而非滚动监听；
  `prefers-reduced-motion` / `prefers-reduced-transparency` 均有兜底。

**无 JS 时的行为**：预订按钮会按普通表单直接提交到 `book_ride.php`，功能不会因禁用 JS 而失效。

**已知取舍**：列表卡片刻意不用 `backdrop-filter`（背后是平滑渐变，模糊与否视觉上无差别，
但一屏十几张同时模糊在低端安卓上很卡），改用高不透明度 + 边缘高光保持玻璃观感。

---

## v1.0.1 — 修复文件权限导致的 403

上传包里的文件权限位是 `700 (-rwx------)`，新建的 `lib/casdoor.php`、`oauth/*.php` 继承 700，
nginx 无权读取 → 403 Access denied。

**规则**：文件 `644`、目录 `755`，属主为 Web 服务用户。以后新增目录都要注意这一条。

---

## v1.0.0 — 接入统一身份认证（Casdoor / OIDC）

**新增文件**

| 文件 | 说明 |
| --- | --- |
| `lib/casdoor.php` | 统一认证封装（授权跳转、换 token、取用户、账号关联与资料同步） |
| `oauth/login.php` | 发起登录（state + PKCE S256），支持 `?silent=1` 静默刷新 |
| `oauth/callback.php` | 登录回调处理 |
| `oauth/logged_out.php` | 单点登出后的落地页 |

**改动**

| 文件 | 改动 |
| --- | --- |
| `config.php` | 新增 `AUTH_MODE` / `CASDOOR_*` 常量 |
| `login.php` | 按 `AUTH_MODE` 跳转统一认证 |
| `header.php` | 登录按钮改为「登录 / 注册」；头像支持显示认证平台头像 |
| `logout.php` | 单点登出（带 `id_token_hint`），回跳 `oauth/logged_out.php` |
| `functions.php` | `getCurrentUser()` 带出 `avatar` |
| `user_center.php` | 昵称/头像改为「新标签页去认证平台改 + 刷新资料」 |
| `register.php` / `forgot_password.php` | 非 local 模式下跳转统一认证 |
| `api/v1/auth_v2.php` | 新增 `casdoor_login`；`wx_login` / `password_login` 关闭 |

**需要单独操作**：`config.local.php` 填 `CASDOOR_CLIENT_ID` / `CASDOOR_CLIENT_SECRET`；
数据库执行 `migrate_v5.sql`；认证平台应用的「许可跳转列表」加入 `callback.php` 与 `logged_out.php`。

**回滚方式**：`config.php`（或 `config.local.php`）里把 `AUTH_MODE` 设为 `local`，
即退回原本的手机号/邮箱 + 密码登录。仓库默认就是 `local`，开箱即用。

---

## v0.9.0 — 项目初始版

面向校内同学的通勤拼车信息共享：手机号 + 邮箱注册登录、发布行程、预订座位、
我的发布 / 我的预订、站内通知与邮件提醒、后台用户 / 行程 / 预订管理与系统配置。
安全基线：CSRF Token、PDO 参数化查询、bcrypt 密码哈希、IP 频率限制、会话重建。
