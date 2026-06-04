<!-- Modal for previewing files directly from the drive view (Google Drive style) -->
<div id="preview-modal" class="preview-modal-overlay" style="display: none;">
    <div class="preview-modal" id="preview-modal-content">
        <!-- Modal header -->
        <div class="preview-modal-header">
            <div class="preview-modal-header-left">
                <h2 id="preview-modal-title" title="">Preview</h2>
                <p class="preview-modal-subtitle" id="preview-modal-subtitle"></p>
            </div>
            <!-- Center Edit button -->
            <div class="preview-modal-header-center" id="preview-modal-header-center" style="display: none; justify-content: center; align-items: center; flex: 1;">
                <button class="btn-modal-edit" id="btn-preview-modal-open-editor">
                    <i class="ri-edit-line"></i> Open with OnlyOffice
                </button>
            </div>
            <div class="preview-modal-header-right">
                <a href="#" class="btn-icon-sm" id="preview-modal-download" title="Download">
                    <i class="ri-download-line"></i>
                </a>
                <a href="#" class="btn-icon-sm" id="preview-modal-fullscreen" title="Open Full Screen" target="_blank">
                    <i class="ri-external-link-line"></i>
                </a>
                <button class="btn-icon-sm btn-close" id="btn-preview-modal-close" title="Close">
                    <i class="ri-close-line"></i>
                </button>
            </div>
        </div>

        <!-- Modal body -->
        <div class="preview-modal-body" id="preview-modal-body">
            <div class="preview-loading">
                <div class="spinner"></div>
                <p>Loading preview...</p>
            </div>
        </div>

        <!-- Navigation Buttons -->
        <button class="preview-nav-btn prev-btn" id="preview-prev-btn" style="display: none;" title="Previous File">
            <i class="ri-arrow-left-s-line"></i>
        </button>
        <button class="preview-nav-btn next-btn" id="preview-next-btn" style="display: none;" title="Next File">
            <i class="ri-arrow-right-s-line"></i>
        </button>

        <!-- Floating Zoom Panel -->
        <div class="preview-zoom-panel" id="preview-zoom-panel" style="display: none;">
            <button class="zoom-btn" id="btn-zoom-out" title="Zoom Out"><i class="ri-zoom-out-line"></i></button>
            <span class="zoom-value" id="zoom-value">100%</span>
            <button class="zoom-btn" id="btn-zoom-in" title="Zoom In"><i class="ri-zoom-in-line"></i></button>
            <div class="zoom-divider"></div>
            <button class="zoom-btn" id="btn-zoom-reset" title="Reset Zoom"><i class="ri-aspect-ratio-line"></i></button>
        </div>

        <!-- Modal footer with file info -->
        <div class="preview-modal-footer" id="preview-modal-footer">
            <span id="preview-file-info"></span>
        </div>
    </div>
</div>

<style>
    .preview-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(11, 11, 11, 0.96);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 2000;
        animation: fadeIn 0.25s ease;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    .preview-modal {
        background: #111111;
        display: flex;
        flex-direction: column;
        width: 100vw;
        height: 100vh;
        max-width: 100vw;
        max-height: 100vh;
        position: relative;
    }

    .preview-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 24px;
        border-bottom: 1px solid #282828;
        flex-shrink: 0;
        background: #1e1e1e;
        height: 60px;
        box-sizing: border-box;
    }

    .preview-modal-header-left {
        flex: 1;
        min-width: 0;
    }

    .preview-modal-header-right {
        display: flex;
        gap: 12px;
        flex-shrink: 0;
        flex: 1;
        justify-content: flex-end;
    }

    .preview-modal-header h2 {
        margin: 0;
        font-size: 16px;
        font-weight: 500;
        color: #ffffff;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .preview-modal-subtitle {
        margin: 2px 0 0 0;
        font-size: 11px;
        color: #a0a0a0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .btn-modal-edit {
        background: #1a73e8;
        border: none;
        color: white;
        border-radius: 4px;
        font-weight: 500;
        font-size: 13px;
        padding: 8px 16px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        transition: background 0.2s;
    }

    .btn-modal-edit:hover {
        background: #155cb8;
    }

    .preview-modal-body {
        flex: 1;
        overflow: auto;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #111111;
        position: relative;
    }

    .preview-modal-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 24px;
        border-top: 1px solid #282828;
        flex-shrink: 0;
        background: #1e1e1e;
        font-size: 11px;
        color: #a0a0a0;
        height: 38px;
        box-sizing: border-box;
    }

    .preview-loading {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 12px;
        color: #a0a0a0;
    }

    .preview-loading .spinner {
        width: 40px;
        height: 40px;
        border: 3px solid #333333;
        border-top-color: #1a73e8;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    .btn-icon-sm {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        border: none;
        background: none;
        cursor: pointer;
        color: #ffffff;
        border-radius: 4px;
        transition: all 0.2s;
        font-size: 20px;
        text-decoration: none;
    }

    .btn-icon-sm:hover {
        background: #2e2e2e;
        color: #ffffff;
    }

    .preview-error {
        display: flex;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        text-align: center;
        color: #ff5252;
        gap: 12px;
        padding: 40px;
    }

    .preview-error i {
        font-size: 48px;
    }

    @media (max-width: 768px) {
        .preview-modal-header {
            padding: 12px 16px;
        }
        .preview-modal-footer {
            padding: 10px 16px;
        }
    }
</style>

<script>
    // Preview modal functions
    window.PreviewModal = {
        currentFileId: null,
        prevFileId: null,
        nextFileId: null,
        zoomLevel: 1.0,
        translateX: 0,
        translateY: 0,
        isDragging: false,
        startX: 0,
        startY: 0,

        open: function(fileId) {
            // Clean up and reset Zoom/Pan
            this.resetZoom();

            this.currentFileId = parseInt(fileId);
            const modal = document.getElementById('preview-modal');
            const body = document.getElementById('preview-modal-body');
            const title = document.getElementById('preview-modal-title');
            const centerHeader = document.getElementById('preview-modal-header-center');

            modal.style.display = 'flex';

            // Show loading state initially
            body.innerHTML = '<div class="preview-loading"><div class="spinner"></div><p>Loading preview...</p></div>';

            // Fetch preview data
            fetch('/api/preview/' + fileId + '?json=true')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const preview = data.preview;
                        const file = data.file;

                        title.textContent = file.name;
                        title.title = file.name;

                        // Set subtitle with size and owner
                        const subtitle = document.getElementById('preview-modal-subtitle');
                        subtitle.textContent = preview.size ? preview.size + ' • ' + preview.created_by : preview.created_by;

                        // Set file info footer
                        const footer = document.getElementById('preview-file-info');
                        footer.textContent = 'Size: ' + (preview.size || 'Unknown') + ' • Modified: ' + (preview.updated_at || 'Unknown');

                        // Show center Edit button if office document
                        if (preview.type === 'office') {
                            if (centerHeader) centerHeader.style.display = 'flex';
                            
                            // Bind the edit click
                            const modalEditBtn = document.getElementById('btn-preview-modal-open-editor');
                            if (modalEditBtn) {
                                const newEditBtn = modalEditBtn.cloneNode(true);
                                modalEditBtn.parentNode.replaceChild(newEditBtn, modalEditBtn);
                                newEditBtn.addEventListener('click', () => {
                                    this.close();
                                    if (window.launchEditor) {
                                        window.launchEditor(fileId);
                                    }
                                });
                            }
                        } else {
                            if (centerHeader) centerHeader.style.display = 'none';
                        }

                        // Attach file_id to preview object for renderer
                        preview.file_id = file.id;

                        this.renderPreview(preview, body);
                        
                        // Update footer links
                        document.getElementById('preview-modal-download').href = 
                            '/drive/files/' + fileId + '/download';
                        document.getElementById('preview-modal-fullscreen').href = 
                            '/preview/' + fileId;

                        // Calculate Sibling Navigation
                        this.setupNavigation(data.prev_id, data.next_id);

                        // Setup Zoom Controls if image
                        this.setupZoomControls(preview.type);

                    } else {
                        body.innerHTML = '<div class="preview-error"><i class="ri-alert-line"></i><p>Failed to load preview</p><p>' + (data.message || 'Unknown error') + '</p></div>';
                        this.setupNavigation(null, null);
                        this.setupZoomControls('none');
                    }
                })
                .catch(error => {
                    console.error('Error loading preview:', error);
                    body.innerHTML = '<div class="preview-error"><i class="ri-alert-line"></i><p>Failed to load preview</p><p>' + error.message + '</p></div>';
                    this.setupNavigation(null, null);
                    this.setupZoomControls('none');
                });
        },

        close: function() {
            const modal = document.getElementById('preview-modal');
            modal.style.display = 'none';
            document.getElementById('preview-modal-body').innerHTML = 
                '<div class="preview-loading"><div class="spinner"></div><p>Loading preview...</p></div>';
            this.resetZoom();
        },

        setupNavigation: function(apiPrevId, apiNextId) {
            let prevId = apiPrevId;
            let nextId = apiNextId;

            // Attempt to get active file list from SPA
            if (window.getCurrentPreviewFiles) {
                const files = window.getCurrentPreviewFiles();
                if (files && files.length > 0) {
                    const currentIndex = files.findIndex(f => f.id === this.currentFileId);
                    if (currentIndex !== -1) {
                        prevId = currentIndex > 0 ? files[currentIndex - 1].id : null;
                        nextId = currentIndex < files.length - 1 ? files[currentIndex + 1].id : null;
                    }
                }
            }

            this.prevFileId = prevId;
            this.nextFileId = nextId;

            const prevBtn = document.getElementById('preview-prev-btn');
            const nextBtn = document.getElementById('preview-next-btn');

            if (prevBtn) prevBtn.style.display = prevId ? 'flex' : 'none';
            if (nextBtn) nextBtn.style.display = nextId ? 'flex' : 'none';
        },

        setupZoomControls: function(previewType) {
            const zoomPanel = document.getElementById('preview-zoom-panel');
            if (!zoomPanel) return;

            if (previewType === 'image') {
                zoomPanel.style.display = 'flex';
                this.updateZoom(false);
            } else {
                zoomPanel.style.display = 'none';
            }
        },

        updateZoom: function(animate = true) {
            const body = document.getElementById('preview-modal-body');
            const img = body.querySelector('img');
            if (!img) return;

            if (this.zoomLevel <= 1.0) {
                this.translateX = 0;
                this.translateY = 0;
                img.style.cursor = 'default';
            } else {
                img.style.cursor = this.isDragging ? 'grabbing' : 'grab';
            }

            img.style.transition = animate ? 'transform 0.2s cubic-bezier(0.2, 0.8, 0.2, 1)' : 'none';
            img.style.transform = `scale(${this.zoomLevel}) translate(${this.translateX / this.zoomLevel}px, ${this.translateY / this.zoomLevel}px)`;

            const valEl = document.getElementById('zoom-value');
            if (valEl) valEl.textContent = Math.round(this.zoomLevel * 100) + '%';
        },

        resetZoom: function() {
            this.zoomLevel = 1.0;
            this.translateX = 0;
            this.translateY = 0;
            this.isDragging = false;
            const valEl = document.getElementById('zoom-value');
            if (valEl) valEl.textContent = '100%';
            const zoomPanel = document.getElementById('preview-zoom-panel');
            if (zoomPanel) zoomPanel.style.display = 'none';
        },

        renderPreview: function(preview, container) {
            let html = '';

            switch (preview.type) {
                case 'image':
                    html = '<img src="' + preview.url + '" class="preview-image" style="max-width: 90%; max-height: 85vh; object-fit: contain; box-shadow: 0 10px 30px rgba(0,0,0,0.5); border-radius: 4px;" />';
                    container.innerHTML = html;
                    break;
                case 'text':
                case 'code':
                    html = '<pre style="padding: 24px; overflow: auto; width: 100%; height: 100%; background: #1e1f26; font-size: 13px; line-height: 1.6; margin: 0; font-family: \'Fira Code\', \'Courier New\', monospace; color: #abb2bf; text-align: left; box-sizing: border-box;">' + 
                           escapeHtml(preview.content || preview.error || 'Unable to load content') + '</pre>';
                    container.innerHTML = html;
                    break;
                case 'pdf':
                    html = '<iframe src="' + preview.url + '#toolbar=0" style="width: 100%; height: 100%; border: none;"></iframe>';
                    container.innerHTML = html;
                    break;
                case 'office':
                    const editorDivId = 'preview-modal-office-editor-' + Math.random().toString(36).substring(2, 9);
                    html = '<div id="' + editorDivId + '" style="width: 100%; height: 100%;"></div>';
                    container.innerHTML = html;
                    
                    fetch('/office/config/' + preview.file_id + '?mode=view')
                        .then(res => res.json())
                        .then(config => {
                            const initEditor = () => {
                                new DocsAPI.DocEditor(editorDivId, config);
                            };
                            if (typeof DocsAPI === 'undefined') {
                                const script = document.createElement('script');
                                script.src = 'https://onlyoffice.khaleefapps.com/web-apps/apps/api/documents/api.js';
                                script.onload = initEditor;
                                document.head.appendChild(script);
                            } else {
                                initEditor();
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            container.innerHTML = '<div class="preview-error"><p>Failed to load Office Viewer</p></div>';
                        });
                    break;
                case 'video':
                    html = '<video controls style="max-width: 90%; max-height: 85vh; margin: auto; display: block; border-radius: 4px; box-shadow: 0 10px 30px rgba(0,0,0,0.5);"><source src="' + preview.url + '" type="' + (preview.mime_type || 'video/mp4') + '">Your browser does not support video</video>';
                    container.innerHTML = html;
                    break;
                case 'audio':
                    html = '<div style="padding: 80px 40px; text-align: center; background: linear-gradient(135deg, #1e1e24 0%, #111116 100%); border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.5);"><audio controls style="width: 100%; max-width: 400px;"><source src="' + preview.url + '" type="' + (preview.mime_type || 'audio/mpeg') + '">Your browser does not support audio</audio><div style="margin-top: 20px; color: white;"><h3 style="margin: 0 0 10px 0;">Audio File</h3><p style="margin: 0; font-size: 13px;">Size: ' + preview.size + '</p></div></div>';
                    container.innerHTML = html;
                    break;
                default:
                    html = '<div style="padding: 40px; text-align: center; color: #999;"><i class="ri-alert-line" style="font-size: 48px; margin-bottom: 12px; display: block; color: #ff5252;"></i><p>Preview not available for ' + (preview.type || 'this') + ' files</p><p style="font-size: 12px; margin-top: 10px;">Size: ' + preview.size + '</p></div>';
                    container.innerHTML = html;
            }
        }
    };

    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    // Close modal button and navigation/zoom events
    document.addEventListener('DOMContentLoaded', function() {
        const closeBtn = document.getElementById('btn-preview-modal-close');
        const modal = document.getElementById('preview-modal');
        const modalBody = document.getElementById('preview-modal-body');

        if (closeBtn) {
            closeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                window.PreviewModal.close();
            });
        }

        // Sibling navigation triggers
        document.getElementById('preview-prev-btn')?.addEventListener('click', function() {
            if (window.PreviewModal.prevFileId) {
                window.PreviewModal.open(window.PreviewModal.prevFileId);
            }
        });

        document.getElementById('preview-next-btn')?.addEventListener('click', function() {
            if (window.PreviewModal.nextFileId) {
                window.PreviewModal.open(window.PreviewModal.nextFileId);
            }
        });

        // Zoom button triggers
        document.getElementById('btn-zoom-in')?.addEventListener('click', function() {
            window.PreviewModal.zoomLevel = Math.min(window.PreviewModal.zoomLevel + 0.25, 4.0);
            window.PreviewModal.updateZoom(true);
        });

        document.getElementById('btn-zoom-out')?.addEventListener('click', function() {
            window.PreviewModal.zoomLevel = Math.max(window.PreviewModal.zoomLevel - 0.25, 0.25);
            window.PreviewModal.updateZoom(true);
        });

        document.getElementById('btn-zoom-reset')?.addEventListener('click', function() {
            window.PreviewModal.zoomLevel = 1.0;
            window.PreviewModal.translateX = 0;
            window.PreviewModal.translateY = 0;
            window.PreviewModal.updateZoom(true);
        });

        // Image drag and pan behaviors
        modalBody?.addEventListener('mousedown', function(e) {
            const img = modalBody.querySelector('img');
            if (!img || window.PreviewModal.zoomLevel <= 1.0 || e.button !== 0) return;

            window.PreviewModal.isDragging = true;
            window.PreviewModal.startX = e.clientX - window.PreviewModal.translateX;
            window.PreviewModal.startY = e.clientY - window.PreviewModal.translateY;
            window.PreviewModal.updateZoom(false);
            e.preventDefault();
        });

        document.addEventListener('mousemove', function(e) {
            if (!window.PreviewModal.isDragging) return;
            window.PreviewModal.translateX = e.clientX - window.PreviewModal.startX;
            window.PreviewModal.translateY = e.clientY - window.PreviewModal.startY;
            window.PreviewModal.updateZoom(false);
        });

        document.addEventListener('mouseup', function() {
            if (window.PreviewModal.isDragging) {
                window.PreviewModal.isDragging = false;
                window.PreviewModal.updateZoom(false);
            }
        });

        // Double-click to toggle zoom (1.0x <-> 2.0x)
        modalBody?.addEventListener('dblclick', function(e) {
            const img = modalBody.querySelector('img');
            if (!img || e.target !== img) return;

            if (window.PreviewModal.zoomLevel > 1.0) {
                window.PreviewModal.zoomLevel = 1.0;
                window.PreviewModal.translateX = 0;
                window.PreviewModal.translateY = 0;
            } else {
                window.PreviewModal.zoomLevel = 2.0;
                // Center the zoom on the double click location
                const rect = img.getBoundingClientRect();
                const offsetX = e.clientX - (rect.left + rect.width / 2);
                const offsetY = e.clientY - (rect.top + rect.height / 2);
                window.PreviewModal.translateX = -offsetX;
                window.PreviewModal.translateY = -offsetY;
            }
            window.PreviewModal.updateZoom(true);
        });

        // Close on overlay click (but not modal content click)
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    window.PreviewModal.close();
                }
            });
        }

        // Keyboard navigation and escape close
        document.addEventListener('keydown', function(e) {
            if (modal && modal.style.display !== 'none') {
                if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;

                if (e.key === 'Escape') {
                    window.PreviewModal.close();
                } else if (e.key === 'ArrowLeft') {
                    const prevBtn = document.getElementById('preview-prev-btn');
                    if (prevBtn && prevBtn.style.display !== 'none') {
                        prevBtn.click();
                    }
                } else if (e.key === 'ArrowRight') {
                    const nextBtn = document.getElementById('preview-next-btn');
                    if (nextBtn && nextBtn.style.display !== 'none') {
                        nextBtn.click();
                    }
                }
            }
        });
    });
</script>
