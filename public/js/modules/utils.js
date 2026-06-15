/**
 * Utility Functions
 * Common helpers for formatting, HTML escaping, date/time handling, etc.
 */

export function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

export function formatBytes(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
}

export function formatDate(dateString) {
    if (!dateString) return 'Unknown';
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    if (diffHours < 24) return `${diffHours}h ago`;
    if (diffDays < 7) return `${diffDays}d ago`;
    if (diffDays < 30) return `${Math.floor(diffDays / 7)}w ago`;

    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: date.getFullYear() !== now.getFullYear() ? 'numeric' : undefined });
}

export function showToast(message, type = 'success', duration = 3000) {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
        <div class="toast-inner">
            <i class="ri-${type === 'success' ? 'check-circle-line' : type === 'error' ? 'error-warning-line' : 'information-line'}"></i>
            <span>${escapeHtml(message)}</span>
        </div>
    `;

    container.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.add('show');
    }, 10);

    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

export function renderCardTagsInline(tags = []) {
    if (!tags || tags.length === 0) return '';
    
    const tagElements = tags.slice(0, 2).map(tag => {
        const isStandard = ['Red', 'Orange', 'Yellow', 'Green', 'Blue', 'Purple', 'Grey'].includes(tag);
        return `
            <span class="card-tag-dot ${isStandard ? 'tag-dot-' + tag.toLowerCase() : 'tag-dot-custom'}" title="${escapeHtml(tag)}">
                ${isStandard ? '' : '<i class="ri-price-tag-3-fill"></i>'}
            </span>
        `;
    }).join('');

    if (tags.length > 2) {
        return `
            <div class="d-flex gap-4 ai-center">
                ${tagElements}
                <span class="fs-11 clr-grey2">+${tags.length - 2}</span>
            </div>
        `;
    }

    return `<div class="d-flex gap-4">${tagElements}</div>`;
}

export function renderDrawerTags(tags = []) {
    if (!tags || tags.length === 0) return '';
    
    return tags.map(tag => {
        const isStandard = ['Red', 'Orange', 'Yellow', 'Green', 'Blue', 'Purple', 'Grey'].includes(tag);
        return `
            <span class="tag-badge" data-tag="${escapeHtml(tag)}">
                <span class="tag-dot ${isStandard ? 'tag-dot-' + tag.toLowerCase() : 'tag-dot-custom'}">
                    ${isStandard ? '' : '<i class="ri-price-tag-3-fill"></i>'}
                </span>
                <span class="tag-label">${escapeHtml(tag)}</span>
                <button type="button" class="btn-remove-tag" data-tag="${escapeHtml(tag)}">
                    <i class="ri-close-line"></i>
                </button>
            </span>
        `;
    }).join('');
}

export function renderModalActiveTags(tagsArray, containerId = 'modal-active-tags-container') {
    const container = document.getElementById(containerId);
    if (!container) return;

    container.innerHTML = tagsArray.map(tag => {
        const isStandard = ['Red', 'Orange', 'Yellow', 'Green', 'Blue', 'Purple', 'Grey'].includes(tag);
        return `
            <div class="tag-badge" data-tag="${escapeHtml(tag)}" style="display:inline-flex; align-items:center; gap:6px; padding:4px 10px; background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.12); border-radius:16px; font-size:12px;">
                <span class="tag-dot ${isStandard ? 'tag-dot-' + tag.toLowerCase() : 'tag-dot-custom'}" style="width:8px; height:8px; border-radius:50%;"></span>
                <span>${escapeHtml(tag)}</span>
                <button type="button" class="btn-remove-tag" data-tag="${escapeHtml(tag)}" style="background:none; border:none; color:inherit; cursor:pointer; padding:0; display:inline-flex; align-items:center;">
                    <i class="ri-close-line"></i>
                </button>
            </div>
        `;
    }).join('');

    // Bind remove buttons
    container.querySelectorAll('.btn-remove-tag').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const tag = btn.getAttribute('data-tag');
            const index = tagsArray.indexOf(tag);
            if (index > -1) {
                tagsArray.splice(index, 1);
                renderModalActiveTags(tagsArray, containerId);
            }
        });
    });
}

export function getFileTypeInfo(filename, isFolder, mimeType = '') {
    if (isFolder) {
        return { icon: 'ri-folder-3-fill', iconColorClass: 'icon-folder', label: 'Folder' };
    }

    const ext = filename.split('.').pop().toLowerCase();
    
    const typeMap = {
        'doc': { icon: 'ri-file-word-2-fill', iconColorClass: 'icon-word', label: 'Word Document' },
        'docx': { icon: 'ri-file-word-2-fill', iconColorClass: 'icon-word', label: 'Word Document' },
        'xls': { icon: 'ri-file-excel-2-fill', iconColorClass: 'icon-excel', label: 'Excel Spreadsheet' },
        'xlsx': { icon: 'ri-file-excel-2-fill', iconColorClass: 'icon-excel', label: 'Excel Spreadsheet' },
        'ppt': { icon: 'ri-slideshow-3-fill', iconColorClass: 'icon-powerpoint', label: 'PowerPoint Presentation' },
        'pptx': { icon: 'ri-slideshow-3-fill', iconColorClass: 'icon-powerpoint', label: 'PowerPoint Presentation' },
        'pdf': { icon: 'ri-file-pdf-fill', iconColorClass: 'icon-pdf', label: 'PDF Document' },
        'jpg': { icon: 'ri-image-2-fill', iconColorClass: 'icon-image', label: 'Image File' },
        'jpeg': { icon: 'ri-image-2-fill', iconColorClass: 'icon-image', label: 'Image File' },
        'png': { icon: 'ri-image-2-fill', iconColorClass: 'icon-image', label: 'Image File' },
        'gif': { icon: 'ri-image-2-fill', iconColorClass: 'icon-image', label: 'Image File' },
        'webp': { icon: 'ri-image-2-fill', iconColorClass: 'icon-image', label: 'Image File' },
        'svg': { icon: 'ri-image-2-fill', iconColorClass: 'icon-image', label: 'Image File' },
        'zip': { icon: 'ri-folder-zip-fill', iconColorClass: 'icon-other', label: 'Compressed Archive' },
        'rar': { icon: 'ri-folder-zip-fill', iconColorClass: 'icon-other', label: 'Compressed Archive' },
        '7z': { icon: 'ri-folder-zip-fill', iconColorClass: 'icon-other', label: 'Compressed Archive' }
    };

    return typeMap[ext] || { icon: 'ri-file-3-fill', iconColorClass: 'icon-other', label: 'File' };
}

export function selectItem(itemId, itemType, state) {
    const item = itemType === 'folder'
        ? state.folders.find(f => f.id === parseInt(itemId))
        : state.files.find(f => f.id === parseInt(itemId));

    if (item) {
        state.selectedItem = { ...item, is_folder: itemType === 'folder' };
        
        // Update card visual
        document.querySelectorAll('.drive-card').forEach(card => {
            if (card.getAttribute('data-item-id') === itemId.toString() && card.getAttribute('data-item-type') === itemType) {
                card.classList.add('selected');
            } else {
                card.classList.remove('selected');
            }
        });
    }
}
