    </main>

    <!-- 底部导航栏（移动端） -->
    <nav class="bottom-nav" id="bottomNav">
        <a href="/index.php" class="bn-item <?php echo basename($_SERVER['SCRIPT_NAME']) === 'index.php' ? 'active' : ''; ?>">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            <span>首页</span>
        </a>
        <a href="/post_ride.php" class="bn-item <?php echo basename($_SERVER['SCRIPT_NAME']) === 'post_ride.php' ? 'active' : ''; ?>">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
            <span>发布</span>
        </a>
        <a href="/my_bookings.php" class="bn-item <?php echo basename($_SERVER['SCRIPT_NAME']) === 'my_bookings.php' ? 'active' : ''; ?>">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            <span>预订</span>
        </a>
        <a href="/user_center.php" class="bn-item <?php echo basename($_SERVER['SCRIPT_NAME']) === 'user_center.php' ? 'active' : ''; ?>">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <span>我的</span>
        </a>
    </nav>

    <!-- 底部间距（防止被底部导航遮挡） -->
    <div class="bottom-spacer"></div>

    <script src="/assets/js/app.js?v=2"></script>
</body>
</html>
