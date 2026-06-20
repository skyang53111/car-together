/**
 * 校区拼车平台 - 前端脚本
 */

document.addEventListener('DOMContentLoaded', () => {

    // ========== 用户下拉菜单 ==========
    const userMenuBtn = document.getElementById('userMenuBtn');
    const userDropdown = document.getElementById('userDropdown');
    if (userMenuBtn && userDropdown) {
        userMenuBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const isVisible = userDropdown.style.display === 'block';
            userDropdown.style.display = isVisible ? 'none' : 'block';
        });
        document.addEventListener('click', () => {
            userDropdown.style.display = 'none';
        });
        userDropdown.addEventListener('click', (e) => e.stopPropagation());
    }

    // ========== 发送验证码按钮 ==========
    const sendCodeBtn = document.getElementById('sendCodeBtn');
    if (sendCodeBtn) {
        let countdown = 0;
        let timer = null;
        const originalText = sendCodeBtn.textContent;

        sendCodeBtn.addEventListener('click', async () => {
            if (countdown > 0) return;

            const emailInput = document.getElementById('email');
            if (!emailInput || !emailInput.value.trim()) {
                showToast('请先输入邮箱地址', 'warning');
                return;
            }

            if (!isValidEmail(emailInput.value.trim())) {
                showToast('请输入有效的邮箱地址', 'warning');
                return;
            }

            sendCodeBtn.disabled = true;
            sendCodeBtn.textContent = '发送中...';

            try {
                const formData = new FormData();
                formData.append('email', emailInput.value.trim());
                formData.append('action', 'send_code');

                const resp = await fetch('register.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await resp.json();

                if (data.success) {
                    showToast('验证码已发送，请查收邮件', 'success');
                    startCountdown();
                } else {
                    showToast(data.message || '发送失败，请重试', 'error');
                    sendCodeBtn.disabled = false;
                    sendCodeBtn.textContent = originalText;
                }
            } catch (err) {
                showToast('网络错误，请重试', 'error');
                sendCodeBtn.disabled = false;
                sendCodeBtn.textContent = originalText;
            }
        });

        function startCountdown() {
            countdown = 60;
            updateBtnText();
            timer = setInterval(() => {
                countdown--;
                if (countdown <= 0) {
                    clearInterval(timer);
                    sendCodeBtn.disabled = false;
                    sendCodeBtn.textContent = originalText;
                } else {
                    updateBtnText();
                }
            }, 1000);
        }

        function updateBtnText() {
            sendCodeBtn.textContent = countdown + 's后重发';
        }
    }

    // ========== Toast提示 ==========
    window.showToast = function (message, type = 'info') {
        document.querySelectorAll('.toast-msg').forEach(el => el.remove());

        const toast = document.createElement('div');
        toast.className = 'toast-msg toast-' + type;
        toast.textContent = message;
        toast.style.cssText = `
            position: fixed; top: 70px; left: 50%; transform: translateX(-50%);
            padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 500;
            z-index: 9999; white-space: nowrap; animation: slideDown .3s ease;
            background: ${type === 'success' ? '#D1FAE5' : type === 'error' ? '#FEE2E2' : type === 'warning' ? '#FEF3C7' : '#EEF2FF'};
            color: ${type === 'success' ? '#065F46' : type === 'error' ? '#991B1B' : type === 'warning' ? '#92400E' : '#3730A3'};
            border: 1px solid ${type === 'success' ? '#A7F3D0' : type === 'error' ? '#FECACA' : type === 'warning' ? '#FDE68A' : '#C7D2FE'};
            pointer-events: none;
        `;

        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity .3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 2500);
    };

    const styleSheet = document.createElement('style');
    styleSheet.textContent = '@keyframes slideDown { from { opacity: 0; transform: translateX(-50%) translateY(-10px); } to { opacity: 1; transform: translateX(-50%) translateY(0); } }';
    document.head.appendChild(styleSheet);

    // ========== 表单验证 ==========
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', (e) => {
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            if (password && confirmPassword && password.value !== confirmPassword.value) {
                e.preventDefault();
                showToast('两次输入的密码不一致', 'error');
            }
        });
    }

    // ========== 搜索自动提交 ==========
    const filterSelects = document.querySelectorAll('.filter-auto-submit');
    filterSelects.forEach(select => {
        select.addEventListener('change', () => {
            select.closest('form').submit();
        });
    });

    // ========== 确认删除 ==========
    const confirmLinks = document.querySelectorAll('[data-confirm]');
    confirmLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            if (!confirm(link.dataset.confirm || '确认执行此操作？')) {
                e.preventDefault();
            }
        });
    });

    // ========== 刷新页面时间（首页） ==========
    const timeElement = document.getElementById('liveTime');
    if (timeElement) {
        const updateTime = () => {
            const now = new Date();
            timeElement.textContent = now.toLocaleString('zh-CN', {
                year: 'numeric', month: '2-digit', day: '2-digit',
                hour: '2-digit', minute: '2-digit', second: '2-digit',
                hour12: false
            });
        };
        updateTime();
        setInterval(updateTime, 1000);
    }

});

/**
 * 简单邮箱格式验证
 */
function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}
