    </main>

    <!-- 底部标签栏（移动端。桌面端由 CSS 隐藏，主操作在顶部导航） -->
    <?php
    $__cur = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $__tabActive = function (array $files) use ($__cur): string {
        return in_array($__cur, $files, true) ? ' active' : '';
    };
    ?>
    <nav class="tabbar" aria-label="主导航">
        <a href="/index.php" class="tab<?php echo $__tabActive(['index.php']); ?>">
            <svg class="ico"><use href="#i-house"/></svg><span>首页</span>
        </a>
        <a href="/my_bookings.php" class="tab<?php echo $__tabActive(['my_bookings.php']); ?>">
            <svg class="ico"><use href="#i-ticket"/></svg><span>我的预订</span>
        </a>
        <a href="/post_ride.php" class="tab tab-primary<?php echo $__tabActive(['post_ride.php']); ?>">
            <span class="ico-wrap"><svg class="ico"><use href="#i-plus"/></svg></span><span>发布</span>
        </a>
        <a href="/user_center.php" class="tab<?php echo $__tabActive(['user_center.php', 'my_rides.php', 'profile.php']); ?>">
            <svg class="ico"><use href="#i-user"/></svg><span>我的</span>
        </a>
    </nav>

    <!-- 底部间距（防止内容被标签栏遮挡） -->
    <div class="bottom-spacer"></div>

    <script src="/assets/js/app.js?v=2"></script>
    <script>
    /* 首页交互增强。全部做空值保护，其他页面加载本文件不会报错。
       不使用 scroll 监听（会让手机掉帧），改用 IntersectionObserver。 */
    (function () {
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var hasIO = 'IntersectionObserver' in window;

        /* ---- 1. 粘性搜索栏：吸附时加毛玻璃与阴影 ---- */
        var sentinel = document.getElementById('searchSentinel');
        var sticky = document.getElementById('searchSticky');
        if (sentinel && sticky && hasIO) {
            new IntersectionObserver(function (entries) {
                sticky.classList.toggle('is-stuck', !entries[0].isIntersecting);
            }, { threshold: 0 }).observe(sentinel);
        }

        /* ---- 2. 卡片入场：进入视口才播放 ---- */
        /* 注意：下面全部用下标 for 循环，不用 NodeList.forEach ——
           老手机内核（部分微信 / Android WebView）没有 NodeList.prototype.forEach，
           一抛错就会把这个 script 块里后面的代码全部带走（2026-10-07 踩过）。 */
        var cards = document.querySelectorAll('.ride-card');
        if (cards.length && hasIO && !reduce) {
            for (var ci = 0; ci < cards.length; ci++) {
                cards[ci].classList.add('reveal');
                cards[ci].style.animationPlayState = 'paused';
            }
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (e) {          // entries 是数组，这里的 forEach 没问题
                    if (e.isIntersecting) {
                        e.target.style.animationPlayState = 'running';
                        io.unobserve(e.target);
                    }
                });
            }, { rootMargin: '0px 0px -6% 0px', threshold: 0.06 });
            for (var cj = 0; cj < cards.length; cj++) { io.observe(cards[cj]); }
        }

        /* ---- 3. 预订确认面板 ----
           没有 JS 时这里不会介入，「立即预订」按钮会按普通表单直接提交到
           book_ride.php（与改造前行为一致），所以功能不会因为禁用 JS 而失效。 */
        var sheet = document.getElementById('bookSheet');
        var backdrop = document.getElementById('bookBackdrop');
        if (!sheet || !backdrop) return;

        var pendingForm = null;
        var lastFocus = null;

        function setText(id, val) {
            var el = document.getElementById(id);
            if (el) el.textContent = val || '';
        }
        function openSheet(form) {
            pendingForm = form;
            lastFocus = form.querySelector('button');
            setText('sRoute', form.dataset.route);
            setText('sTime', form.dataset.time);
            setText('sOwner', form.dataset.owner);
            setText('sSeats', form.dataset.seats);
            sheet.classList.add('is-open');
            backdrop.classList.add('is-open');
            var c = document.getElementById('sheetClose');
            if (c) c.focus();
        }
        function closeSheet() {
            sheet.classList.remove('is-open');
            backdrop.classList.remove('is-open');
            pendingForm = null;
            if (lastFocus) lastFocus.focus();
        }

        /* 同样避开 NodeList.forEach：老手机内核没有这个方法，一抛错整段脚本就断了 */
        var bookForms = document.querySelectorAll('.book-form');
        for (var bi = 0; bi < bookForms.length; bi++) {
            bookForms[bi].addEventListener('submit', function (e) {
                e.preventDefault();
                openSheet(this);          // this = 触发事件的 form，比闭包捕获更稳
            });
        }
        backdrop.addEventListener('click', closeSheet);
        var closeBtn = document.getElementById('sheetClose');
        if (closeBtn) closeBtn.addEventListener('click', closeSheet);
        var cancelBtn = document.getElementById('sheetCancel');
        if (cancelBtn) cancelBtn.addEventListener('click', closeSheet);
        var confirmBtn = document.getElementById('sheetConfirm');
        if (confirmBtn) confirmBtn.addEventListener('click', function () {
            if (pendingForm) pendingForm.submit();   // 原生 submit 不会再触发上面的拦截
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && sheet.classList.contains('is-open')) closeSheet();
        });
    })();
    </script>

    <!-- 首页公告弹窗：刻意单独放一个 script 块，不跟上面挤在同一个块里。
         一个块里抛错，浏览器只中断当前块、不影响其他块；
         这样上面那段交互（在老手机内核上可能报错）就带不倒公告逻辑。 -->
    <script>
    (function () {
        var modal = document.getElementById('annModal');
        if (!modal) return;

        var backdrop = document.getElementById('annBackdrop');
        var okBtn    = document.getElementById('annOk');
        var closeBtn = document.getElementById('annClose');
        var token    = modal.getAttribute('data-ann-token') || '';
        var maxAge   = parseInt(modal.getAttribute('data-ann-maxage') || '0', 10);
        var lastFocus = null;

        function closeAnn() {
            modal.classList.remove('is-open');
            if (backdrop) backdrop.classList.remove('is-open');
            /* 记住「看过了」。token 为空 = 每次打开都弹，不写 cookie。 */
            if (token && maxAge > 0) {
                document.cookie = 'carpool_ann=' + encodeURIComponent(token) +
                    ';max-age=' + maxAge + ';path=/;samesite=lax';
            }
            if (lastFocus && lastFocus.focus) lastFocus.focus();
        }

        if (okBtn)    okBtn.addEventListener('click', closeAnn);
        if (closeBtn) closeBtn.addEventListener('click', closeAnn);
        if (backdrop) backdrop.addEventListener('click', closeAnn);
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.classList.contains('is-open')) closeAnn();
        });

        /* 稍延迟再弹，避免和首屏渲染抢帧 */
        setTimeout(function () {
            lastFocus = document.activeElement;
            modal.classList.add('is-open');
            if (backdrop) backdrop.classList.add('is-open');
            if (okBtn) okBtn.focus();
        }, 320);
    })();
    </script>
</body>
</html>
