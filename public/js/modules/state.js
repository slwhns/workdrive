/**
 * State Management
 * Central state object for the WorkDrive SPA
 */

export const createAppState = () => {
    return {
        currentTab: 'index',           // 'index', 'shared', 'recents', 'starred', 'trash', 'search', 'tag'
        currentFolderId: null,         // Current directory ID
        currentTag: null,              // Current active tag name
        tags: [],                      // All tags list
        searchQuery: '',               // Search query
        selectedItem: null,            // Selected file/folder object
        viewMode: localStorage.getItem('drive_view_mode') || 'grid', // 'grid' or 'list'
        folders: [],
        files: [],
        breadcrumbs: [],
        currentFolder: null,
        storageUsed: 0,                // Total storage used in bytes
        storageQuota: 5368709120,      // 5 GB Quota
        docEditor: null,               // OnlyOffice editor instance
        foldersLimit: 5,
        filesLimit: 10,
        showAllFolders: false,
        showAllFiles: false,
        listLimit: 10,
        showAllList: false
    };
};

export const getCSRFToken = () => {
    return document.querySelector('meta[name="csrf-token"]').getAttribute('content');
};

export const getDOMElements = () => {
    return {
        spaContentArea: document.getElementById('spa-content-area'),
        mainContent: document.getElementById('main-content'),
        appNewTrigger: document.getElementById('app-new-trigger'),
        appNewMenu: document.getElementById('app-new-menu'),
        detailsDrawer: document.getElementById('details-drawer'),
        contextMenu: document.getElementById('context-menu'),
        renameModal: document.getElementById('rename-modal'),
        shareModal: document.getElementById('share-modal'),
        moveModal: document.getElementById('move-modal'),
        onlyOfficeEditor: document.getElementById('onlyoffice-editor'),
        dragOverlay: document.getElementById('drag-overlay'),
        toastContainer: document.getElementById('toast-container')
    };
};
