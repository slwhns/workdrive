document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('password-toggle');
    const password = document.getElementById('password');
    const showIcon = document.getElementById('password-icon-show');
    const hideIcon = document.getElementById('password-icon-hide');

    if (!toggle || !password || !showIcon || !hideIcon) {
        return;
    }

    toggle.addEventListener('click', () => {
        const showing = password.type === 'text';
        password.type = showing ? 'password' : 'text';
        toggle.setAttribute('aria-pressed', showing ? 'false' : 'true');
        toggle.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
        showIcon.style.display = showing ? 'block' : 'none';
        hideIcon.style.display = showing ? 'none' : 'block';
    });
});
