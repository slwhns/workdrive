// ===== WorkDrive Main JavaScript =====

document.addEventListener('DOMContentLoaded', function () {
    // User menu toggle
    const userMenuToggle = document.getElementById('user-menu-toggle');
    const userDropdown = document.getElementById('user-dropdown');
    const profileRailOverlay = document.getElementById('profile-rail-overlay');

    if (userMenuToggle && userDropdown) {
        userMenuToggle.addEventListener('click', function (e) {
            e.stopPropagation();
            userDropdown.classList.toggle('show');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.user-menu')) {
                userDropdown.classList.remove('show');
            }
        });
    }

    // Sidebar toggle for mobile
    const toggleLeftSidebar = document.getElementById('toggle-left-sidebar');
    const toggleRightSidebar = document.getElementById('toggle-right-sidebar');
    const sidebarLeft = document.querySelector('.sidebar-left');
    const sidebarRight = document.getElementById('right-sidebar');

    if (toggleLeftSidebar) {
        toggleLeftSidebar.addEventListener('click', function () {
            sidebarLeft.classList.toggle('hidden');
        });
    }

    if (toggleRightSidebar) {
        toggleRightSidebar.addEventListener('click', function () {
            if (sidebarRight) {
                sidebarRight.classList.toggle('hidden');
            }
        });
    }

    // New button functionality (will be expanded)
    const btnNew = document.getElementById('btn-new');
    if (btnNew) {
        btnNew.addEventListener('click', function () {
            // TODO: Show modal for new file/folder
            console.log('New button clicked');
        });
    }
});
