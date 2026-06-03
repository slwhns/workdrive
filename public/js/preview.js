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
    });

    // Handle PDF preview keyboard shortcuts
    const pdfPageNum = document.getElementById('pdf-page-num');
    if (pdfPageNum) {
        document.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowLeft') {
                document.getElementById('pdf-prev')?.click();
            } else if (e.key === 'ArrowRight') {
                document.getElementById('pdf-next')?.click();
            }
        });
    }

    // Auto-fit for images
    const previewImage = document.getElementById('preview-image');
    if (previewImage) {
        previewImage.addEventListener('load', function() {
            // Image loaded successfully
            console.log('Image preview loaded');
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
