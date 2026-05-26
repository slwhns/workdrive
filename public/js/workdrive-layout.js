document.addEventListener('DOMContentLoaded', function () {
    var trigger = document.getElementById('header-profile-trigger');
    var overlay = document.getElementById('profile-rail-overlay');
    var rail = document.getElementById('profile-rail');
    var hitArea = document.getElementById('profile-rail-hitarea');
    var newTrigger = document.getElementById('app-new-trigger');
    var newWrap = document.querySelector('.app-new-wrap');
    var newFolderForm = document.getElementById('new-folder-form');
    var newFolderName = document.getElementById('new-folder-name');
    var uploadFilesInput = document.getElementById('upload-files-input');
    var uploadFolderInput = document.getElementById('upload-folder-input');
    var themeToggleBtn = document.getElementById('theme-toggle-btn');
    var themeIcon = document.getElementById('theme-icon');
    var closeTimer = null;
    var themeStorageKey = 'workdrive_theme';

    function closeRail() {
        if (closeTimer) {
            clearTimeout(closeTimer);
            closeTimer = null;
        }
        document.body.classList.remove('profile-rail-open');
    }

    function openRail() {
        if (closeTimer) {
            clearTimeout(closeTimer);
            closeTimer = null;
        }
        document.body.classList.add('profile-rail-open');
    }

    function scheduleCloseRail() {
        if (closeTimer) {
            clearTimeout(closeTimer);
        }

        closeTimer = setTimeout(function () {
            document.body.classList.remove('profile-rail-open');
        }, 160);
    }

    function closeNewMenu() {
        if (!newWrap || !newTrigger) {
            return;
        }

        newWrap.classList.remove('is-open');
        newTrigger.setAttribute('aria-expanded', 'false');
    }

    function toggleNewMenu() {
        if (!newWrap || !newTrigger) {
            return;
        }

        var isOpen = newWrap.classList.toggle('is-open');
        newTrigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }

    function applyTheme(theme) {
        if (theme === 'light') {
            document.body.classList.add('light-theme');
            if (themeIcon) {
                themeIcon.className = 'ri-moon-line';
            }
            if (themeToggleBtn) {
                themeToggleBtn.setAttribute('title', 'Switch to Dark Theme');
            }
        } else {
            document.body.classList.remove('light-theme');
            if (themeIcon) {
                themeIcon.className = 'ri-sun-line';
            }
            if (themeToggleBtn) {
                themeToggleBtn.setAttribute('title', 'Switch to Light Theme');
            }
        }
    }

    trigger && trigger.addEventListener('click', function (event) {
        event.stopPropagation();
        document.body.classList.toggle('profile-rail-open');
    });

    newTrigger && newTrigger.addEventListener('click', function (event) {
        event.stopPropagation();
        toggleNewMenu();
    });

    document.querySelectorAll('[data-new-action="folder"]').forEach(function (button) {
        button.addEventListener('click', function () {
            var folderName = window.prompt('Enter a folder name');

            if (!folderName || !newFolderForm || !newFolderName) {
                return;
            }

            newFolderName.value = folderName.trim();
            if (!newFolderName.value) {
                return;
            }

            newFolderForm.submit();
            closeNewMenu();
        });
    });

    uploadFilesInput && uploadFilesInput.addEventListener('change', function () {
        if (this.files && this.files.length) {
            this.closest('form').submit();
            closeNewMenu();
        }
    });

    uploadFolderInput && uploadFolderInput.addEventListener('change', function () {
        if (this.files && this.files.length) {
            this.closest('form').submit();
            closeNewMenu();
        }
    });

    overlay && overlay.addEventListener('click', closeRail);

    hitArea && hitArea.addEventListener('mouseenter', openRail);
    rail && rail.addEventListener('mouseenter', openRail);
    hitArea && hitArea.addEventListener('mouseleave', scheduleCloseRail);
    rail && rail.addEventListener('mouseleave', scheduleCloseRail);

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeRail();
            closeNewMenu();
        }
    });

    document.addEventListener('click', function (event) {
        if (!newWrap || !newWrap.contains(event.target)) {
            closeNewMenu();
        }
    });

    var savedTheme = localStorage.getItem(themeStorageKey) || 'dark';
    applyTheme(savedTheme);

    themeToggleBtn && themeToggleBtn.addEventListener('click', function () {
        var nextTheme = document.body.classList.contains('light-theme') ? 'dark' : 'light';
        localStorage.setItem(themeStorageKey, nextTheme);
        applyTheme(nextTheme);
    });
});
