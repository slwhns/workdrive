/* ==========================================
   WORKDRIVE PREMIUM SPA ENGINE
   Dynamic SPA Routing, Rendering, Modals, Drag-and-Drop & OnlyOffice
   ========================================== */

document.addEventListener('DOMContentLoaded', function () {
    // ------------------------------------------
    // 1. STATE MANAGEMENT
    // ------------------------------------------
    const state = {
        currentTab: 'index',      // 'index', 'shared', 'recents', 'starred', 'trash', 'search'
        currentFolderId: null,     // Current directory ID
        searchQuery: '',           // Search query
        selectedItem: null,        // Selected file/folder object
        viewMode: 'grid',          // 'grid' or 'list'
        folders: [],
        files: [],
        breadcrumbs: [],
        currentFolder: null,
        storageUsed: 0,            // Total storage used in bytes
        storageQuota: 5368709120   // 5 GB Quota
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
            } else if (path.includes('/recents')) {
                state.currentTab = 'recents';
            } else if (path.includes('/starred')) {
                state.currentTab = 'starred';
            } else if (path.includes('/trash')) {
                state.currentTab = 'trash';
            } else if (path.includes('/search')) {
                state.currentTab = 'search';
                state.searchQuery = url.searchParams.get('q') || '';
            } else {
                state.currentTab = 'index';
            }

            state.currentFolderId = url.searchParams.get('folder_id') || null;
        } catch (e) {
            state.currentTab = 'index';
            state.currentFolderId = null;
        }
    }

    // ------------------------------------------
    // 3. SPA ROUTING & API INTEGRATION
    // ------------------------------------------
    function navigateSPA(tab, folderId = null, query = '', pushHistory = true) {
        state.currentTab = tab;
        state.currentFolderId = folderId;
        state.searchQuery = query;
        state.selectedItem = null; // Reset selection on navigate

        // Build browser URL
        let url = '/';
        if (tab === 'shared') url = '/shared';
        else if (tab === 'recents') url = '/recents';
        else if (tab === 'starred') url = '/starred';
        else if (tab === 'trash') url = '/trash';
        else if (tab === 'search') url = `/search?q=${encodeURIComponent(query)}`;

        if (folderId) {
            url += (url.includes('?') ? '&' : '?') + `folder_id=${folderId}`;
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
                
                renderSPAView();
                fetchStorageUsage(); // Update storage panel
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
            container.innerHTML = `
                <div class="breadcrumbs">
                    <a href="#" data-spa-tab="index">WorkDrive</a>
                    <span class="divider">/</span>
                    <span class="active">${state.currentTab.toUpperCase()}</span>
                </div>
            `;
            return;
        }

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

        if (totalItems === 0) {
            let emptyTitle = 'No files yet';
            let emptySubtitle = 'Start by creating a folder, uploading files, or creating office documents.';
            if (state.currentTab === 'shared') { emptyTitle = 'Shared folder is empty'; emptySubtitle = 'When other users share documents with you, they will show up here.'; }
            else if (state.currentTab === 'recents') { emptyTitle = 'No recent activity'; emptySubtitle = 'All files you view, edit, or upload will list here in order.'; }
            else if (state.currentTab === 'starred') { emptyTitle = 'Nothing starred yet'; emptySubtitle = 'Star important documents and folders to access them quickly.'; }
            else if (state.currentTab === 'trash') { emptyTitle = 'Trash is empty'; emptySubtitle = 'Items moved to trash will reside here for 30 days.'; }
            else if (state.currentTab === 'search') { emptyTitle = 'No matches found'; emptySubtitle = `We couldn't find any folders or files matching "${state.searchQuery}".`; }

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

        // If list view, draw table column headers
        if (state.viewMode === 'list') {
            html += `
                <div class="drive-list-header" role="rowgroup">
                    <div class="col-name">Name</div>
                    <div class="col-modified">Last modified</div>
                    <div class="col-size">Size</div>
                    <div class="col-actions" aria-hidden="true"></div>
                </div>
            `;
            
            // Draw Folders
            state.folders.forEach(folder => {
                html += generateItemCardHtml(folder, true);
            });

            // Draw Files
            state.files.forEach(file => {
                html += generateItemCardHtml(file, false);
            });
            
            grid.innerHTML = html;
        } else {
            // Grid mode with beautiful grouped headings for folders and files
            let gridHtml = '';
            
            if (state.folders.length > 0) {
                gridHtml += `
                    <div class="grid-section-title w-100 clr-white fw-700 fs-14 mg-t-10 mg-b-10" style="grid-column: 1 / -1;">Folders</div>
                    <div class="folders-grid">
                `;
                state.folders.forEach(folder => {
                    gridHtml += generateItemCardHtml(folder, true);
                });
                gridHtml += `</div>`;
            }

            if (state.files.length > 0) {
                gridHtml += `
                    <div class="grid-section-title w-100 clr-white fw-700 fs-14 mg-t-10 mg-b-10" style="grid-column: 1 / -1;">Files</div>
                    <div class="files-grid">
                `;
                state.files.forEach(file => {
                    gridHtml += generateItemCardHtml(file, false);
                });
                gridHtml += `</div>`;
            }

            grid.innerHTML = gridHtml;
        }
    }

    function generateItemCardHtml(item, isFolder) {
        const isSelected = state.selectedItem && state.selectedItem.id === item.id && state.selectedItem.is_folder === isFolder;
        const selectedClass = isSelected ? 'selected' : '';
        const starClass = item.is_starred ? 'active' : '';
        const starIcon = item.is_starred ? 'ri-star-fill' : 'ri-star-line';
        
        // Dynamic file type mapping
        const typeInfo = getFileTypeInfo(item.name, isFolder, item.mime_type);
        const formattedDate = formatDate(item.updated_at || item.created_at);
        const formattedSize = isFolder ? '--' : formatBytes(item.size);

        if (state.viewMode === 'list') {
            return `
                <div class="drive-card ${selectedClass}" data-item-id="${item.id}" data-item-type="${isFolder ? 'folder' : 'file'}">
                    <div class="drive-card-top">
                        <div class="drive-card-icon ${typeInfo.iconColorClass}">
                            <i class="${typeInfo.icon}"></i>
                        </div>
                        <div class="drive-card-title" title="${escapeHtml(item.name)}">${escapeHtml(item.name)}</div>
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
                // Compact Google Drive-style Folder Card
                return `
                    <div class="drive-card folder-card ${selectedClass}" data-item-id="${item.id}" data-item-type="folder">
                        <div class="folder-card-content">
                            <div class="drive-card-icon ${typeInfo.iconColorClass}">
                                <i class="${typeInfo.icon}"></i>
                            </div>
                            <div class="drive-card-title" title="${escapeHtml(item.name)}">${escapeHtml(item.name)}</div>
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
                // Detailed Google Drive-style File Card with Preview Area
                return `
                    <div class="drive-card file-card ${selectedClass}" data-item-id="${item.id}" data-item-type="file">
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
                            <div class="file-card-meta">
                                <span>${formattedSize}</span>
                                <span>•</span>
                                <span>${formattedDate}</span>
                            </div>
                        </div>
                    </div>
                `;
            }
        }
    }

    function renderFilePreview(item, typeInfo) {
        const ext = item.name.split('.').pop().toLowerCase();
        
        // If it's an image, render the actual image!
        if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'].includes(ext)) {
            return `
                <div class="preview-image-wrapper">
                    <img src="/drive/files/${item.id}/download" alt="${escapeHtml(item.name)}" loading="lazy" class="preview-image">
                </div>
            `;
        }
        
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
        
        // If it's an Excel spreadsheet
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
                if (href.startsWith('/') || href.startsWith('http://localhost') || href.startsWith('http://127.0.0.1')) {
                    e.preventDefault();
                    
                    let tab = 'index';
                    if (href.includes('/shared')) tab = 'shared';
                    else if (href.includes('/recents')) tab = 'recents';
                    else if (href.includes('/starred')) tab = 'starred';
                    else if (href.includes('/trash')) tab = 'trash';

                    navigateSPA(tab, null);
                }
            });
        });
    }

    function bindNewViewEvents() {
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
                    // Open preview modal / OnlyOffice launcher
                    launchPreview(itemId);
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
                    <i class="${isFolder ? 'ri-folder-open-line' : 'ri-eye-line'}"></i> <span>Open</span>
                </button>
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

        // Position context menu
        contextMenu.style.left = `${x}px`;
        contextMenu.style.top = `${y}px`;
        contextMenu.classList.add('active');

        // Bind clicks
        document.getElementById('ctx-open')?.addEventListener('click', () => {
            closeContextMenu();
            if (isFolder) {
                navigateSPA(state.currentTab, state.selectedItem.id);
            } else {
                launchPreview(state.selectedItem.id);
            }
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
                        ` : `
                            <button class="btn btn-primary" id="drawer-btn-open" style="grid-column: 1 / -1;"><i class="ri-folder-open-line"></i> Open Folder</button>
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

    function openShareModal() {
        if (!shareModal) return;
        openModal(shareModal);
    }

    function openModal(modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden'; // Lock background scroll
    }

    function closeModal(modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
        
        // Clean forms
        modal.querySelectorAll('input').forEach(i => i.value = '');
    }

    // Modal action forms
    document.getElementById('btn-close-rename')?.addEventListener('click', () => closeModal(renameModal));
    document.getElementById('btn-close-share')?.addEventListener('click', () => closeModal(shareModal));

    document.getElementById('rename-form')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const input = this.querySelector('#rename-name-input');
        if (input && input.value.trim()) {
            executeRename(input.value.trim());
        }
    });

    document.getElementById('share-form')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const email = this.querySelector('#share-email-input').value.trim();
        const permission = this.querySelector('#share-permission-select').value;
        if (email) {
            executeShare(email, permission);
        }
    });

    // ------------------------------------------
    // 10. ONLYOFFICE PREVIEW SHELL MOCKUP
    // ------------------------------------------
    function launchPreview(fileId) {
        const file = state.files.find(f => f.id === parseInt(fileId));
        if (!file || !onlyOfficeEditor) return;

        const loader = onlyOfficeEditor.querySelector('.onlyoffice-loader');
        const filenameSpan = onlyOfficeEditor.querySelector('.onlyoffice-filename');
        const mockDoc = onlyOfficeEditor.querySelector('.onlyoffice-mock-doc');
        const apptag = onlyOfficeEditor.querySelector('.onlyoffice-apptag');

        if (filenameSpan) filenameSpan.textContent = file.name;

        // Show editor modal
        onlyOfficeEditor.classList.add('active');
        if (loader) loader.style.opacity = '1';
        if (loader) loader.style.display = 'flex';

        const ext = file.name.split('.').pop().toLowerCase();
        let appLabel = 'Document Suite';
        let docTitle = 'WorkDrive Document';
        let iconClass = 'ri-file-word-2-line clr-blue';

        if (ext === 'xlsx' || ext === 'xls') {
            appLabel = 'Spreadsheet Suite';
            docTitle = 'Financial Model & Data Analysis';
            iconClass = 'ri-file-excel-2-line clr-green';
        } else if (ext === 'pptx' || ext === 'ppt') {
            appLabel = 'Presentation Suite';
            docTitle = 'Corporate Keynote Deck';
            iconClass = 'ri-slideshow-3-line clr-orange';
        } else if (ext === 'pdf') {
            appLabel = 'PDF Viewer';
            docTitle = 'Rendered Portable Document';
            iconClass = 'ri-file-pdf-line clr-red';
        } else if (['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'].includes(ext)) {
            appLabel = 'Lightbox Viewer';
            docTitle = 'Image Lightbox Preview';
            iconClass = 'ri-image-2-line clr-purple';
        }

        if (apptag) apptag.textContent = appLabel;

        // Render mockup editor interface inside A4 paper mockup
        if (mockDoc) {
            mockDoc.innerHTML = `
                <div class="onlyoffice-mock-header">
                    <span>${appLabel} | Powered by OnlyOffice</span>
                    <span>Last modified: ${formatDate(file.updated_at)}</span>
                </div>
                <div>
                    <h2 class="onlyoffice-mock-title"><i class="${iconClass}"></i> ${escapeHtml(file.name)}</h2>
                    <hr style="border: 0; border-top: 1px solid rgba(0,0,0,0.1); margin-top:20px; margin-bottom: 20px;">
                    <div class="onlyoffice-mock-body">
                        <p style="font-size: 16px; margin-bottom: 12px;"><strong>Welcome to the WorkDrive Premium Workspace!</strong></p>
                        <p style="margin-bottom: 12px;">This is a fully styled client-side placeholder mockup designed for the <strong>OnlyOffice Integration APIs</strong>. Currently, local file storage operations are structured around Laravel's default abstracted system which is fully compatible with <strong>Contabo Object Storage</strong>.</p>
                        <div style="background: rgba(255, 91, 4, 0.04); border-left: 4px solid var(--accent-orange); padding: 15px; border-radius: 6px; margin-top: 25px; margin-bottom: 25px;">
                            <p style="font-size: 13px; color: #222; margin: 0;"><strong>Developer Notice:</strong> You can easily wire the real OnlyOffice document editing frame in ` + "`c:\\xampp\\htdocs\\workdrive\\public\\js\\drive-premium.js`" + ` inside the ` + "`launchPreview`" + ` function once you obtain your OnlyOffice API license keys!</p>
                        </div>
                        <p>Feel free to click <strong>Save & Close Document</strong> above to return back to your Single Page Application workspace.</p>
                    </div>
                </div>
                <div class="onlyoffice-mock-footer">
                    <span>WorkDrive Company Internal Suite | File Storage Abstraction ready for S3 Driver</span>
                </div>
            `;
        }

        // Simulate OnlyOffice Loading screen (1s delay)
        setTimeout(() => {
            if (loader) {
                loader.style.opacity = '0';
                setTimeout(() => loader.style.display = 'none', 500);
            }
        }, 1200);
    }

    document.getElementById('btn-close-onlyoffice')?.addEventListener('click', () => {
        if (onlyOfficeEditor) onlyOfficeEditor.classList.remove('active');
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
                <div class="premium-modal-backdrop"></div>
                <div class="premium-modal-dialog">
                    <div class="premium-modal-header">
                        <span class="premium-modal-title"><i class="ri-share-line clr-plt1"></i> Share document</span>
                        <button type="button" class="btn-close-drawer" onclick="this.closest('.premium-modal').classList.remove('active')"><i class="ri-close-line"></i></button>
                    </div>
                    <form id="share-form">
                        <div class="premium-modal-body">
                            <div class="form-group">
                                <label class="form-label" for="share-email-input">Colleague's Email</label>
                                <input type="email" id="share-email-input" class="form-control" placeholder="colleague@example.com" required autocomplete="off">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="share-permission-select">Permission Level</label>
                                <select id="share-permission-select" class="form-control" style="background:#181b22;">
                                    <option value="view">Can view</option>
                                    <option value="edit">Can edit</option>
                                </select>
                            </div>
                        </div>
                        <div class="premium-modal-footer">
                            <button type="button" class="btn btn-outline" id="btn-close-share">Cancel</button>
                            <button type="submit" class="btn btn-primary">Share</button>
                        </div>
                    </form>
                </div>
            `;
            document.body.appendChild(share);
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
            else if (state.currentTab === 'index' && !href.includes('/shared') && !href.includes('/recents') && !href.includes('/starred') && !href.includes('/trash')) isCurrent = true;

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
});
