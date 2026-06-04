// ===== File Preview JavaScript =====

document.addEventListener('DOMContentLoaded', function() {
    // Back button
    const btnBack = document.getElementById('btn-preview-back');
    if (btnBack) {
        btnBack.addEventListener('click', function() {
            window.history.back();
        });
    }

    // Download button
    const btnDownload = document.getElementById('btn-preview-download');
    const downloadLink = document.querySelector('a[data-action="download"]');
    if (btnDownload && downloadLink) {
        btnDownload.addEventListener('click', function() {
            downloadLink.click();
        });
    }

    // Share button
    const btnShare = document.getElementById('btn-preview-share');
    const btnShareBtn = document.getElementById('btn-preview-share-btn');
    if (btnShare || btnShareBtn) {
        const handleShare = function() {
            // TODO: Implement share modal
            console.log('Share button clicked');
            alert('Share functionality coming soon');
        };
        if (btnShare) btnShare.addEventListener('click', handleShare);
        if (btnShareBtn) btnShareBtn.addEventListener('click', handleShare);
    }

    // More options button
    const btnMore = document.getElementById('btn-preview-more');
    if (btnMore) {
        btnMore.addEventListener('click', function() {
            // TODO: Implement more options menu
            console.log('More options clicked');
            alert('More options coming soon');
        });
    }

    // Edit button (for office documents)
    const btnEdit = document.getElementById('btn-preview-edit');
    if (btnEdit) {
        btnEdit.addEventListener('click', function() {
            // The OnlyOffice editor is already loaded, so this would enable edit mode
            if (window.editor) {
                console.log('Edit mode enabled');
            }
        });
    }

    // Sidebar toggle
    const sidebarToggle = document.getElementById('btn-preview-sidebar-toggle');
    const sidebar = document.getElementById('preview-sidebar');
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
        });
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;

        // ESC to close preview
        if (e.key === 'Escape') {
            window.history.back();
        }

        // CTRL/CMD+D to download
        if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
            e.preventDefault();
            const downloadBtn = document.querySelector('a[href*="/download"]');
            if (downloadBtn) downloadBtn.click();
        }

        // Standalone Preview Sibling Navigation
        if (e.key === 'ArrowLeft') {
            // Check if pdf page switcher exists and is active. If so, pdf-prev is handled
            const pdfPrev = document.getElementById('pdf-prev');
            if (pdfPrev) {
                pdfPrev.click();
            } else {
                document.getElementById('preview-prev-btn')?.click();
            }
        } else if (e.key === 'ArrowRight') {
            const pdfNext = document.getElementById('pdf-next');
            if (pdfNext) {
                pdfNext.click();
            } else {
                document.getElementById('preview-next-btn')?.click();
            }
        }
    });

    // Zoom and pan logic for standalone image preview
    const previewImage = document.getElementById('preview-image');
    const zoomPanel = document.getElementById('preview-zoom-panel');
    const imageViewer = document.querySelector('.image-viewer');

    if (previewImage && zoomPanel && imageViewer) {
        let zoomLevel = 1.0;
        let isDragging = false;
        let startX = 0, startY = 0;
        let translateX = 0, translateY = 0;

        const updateZoom = (animate = true) => {
            if (zoomLevel <= 1.0) {
                translateX = 0;
                translateY = 0;
                previewImage.style.cursor = 'default';
            } else {
                previewImage.style.cursor = isDragging ? 'grabbing' : 'grab';
            }

            previewImage.style.transition = animate ? 'transform 0.2s cubic-bezier(0.2, 0.8, 0.2, 1)' : 'none';
            previewImage.style.transform = `scale(${zoomLevel}) translate(${translateX / zoomLevel}px, ${translateY / zoomLevel}px)`;

            const zoomValEl = document.getElementById('zoom-value');
            if (zoomValEl) {
                zoomValEl.textContent = Math.round(zoomLevel * 100) + '%';
            }
        };

        document.getElementById('btn-zoom-in')?.addEventListener('click', () => {
            zoomLevel = Math.min(zoomLevel + 0.25, 4.0);
            updateZoom(true);
        });

        document.getElementById('btn-zoom-out')?.addEventListener('click', () => {
            zoomLevel = Math.max(zoomLevel - 0.25, 0.25);
            updateZoom(true);
        });

        document.getElementById('btn-zoom-reset')?.addEventListener('click', () => {
            zoomLevel = 1.0;
            translateX = 0;
            translateY = 0;
            updateZoom(true);
        });

        imageViewer.addEventListener('mousedown', (e) => {
            if (zoomLevel <= 1.0 || e.button !== 0) return;
            isDragging = true;
            startX = e.clientX - translateX;
            startY = e.clientY - translateY;
            updateZoom(false);
            e.preventDefault();
        });

        document.addEventListener('mousemove', (e) => {
            if (!isDragging) return;
            translateX = e.clientX - startX;
            translateY = e.clientY - startY;
            updateZoom(false);
        });

        document.addEventListener('mouseup', () => {
            if (isDragging) {
                isDragging = false;
                updateZoom(false);
            }
        });

        imageViewer.addEventListener('dblclick', (e) => {
            if (e.target !== previewImage) return;
            if (zoomLevel > 1.0) {
                zoomLevel = 1.0;
                translateX = 0;
                translateY = 0;
            } else {
                zoomLevel = 2.0;
                const rect = previewImage.getBoundingClientRect();
                const offsetX = e.clientX - (rect.left + rect.width / 2);
                const offsetY = e.clientY - (rect.top + rect.height / 2);
                translateX = -offsetX;
                translateY = -offsetY;
            }
            updateZoom(true);
        });
    }

    // Handle code syntax highlighting
    const codeBlock = document.getElementById('code-block');
    if (codeBlock && window.hljs) {
        hljs.highlightElement(codeBlock);
    }
});

// Function to open file preview in modal or new page
function openFilePreview(fileId) {
    const previewUrl = '/preview/' + fileId;
    window.location.href = previewUrl;
}

// Function to get preview data via API
async function getFilePreviewData(fileId) {
    try {
        const response = await fetch('/api/preview/' + fileId + '?json=true');
        if (!response.ok) {
            throw new Error('Failed to fetch preview data');
        }
        return await response.json();
    } catch (error) {
        console.error('Error fetching preview data:', error);
        return null;
    }
}

// Export functions
window.openFilePreview = openFilePreview;
window.getFilePreviewData = getFilePreviewData;
