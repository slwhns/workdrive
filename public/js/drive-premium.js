/* ==========================================
   WORKDRIVE PREMIUM SPA ENGINE
   Dynamic SPA Routing, Rendering, Modals, Drag-and-Drop & OnlyOffice
   ========================================== */

document.addEventListener('DOMContentLoaded', function () {
    // ------------------------------------------
    // 1. STATE MANAGEMENT
    // ------------------------------------------
    const state = {
        currentTab: 'index',      // 'index', 'shared', 'recents', 'starred', 'trash', 'search', 'tag'
        currentFolderId: null,     // Current directory ID
        currentTag: null,          // Current active tag name
        tags: [],                  // All tags list
        searchQuery: '',           // Search query
        selectedItem: null,        // Selected file/folder object
        viewMode: 'grid',          // 'grid' or 'list'
        folders: [],
        files: [],
        breadcrumbs: [],
        currentFolder: null,
        storageUsed: 0,            // Total storage used in bytes
        storageQuota: 5368709120,  // 5 GB Quota
        docEditor: null,           // OnlyOffice editor instance
        foldersLimit: 5,
        filesLimit: 10,
        showAllFolders: false,
        showAllFiles: false,
        listLimit: 10,
        showAllList: false
    };

    // Grab CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    // DOM Selectors
    const spaContentArea = document.getElementById('spa-content-area');
    const mainContent = document.getElementById('main-content');
    const appNewTrigger = document.getElementById('app-new-trigger');
    const appNewMenu = document.getElementById('app-new-menu');
    
    // Create new UI shell items if missing
    ensureAppShellElements();
    
    const detailsDrawer = document.getElementById('details-drawer');
    const contextMenu = document.getElementById('context-menu');
    const renameModal = document.getElementById('rename-modal');
    const shareModal = document.getElementById('share-modal');
    const moveModal = document.getElementById('move-modal');
    const onlyOfficeEditor = document.getElementById('onlyoffice-editor');
    const dragOverlay = document.getElementById('drag-overlay');
    const toastContainer = document.getElementById('toast-container');

    // ------------------------------------------
    // 2. INITIALIZATION
    // ------------------------------------------
    initSPA();

    function initSPA() {
        // Load initial state based on URL
        parseUrlToState(window.location.href);
        loadCurrentView(false);

        // Bind layout sidebar click listeners
        bindSidebarNavigation();

        // Listen for browser Back/Forward buttons
        window.addEventListener('popstate', function (event) {
            if (event.state) {
                state.currentTab = event.state.currentTab;
                state.currentFolderId = event.state.currentFolderId;
                state.searchQuery = event.state.searchQuery;
                loadCurrentView(false);
            } else {
                parseUrlToState(window.location.href);
                loadCurrentView(false);
            }
        });

        // Setup Drag & Drop Upload
        setupDragAndDrop();

        // Bind document level click behaviors (closing menus, drawers, selections)
        bindGlobalDocumentClicks();
    }

    // Parse incoming URL to state (helps handle bookmarks or initial load)
    function parseUrlToState(urlStr) {
        try {
            const url = new URL(urlStr);
            const path = url.pathname;
            
            if (path.includes('/shared')) {
                state.currentTab = 'shared';
                state.currentTag = null;
            } else if (path.includes('/recents')) {
                state.currentTab = 'recents';
                state.currentTag = null;
            } else if (path.includes('/starred')) {
                state.currentTab = 'starred';
                state.currentTag = null;
            } else if (path.includes('/trash')) {
                state.currentTab = 'trash';
                state.currentTag = null;
            } else if (path.includes('/search')) {
                state.currentTab = 'search';
                state.currentTag = null;
                state.searchQuery = url.searchParams.get('q') || '';
            } else if (path.includes('/tags/')) {
                state.currentTab = 'tag';
                const segments = path.split('/');
                state.currentTag = decodeURIComponent(segments[segments.indexOf('tags') + 1] || 'all');
            } else {
                state.currentTab = 'index';
                state.currentTag = null;
            }

            state.currentFolderId = url.searchParams.get('folder_id') || null;
        } catch (e) {
            state.currentTab = 'index';
            state.currentFolderId = null;
            state.currentTag = null;
        }
    }

    // ------------------------------------------
    // 3. SPA ROUTING & API INTEGRATION
    // ------------------------------------------
    function navigateSPA(tab, folderId = null, query = '', pushHistory = true) {
        state.currentTab = tab;
        if (tab === 'tag') {
            state.currentTag = folderId; // folderId is tag name
            state.currentFolderId = null;
        } else {
            state.currentFolderId = folderId;
            state.currentTag = null;
        }
        state.searchQuery = query;
        state.selectedItem = null; // Reset selection on navigate
        state.showAllFolders = false;
        state.showAllFiles = false;
        state.showAllList = false;

        // Update document title dynamically
        let pageTitle = 'My Drive';
        if (tab === 'shared') pageTitle = 'Shared with me';
        else if (tab === 'recents') pageTitle = 'Recents';
        else if (tab === 'starred') pageTitle = 'Starred';
        else if (tab === 'trash') pageTitle = 'Trash';
        else if (tab === 'search') pageTitle = 'Search Results';
        else if (tab === 'tag') pageTitle = state.currentTag === 'all' ? 'All Tags' : `Tag: ${state.currentTag}`;
        document.title = `WD | ${pageTitle}`;

        // Build browser URL
        let url = '/';
        if (tab === 'shared') url = '/shared';
        else if (tab === 'recents') url = '/recents';
        else if (tab === 'starred') url = '/starred';
        else if (tab === 'trash') url = '/trash';
        else if (tab === 'search') url = `/search?q=${encodeURIComponent(query)}`;
        else if (tab === 'tag') url = `/tags/${encodeURIComponent(state.currentTag)}`;

        if (state.currentFolderId) {
            url += (url.includes('?') ? '&' : '?') + `folder_id=${state.currentFolderId}`;
        }

        if (pushHistory) {
            window.history.pushState({
                currentTab: state.currentTab,
                currentFolderId: state.currentFolderId,
                searchQuery: state.searchQuery
            }, '', url);
        }

        // Collapse detail drawer
        if (detailsDrawer) detailsDrawer.classList.add('collapsed');
        
        loadCurrentView();
        updateActiveSidebarClass();
    }

    function loadCurrentView(showLoading = true) {
        if (!spaContentArea) return;

        if (showLoading) {
            spaContentArea.innerHTML = `
                <div class="d-flex fd-column ai-center jc-center" style="min-height: 350px;">
                    <div class="spinner"></div>
                    <div class="fs-13 clr-grey2">Loading your Workspace...</div>
                </div>
            `;
        }

        // Build API URL
        let apiUrl = '/';
        if (state.currentTab === 'shared') apiUrl = '/shared';
        else if (state.currentTab === 'recents') apiUrl = '/recents';
        else if (state.currentTab === 'starred') apiUrl = '/starred';
        else if (state.currentTab === 'trash') apiUrl = '/trash';
        else if (state.currentTab === 'search') apiUrl = `/search?q=${encodeURIComponent(state.searchQuery)}`;
        else if (state.currentTab === 'tag') apiUrl = `/tags/${encodeURIComponent(state.currentTag)}`;

        const params = [];
        params.push('json=1');
        if (state.currentFolderId) params.push(`folder_id=${state.currentFolderId}`);
        apiUrl += (apiUrl.includes('?') ? '&' : '?') + params.join('&');

        fetch(apiUrl, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(response => {
            if (!response.ok) throw new Error('Failed to fetch view');
            return response.json();
        })
        .then(data => {
            if (data.status === 'success' || data.files !== undefined) {
                state.folders = data.folders || [];
                state.files = data.files || [];
                state.breadcrumbs = data.breadcrumbs || [];
                state.currentFolder = data.currentFolder || null;
                state.tags = data.tags || [];
                
                renderSPAView();
                fetchStorageUsage(); // Update storage panel

                // Check for open_file_id parameter to auto-launch
                const urlParams = new URLSearchParams(window.location.search);
                const openFileId = urlParams.get('open_file_id');
                if (openFileId) {
                    // Clean up parameter from URL so it doesn't reopen on manual page refresh
                    urlParams.delete('open_file_id');
                    const cleanUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
                    window.history.replaceState(null, '', cleanUrl);

                    launchEditor(openFileId);
                }
            }
        })
        .catch(err => {
            console.error(err);
            spaContentArea.innerHTML = `
                <div class="d-flex fd-column ai-center jc-center text-center pd-30" style="min-height: 300px;">
                    <i class="ri-error-warning-line fs-40 clr-red mg-b-10"></i>
                    <div class="fs-16 fw-600 clr-white">Connection Error</div>
                    <div class="fs-13 clr-grey2 mg-t-8">Could not connect to WorkDrive. Please try again.</div>
                    <button class="btn btn-primary mg-t-15" id="btn-retry-spa">Retry</button>
                </div>
            `;
            document.getElementById('btn-retry-spa')?.addEventListener('click', () => loadCurrentView());
        });
    }

    function fetchStorageUsage() {
        // Run a background fetch of My Drive files size, or query it
        let total = 0;
        state.files.forEach(f => total += parseInt(f.size || 0));
        // Use a background call or sum what we have, or simply query FileService via a quick API if necessary
        // For premium visual feel, let's keep it updated dynamically
        state.storageUsed = total;
        updateStorageWidget();
    }

    // ------------------------------------------
    // 4. VIEW RENDERING
    // ------------------------------------------
    function renderSPAView() {
        if (!spaContentArea) return;

        // Determine view mode active class
        const viewModeListClass = state.viewMode === 'list' ? 'active' : '';
        const viewModeGridClass = state.viewMode === 'grid' ? 'active' : '';

        // Draw header
        let title = 'My Drive';
        let subtitle = 'Organize and access your files';
        if (state.currentTab === 'shared') { title = 'Shared with me'; subtitle = 'Files shared with you by other members'; }
        else if (state.currentTab === 'recents') { title = 'Recents'; subtitle = 'Recently accessed or modified files'; }
        else if (state.currentTab === 'starred') { title = 'Starred'; subtitle = 'Starred documents and folders'; }
        else if (state.currentTab === 'trash') { title = 'Trash'; subtitle = 'Deleted items are stored here'; }
        else if (state.currentTab === 'search') { title = 'Search Results'; subtitle = `Showing results for "${state.searchQuery}"`; }
        else if (state.currentTab === 'tag') {
            if (state.currentTag === 'all') {
                title = 'All Tags';
                subtitle = 'Organize and access items using tags';
            } else {
                title = `Tag: ${state.currentTag}`;
                subtitle = `Showing items tagged with ${state.currentTag}`;
            }
        }

        let html = `
            <div class="drive-view">
                <div class="drive-header d-flex jc-between ai-center mg-b-20">
                    <div>
                        <h1 class="fs-24 fw-700 clr-white">${title}</h1>
                        <div class="fs-12 clr-grey1">${subtitle}</div>
                    </div>

                    <div class="drive-actions d-flex ai-center gap-10">
                        <div class="drive-search position-relative">
                            <input type="search" id="spa-search-input" value="${state.searchQuery}" placeholder="Search files and folders..." class="drive-search-input">
                        </div>

                        <button class="btn btn-outline ${viewModeListClass}" id="view-toggle-list" title="List view" aria-pressed="${state.viewMode === 'list'}"><i class="ri-list-unordered"></i></button>
                        <button class="btn btn-outline ${viewModeGridClass}" id="view-toggle-grid" title="Grid view" aria-pressed="${state.viewMode === 'grid'}"><i class="ri-layout-grid-line"></i></button>
                        <button class="btn btn-primary d-none d-sm-flex" id="btn-refresh-spa"><i class="ri-refresh-line"></i> Refresh</button>
                    </div>
                </div>

                <!-- Breadcrumbs & Toolbar -->
                <div class="d-flex jc-between ai-center fw-wrap gap-10 mg-b-15">
                    <div id="spa-breadcrumbs-container"></div>
                    
                    <div class="d-flex ai-center gap-8">
                        ${state.currentTab === 'index' ? `
                            <button class="btn btn-secondary" id="spa-btn-create-folder"><i class="ri-folder-add-line"></i> Create Folder</button>
                            <label class="btn btn-secondary cursor-pointer" for="spa-upload-input"><i class="ri-upload-2-line"></i> Upload Files</label>
                            <input type="file" id="spa-upload-input" multiple style="display:none;">
                        ` : ''}
                        
                        ${state.currentTab === 'trash' && (state.folders.length > 0 || state.files.length > 0) ? `
                            <span class="fs-12 clr-grey2">Right-click items to restore or delete permanently</span>
                        ` : ''}
                    </div>
                </div>

                <!-- Main split layout: File list + Drawer -->
                <div class="drive-content-split">
                    <div class="drive-main-panel">
                        <div class="drive-grid ${state.viewMode === 'list' ? 'list-view' : ''}" id="spa-grid-container">
                            <!-- Dynamically generated items -->
                        </div>
                    </div>

                    <!-- Details drawer panel -->
                    <div class="drive-details-drawer collapsed" id="details-drawer">
                        <!-- Drawer contents populate here -->
                    </div>
                </div>
            </div>
        `;

        spaContentArea.innerHTML = html;

        // Render Breadcrumbs
        renderBreadcrumbs();

        // Render Cards
        renderCards();

        // Setup event handlers on newly rendered HTML
        bindNewViewEvents();
    }

    function renderBreadcrumbs() {
        const container = document.getElementById('spa-breadcrumbs-container');
        if (!container) return;

        if (state.currentTab !== 'index') {
            const tabLabel = state.currentTab === 'tag' ? (state.currentTag === 'all' ? 'ALL TAGS' : `TAG: ${state.currentTag.toUpperCase()}`) : state.currentTab.toUpperCase();
            container.innerHTML = `
                <div class="breadcrumbs">
                    <a href="#" data-spa-tab="index" data-spa-folder="null">My Drive</a>
                    <span class="divider">/</span>
                    <span class="active">${tabLabel}</span>
                </div>
            `;
        } else {
            let bcHtml = `<div class="breadcrumbs">`;
            bcHtml += `<a href="#" data-spa-tab="index" data-spa-folder="null">My Drive</a>`;

            state.breadcrumbs.forEach((node, index) => {
                bcHtml += `<span class="divider">/</span>`;
                if (index === state.breadcrumbs.length - 1) {
                    bcHtml += `<span class="active">${escapeHtml(node.name)}</span>`;
                } else {
                    bcHtml += `<a href="#" data-spa-tab="index" data-spa-folder="${node.id}">${escapeHtml(node.name)}</a>`;
                }
            });

            bcHtml += `</div>`;
            container.innerHTML = bcHtml;
        }

        // Bind clicks
        container.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const tab = this.getAttribute('data-spa-tab');
                const folderId = this.getAttribute('data-spa-folder');
                navigateSPA(tab, folderId === 'null' ? null : folderId);
            });
        });
    }

    function renderCards() {
        const grid = document.getElementById('spa-grid-container');
        if (!grid) return;

        const totalItems = state.folders.length + state.files.length;

        if (state.currentTab === 'tag' && state.currentTag === 'all') {
            let tagsHtml = `
                <div class="tags-grid" style="grid-column: 1 / -1;">
            `;
            const tags = state.tags || [];
            tags.forEach(tag => {
                const isStandard = ['Red', 'Orange', 'Yellow', 'Green', 'Blue', 'Purple', 'Grey'].includes(tag);
                tagsHtml += `
                    <div class="tag-card" data-tag-name="${tag}">
                        <span class="tag-card-dot ${isStandard ? 'tag-dot-' + tag.toLowerCase() : 'tag-dot-custom'}">
                            ${isStandard ? '' : '<i class="ri-price-tag-3-fill"></i>'}
                        </span>
                        <span class="tag-card-name">${tag}</span>
                    </div>
                `;
            });
            tagsHtml += `</div>`;
            grid.innerHTML = tagsHtml;
            return;
        }

        if (totalItems === 0) {
            let emptyTitle = 'No files yet';
            let emptySubtitle = 'Start by creating a folder, uploading files, or creating office documents.';
            if (state.currentTab === 'shared') { emptyTitle = 'Shared folder is empty'; emptySubtitle = 'When other users share documents with you, they will show up here.'; }
            else if (state.currentTab === 'recents') { emptyTitle = 'No recent activity'; emptySubtitle = 'All files you view, edit, or upload will list here in order.'; }
            else if (state.currentTab === 'starred') { emptyTitle = 'Nothing starred yet'; emptySubtitle = 'Star important documents and folders to access them quickly.'; }
            else if (state.currentTab === 'trash') { emptyTitle = 'Trash is empty'; emptySubtitle = 'Items moved to trash will reside here for 30 days.'; }
            else if (state.currentTab === 'search') { emptyTitle = 'No matches found'; emptySubtitle = `We couldn't find any folders or files matching "${state.searchQuery}".`; }
            else if (state.currentTab === 'tag') {
                emptyTitle = `No items tagged with "${state.currentTag}"`;
                emptySubtitle = 'Add this tag to files or folders to see them here.';
            }

            grid.innerHTML = `
                <div class="drive-empty-card pd-40 text-center w-100">
                    <i class="ri-folder-open-line fs-50 clr-grey2 mg-b-15 d-inline-block"></i>
                    <div class="fs-18 fw-700 clr-white">${emptyTitle}</div>
                    <div class="fs-13 clr-grey2 mg-t-8" style="max-width: 400px; margin-left: auto; margin-right: auto;">${emptySubtitle}</div>
                    ${state.currentTab === 'index' ? `
                        <div class="mg-t-15 d-flex jc-center gap-10">
                            <button class="btn btn-primary" id="empty-create-btn"><i class="ri-folder-add-line"></i> Create Folder</button>
                            <label class="btn btn-outline cursor-pointer" for="spa-upload-input"><i class="ri-upload-2-line"></i> Upload</label>
                        </div>
                    ` : ''}
                </div>
            `;
            
            document.getElementById('empty-create-btn')?.addEventListener('click', () => {
                document.getElementById('spa-btn-create-folder')?.click();
            });
            return;
        }

        let html = '';

        // If list view, draw folders as grid and files as list
        if (state.viewMode === 'list') {
            // 1. Draw Folders (Grid style)
            if (state.folders.length > 0) {
                html += `
                    <div class="grid-section-title w-100 clr-white fw-700 fs-14 mg-t-10 mg-b-10">Folders</div>
                    <div class="folders-grid">
                `;
                
                let foldersToRender = state.folders;
                let showSeeMoreFolders = false;
                if (state.folders.length > state.foldersLimit && !state.showAllFolders) {
                    foldersToRender = state.folders.slice(0, state.foldersLimit);
                    showSeeMoreFolders = true;
                }

                foldersToRender.forEach(folder => {
                    html += generateItemCardHtml(folder, true, true); // force grid view
                });
                html += `</div>`;

                if (showSeeMoreFolders) {
                    html += `
                        <div class="see-more-container">
                            <button class="see-more-btn" id="see-more-folders">
                                <span>See more</span>
                                <i class="ri-arrow-down-s-line"></i>
                            </button>
                        </div>
                    `;
                }
            }

            // 2. Draw Files (List style)
            if (state.files.length > 0) {
                if (state.folders.length > 0) {
                    html += `<div class="grid-section-title w-100 clr-white fw-700 fs-14 mg-t-10 mg-b-10">Files</div>`;
                }

                html += `
                    <div class="drive-list-header" role="rowgroup">
                        <div class="col-name">Name</div>
                        <div class="col-modified">Last modified</div>
                        <div class="col-size">Size</div>
                        <div class="col-actions" aria-hidden="true"></div>
                    </div>
                `;

                let filesToRender = state.files;
                let showSeeMoreFiles = false;
                if (state.files.length > state.filesLimit && !state.showAllFiles) {
                    filesToRender = state.files.slice(0, state.filesLimit);
                    showSeeMoreFiles = true;
                }

                filesToRender.forEach(file => {
                    html += generateItemCardHtml(file, false); // list view style
                });

                if (showSeeMoreFiles) {
                    html += `
                        <div class="see-more-container">
                            <button class="see-more-btn" id="see-more-files">
                                <span>See more</span>
                                <i class="ri-arrow-down-s-line"></i>
                            </button>
                        </div>
                    `;
                }
            }
            
            grid.innerHTML = html;
        } else {
            // Grid mode with beautiful grouped headings for folders and files
            let gridHtml = '';
            
            if (state.folders.length > 0) {
                gridHtml += `
                    <div class="grid-section-title w-100 clr-white fw-700 fs-14 mg-t-10 mg-b-10" style="grid-column: 1 / -1;">Folders</div>
                    <div class="folders-grid">
                `;
                
                let foldersToRender = state.folders;
                let showSeeMoreFolders = false;
                if (state.folders.length > state.foldersLimit && !state.showAllFolders) {
                    foldersToRender = state.folders.slice(0, state.foldersLimit);
                    showSeeMoreFolders = true;
                }

                foldersToRender.forEach(folder => {
                    gridHtml += generateItemCardHtml(folder, true);
                });
                gridHtml += `</div>`;

                if (showSeeMoreFolders) {
                    gridHtml += `
                        <div class="see-more-container">
                            <button class="see-more-btn" id="see-more-folders">
                                <span>See more</span>
                                <i class="ri-arrow-down-s-line"></i>
                            </button>
                        </div>
                    `;
                }
            }

            if (state.files.length > 0) {
                gridHtml += `
                    <div class="grid-section-title w-100 clr-white fw-700 fs-14 mg-t-10 mg-b-10" style="grid-column: 1 / -1;">Files</div>
                    <div class="files-grid">
                `;
                
                let filesToRender = state.files;
                let showSeeMoreFiles = false;
                if (state.files.length > state.filesLimit && !state.showAllFiles) {
                    filesToRender = state.files.slice(0, state.filesLimit);
                    showSeeMoreFiles = true;
                }

                filesToRender.forEach(file => {
                    gridHtml += generateItemCardHtml(file, false);
                });
                gridHtml += `</div>`;

                if (showSeeMoreFiles) {
                    gridHtml += `
                        <div class="see-more-container">
                            <button class="see-more-btn" id="see-more-files">
                                <span>See more</span>
                                <i class="ri-arrow-down-s-line"></i>
                            </button>
                        </div>
                    `;
                }
            }

            grid.innerHTML = gridHtml;
        }
    }

    function generateItemCardHtml(item, isFolder, forceGridView = false) {
        const isSelected = state.selectedItem && state.selectedItem.id === item.id && state.selectedItem.is_folder === isFolder;
        const selectedClass = isSelected ? 'selected' : '';
        const starClass = item.is_starred ? 'active' : '';
        const starIcon = item.is_starred ? 'ri-star-fill' : 'ri-star-line';
        
        // Dynamic file type mapping
        const typeInfo = getFileTypeInfo(item.name, isFolder, item.mime_type);
        const formattedDate = formatDate(item.updated_at || item.created_at);
        const formattedSize = isFolder ? '--' : formatBytes(item.size);

        if (state.viewMode === 'list' && !forceGridView) {
            const tagsHtml = renderCardTagsInline(item.tags);
            return `
                <div class="drive-card ${selectedClass}" data-item-id="${item.id}" data-item-type="${isFolder ? 'folder' : 'file'}" data-file-id="${item.id}" data-is-folder="${isFolder}">
                    <div class="drive-card-top">
                        <div class="drive-card-icon ${typeInfo.iconColorClass}">
                            <i class="${typeInfo.icon}"></i>
                        </div>
                        <div class="d-flex fd-column overflow-hidden">
                            <div class="drive-card-title" title="${escapeHtml(item.name)}">${escapeHtml(item.name)}</div>
                            ${tagsHtml}
                        </div>
                    </div>
                    <div class="col-modified">${formattedDate}</div>
                    <div class="col-size">${formattedSize}</div>
                    <div class="drive-card-actions">
                        <button type="button" class="btn-more spa-card-action-trigger" data-item-id="${item.id}" data-item-type="${isFolder ? 'folder' : 'file'}">
                            <i class="ri-more-2-fill"></i>
                        </button>
                    </div>
                </div>
            `;
        } else {
            if (isFolder) {
                const tagsHtml = renderCardTagsInline(item.tags);
                // Compact Google Drive-style Folder Card
                return `
                    <div class="drive-card folder-card ${selectedClass}" data-item-id="${item.id}" data-item-type="folder" data-file-id="${item.id}" data-is-folder="true">
                        <div class="folder-card-content">
                            <div class="drive-card-icon ${typeInfo.iconColorClass}">
                                <i class="${typeInfo.icon}"></i>
                            </div>
                            <div class="d-flex fd-column fg-1 overflow-hidden" style="margin-inline-start: 4px;">
                                <div class="drive-card-title" title="${escapeHtml(item.name)}">${escapeHtml(item.name)}</div>
                                ${tagsHtml}
                            </div>
                            <div class="folder-card-actions">
                                <i class="spa-star-trigger ${starIcon} drive-card-star ${starClass}" data-item-id="${item.id}" data-item-type="folder" title="Star folder"></i>
                                <button type="button" class="btn-more spa-card-action-trigger" data-item-id="${item.id}" data-item-type="folder">
                                    <i class="ri-more-2-fill"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                const tagsHtml = renderCardTagsInline(item.tags);
                // Detailed Google Drive-style File Card with Preview Area
                return `
                    <div class="drive-card file-card ${selectedClass}" data-item-id="${item.id}" data-item-type="file" data-file-id="${item.id}" data-is-folder="false">
                        <div class="file-card-header">
                            <div class="drive-card-icon ${typeInfo.iconColorClass}">
                                <i class="${typeInfo.icon}"></i>
                            </div>
                            <div class="drive-card-title" title="${escapeHtml(item.name)}">${escapeHtml(item.name)}</div>
                            <button type="button" class="btn-more spa-card-action-trigger" data-item-id="${item.id}" data-item-type="file">
                                <i class="ri-more-2-fill"></i>
                            </button>
                        </div>
                        
                        <div class="file-card-preview-area">
                            ${renderFilePreview(item, typeInfo)}
                        </div>
                        
                        <div class="file-card-footer">
                            <i class="spa-star-trigger ${starIcon} drive-card-star ${starClass}" data-item-id="${item.id}" data-item-type="file" title="Star file"></i>
                            <div class="d-flex fd-column ai-end gap-2">
                                ${tagsHtml}
                                <div class="file-card-meta">
                                    <span>${formattedSize}</span>
                                    <span>•</span>
                                    <span>${formattedDate}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }
        }
    }    function renderFilePreview(item, typeInfo) {
        const ext = item.name.split('.').pop().toLowerCase();
        
        // If it's an image, render the actual image!
        if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'].includes(ext)) {
            return `
                <div class="preview-image-wrapper">
                    <img src="/drive/files/${item.id}/download" alt="${escapeHtml(item.name)}" loading="lazy" class="preview-image">
                </div>
            `;
        }
        
        const officeExtensions = [
            'docx', 'doc', 'docm', 'dot', 'dotx', 'epub', 'fodt', 'htm', 'html', 'mht', 'mhtml', 'odt', 'ott', 'rtf', 'txt', 'djvu', 'xps', 'oxps',
            'xlsx', 'xls', 'xlsm', 'xlt', 'xltx', 'csv', 'fods', 'ods', 'ots',
            'pptx', 'ppt', 'pptm', 'pot', 'potx', 'pps', 'ppsx', 'fodp', 'odp', 'otp', 'pdf'
        ];

        if (officeExtensions.includes(ext)) {
            const mockHtml = getMockPreviewHtml(ext, typeInfo, item);
            return `
                <div class="preview-thumbnail-wrapper" style="width:100%; height:100%; position:relative;">
                    <img src="/drive/files/${item.id}/thumbnail" alt="${escapeHtml(item.name)}" loading="eager" class="real-thumbnail" style="width:100%; height:100%; object-fit:cover; object-position:top; position:absolute; top:0; left:0; z-index:2; background:#ffffff; display:none;" onload="this.style.display='block';">
                    <div class="thumbnail-fallback" style="width:100%; height:100%; position:absolute; top:0; left:0; z-index:1;">
                        ${mockHtml}
                    </div>
                </div>
            `;
        }

        return getMockPreviewHtml(ext, typeInfo, item);
    }

    function getMockPreviewHtml(ext, typeInfo, item) {
        // If it's a PDF, render a simulated PDF document page
        if (ext === 'pdf') {
            return `
                <div class="preview-doc-sim pdf-sim">
                    <div class="sim-header">
                        <i class="ri-file-pdf-fill"></i>
                        <span>PDF Document</span>
                    </div>
                    <div class="sim-body">
                        <div class="sim-line sim-title"></div>
                        <div class="sim-line"></div>
                        <div class="sim-line"></div>
                        <div class="sim-line short"></div>
                        <div class="sim-block"></div>
                    </div>
                </div>
            `;
        }
        
        // If it's a Word document
        if (['doc', 'docx'].includes(ext)) {
            return `
                <div class="preview-doc-sim word-sim">
                    <div class="sim-header">
                        <i class="ri-file-word-2-fill"></i>
                        <span>Word Document</span>
                    </div>
                    <div class="sim-body">
                        <div class="sim-line sim-title"></div>
                        <div class="sim-line"></div>
                        <div class="sim-line"></div>
                        <div class="sim-line"></div>
                        <div class="sim-line short"></div>
                    </div>
                </div>
            `;
        }
        
        // If it's a Excel spreadsheet
        if (['xls', 'xlsx'].includes(ext)) {
            return `
                <div class="preview-sheet-sim">
                    <div class="sim-header">
                        <i class="ri-file-excel-2-fill"></i>
                        <span>Spreadsheet</span>
                    </div>
                    <div class="sim-sheet-grid">
                        <div class="sim-cell header"></div>
                        <div class="sim-cell header"></div>
                        <div class="sim-cell header"></div>
                        <div class="sim-cell"></div>
                        <div class="sim-cell"></div>
                        <div class="sim-cell"></div>
                        <div class="sim-cell"></div>
                        <div class="sim-cell highlight"></div>
                        <div class="sim-cell"></div>
                        <div class="sim-cell"></div>
                        <div class="sim-cell"></div>
                        <div class="sim-cell"></div>
                    </div>
                </div>
            `;
        }
        
        // If it's a PowerPoint slide deck
        if (['ppt', 'pptx'].includes(ext)) {
            return `
                <div class="preview-slide-sim">
                    <div class="sim-header">
                        <i class="ri-slideshow-3-fill"></i>
                        <span>Presentation</span>
                    </div>
                    <div class="sim-slide-canvas">
                        <div class="sim-slide-title">TITLE SLIDE</div>
                        <div class="sim-slide-subtitle">Click to add subtitles</div>
                        <div class="sim-slide-shape"></div>
                    </div>
                </div>
            `;
        }
        
        // If it's a code file (js, css, html, php, py, json, etc.)
        if (['js', 'css', 'html', 'php', 'py', 'json', 'sh', 'sql', 'txt'].includes(ext)) {
            return `
                <div class="preview-code-sim">
                    <div class="sim-header">
                        <i class="ri-code-s-slash-line"></i>
                        <span>${ext.toUpperCase()} Source</span>
                    </div>
                    <div class="sim-code-lines">
                        <span class="code-ln">1</span> <span class="code-kw">import</span> React <span class="code-kw">from</span> <span class="code-str">'react'</span>;<br>
                        <span class="code-ln">2</span> <span class="code-kw">const</span> App = () =&gt; {<br>
                        <span class="code-ln">3</span> &nbsp;&nbsp;console.log(<span class="code-str">"WorkDrive"</span>);<br>
                        <span class="code-ln">4</span> &nbsp;&nbsp;<span class="code-kw">return</span> &lt;<span class="code-tag">div</span>&gt;Hello&lt;/<span class="code-tag">div</span>&gt;;<br>
                        <span class="code-ln">5</span> };
                    </div>
                </div>
            `;
        }

        // Default fallback preview
        return `
            <div class="preview-other-sim">
                <div class="drive-card-icon ${typeInfo.iconColorClass}" style="width: 50px; height: 50px; font-size: 32px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); border-radius: 10px; margin-bottom: 8px; justify-content: center; align-items: center; display: flex;">
                    <i class="${typeInfo.icon}"></i>
                </div>
                <div class="fs-11 clr-grey2">${typeInfo.label}</div>
            </div>
        `;
    }

    // File type and styling helper
    function getFileTypeInfo(filename, isFolder, mimeType = '') {
        if (isFolder) {
            return { icon: 'ri-folder-3-fill', iconColorClass: 'icon-folder', label: 'Folder' };
        }

        const ext = filename.split('.').pop().toLowerCase();
        
        switch (ext) {
            case 'doc':
            case 'docx':
                return { icon: 'ri-file-word-2-fill', iconColorClass: 'icon-word', label: 'Word Document' };
            case 'xls':
            case 'xlsx':
                return { icon: 'ri-file-excel-2-fill', iconColorClass: 'icon-excel', label: 'Excel Spreadsheet' };
            case 'ppt':
            case 'pptx':
                return { icon: 'ri-slideshow-3-fill', iconColorClass: 'icon-powerpoint', label: 'PowerPoint Presentation' };
            case 'pdf':
                return { icon: 'ri-file-pdf-fill', iconColorClass: 'icon-pdf', label: 'PDF Document' };
            case 'jpg':
            case 'jpeg':
            case 'png':
            case 'gif':
            case 'webp':
            case 'svg':
                return { icon: 'ri-image-2-fill', iconColorClass: 'icon-image', label: 'Image File' };
            case 'zip':
            case 'rar':
            case '7z':
            case 'tar':
            case 'gz':
                return { icon: 'ri-folder-zip-fill', iconColorClass: 'icon-other', label: 'Compressed Archive' };
            default:
                return { icon: 'ri-file-3-fill', iconColorClass: 'icon-other', label: 'File' };
        }
    }

    // ------------------------------------------
    // 5. EVENT BINDING & INTERACTIONS
    // ------------------------------------------
    function bindSidebarNavigation() {
        const sidebar = document.querySelector('.app-sidebar');
        if (!sidebar) return;

        sidebar.querySelectorAll('a[href]').forEach(link => {
            link.addEventListener('click', function (e) {
                const href = this.getAttribute('href');
                // Only intercept internal links
                if (href.startsWith('/') || href.startsWith('http://localhost') || href.startsWith('http://127.0.0.1') || href.includes('/tags/')) {
                    e.preventDefault();
                    
                    let tab = 'index';
                    let param = null;
                    if (href.includes('/shared')) tab = 'shared';
                    else if (href.includes('/recents')) tab = 'recents';
                    else if (href.includes('/starred')) tab = 'starred';
                    else if (href.includes('/trash')) tab = 'trash';
                    else if (href.includes('/tags/')) {
                        tab = 'tag';
                        const segments = href.split('/');
                        param = decodeURIComponent(segments[segments.indexOf('tags') + 1] || 'all');
                    }

                    navigateSPA(tab, param);
                }
            });
        });
    }

    function bindNewViewEvents() {
        // Close context menu when main scroll container is scrolled
        document.querySelector('.drive-main-panel')?.addEventListener('scroll', closeContextMenu, { passive: true });

        // Tag card clicks in All Tags view
        document.querySelectorAll('.tag-card').forEach(card => {
            card.addEventListener('click', function(e) {
                e.stopPropagation();
                const tagName = this.getAttribute('data-tag-name');
                navigateSPA('tag', tagName);
            });
        });

        // Toggle view buttons
        document.getElementById('view-toggle-list')?.addEventListener('click', function(e) {
            e.stopPropagation();
            state.viewMode = 'list';
            localStorage.setItem('drive_view_mode', 'list');
            renderSPAView();
        });

        document.getElementById('view-toggle-grid')?.addEventListener('click', function(e) {
            e.stopPropagation();
            state.viewMode = 'grid';
            localStorage.setItem('drive_view_mode', 'grid');
            renderSPAView();
        });

        document.getElementById('btn-refresh-spa')?.addEventListener('click', function(e) {
            e.stopPropagation();
            loadCurrentView();
        });

        // See more buttons click handlers
        document.getElementById('see-more-folders')?.addEventListener('click', function(e) {
            e.stopPropagation();
            state.showAllFolders = true;
            renderSPAView();
        });

        document.getElementById('see-more-files')?.addEventListener('click', function(e) {
            e.stopPropagation();
            state.showAllFiles = true;
            renderSPAView();
        });

        document.getElementById('see-more-list')?.addEventListener('click', function(e) {
            e.stopPropagation();
            state.showAllList = true;
            renderSPAView();
        });

        // Search Input listeners
        const searchInput = document.getElementById('spa-search-input');
        if (searchInput) {
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const query = this.value.trim();
                    navigateSPA('search', null, query);
                }
            });
        }

        // Star triggering (click directly on card stars in grid view)
        document.querySelectorAll('.spa-star-trigger').forEach(star => {
            star.addEventListener('click', function(e) {
                e.stopPropagation();
                const itemId = this.getAttribute('data-item-id');
                const itemType = this.getAttribute('data-item-type');
                executeStarToggle(itemId, itemType);
            });
        });

        // Context trigger buttons ("...") inside list view
        document.querySelectorAll('.spa-card-action-trigger').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const itemId = this.getAttribute('data-item-id');
                const itemType = this.getAttribute('data-item-type');
                
                // Select item
                selectItem(itemId, itemType);
                
                // Show context menu next to button
                const rect = this.getBoundingClientRect();
                showContextMenu(rect.left - 130, rect.bottom + 5, e);
            });
        });

        // Navigation / Selection click on cards
        document.querySelectorAll('.drive-card').forEach(card => {
            card.addEventListener('click', function(e) {
                e.stopPropagation();
                const itemId = this.getAttribute('data-item-id');
                const itemType = this.getAttribute('data-item-type');
                selectItem(itemId, itemType);
            });

            card.addEventListener('dblclick', function(e) {
                e.stopPropagation();
                const itemId = this.getAttribute('data-item-id');
                const itemType = this.getAttribute('data-item-type');
                
                if (itemType === 'folder') {
                    if (state.currentTab === 'trash') {
                        showToast('Cannot open folders in Trash. Restore it first!', 'info');
                        return;
                    }
                    navigateSPA(state.currentTab, itemId);
                } else {
                    const file = state.files.find(f => f.id === parseInt(itemId));
                    const ext = file ? file.name.split('.').pop().toLowerCase() : '';
                    if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'].includes(ext)) {
                        launchPreview(itemId);
                    } else {
                        // Open OnlyOffice editor in edit mode
                        launchEditor(itemId);
                    }
                }
            });

            // Right-click context menu trigger
            card.addEventListener('contextmenu', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const itemId = this.getAttribute('data-item-id');
                const itemType = this.getAttribute('data-item-type');
                
                selectItem(itemId, itemType);
                showContextMenu(e.clientX, e.clientY, e);
            });
        });

        // Bind dynamic creation & uploads in main toolbar
        document.getElementById('spa-btn-create-folder')?.addEventListener('click', function(e) {
            e.stopPropagation();
            const folderName = window.prompt('Enter folder name:');
            if (folderName && folderName.trim()) {
                executeFolderCreate(folderName.trim());
            }
        });

        document.getElementById('spa-upload-input')?.addEventListener('change', function(e) {
            if (this.files && this.files.length) {
                executeFileUpload(this.files);
            }
        });

        // Intercept sidebar uploads to keep the SPA experience pure and support webkitRelativePath
        const sidebarUploadFiles = document.getElementById('upload-files-input');
        if (sidebarUploadFiles) {
            const newUploadFiles = sidebarUploadFiles.cloneNode(true);
            sidebarUploadFiles.parentNode.replaceChild(newUploadFiles, sidebarUploadFiles);
            newUploadFiles.addEventListener('change', function(e) {
                if (this.files && this.files.length) {
                    executeFileUpload(this.files);
                    document.querySelector('.app-new-wrap')?.classList.remove('is-open');
                }
            });
        }

        const sidebarUploadFolder = document.getElementById('upload-folder-input');
        if (sidebarUploadFolder) {
            const newUploadFolder = sidebarUploadFolder.cloneNode(true);
            sidebarUploadFolder.parentNode.replaceChild(newUploadFolder, sidebarUploadFolder);
            newUploadFolder.addEventListener('change', function(e) {
                if (this.files && this.files.length) {
                    executeFolderUpload(this.files);
                    document.querySelector('.app-new-wrap')?.classList.remove('is-open');
                }
            });
        }
    }

    // Selects a file or folder and updates selection classes & Detail drawer
    function selectItem(id, type) {
        id = parseInt(id);
        const isFolder = type === 'folder';

        // Find item in state arrays
        const item = isFolder 
            ? state.folders.find(f => f.id === id)
            : state.files.find(f => f.id === id);

        if (!item) return;

        state.selectedItem = { ...item, is_folder: isFolder };

        // Highlight card visually
        document.querySelectorAll('.drive-card').forEach(card => {
            const cardId = parseInt(card.getAttribute('data-item-id'));
            const cardType = card.getAttribute('data-item-type');
            if (cardId === id && cardType === type) {
                card.classList.add('selected');
            } else {
                card.classList.remove('selected');
            }
        });

        // Open and draw sidebar details drawer
        renderDetailsDrawer();
    }

    // ------------------------------------------
    // 6. FLOATING CONTEXT MENU
    // ------------------------------------------
    function showContextMenu(x, y, event) {
        if (!contextMenu || !state.selectedItem) return;

        const isFolder = state.selectedItem.is_folder;
        const isStarred = state.selectedItem.is_starred;
        const isTrash = state.currentTab === 'trash';
        const typeInfo = getFileTypeInfo(state.selectedItem.name, isFolder, state.selectedItem.mime_type);

        let menuHtml = '';

        if (isTrash) {
            menuHtml = `
                <button type="button" class="context-menu-item" id="ctx-restore">
                    <i class="ri-history-line"></i> <span>Restore</span>
                </button>
                <div class="context-menu-divider"></div>
                <button type="button" class="context-menu-item danger" id="ctx-force-delete">
                    <i class="ri-delete-bin-line"></i> <span>Delete Permanently</span>
                </button>
            `;
        } else {
            menuHtml = `
                <button type="button" class="context-menu-item" id="ctx-open">
                    <i class="${isFolder ? 'ri-folder-open-line' : 'ri-play-line'}"></i> <span>${isFolder ? 'Open' : 'Open in OnlyOffice'}</span>
                </button>
                ${!isFolder ? `
                    <button type="button" class="context-menu-item" id="ctx-preview">
                        <i class="ri-eye-line"></i> <span>Preview</span>
                    </button>
                ` : ''}
                <button type="button" class="context-menu-item" id="ctx-star">
                    <i class="${isStarred ? 'ri-star-fill' : 'ri-star-line'}" style="${isStarred ? 'color:#ffb300' : ''}"></i> 
                    <span>${isStarred ? 'Unstar' : 'Star'}</span>
                </button>
                <button type="button" class="context-menu-item" id="ctx-share">
                    <i class="ri-share-line"></i> <span>Share</span>
                </button>
                <button type="button" class="context-menu-item" id="ctx-rename">
                    <i class="ri-edit-line"></i> <span>Rename</span>
                </button>
                <button type="button" class="context-menu-item" id="ctx-move">
                    <i class="ri-folder-transfer-line"></i> <span>Move to...</span>
                </button>
                <button type="button" class="context-menu-item" id="ctx-tags-edit">
                    <i class="ri-price-tag-3-line"></i> <span>Edit Tags...</span>
                </button>
                ${!isFolder ? `
                    <button type="button" class="context-menu-item" id="ctx-download">
                        <i class="ri-download-line"></i> <span>Download</span>
                    </button>
                ` : ''}
                <div class="context-menu-divider"></div>
                <button type="button" class="context-menu-item danger" id="ctx-delete">
                    <i class="ri-delete-bin-6-line"></i> <span>Move to Trash</span>
                </button>
            `;
        }

        contextMenu.innerHTML = menuHtml;

        // Position context menu using viewport coordinates (fixed positioning)
        // First place it off-screen to measure its real dimensions
        contextMenu.style.left = '-9999px';
        contextMenu.style.top = '-9999px';
        contextMenu.classList.add('active');

        // Now measure and reposition with viewport boundary checks
        const menuRect = contextMenu.getBoundingClientRect();
        const vw = window.innerWidth;
        const vh = window.innerHeight;

        let finalX = x;
        let finalY = y;

        // Flip left if overflows right edge
        if (finalX + menuRect.width > vw - 10) {
            finalX = Math.max(10, x - menuRect.width);
        }

        // Flip upward if overflows bottom edge
        if (finalY + menuRect.height > vh - 10) {
            finalY = Math.max(10, y - menuRect.height);
        }

        contextMenu.style.left = `${finalX}px`;
        contextMenu.style.top = `${finalY}px`;

        // Bind clicks
        document.getElementById('ctx-open')?.addEventListener('click', () => {
            closeContextMenu();
            if (isFolder) {
                navigateSPA(state.currentTab, state.selectedItem.id);
            } else {
                launchEditor(state.selectedItem.id);
            }
        });

        document.getElementById('ctx-preview')?.addEventListener('click', () => {
            closeContextMenu();
            launchPreview(state.selectedItem.id);
        });

        document.getElementById('ctx-star')?.addEventListener('click', () => {
            closeContextMenu();
            executeStarToggle(state.selectedItem.id, isFolder ? 'folder' : 'file');
        });

        document.getElementById('ctx-share')?.addEventListener('click', () => {
            closeContextMenu();
            openShareModal();
        });

        document.getElementById('ctx-rename')?.addEventListener('click', () => {
            closeContextMenu();
            openRenameModal();
        });

        document.getElementById('ctx-move')?.addEventListener('click', () => {
            closeContextMenu();
            openMoveModal();
        });

        document.getElementById('ctx-tags-edit')?.addEventListener('click', () => {
            closeContextMenu();
            openTagsModal();
        });

        document.getElementById('ctx-download')?.addEventListener('click', () => {
            closeContextMenu();
            executeDownload(state.selectedItem.id);
        });

        document.getElementById('ctx-delete')?.addEventListener('click', () => {
            closeContextMenu();
            executeDelete(state.selectedItem.id);
        });

        document.getElementById('ctx-restore')?.addEventListener('click', () => {
            closeContextMenu();
            executeRestore(state.selectedItem.id);
        });

        document.getElementById('ctx-force-delete')?.addEventListener('click', () => {
            closeContextMenu();
            executeForceDelete(state.selectedItem.id);
        });
    }

    function closeContextMenu() {
        if (contextMenu) contextMenu.classList.remove('active');
    }

    // ------------------------------------------
    // 7. SIDEBAR DETAILS DRAWER RENDERING
    // ------------------------------------------
    function renderDetailsDrawer() {
        if (!detailsDrawer || !state.selectedItem) return;

        const item = state.selectedItem;
        const isFolder = item.is_folder;
        const typeInfo = getFileTypeInfo(item.name, isFolder, item.mime_type);
        const formattedSize = isFolder ? '--' : formatBytes(item.size);
        const formattedCreated = formatDate(item.created_at);
        const formattedUpdated = formatDate(item.updated_at);
        const isTrash = state.currentTab === 'trash';

        let drawerHtml = `
            <div class="drawer-header">
                <span class="drawer-title">Item Details</span>
                <button type="button" class="btn-close-drawer" id="btn-close-drawer"><i class="ri-close-line"></i></button>
            </div>
            <div class="drawer-body">
                <div class="drawer-preview">
                    <div class="drawer-preview-icon ${typeInfo.iconColorClass}">
                        <i class="${typeInfo.icon}"></i>
                    </div>
                </div>

                <div class="drawer-file-name text-center" title="${escapeHtml(item.name)}">${escapeHtml(item.name)}</div>
                
                <div class="mg-t-10">
                    <div class="drawer-section-title">Properties</div>
                    <div class="drawer-info-grid">
                        <div class="drawer-info-row">
                            <span class="drawer-info-label">Type</span>
                            <span class="drawer-info-value">${typeInfo.label}</span>
                        </div>
                        <div class="drawer-info-row">
                            <span class="drawer-info-label">Size</span>
                            <span class="drawer-info-value">${formattedSize}</span>
                        </div>
                        <div class="drawer-info-row">
                            <span class="drawer-info-label">Created</span>
                            <span class="drawer-info-value">${formattedCreated}</span>
                        </div>
                        <div class="drawer-info-row">
                            <span class="drawer-info-label">Modified</span>
                            <span class="drawer-info-value">${formattedUpdated}</span>
                        </div>
                        <div class="drawer-info-row" style="align-items: center;">
                            <span class="drawer-info-label">Tags</span>
                            <span class="drawer-info-value" id="drawer-tags-val">${renderDrawerTags(item.tags)}</span>
                        </div>
                        <div class="drawer-info-row">
                            <span class="drawer-info-label">Starred</span>
                            <span class="drawer-info-value">${item.is_starred ? 'Yes' : 'No'}</span>
                        </div>
                        <div class="drawer-info-row">
                            <span class="drawer-info-label">Shared</span>
                            <span class="drawer-info-value">${item.is_shared ? 'Yes' : 'No'}</span>
                        </div>
                    </div>
                </div>

                <div class="drawer-actions">
                    ${isTrash ? `
                        <button class="btn btn-secondary w-100" id="drawer-btn-restore"><i class="ri-history-line"></i> Restore</button>
                        <button class="btn btn-outline w-100" id="drawer-btn-force-delete"><i class="ri-delete-bin-line"></i> Purge</button>
                    ` : `
                        ${!isFolder ? `
                            <button class="btn btn-primary" id="drawer-btn-preview"><i class="ri-eye-line"></i> Preview</button>
                            <button class="btn btn-secondary" id="drawer-btn-download"><i class="ri-download-line"></i> Get File</button>
                            <button class="btn btn-outline" id="drawer-btn-move" style="grid-column: 1 / -1; margin-top: 5px;"><i class="ri-folder-transfer-line"></i> Move Item</button>
                        ` : `
                            <button class="btn btn-primary" id="drawer-btn-open" style="grid-column: 1 / -1;"><i class="ri-folder-open-line"></i> Open Folder</button>
                            <button class="btn btn-outline" id="drawer-btn-move" style="grid-column: 1 / -1; margin-top: 5px;"><i class="ri-folder-transfer-line"></i> Move Folder</button>
                        `}
                    `}
                </div>
            </div>
        `;

        detailsDrawer.innerHTML = drawerHtml;
        detailsDrawer.classList.remove('collapsed');

        // Bind drawer clicks
        document.getElementById('btn-close-drawer')?.addEventListener('click', () => {
            detailsDrawer.classList.add('collapsed');
            state.selectedItem = null;
            document.querySelectorAll('.drive-card').forEach(c => c.classList.remove('selected'));
        });

        document.getElementById('drawer-btn-open')?.addEventListener('click', () => {
            navigateSPA(state.currentTab, item.id);
        });

        document.getElementById('btn-drawer-edit-tags')?.addEventListener('click', () => {
            openTagsModal();
        });

        document.getElementById('drawer-btn-preview')?.addEventListener('click', () => {
            launchPreview(item.id);
        });

        document.getElementById('drawer-btn-download')?.addEventListener('click', () => {
            executeDownload(item.id);
        });

        document.getElementById('drawer-btn-restore')?.addEventListener('click', () => {
            executeRestore(item.id);
        });

        document.getElementById('drawer-btn-force-delete')?.addEventListener('click', () => {
            executeForceDelete(item.id);
        });

        document.getElementById('drawer-btn-move')?.addEventListener('click', () => {
            openMoveModal();
        });
    }

    // ------------------------------------------
    // 8. API EXECUTIONS (FETCH ACTIONS)
    // ------------------------------------------
    function executeStarToggle(id, type) {
        fetch(`/drive/files/${id}/star`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                showToast(data.message);
                loadCurrentView(false); // Reload current view silently
            }
        })
        .catch(err => showToast('Error starring file.', 'error'));
    }

    function executeFolderCreate(name) {
        const formData = new FormData();
        formData.append('name', name);
        if (state.currentFolderId) {
            formData.append('parent_id', state.currentFolderId);
        }

        fetch('/folders', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                showToast(data.message);
                loadCurrentView(false);
            }
        })
        .catch(err => showToast('Error creating folder.', 'error'));
    }

    function executeFileUpload(fileList) {
        const formData = new FormData();
        for (let i = 0; i < fileList.length; i++) {
            formData.append('files[]', fileList[i]);
        }
        if (state.currentFolderId) {
            formData.append('parent_id', state.currentFolderId);
        }

        showToast(`Uploading ${fileList.length} files...`, 'info');

        fetch('/uploads/files', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                showToast(data.message);
                loadCurrentView(false);
            } else {
                showToast(data.message || 'Error uploading files.', 'error');
            }
        })
        .catch(err => showToast('Error uploading files.', 'error'));
    }

    function executeFolderUpload(fileList) {
        const formData = new FormData();
        for (let i = 0; i < fileList.length; i++) {
            formData.append('files[]', fileList[i]);
            formData.append('paths[]', fileList[i].webkitRelativePath || fileList[i].customPath || fileList[i].name);
        }
        if (state.currentFolderId) {
            formData.append('parent_id', state.currentFolderId);
        }

        showToast(`Uploading folder with ${fileList.length} files...`, 'info');

        fetch('/uploads/folder', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                showToast(data.message);
                loadCurrentView(false);
            } else {
                showToast(data.message || 'Error uploading folder.', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Error uploading folder.', 'error');
        });
    }

    function executeRename(name) {
        if (!state.selectedItem) return;
        const id = state.selectedItem.id;

        fetch(`/drive/files/${id}/rename`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ name: name })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                showToast(data.message);
                closeModal(renameModal);
                loadCurrentView(false);
            }
        })
        .catch(err => showToast('Error renaming item.', 'error'));
    }

    function executeShare(email, permission) {
        if (!state.selectedItem) return;
        const id = state.selectedItem.id;

        fetch(`/drive/files/${id}/share`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ email: email, permission: permission })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                showToast(data.message);
                closeModal(shareModal);
                loadCurrentView(false);
            } else {
                showToast(data.message || 'Error sharing file.', 'error');
            }
        })
        .catch(err => showToast('User email not found or error occurred.', 'error'));
    }

    function executeDownload(id) {
        // Direct link execution to download endpoint
        window.location.href = `/drive/files/${id}/download`;
        showToast('Downloading file...', 'info');
    }

    function executeDelete(id) {
        if (!window.confirm('Are you sure you want to move this item to Trash?')) return;
        
        fetch(`/drive/files/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                showToast(data.message);
                loadCurrentView(false);
            }
        })
        .catch(err => showToast('Error moving item to Trash.', 'error'));
    }

    function executeRestore(id) {
        fetch(`/drive/files/${id}/restore`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                showToast(data.message);
                loadCurrentView(false);
            }
        })
        .catch(err => showToast('Error restoring item.', 'error'));
    }

    function executeForceDelete(id) {
        if (!window.confirm('This will permanently delete this item. This action CANNOT be undone! Proceed?')) return;

        fetch(`/drive/files/${id}/force`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                showToast(data.message);
                loadCurrentView(false);
            }
        })
        .catch(err => showToast('Error permanently deleting item.', 'error'));
    }

    // ------------------------------------------
    // 9. MODALS CONTROL
    // ------------------------------------------
    function openRenameModal() {
        if (!renameModal || !state.selectedItem) return;
        
        const nameInput = renameModal.querySelector('#rename-name-input');
        if (nameInput) nameInput.value = state.selectedItem.name;

        openModal(renameModal);
    }

    function openModal(modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden'; // Lock background scroll
    }

    function closeModal(modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
        modal.querySelectorAll('input').forEach(i => i.value = '');
    }

    document.getElementById('btn-close-rename')?.addEventListener('click', () => closeModal(renameModal));

    document.getElementById('rename-form')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const input = this.querySelector('#rename-name-input');
        if (input && input.value.trim()) {
            executeRename(input.value.trim());
        }
    });

    function openShareModal() {
        if (!shareModal || !state.selectedItem) return;
        openModal(shareModal);
        loadShareSettings(state.selectedItem.id);
    }

    function loadShareSettings(itemId) {
        fetch(`/drive/files/${itemId}/shares`, {
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                const titleEl = document.getElementById('share-modal-title');
                if (titleEl) titleEl.innerText = `Share '${state.selectedItem.name}'`;

                const listEl = document.getElementById('collaborators-list');
                if (listEl) {
                    listEl.innerHTML = '';
                    
                    // Add Owner
                    const ownerHtml = `
                        <div class="collaborator-item">
                            <div class="collab-avatar">${data.owner.name.substring(0, 1).toUpperCase()}</div>
                            <div class="collab-info">
                                <span class="collab-name">${data.owner.name}</span>
                                <span class="collab-email">${data.owner.email}</span>
                            </div>
                            <span class="collab-role-label">Owner</span>
                        </div>
                    `;
                    listEl.insertAdjacentHTML('beforeend', ownerHtml);

                    // Add Collaborators
                    data.collaborators.forEach(collab => {
                        const collabHtml = `
                            <div class="collaborator-item">
                                <div class="collab-avatar">${collab.user.name.substring(0, 1).toUpperCase()}</div>
                                <div class="collab-info">
                                    <span class="collab-name">${collab.user.name}</span>
                                    <span class="collab-email">${collab.user.email}</span>
                                </div>
                                <div class="collab-actions">
                                    <select onchange="updateCollaboratorRole('${collab.id}', this.value)" class="collab-role-select">
                                        <option value="view" ${collab.permission === 'view' ? 'selected' : ''}>Can view</option>
                                        <option value="edit" ${collab.permission === 'edit' ? 'selected' : ''}>Can edit</option>
                                    </select>
                                    <button type="button" class="collab-revoke-btn" onclick="revokeCollaboratorAccess('${collab.id}')" title="Revoke access">
                                        <i class="ri-close-line"></i>
                                    </button>
                                </div>
                            </div>
                        `;
                        listEl.insertAdjacentHTML('beforeend', collabHtml);
                    });
                }

                // Public link toggle & fields
                const toggle = document.getElementById('public-link-toggle');
                const details = document.getElementById('public-link-details');
                const label = document.getElementById('public-link-status-label');
                const urlInput = document.getElementById('shareable-link-url');

                if (toggle) toggle.checked = data.public_link.active;
                if (label) label.innerText = data.public_link.active ? 'Shareable link is created' : 'Shareable link is disabled';
                if (details) details.style.display = data.public_link.active ? 'block' : 'none';
                if (urlInput) urlInput.value = data.public_link.share_url || '';

                // Populate settings fields
                const expiryToggle = document.getElementById('expiry-toggle');
                const expiryDateContainer = document.getElementById('expiry-date-container');
                const expiresAtInput = document.getElementById('settings-expires-at');
                
                const pwdToggle = document.getElementById('password-toggle');
                const pwdInputContainer = document.getElementById('password-input-container');
                const pwdInput = document.getElementById('settings-password');

                const dlToggle = document.getElementById('settings-allow-download');
                const impToggle = document.getElementById('settings-allow-import');
                const daToggle = document.getElementById('settings-allow-direct-access');

                if (expiryToggle) {
                    expiryToggle.checked = !!data.public_link.expires_at;
                    expiryDateContainer.style.display = data.public_link.expires_at ? 'block' : 'none';
                }
                if (expiresAtInput) expiresAtInput.value = data.public_link.expires_at || '';

                if (pwdToggle) {
                    pwdToggle.checked = data.public_link.has_password;
                    pwdInputContainer.style.display = data.public_link.has_password ? 'block' : 'none';
                }
                if (pwdInput) pwdInput.value = ''; // Don't show password hash

                if (dlToggle) dlToggle.checked = data.public_link.allow_download;
                if (impToggle) impToggle.checked = data.public_link.allow_import;
                if (daToggle) daToggle.checked = data.public_link.allow_direct_access;
            }
        });
    }

    // Attach global functions to window
    window.updateCollaboratorRole = function(shareId, permission) {
        if (!state.selectedItem) return;
        const fileId = state.selectedItem.id;
        fetch(`/drive/files/${fileId}/shares/${shareId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ permission: permission })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                showToast(data.message);
                loadShareSettings(fileId);
            }
        });
    };

    window.revokeCollaboratorAccess = function(shareId) {
        if (!state.selectedItem) return;
        const fileId = state.selectedItem.id;
        if (!confirm('Are you sure you want to revoke access for this user?')) return;

        fetch(`/drive/files/${fileId}/shares/${shareId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                showToast(data.message);
                loadShareSettings(fileId);
            }
        });
    };

    window.handleInvite = function(event) {
        event.preventDefault();
        if (!state.selectedItem) return;
        const fileId = state.selectedItem.id;
        const email = document.getElementById('invite-email-input').value.trim();
        const permission = document.getElementById('invite-permission-select').value;

        if (!email) return;

        fetch(`/drive/files/${fileId}/share`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ email: email, permission: permission })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                showToast(data.message);
                document.getElementById('invite-email-input').value = '';
                loadShareSettings(fileId);
                loadCurrentView(false);
            } else {
                showToast(data.message || 'Error inviting user.', 'error');
            }
        })
        .catch(err => showToast('User email not found or error occurred.', 'error'));
    };

    window.handlePublicLinkToggle = function(checkbox) {
        if (!state.selectedItem) return;
        const fileId = state.selectedItem.id;
        const active = checkbox.checked;

        fetch(`/drive/files/${fileId}/public-link`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ active: active })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                showToast(active ? 'Public link enabled.' : 'Public link disabled.');
                loadShareSettings(fileId);
                loadCurrentView(false);
            }
        });
    };

    window.switchToSettingsPanel = function() {
        document.getElementById('share-main-panel').classList.remove('active');
        document.getElementById('share-settings-panel').classList.add('active');
    };

    window.switchToMainPanel = function() {
        document.getElementById('share-settings-panel').classList.remove('active');
        document.getElementById('share-main-panel').classList.add('active');
    };

    window.toggleSettingsInput = function(checkbox, targetId) {
        document.getElementById(targetId).style.display = checkbox.checked ? 'block' : 'none';
    };

    window.copyShareableLink = function() {
        const urlInput = document.getElementById('shareable-link-url');
        if (urlInput && urlInput.value) {
            urlInput.select();
            navigator.clipboard.writeText(urlInput.value)
                .then(() => showToast('Link copied to clipboard!'))
                .catch(() => showToast('Failed to copy link.', 'error'));
        }
    };

    window.closeShareModal = function() {
        const modal = document.getElementById('share-modal');
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
            switchToMainPanel();
        }
    };

    window.handleSaveSettings = function(event) {
        event.preventDefault();
        if (!state.selectedItem) return;
        const fileId = state.selectedItem.id;

        const expiryToggle = document.getElementById('expiry-toggle').checked;
        const expiresAt = expiryToggle ? document.getElementById('settings-expires-at').value : null;

        const passwordToggle = document.getElementById('password-toggle').checked;
        const password = passwordToggle ? document.getElementById('settings-password').value : null;

        const allowDownload = document.getElementById('settings-allow-download').checked;
        const allowImport = document.getElementById('settings-allow-import').checked;
        const allowDirectAccess = document.getElementById('settings-allow-direct-access').checked;

        fetch(`/drive/files/${fileId}/public-link-settings`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                expires_at: expiresAt,
                password_enabled: passwordToggle,
                password: password,
                allow_download: allowDownload,
                allow_import: allowImport,
                allow_direct_access: allowDirectAccess
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                showToast(data.message);
                switchToMainPanel();
                loadShareSettings(fileId);
            }
        });
    };

    let moveModalFolders = [];
    let moveCurrentFolderId = null;

    function openMoveModal() {
        if (!state.selectedItem) return;

        // Reset navigation to root (My Drive)
        moveCurrentFolderId = null;

        // Hide inline new folder input wrap if open
        const wrap = document.getElementById('move-new-folder-input-wrap');
        if (wrap) wrap.style.display = 'none';

        // Fetch folders list from server
        fetch('/drive/folders-list', {
            headers: {
                'Accept': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                moveModalFolders = data.folders;
                renderMoveFolderList();
                openModal(moveModal);
            } else {
                showToast('Error loading folders list.', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Error loading folders list.', 'error');
        });
    }

    function renderMoveFolderList() {
        const listContainer = document.getElementById('move-folder-list');
        const backBtn = document.getElementById('move-btn-back');
        const locationSpan = document.getElementById('move-current-location');
        const confirmBtn = document.getElementById('btn-confirm-move');

        if (!listContainer || !state.selectedItem) return;

        // Update Location Name
        if (moveCurrentFolderId === null) {
            locationSpan.textContent = 'My Drive (Root)';
            backBtn.disabled = true;
        } else {
            const currentFolder = moveModalFolders.find(f => f.id === moveCurrentFolderId);
            locationSpan.textContent = currentFolder ? currentFolder.name : 'Folder';
            backBtn.disabled = false;
        }

        // Exclude the selected item (if it is a folder) and all its descendants to avoid cycles
        const forbiddenIds = [];
        if (state.selectedItem.is_folder) {
            forbiddenIds.push(state.selectedItem.id);
            forbiddenIds.push(...getDescendantFolderIds(state.selectedItem.id));
        }

        // Get folders in current navigated level
        const levelFolders = moveModalFolders.filter(f => {
            return f.parent_id === moveCurrentFolderId && !forbiddenIds.includes(f.id);
        });

        // Check if the item's current folder is this navigated folder
        const isCurrentParent = state.selectedItem.parent_id === moveCurrentFolderId;

        // Update Confirm Button Label and state
        if (isCurrentParent) {
            confirmBtn.disabled = true;
            confirmBtn.textContent = 'Already here';
            confirmBtn.style.opacity = '0.6';
            confirmBtn.style.pointerEvents = 'none';
        } else {
            confirmBtn.disabled = false;
            confirmBtn.style.opacity = '1';
            confirmBtn.style.pointerEvents = 'auto';
            confirmBtn.textContent = moveCurrentFolderId === null ? 'Move to My Drive' : `Move Here`;
        }

        if (levelFolders.length === 0) {
            listContainer.innerHTML = `
                <div class="text-center pd-20 clr-grey2 fs-13" style="padding: 20px 10px;">
                    <i class="ri-folder-open-line fs-20 mg-b-5 d-block clr-plt1"></i>
                    No subfolders in this directory
                </div>
            `;
            return;
        }

        let html = '<ul class="move-folders-ul" style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 4px;">';
        levelFolders.forEach(folder => {
            html += `
                <li class="move-folder-li" data-id="${folder.id}" style="display: flex; align-items: center; justify-content: space-between; padding: 8px 12px; border-radius: 6px; cursor: pointer; transition: background 0.2s;">
                    <div style="display: flex; align-items: center; gap: 8px; flex-grow: 1;">
                        <i class="ri-folder-fill clr-plt1 fs-18"></i>
                        <span class="clr-white fs-13" style="font-weight: 500;">${escapeHtml(folder.name)}</span>
                    </div>
                    <button type="button" class="btn btn-outline btn-xs btn-open-subfolder" data-id="${folder.id}" style="padding: 2px 6px; font-size: 11px; border-radius: 4px;">
                        Open <i class="ri-arrow-right-s-line"></i>
                    </button>
                </li>
            `;
        });
        html += '</ul>';
        listContainer.innerHTML = html;

        // Bind clicks on list items to select a folder row (for potential selection, though move here uses navigated level)
        listContainer.querySelectorAll('.move-folder-li').forEach(li => {
            li.addEventListener('click', function(e) {
                // Remove selected styling from others
                listContainer.querySelectorAll('.move-folder-li').forEach(el => el.classList.remove('active'));
                
                // Select this one
                this.classList.add('active');
            });
        });

        // Bind clicks on "Open" buttons to navigate down
        listContainer.querySelectorAll('.btn-open-subfolder').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation(); // Avoid selecting the row
                const id = parseInt(this.getAttribute('data-id'));
                moveCurrentFolderId = id;
                renderMoveFolderList();
            });
        });
    }

    function getDescendantFolderIds(folderId) {
        const ids = [];
        const children = moveModalFolders.filter(f => f.parent_id === folderId);
        for (const child of children) {
            ids.push(child.id);
            ids.push(...getDescendantFolderIds(child.id));
        }
        return ids;
    }

    function executeMove(targetFolderId) {
        if (!state.selectedItem) return;
        const id = state.selectedItem.id;

        fetch(`/drive/files/${id}/move`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ parent_id: targetFolderId })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                showToast(data.message);
                closeModal(moveModal);
                loadCurrentView(false); // Reload current view
            } else {
                showToast(data.message || 'Error moving item.', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Error moving item.', 'error');
        });
    }

    // Bind Move Modal Controls
    document.getElementById('move-btn-back')?.addEventListener('click', function() {
        if (moveCurrentFolderId === null) return;
        const currentFolder = moveModalFolders.find(f => f.id === moveCurrentFolderId);
        moveCurrentFolderId = currentFolder ? currentFolder.parent_id : null;
        renderMoveFolderList();
    });

    document.getElementById('btn-close-move')?.addEventListener('click', () => closeModal(moveModal));

    document.getElementById('btn-confirm-move')?.addEventListener('click', function() {
        executeMove(moveCurrentFolderId);
    });

    // Show/hide inline new folder field
    document.getElementById('move-btn-new-folder')?.addEventListener('click', function() {
        const wrap = document.getElementById('move-new-folder-input-wrap');
        const input = document.getElementById('move-new-folder-name');
        if (wrap && input) {
            wrap.style.display = 'flex';
            input.value = '';
            input.focus();
        }
    });

    document.getElementById('move-btn-new-folder-cancel')?.addEventListener('click', function() {
        const wrap = document.getElementById('move-new-folder-input-wrap');
        if (wrap) {
            wrap.style.display = 'none';
        }
    });

    function submitInlineFolder() {
        const wrap = document.getElementById('move-new-folder-input-wrap');
        const input = document.getElementById('move-new-folder-name');
        if (!input) return;

        const folderName = input.value.trim();
        if (!folderName) {
            showToast('Folder name cannot be empty.', 'warning');
            return;
        }

        fetch('/folders', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                name: folderName,
                parent_id: moveCurrentFolderId
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                showToast(data.message);
                if (wrap) wrap.style.display = 'none';
                
                // Add the newly created folder to our list
                moveModalFolders.push(data.folder);
                
                // Re-render folder list
                renderMoveFolderList();
                
                // Keep background view in sync
                loadCurrentView(false);
            } else {
                showToast(data.message || 'Error creating folder.', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Error creating folder.', 'error');
        });
    }

    document.getElementById('move-btn-new-folder-save')?.addEventListener('click', submitInlineFolder);

    document.getElementById('move-new-folder-name')?.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            submitInlineFolder();
        } else if (e.key === 'Escape') {
            const wrap = document.getElementById('move-new-folder-input-wrap');
            if (wrap) wrap.style.display = 'none';
        }
    });

    // ------------------------------------------
    // 10. ONLYOFFICE PREVIEW SHELL MOCKUP
    // ------------------------------------------
    function launchEditor(fileId) {
        const file = state.files.find(f => f.id === parseInt(fileId));
        if (!file || !onlyOfficeEditor) return;

        const loader = onlyOfficeEditor.querySelector('.onlyoffice-loader');
        const filenameSpan = onlyOfficeEditor.querySelector('.onlyoffice-filename');
        const mockDoc = onlyOfficeEditor.querySelector('.onlyoffice-mock-doc');
        const apptag = onlyOfficeEditor.querySelector('.onlyoffice-apptag');

        if (filenameSpan) filenameSpan.textContent = file.name;

        // Show editor modal
        onlyOfficeEditor.classList.add('active');
        if (loader) {
            loader.style.opacity = '1';
            loader.style.display = 'flex';
        }

        const ext = file.name.split('.').pop().toLowerCase();
        
        // Clean up previous editor if exists
        if (state.docEditor) {
            try {
                state.docEditor.destroyEditor();
            } catch (e) {
                console.error(e);
            }
            state.docEditor = null;
        }

        // Clear mockDoc container
        if (mockDoc) {
            mockDoc.innerHTML = '';
        }

        // Check file type
        const officeExtensions = [
            'docx', 'doc', 'docm', 'dot', 'dotx', 'epub', 'fodt', 'htm', 'html', 'mht', 'mhtml', 'odt', 'ott', 'rtf', 'txt', 'djvu', 'xps', 'oxps',
            'xlsx', 'xls', 'xlsm', 'xlt', 'xltx', 'csv', 'fods', 'ods', 'ots',
            'pptx', 'ppt', 'pptm', 'pot', 'potx', 'pps', 'ppsx', 'fodp', 'odp', 'otp'
        ];

        if (officeExtensions.includes(ext)) {
            if (mockDoc) mockDoc.classList.add('full-workspace');
            let appLabel = 'Document Suite';
            if (['xlsx', 'xls', 'csv', 'ods'].includes(ext)) {
                appLabel = 'Spreadsheet Suite';
            } else if (['pptx', 'ppt', 'odp'].includes(ext)) {
                appLabel = 'Presentation Suite';
            }
            if (apptag) apptag.textContent = appLabel + ' (OnlyOffice)';

            // Fetch configuration and initialize DocEditor
            const initEditor = () => {
                fetch(`/office/config/${file.id}`)
                    .then(res => {
                        if (!res.ok) throw new Error('Failed to load document configuration.');
                        return res.json();
                    })
                    .then(config => {
                        // Create iframe container
                        const iframeContainer = document.createElement('div');
                        iframeContainer.id = 'onlyoffice-iframe-container';
                        iframeContainer.style.width = '100%';
                        iframeContainer.style.height = '100%';
                        if (mockDoc) mockDoc.appendChild(iframeContainer);

                        // Instantiate Editor
                        state.docEditor = new DocsAPI.DocEditor("onlyoffice-iframe-container", config);

                        // Hide loader
                        if (loader) {
                            loader.style.opacity = '0';
                            setTimeout(() => loader.style.display = 'none', 500);
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        showToast(err.message || 'Error loading OnlyOffice editor.', 'error');
                        if (mockDoc) {
                            mockDoc.innerHTML = `
                                <div class="d-flex fd-column ai-center jc-center text-center pd-30" style="height: 100%;">
                                    <i class="ri-error-warning-line fs-40 clr-red mg-b-10"></i>
                                    <div class="fs-16 fw-600 clr-white">Load Error</div>
                                    <div class="fs-13 clr-grey2 mg-t-8">Could not retrieve configuration for this document.</div>
                                </div>
                            `;
                        }
                        if (loader) {
                            loader.style.opacity = '0';
                            setTimeout(() => loader.style.display = 'none', 500);
                        }
                    });
            };

            // Dynamically load ONLYOFFICE DocsAPI JavaScript library if not already loaded
            if (typeof DocsAPI === 'undefined') {
                const script = document.createElement('script');
                script.src = 'https://onlyoffice.khaleefapps.com/web-apps/apps/api/documents/api.js';
                script.onload = initEditor;
                script.onerror = () => {
                    showToast('Failed to load OnlyOffice DocServer API script. Check your server connection.', 'error');
                    if (loader) {
                        loader.style.opacity = '0';
                        setTimeout(() => loader.style.display = 'none', 500);
                    }
                };
                document.head.appendChild(script);
            } else {
                initEditor();
            }

        } else if (ext === 'pdf') {
            if (apptag) apptag.textContent = 'PDF Reader';
            if (mockDoc) {
                mockDoc.classList.add('full-workspace');
                mockDoc.innerHTML = `
                    <iframe src="/drive/files/${file.id}/inline" style="width: 100%; height: 100%; border: none; background: #1e1e1e;"></iframe>
                `;
            }
            if (loader) {
                loader.style.opacity = '0';
                setTimeout(() => loader.style.display = 'none', 500);
            }

        } else if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'].includes(ext)) {
            if (apptag) apptag.textContent = 'Lightbox Viewer';
            if (mockDoc) {
                mockDoc.classList.add('full-workspace');
                mockDoc.innerHTML = `
                    <div class="d-flex ai-center jc-center" style="width: 100%; height: 100%; padding: 20px; background: rgba(0,0,0,0.85); box-sizing: border-box;">
                        <img src="/drive/files/${file.id}/inline" alt="${escapeHtml(file.name)}" style="max-width: 100%; max-height: 100%; object-fit: contain; border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
                    </div>
                `;
            }
            if (loader) {
                loader.style.opacity = '0';
                setTimeout(() => loader.style.display = 'none', 500);
            }

        } else {
            // Default other type file preview / download recommendation
            if (apptag) apptag.textContent = 'Preview Not Available';
            if (mockDoc) {
                mockDoc.classList.remove('full-workspace');
                mockDoc.innerHTML = `
                    <div class="d-flex fd-column ai-center jc-center text-center pd-30" style="height: 100%;">
                        <i class="ri-file-unknow-line fs-50 clr-grey2 mg-b-15"></i>
                        <div class="fs-18 fw-700 clr-white">No Preview Available</div>
                        <div class="fs-13 clr-grey2 mg-t-8" style="max-width: 350px;">This file type (.${ext}) cannot be previewed in the browser.</div>
                        <button class="btn btn-primary mg-t-20" id="btn-fallback-download"><i class="ri-download-line"></i> Download file</button>
                    </div>
                `;
                document.getElementById('btn-fallback-download')?.addEventListener('click', () => {
                    executeDownload(file.id);
                });
            }
            if (loader) {
                loader.style.opacity = '0';
                setTimeout(() => loader.style.display = 'none', 500);
            }
        }
    }

    function launchPreview(fileId) {
        if (window.PreviewModal) {
            window.PreviewModal.open(fileId);
        } else {
            window.location.href = '/preview/' + fileId;
        }
    }

    // Expose functions to window
    window.launchEditor = launchEditor;
    window.launchPreview = launchPreview;
    window.getCurrentPreviewFiles = function() {
        return state.files;
    };

    document.getElementById('btn-close-onlyoffice')?.addEventListener('click', () => {
        if (onlyOfficeEditor) onlyOfficeEditor.classList.remove('active');
        const mockDoc = onlyOfficeEditor?.querySelector('.onlyoffice-mock-doc');
        if (mockDoc) {
            mockDoc.classList.remove('full-workspace');
            mockDoc.innerHTML = '';
        }
        
        // Clean up editor instance if active
        if (state.docEditor) {
            try {
                state.docEditor.destroyEditor();
            } catch (e) {
                console.error(e);
            }
            state.docEditor = null;
        }

        // Refresh the view so size / date is updated
        loadCurrentView(false);
    });

    // ------------------------------------------
    // 11. DRAG AND DROP UPLOADING
    // ------------------------------------------
    function setupDragAndDrop() {
        if (!dragOverlay) return;

        let dragCounter = 0;

        window.addEventListener('dragenter', function(e) {
            e.preventDefault();
            dragCounter++;
            
            // Only allow drops inside dynamic Drive/Index view
            if (state.currentTab === 'index') {
                dragOverlay.classList.add('active');
            }
        });

        window.addEventListener('dragleave', function(e) {
            e.preventDefault();
            dragCounter--;
            if (dragCounter === 0) {
                dragOverlay.classList.remove('active');
            }
        });

        window.addEventListener('dragover', function(e) {
            e.preventDefault();
        });

        window.addEventListener('drop', async function(e) {
            e.preventDefault();
            dragCounter = 0;
            dragOverlay.classList.remove('active');

            if (state.currentTab === 'index' && e.dataTransfer.items) {
                const items = e.dataTransfer.items;
                const entries = [];
                const plainFiles = [];
                let hasFolder = false;

                // Sync phase: extract entries synchronously before they are cleared by the browser
                for (let i = 0; i < items.length; i++) {
                    const item = items[i];
                    if (item.kind === 'file') {
                        const entry = item.webkitGetAsEntry ? item.webkitGetAsEntry() : null;
                        if (entry) {
                            entries.push(entry);
                        } else if (item.getAsFile) {
                            const file = item.getAsFile();
                            if (file) {
                                plainFiles.push(file);
                            }
                        }
                    }
                }

                // Async phase: process the extracted entries recursively
                const files = [];

                async function readEntry(entry, path = '') {
                    if (entry.isFile) {
                        return new Promise(resolve => {
                            entry.file(file => {
                                file.customPath = path + file.name;
                                files.push(file);
                                resolve();
                            }, err => {
                                console.error("Error reading file entry:", err);
                                resolve();
                            });
                        });
                    } else if (entry.isDirectory) {
                        hasFolder = true;
                        const dirReader = entry.createReader();

                        const readBatch = () => {
                            return new Promise(resolve => {
                                dirReader.readEntries(async batch => {
                                    resolve(batch);
                                }, err => {
                                    console.error("Error reading directory entries:", err);
                                    resolve([]);
                                });
                            });
                        };

                        let batch;
                        do {
                            batch = await readBatch();
                            for (let i = 0; i < batch.length; i++) {
                                await readEntry(batch[i], path + entry.name + '/');
                            }
                        } while (batch.length > 0);
                    }
                }

                // Process all extracted entries
                for (let i = 0; i < entries.length; i++) {
                    await readEntry(entries[i]);
                }

                // Add any plain files extracted synchronously that didn't have entries
                for (let i = 0; i < plainFiles.length; i++) {
                    if (!files.some(f => f.name === plainFiles[i].name && f.size === plainFiles[i].size)) {
                        files.push(plainFiles[i]);
                    }
                }

                if (files.length > 0) {
                    if (hasFolder) {
                        executeFolderUpload(files);
                    } else {
                        executeFileUpload(files);
                    }
                } else {
                    showToast('No files or folders detected.', 'warning');
                }
            } else if (state.currentTab === 'index' && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                executeFileUpload(e.dataTransfer.files);
            }
        });
    }

    // ------------------------------------------
    // 12. GLOBAL BEHAVIORS & SHELL HELPERS
    // ------------------------------------------
    function ensureAppShellElements() {
        // Create context-menu if not present
        if (!document.getElementById('context-menu')) {
            const menu = document.createElement('div');
            menu.id = 'context-menu';
            menu.className = 'context-menu';
            document.body.appendChild(menu);
        }

        // Create Rename Modal
        if (!document.getElementById('rename-modal')) {
            const rename = document.createElement('div');
            rename.id = 'rename-modal';
            rename.className = 'premium-modal';
            rename.innerHTML = `
                <div class="premium-modal-backdrop"></div>
                <div class="premium-modal-dialog">
                    <div class="premium-modal-header">
                        <span class="premium-modal-title"><i class="ri-edit-line clr-plt1"></i> Rename Item</span>
                        <button type="button" class="btn-close-drawer" onclick="this.closest('.premium-modal').classList.remove('active')"><i class="ri-close-line"></i></button>
                    </div>
                    <form id="rename-form">
                        <div class="premium-modal-body">
                            <div class="form-group">
                                <label class="form-label" for="rename-name-input">New Name</label>
                                <input type="text" id="rename-name-input" class="form-control" required autocomplete="off">
                            </div>
                        </div>
                        <div class="premium-modal-footer">
                            <button type="button" class="btn btn-outline" id="btn-close-rename">Cancel</button>
                            <button type="submit" class="btn btn-primary">Rename</button>
                        </div>
                    </form>
                </div>
            `;
            document.body.appendChild(rename);
        }

        // Create Share Modal
        if (!document.getElementById('share-modal')) {
            const share = document.createElement('div');
            share.id = 'share-modal';
            share.className = 'premium-modal';
            share.innerHTML = `
                <div class="premium-modal-backdrop" onclick="closeShareModal()"></div>
                <div class="premium-modal-dialog share-dialog">
                    <!-- Main Share Panel -->
                    <div id="share-main-panel" class="share-panel active">
                        <div class="premium-modal-header">
                            <span class="premium-modal-title" id="share-modal-title">Share document</span>
                            <button type="button" class="btn-close-drawer" onclick="closeShareModal()"><i class="ri-close-line"></i></button>
                        </div>
                        <div class="premium-modal-body">
                            <!-- Invite People Section -->
                            <div class="share-section">
                                <h3 class="share-section-title">Invite people</h3>
                                <form id="invite-form" onsubmit="handleInvite(event)">
                                    <div class="invite-input-row">
                                        <input type="email" id="invite-email-input" class="form-control" placeholder="Enter email address" required autocomplete="off">
                                        <select id="invite-permission-select" class="form-control permission-select">
                                            <option value="view">Can view</option>
                                            <option value="edit">Can edit</option>
                                        </select>
                                        <button type="submit" class="btn btn-primary btn-invite">Invite</button>
                                    </div>
                                </form>
                            </div>

                            <!-- People with Access Section -->
                            <div class="share-section">
                                <h3 class="share-section-title">People with access</h3>
                                <div id="collaborators-list" class="collaborators-list">
                                    <!-- Dynamic collaborators -->
                                </div>
                            </div>

                            <!-- Public Access Section -->
                            <div class="share-section public-access-section">
                                <div class="public-access-header">
                                    <h3 class="share-section-title">Public access</h3>
                                    <div class="public-toggle-container">
                                        <label class="switch">
                                            <input type="checkbox" id="public-link-toggle" onchange="handlePublicLinkToggle(this)">
                                            <span class="slider round"></span>
                                        </label>
                                        <span class="toggle-label" id="public-link-status-label">Shareable link is disabled</span>
                                    </div>
                                </div>
                                
                                <div id="public-link-details" class="public-link-details" style="display: none;">
                                    <div class="link-url-row">
                                        <input type="text" id="shareable-link-url" class="form-control link-url-input" readonly>
                                        <button type="button" class="btn btn-primary btn-copy" onclick="copyShareableLink()">
                                            <i class="ri-file-copy-line"></i> Copy
                                        </button>
                                    </div>
                                    <button type="button" class="link-settings-trigger-btn" onclick="switchToSettingsPanel()">
                                        <i class="ri-settings-4-line"></i> Link settings
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Link Settings Panel -->
                    <div id="share-settings-panel" class="share-panel">
                        <div class="premium-modal-header">
                            <span class="premium-modal-title">Shareable Link Settings</span>
                            <button type="button" class="btn-close-drawer" onclick="closeShareModal()"><i class="ri-close-line"></i></button>
                        </div>
                        <form id="link-settings-form" onsubmit="handleSaveSettings(event)">
                            <div class="premium-modal-body">
                                <!-- Link expiration -->
                                <div class="settings-group">
                                    <div class="settings-toggle-row">
                                        <div class="settings-text">
                                            <span class="settings-title">Link expiration</span>
                                            <span class="settings-desc">Link is valid until a specific date</span>
                                        </div>
                                        <label class="switch">
                                            <input type="checkbox" id="expiry-toggle" onchange="toggleSettingsInput(this, 'expiry-date-container')">
                                            <span class="slider round"></span>
                                        </label>
                                    </div>
                                    <div id="expiry-date-container" class="settings-input-container" style="display: none;">
                                        <input type="datetime-local" id="settings-expires-at" class="form-control">
                                    </div>
                                </div>

                                <!-- Password protect -->
                                <div class="settings-group">
                                    <div class="settings-toggle-row">
                                        <div class="settings-text">
                                            <span class="settings-title">Password protect</span>
                                            <span class="settings-desc">Users will need to enter password in order to view this link</span>
                                        </div>
                                        <label class="switch">
                                            <input type="checkbox" id="password-toggle" onchange="toggleSettingsInput(this, 'password-input-container')">
                                            <span class="slider round"></span>
                                        </label>
                                    </div>
                                    <div id="password-input-container" class="settings-input-container" style="display: none;">
                                        <input type="password" id="settings-password" class="form-control" placeholder="Enter new password (min 4 characters)">
                                        <p class="settings-note">Password will not be requested when viewing the link as file owner.</p>
                                    </div>
                                </div>

                                <!-- Allow download -->
                                <div class="settings-group">
                                    <div class="settings-toggle-row">
                                        <div class="settings-text">
                                            <span class="settings-title">Allow download</span>
                                            <span class="settings-desc">Users with link can download this item</span>
                                        </div>
                                        <label class="switch">
                                            <input type="checkbox" id="settings-allow-download" checked>
                                            <span class="slider round"></span>
                                        </label>
                                    </div>
                                </div>

                                <!-- Allow import -->
                                <div class="settings-group">
                                    <div class="settings-toggle-row">
                                        <div class="settings-text">
                                            <span class="settings-title">Allow import</span>
                                            <span class="settings-desc">Users with link can import this item into their own drive</span>
                                        </div>
                                        <label class="switch">
                                            <input type="checkbox" id="settings-allow-import" checked>
                                            <span class="slider round"></span>
                                        </label>
                                    </div>
                                </div>

                                <!-- Allow direct access -->
                                <div class="settings-group">
                                    <div class="settings-toggle-row">
                                        <div class="settings-text">
                                            <span class="settings-title">Allow direct access</span>
                                            <span class="settings-desc">Allow accessing contents of the file directly using this link</span>
                                        </div>
                                        <label class="switch">
                                            <input type="checkbox" id="settings-allow-direct-access" checked>
                                            <span class="slider round"></span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="premium-modal-footer settings-footer">
                                <button type="button" class="btn btn-outline" onclick="switchToMainPanel()">
                                    <i class="ri-arrow-left-line"></i> Back
                                </button>
                                <button type="submit" class="btn btn-primary">Save</button>
                            </div>
                        </form>
                    </div>
                </div>
            `;
            document.body.appendChild(share);
        }

        // Create Move Modal
        if (!document.getElementById('move-modal')) {
            const move = document.createElement('div');
            move.id = 'move-modal';
            move.className = 'premium-modal';
            move.innerHTML = `
                <div class="premium-modal-backdrop"></div>
                <div class="premium-modal-dialog">
                    <div class="premium-modal-header">
                        <span class="premium-modal-title"><i class="ri-folder-transfer-line clr-plt1"></i> Move Item</span>
                        <button type="button" class="btn-close-drawer" onclick="this.closest('.premium-modal').classList.remove('active')"><i class="ri-close-line"></i></button>
                    </div>
                    <div class="premium-modal-body">
                        <div class="move-modal-navigation" style="display: flex; align-items: center; justify-content: space-between; gap: 10px;">
                            <div style="display: flex; align-items: center;">
                                <button type="button" class="btn btn-outline btn-xs" id="move-btn-back" disabled style="padding: 4px 8px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px;">
                                    <i class="ri-arrow-left-line"></i> Back
                                </button>
                                <span id="move-current-location" class="fw-600 clr-white" style="margin-left:12px; font-size:14px;">My Drive</span>
                            </div>
                            <button type="button" class="btn btn-outline btn-xs" id="move-btn-new-folder" style="padding: 4px 8px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px; border-color: var(--accent-orange); color: var(--accent-orange);">
                                <i class="ri-folder-add-line"></i> New Folder
                            </button>
                        </div>
                        
                        <!-- Inline new folder input wrap -->
                        <div id="move-new-folder-input-wrap" style="display: none; margin-top: 12px; align-items: center; gap: 8px; padding: 6px; background: rgba(255,255,255,0.03); border: 1px dashed rgba(255,91,4,0.3); border-radius: 6px;">
                            <i class="ri-folder-add-fill clr-plt1" style="font-size: 18px;"></i>
                            <input type="text" id="move-new-folder-name" placeholder="New folder name..." class="form-control" style="flex-grow: 1; padding: 4px 8px; font-size: 13px; height: auto;" autocomplete="off">
                            <button type="button" class="btn btn-primary btn-xs" id="move-btn-new-folder-save" style="padding: 4px 8px;"><i class="ri-check-line"></i></button>
                            <button type="button" class="btn btn-outline btn-xs" id="move-btn-new-folder-cancel" style="padding: 4px 8px;"><i class="ri-close-line"></i></button>
                        </div>
                        
                        <div class="move-folder-list-container" style="max-height: 250px; min-height: 120px; overflow-y: auto; margin-top: 15px; border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 8px; padding: 6px; background: rgba(0,0,0,0.2);">
                            <div id="move-folder-list">
                                <!-- Folders loaded dynamically -->
                            </div>
                        </div>
                    </div>
                    <div class="premium-modal-footer">
                        <button type="button" class="btn btn-outline" id="btn-close-move">Cancel</button>
                        <button type="button" class="btn btn-primary" id="btn-confirm-move">Move Here</button>
                    </div>
                </div>
            `;
            document.body.appendChild(move);
        }

        // Create OnlyOffice Preview Modal
        if (!document.getElementById('onlyoffice-editor')) {
            const editor = document.createElement('div');
            editor.id = 'onlyoffice-editor';
            editor.className = 'onlyoffice-fullscreen-editor';
            editor.innerHTML = `
                <div class="onlyoffice-header">
                    <div class="onlyoffice-brand">
                        <i class="ri-code-box-line clr-plt1"></i>
                        <div>
                            <span class="onlyoffice-filename">Document.docx</span>
                            <span class="onlyoffice-apptag" style="margin-left: 10px;">Document Suite</span>
                        </div>
                    </div>
                    <div>
                        <button class="btn btn-outline" id="btn-close-onlyoffice"><i class="ri-save-line"></i> Save & Close Document</button>
                    </div>
                </div>
                <div class="onlyoffice-workspace">
                    <div class="onlyoffice-loader">
                        <div class="spinner"></div>
                        <div style="font-size: 13px; color:#bec3d0;">Loading OnlyOffice Suite Workspace...</div>
                    </div>
                    <div class="onlyoffice-mock-doc">
                        <!-- Filled in dynamically -->
                    </div>
                </div>
            `;
            document.body.appendChild(editor);
        }

        // Create Dragover Overlay
        if (!document.getElementById('drag-overlay')) {
            const drag = document.createElement('div');
            drag.id = 'drag-overlay';
            drag.className = 'upload-drag-overlay';
            drag.innerHTML = `
                <div class="upload-drag-box">
                    <i class="ri-upload-cloud-2-line"></i>
                    <div class="upload-drag-title">Release to upload!</div>
                    <div class="upload-drag-subtitle">Your folders and documents will upload immediately into My Drive</div>
                </div>
            `;
            document.body.appendChild(drag);
        }

        // Create Toast Container
        if (!document.getElementById('toast-container')) {
            const toast = document.createElement('div');
            toast.id = 'toast-container';
            toast.className = 'toast-container';
            document.body.appendChild(toast);
        }

        // Create Tags Modal
        if (!document.getElementById('tags-modal')) {
            const tags = document.createElement('div');
            tags.id = 'tags-modal';
            tags.className = 'premium-modal';
            tags.innerHTML = `
                <div class="premium-modal-backdrop"></div>
                <div class="premium-modal-dialog">
                    <div class="premium-modal-header">
                        <span class="premium-modal-title"><i class="ri-price-tag-3-line clr-plt1"></i> Edit Tags</span>
                        <button type="button" class="btn-close-drawer" onclick="this.closest('.premium-modal').classList.remove('active')"><i class="ri-close-line"></i></button>
                    </div>
                    <form id="tags-form">
                        <div class="premium-modal-body">
                            <div class="form-group">
                                <label class="form-label">Active Tags</label>
                                <div id="modal-active-tags-container" class="d-flex fw-wrap gap-8 mg-b-15" style="margin-bottom: 15px;">
                                    <!-- tags render here -->
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="tags-input">Add Tag</label>
                                <div class="d-flex gap-8" style="display: flex; gap: 8px;">
                                    <input type="text" id="tags-input" class="form-control" placeholder="Enter tag name..." autocomplete="off">
                                    <button type="button" id="btn-add-modal-tag" class="btn btn-secondary">Add</button>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Quick Color Tags</label>
                                <div class="d-flex gap-10" style="display: flex; gap: 10px; margin-top: 5px;">
                                    <span class="tag-dot tag-dot-click tag-dot-red select-color-tag" data-tag="Red" title="Red"></span>
                                    <span class="tag-dot tag-dot-click tag-dot-orange select-color-tag" data-tag="Orange" title="Orange"></span>
                                    <span class="tag-dot tag-dot-click tag-dot-yellow select-color-tag" data-tag="Yellow" title="Yellow"></span>
                                    <span class="tag-dot tag-dot-click tag-dot-green select-color-tag" data-tag="Green" title="Green"></span>
                                    <span class="tag-dot tag-dot-click tag-dot-blue select-color-tag" data-tag="Blue" title="Blue"></span>
                                    <span class="tag-dot tag-dot-click tag-dot-purple select-color-tag" data-tag="Purple" title="Purple"></span>
                                    <span class="tag-dot tag-dot-click tag-dot-grey select-color-tag" data-tag="Grey" title="Grey"></span>
                                </div>
                            </div>
                        </div>
                        <div class="premium-modal-footer">
                            <button type="button" class="btn btn-outline" id="btn-close-tags">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Tags</button>
                        </div>
                    </form>
                </div>
            `;
            document.body.appendChild(tags);

            const tagsModal = document.getElementById('tags-modal');
            
            tagsModal.querySelector('#btn-add-modal-tag')?.addEventListener('click', () => {
                const input = tagsModal.querySelector('#tags-input');
                const tagVal = input.value.trim();
                if (tagVal) {
                    if (!modalActiveTags.includes(tagVal)) {
                        modalActiveTags.push(tagVal);
                        renderModalActiveTags();
                    }
                    input.value = '';
                }
            });

            tagsModal.querySelector('#tags-input')?.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    tagsModal.querySelector('#btn-add-modal-tag')?.click();
                }
            });

            tagsModal.querySelectorAll('.select-color-tag').forEach(dot => {
                dot.addEventListener('click', function() {
                    const tagVal = this.getAttribute('data-tag');
                    if (!modalActiveTags.includes(tagVal)) {
                        modalActiveTags.push(tagVal);
                        renderModalActiveTags();
                    }
                });
            });

            tagsModal.querySelector('#btn-close-tags')?.addEventListener('click', () => closeModal(tagsModal));

            tagsModal.querySelector('#tags-form')?.addEventListener('submit', function(e) {
                e.preventDefault();
                executeTagsSave(modalActiveTags);
            });
        }
    }

    function bindGlobalDocumentClicks() {
        document.addEventListener('click', function (e) {
            // Close context menu on outside click
            if (contextMenu && !contextMenu.contains(e.target)) {
                closeContextMenu();
            }

            // Close new menu if click lands outside trigger
            if (appNewMenu && appNewTrigger && !appNewMenu.contains(e.target) && !appNewTrigger.contains(e.target)) {
                document.querySelector('.app-new-wrap')?.classList.remove('is-open');
            }
        });

        // Close context menu when any container is scrolled (using capture phase on window)
        window.addEventListener('scroll', closeContextMenu, true);

        // Intercept close events on modal backdrops
        document.querySelectorAll('.premium-modal').forEach(modal => {
            modal.querySelector('.premium-modal-backdrop')?.addEventListener('click', () => {
                closeModal(modal);
            });
        });
    }

    function updateActiveSidebarClass() {
        const sidebar = document.querySelector('.app-sidebar');
        if (!sidebar) return;

        sidebar.querySelectorAll('a').forEach(link => {
            const href = link.getAttribute('href');
            let isCurrent = false;

            if (state.currentTab === 'shared' && href.includes('/shared')) isCurrent = true;
            else if (state.currentTab === 'recents' && href.includes('/recents')) isCurrent = true;
            else if (state.currentTab === 'starred' && href.includes('/starred')) isCurrent = true;
            else if (state.currentTab === 'trash' && href.includes('/trash')) isCurrent = true;
            else if (state.currentTab === 'tag' && href.includes('/tags/')) {
                const segments = href.split('/');
                const tagInHref = decodeURIComponent(segments[segments.indexOf('tags') + 1] || '');
                if (tagInHref === state.currentTag) isCurrent = true;
            }
            else if (state.currentTab === 'index' && !href.includes('/shared') && !href.includes('/recents') && !href.includes('/starred') && !href.includes('/trash') && !href.includes('/tags/')) isCurrent = true;

            if (isCurrent) {
                link.classList.add('app-nav-active');
            } else {
                link.classList.remove('app-nav-active');
            }
        });
    }

    // Storage progress visual updates
    function updateStorageWidget() {
        // Render or update a storage progress bar inside My Drive sidebar if it exists
        // Let's check if the sidebar has a storage section. If not, we can inject one cleanly!
        let widget = document.getElementById('spa-sidebar-storage-widget');
        const sidebarInner = document.querySelector('.app-sidebar-inner');

        if (!widget && sidebarInner) {
            widget = document.createElement('div');
            widget.id = 'spa-sidebar-storage-widget';
            widget.className = 'storage-box mg-t-20';
            sidebarInner.appendChild(widget);
        }

        if (widget) {
            const usedStr = formatBytes(state.storageUsed);
            const quotaStr = formatBytes(state.storageQuota);
            
            const rawPercent = (state.storageUsed / state.storageQuota) * 100;
            let percentDisplay = '0.0%';
            let progressWidth = 0;

            if (state.storageUsed > 0) {
                if (rawPercent < 0.1) {
                    percentDisplay = '< 0.1%';
                    progressWidth = 0.5; // Visually show a tiny sliver of progress (0.5%) instead of a blank bar
                } else {
                    const pct = Math.min(100, rawPercent);
                    percentDisplay = pct.toFixed(1) + '%';
                    progressWidth = pct;
                }
            }

            widget.innerHTML = `
                <div class="storage-title">
                    <span><i class="ri-cloud-line" style="margin-right: 4px; vertical-align: middle;"></i> Storage space</span>
                    <span>${percentDisplay}</span>
                </div>
                <div class="storage-progress">
                    <div class="storage-progress-bar" style="width: ${progressWidth}%;"></div>
                </div>
                <div class="storage-meta">
                    ${usedStr} used of ${quotaStr}
                </div>
                <div class="storage-collapsed-icon-wrap" title="${percentDisplay} (${usedStr} of ${quotaStr})">
                    <i class="ri-database-2-line"></i>
                    <div class="storage-collapsed-progress-dot" style="background: var(--accent-orange); opacity: ${state.storageUsed > 0 ? 1 : 0.3};"></div>
                </div>
            `;
        }
    }

    // Spawns a floating toast notification
    function showToast(message, type = 'success') {
        const toastContainer = document.getElementById('toast-container');
        if (!toastContainer) return;

        const toast = document.createElement('div');
        toast.className = 'toast';
        
        let icon = 'ri-checkbox-circle-fill toast-icon-success';
        if (type === 'error') icon = 'ri-error-warning-fill toast-icon-error';
        else if (type === 'info') icon = 'ri-information-fill toast-icon-info';

        toast.innerHTML = `
            <i class="${icon}"></i>
            <span>${escapeHtml(message)}</span>
        `;

        toastContainer.appendChild(toast);

        // Slide in
        setTimeout(() => toast.classList.add('show'), 50);

        // Slide out and remove
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // ------------------------------------------
    // 13. DATA FORMATTING UTILITIES
    // ------------------------------------------
    function formatBytes(bytes, decimals = 1) {
        if (!bytes || bytes === 0) return '0 Bytes';
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }

    function formatDate(dateStr) {
        if (!dateStr) return '--';
        try {
            const date = new Date(dateStr);
            if (isNaN(date.getTime())) return dateStr;
            
            // Format to: "May 26, 2026, 12:00 PM"
            return date.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        } catch (e) {
            return dateStr;
        }
    }

    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // Tag management helpers
    let modalActiveTags = [];

    function openTagsModal() {
        const modal = document.getElementById('tags-modal');
        if (!modal || !state.selectedItem) return;

        modalActiveTags = [...(state.selectedItem.tags || [])];
        renderModalActiveTags();

        const input = modal.querySelector('#tags-input');
        if (input) input.value = '';

        openModal(modal);
    }

    function renderModalActiveTags() {
        const container = document.getElementById('modal-active-tags-container');
        if (!container) return;

        if (modalActiveTags.length === 0) {
            container.innerHTML = '<span class="clr-grey2 italic fs-12">No active tags</span>';
            return;
        }

        let html = '';
        modalActiveTags.forEach((tag, idx) => {
            const isStandard = ['Red', 'Orange', 'Yellow', 'Green', 'Blue', 'Purple', 'Grey'].includes(tag);
            const dotClass = isStandard ? `tag-dot tag-dot-mini tag-dot-${tag.toLowerCase()}` : '';
            html += `
                <span class="tag-pill-modal" style="${isStandard ? 'border-color:' + getColorCodeForTag(tag) : ''}">
                    ${isStandard ? `<span class="${dotClass}"></span>` : ''}
                    <span>${escapeHtml(tag)}</span>
                    <button type="button" class="btn-remove-tag" data-index="${idx}">&times;</button>
                </span>
            `;
        });
        container.innerHTML = html;

        container.querySelectorAll('.btn-remove-tag').forEach(btn => {
            btn.addEventListener('click', function() {
                const idx = parseInt(this.getAttribute('data-index'));
                modalActiveTags.splice(idx, 1);
                renderModalActiveTags();
            });
        });
    }

    function renderDrawerTags(tags) {
        let html = '<div class="drawer-tags-wrap d-flex ai-center gap-4 fw-wrap" style="display: flex; align-items: center; gap: 4px; flex-wrap: wrap;">';
        if (tags && Array.isArray(tags) && tags.length > 0) {
            tags.forEach(tag => {
                const isStandard = ['Red', 'Orange', 'Yellow', 'Green', 'Blue', 'Purple', 'Grey'].includes(tag);
                if (isStandard) {
                    html += `<span class="tag-pill-drawer is-color" style="border-color:${getColorCodeForTag(tag)}"><span class="tag-dot tag-dot-mini tag-dot-${tag.toLowerCase()}"></span> ${tag}</span>`;
                } else {
                    html += `<span class="tag-pill-drawer">${escapeHtml(tag)}</span>`;
                }
            });
        } else {
            html += '<span class="clr-grey2 italic fs-12">None</span>';
        }
        html += `<button type="button" id="btn-drawer-edit-tags" class="btn-edit-tags-mini" title="Edit tags"><i class="ri-edit-2-line"></i></button>`;
        html += '</div>';
        return html;
    }

    function executeTagsSave(tags) {
        if (!state.selectedItem) return;
        const id = state.selectedItem.id;

        fetch(`/drive/files/${id}/tags`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ tags: tags })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                showToast(data.message);
                closeModal(document.getElementById('tags-modal'));
                
                if (state.selectedItem && state.selectedItem.id === id) {
                    state.selectedItem.tags = tags;
                }
                const folderIndex = state.folders.findIndex(f => f.id === id);
                if (folderIndex !== -1) state.folders[folderIndex].tags = tags;
                const fileIndex = state.files.findIndex(f => f.id === id);
                if (fileIndex !== -1) state.files[fileIndex].tags = tags;
                
                loadCurrentView(false);
            } else {
                showToast(data.message || 'Error saving tags.', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Error saving tags.', 'error');
        });
    }

    function renderCardTagsInline(tags) {
        if (!tags || !Array.isArray(tags) || tags.length === 0) return '';
        let html = '<div class="card-tags-inline d-flex gap-4 ai-center">';
        tags.forEach(tag => {
            const isStandard = ['Red', 'Orange', 'Yellow', 'Green', 'Blue', 'Purple', 'Grey'].includes(tag);
            if (isStandard) {
                html += `<span class="tag-dot-mini tag-dot-${tag.toLowerCase()}" title="${tag}"></span>`;
            } else {
                html += `<span class="tag-pill-mini" title="${tag}">${escapeHtml(tag)}</span>`;
            }
        });
        html += '</div>';
        return html;
    }

    function getColorCodeForTag(tag) {
        switch (tag) {
            case 'Red': return 'rgba(255, 59, 48, 0.3)';
            case 'Orange': return 'rgba(255, 149, 0, 0.3)';
            case 'Yellow': return 'rgba(255, 204, 0, 0.3)';
            case 'Green': return 'rgba(52, 199, 89, 0.3)';
            case 'Blue': return 'rgba(0, 122, 255, 0.3)';
            case 'Purple': return 'rgba(175, 82, 222, 0.3)';
            case 'Grey': return 'rgba(142, 142, 147, 0.3)';
            default: return 'rgba(255, 255, 255, 0.1)';
        }
    }
});
