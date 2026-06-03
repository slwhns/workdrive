<div class="preview-viewer office-viewer" id="office-viewer-container" style="width: 100%; height: 100%;">
    <div id="onlyoffice-editor" style="height: 100%; width: 100%;"></div>
</div>

<!-- Floating Exit Button for full-screen edit mode -->
<button id="btn-exit-edit-mode" class="btn btn-outline floating-exit-btn" style="display: none; position: fixed; top: 12px; right: 20px; z-index: 9999; background: rgba(30, 30, 30, 0.9); border: 1px solid rgba(255, 255, 255, 0.15); color: white; border-radius: 4px; font-weight: 500; font-size: 12px; padding: 6px 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.5);">
    <i class="ri-close-line" style="margin-right: 4px; font-size: 14px; vertical-align: middle;"></i> Close Editor
</button>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const onlyofficeUrl = '{{ config("onlyoffice.url") }}';
        let docEditor = null;
        let currentMode = 'view';

        function loadEditor(mode) {
            currentMode = mode;
            const configUrl = '{{ route("onlyoffice.config", ["file" => $file->id]) }}?mode=' + mode;

            // Fetch OnlyOffice configuration
            fetch(configUrl)
                .then(response => response.json())
                .then(config => {
                    // Destroy existing editor if any
                    if (docEditor) {
                        try {
                            docEditor.destroyEditor();
                        } catch (e) {
                            console.error(e);
                        }
                        docEditor = null;
                    }

                    // Clear container
                    document.getElementById('onlyoffice-editor').innerHTML = '';

                    const initDocsAPI = () => {
                        docEditor = new DocsAPI.DocEditor("onlyoffice-editor", config);
                        
                        // Handle layout adjustments based on mode
                        const container = document.querySelector('.preview-container');
                        const exitBtn = document.getElementById('btn-exit-edit-mode');
                        
                        if (mode === 'edit') {
                            container.classList.add('editing-active');
                            if (exitBtn) exitBtn.style.display = 'block';
                        } else {
                            container.classList.remove('editing-active');
                            if (exitBtn) exitBtn.style.display = 'none';
                        }
                    };

                    if (typeof DocsAPI === 'undefined') {
                        const script = document.createElement('script');
                        script.src = onlyofficeUrl + '/web-apps/apps/api/documents/api.js';
                        script.onload = initDocsAPI;
                        document.head.appendChild(script);
                    } else {
                        initDocsAPI();
                    }
                })
                .catch(error => {
                    console.error('Failed to load OnlyOffice config:', error);
                    document.getElementById('onlyoffice-editor').innerHTML = 
                        '<div class="preview-error"><p>Failed to load document editor</p></div>';
                });
        }

        // Initialize in view mode
        loadEditor('view');

        // Bind Edit buttons
        const editBtn = document.getElementById('btn-preview-open-editor');
        const sidebarEditBtn = document.getElementById('btn-sidebar-edit');
        const exitEditBtn = document.getElementById('btn-exit-edit-mode');

        if (editBtn) {
            editBtn.addEventListener('click', () => loadEditor('edit'));
        }
        if (sidebarEditBtn) {
            sidebarEditBtn.addEventListener('click', () => loadEditor('edit'));
        }
        if (exitEditBtn) {
            exitEditBtn.addEventListener('click', () => loadEditor('view'));
        }
    });
</script>
