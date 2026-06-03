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

    // File preview click handlers
    setupFilePreviewHandlers();
});

/**
 * Setup file preview click handlers
 */
function setupFilePreviewHandlers() {
    // Add click handlers to all file items that support preview
    document.addEventListener('dblclick', function (e) {
        const fileItem = e.target.closest('[data-file-id]');
        if (fileItem && !e.target.closest('.file-menu, .checkbox, .action-button')) {
            const fileId = fileItem.getAttribute('data-file-id');
            const isFolder = fileItem.getAttribute('data-is-folder') === 'true';
            
            if (!isFolder && fileId) {
                // If SPA editor launcher is active, let drive-premium handle it
                if (typeof window.launchEditor === 'function') {
                    return;
                }
                openFilePreview(fileId);
            }
        }
    }, true);

    // Right-click context menu preview option
    document.addEventListener('contextmenu', function (e) {
        const fileItem = e.target.closest('[data-file-id]');
        if (fileItem) {
            const fileId = fileItem.getAttribute('data-file-id');
            const isFolder = fileItem.getAttribute('data-is-folder') === 'true';
            
            if (!isFolder && fileId) {
                // Context menu will be handled separately
                const menuEvent = new CustomEvent('fileContextMenu', {
                    detail: { fileId: fileId }
                });
                document.dispatchEvent(menuEvent);
            }
        }
    }, true);
}

/**
 * Open file preview
 */
function openFilePreview(fileId) {
    if (fileId) {
        window.location.href = '/preview/' + fileId;
    }
}
