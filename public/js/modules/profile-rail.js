export function initProfileRail() {
    const trigger = document.getElementById('header-profile-trigger');
    const overlay = document.getElementById('profile-rail-overlay');
    const rail = document.getElementById('profile-rail');
    const hitArea = document.getElementById('profile-rail-hitarea');
    let closeTimer = null;

    const closeRail = () => {
        if (closeTimer) {
            clearTimeout(closeTimer);
            closeTimer = null;
        }
        document.body.classList.remove('profile-rail-open');
    };

    const toggleRail = () => {
        document.body.classList.toggle('profile-rail-open');
    };

    const openRail = () => {
        if (closeTimer) {
            clearTimeout(closeTimer);
            closeTimer = null;
        }
        document.body.classList.add('profile-rail-open');
    };

    const scheduleCloseRail = () => {
        if (closeTimer) {
            clearTimeout(closeTimer);
        }

        closeTimer = setTimeout(() => {
            document.body.classList.remove('profile-rail-open');
        }, 160);
    };

    trigger?.addEventListener('click', (event) => {
        event.stopPropagation();
        toggleRail();
    });

    overlay?.addEventListener('click', closeRail);

    hitArea?.addEventListener('mouseenter', openRail);
    rail?.addEventListener('mouseenter', openRail);
    hitArea?.addEventListener('mouseleave', scheduleCloseRail);
    rail?.addEventListener('mouseleave', scheduleCloseRail);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeRail();
        }
    });
}
