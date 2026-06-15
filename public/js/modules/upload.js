/**
 * Drag and Drop File Upload Handler
 * Manages file/folder uploads via drag-and-drop
 */

export function setupDragAndDrop(state, { dragOverlay }, { executeFileUpload, executeFolderUpload, showToast }) {
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
                    executeFolderUpload(files, state.currentFolderId);
                } else {
                    executeFileUpload(files, state.currentFolderId);
                }
            } else {
                showToast('No files or folders detected.', 'warning');
            }
        } else if (state.currentTab === 'index' && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            executeFileUpload(Array.from(e.dataTransfer.files), state.currentFolderId);
        }
    });
}
