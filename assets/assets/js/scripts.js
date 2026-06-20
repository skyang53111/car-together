// assets/js/scripts.js
document.addEventListener('DOMContentLoaded', function() {
    const submitButtons = document.querySelectorAll('input[type="submit"]');
    submitButtons.forEach(button => {
        button.addEventListener('click', function() {
            button.style.backgroundColor = '#007bff';
            button.style.color = '#fff';
        });
    });

    const currentTimeElement = document.getElementById('current-time');
    function updateTime() {
        const now = new Date();
        const options = { hour: '2-digit', minute: '2-digit', second: '2-digit' };
        currentTimeElement.textContent = now.toLocaleTimeString('zh-CN', options);
    }
    setInterval(updateTime, 1000);
    updateTime();
});
