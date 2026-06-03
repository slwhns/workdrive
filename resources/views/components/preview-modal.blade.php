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
        open: function(fileId) {
            const modal = document.getElementById('preview-modal');
            const body = document.getElementById('preview-modal-body');
            const title = document.getElementById('preview-modal-title');
            const centerHeader = document.getElementById('preview-modal-header-center');

            modal.style.display = 'flex';

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
                    } else {
                        body.innerHTML = '<div class="preview-error"><i class="ri-alert-line"></i><p>Failed to load preview</p><p>' + (data.message || 'Unknown error') + '</p></div>';
                    }
                })
                .catch(error => {
                    console.error('Error loading preview:', error);
                    body.innerHTML = '<div class="preview-error"><i class="ri-alert-line"></i><p>Failed to load preview</p><p>' + error.message + '</p></div>';
                });
        },

        close: function() {
            const modal = document.getElementById('preview-modal');
            modal.style.display = 'none';
            document.getElementById('preview-modal-body').innerHTML = 
                '<div class="preview-loading"><div class="spinner"></div><p>Loading preview...</p></div>';
        },

        renderPreview: function(preview, container) {
            let html = '';

            switch (preview.type) {
                case 'image':
                    html = '<img src="' + preview.url + '" style="max-width: 90%; max-height: 85vh; object-fit: contain; box-shadow: 0 10px 30px rgba(0,0,0,0.5); border-radius: 4px;" />';
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

    // Close modal button
    document.addEventListener('DOMContentLoaded', function() {
        const closeBtn = document.getElementById('btn-preview-modal-close');
        const modal = document.getElementById('preview-modal');

        if (closeBtn) {
            closeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                window.PreviewModal.close();
            });
        }

        // Close on overlay click (but not modal content click)
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    window.PreviewModal.close();
                }
            });
        }

        // Keyboard close
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal && modal.style.display !== 'none') {
                window.PreviewModal.close();
            }
        });
    });
</script>
