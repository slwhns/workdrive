// ===== PDF Preview Specific JavaScript =====

// Set PDF.js worker
if (typeof pdfjsLib !== 'undefined') {
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
}

// PDF Navigation object
window.PDFViewer = {
    pdfDoc: null,
    pageNum: 1,
    pageRendering: false,
    pageNumPending: null,
    scale: 1.5,

    /**
     * Initialize PDF viewer
     */
    init: function(pdfUrl) {
        if (typeof pdfjsLib === 'undefined') {
            console.error('PDF.js library not loaded');
            return;
        }

        pdfjsLib.getDocument(pdfUrl).promise.then((pdf) => {
            this.pdfDoc = pdf;
            document.getElementById('pdf-page-count').textContent = pdf.numPages;
            this.renderPage(this.pageNum);
        }).catch((error) => {
            console.error('Error loading PDF:', error);
            this.showError('Failed to load PDF file');
        });
    },

    /**
     * Render a specific page
     */
    renderPage: function(num) {
        if (this.pageRendering) {
            this.pageNumPending = num;
            return;
        }
        this.pageRendering = true;

        this.pdfDoc.getPage(num).then((page) => {
            const viewport = page.getViewport({ scale: this.scale });
            const canvas = document.getElementById('pdf-canvas');
            canvas.height = viewport.height;
            canvas.width = viewport.width;

            page.render({
                canvasContext: canvas.getContext('2d'),
                viewport: viewport
            }).promise.then(() => {
                this.pageRendering = false;
                if (this.pageNumPending !== null) {
                    this.renderPage(this.pageNumPending);
                    this.pageNumPending = null;
                }
            });
        });

        document.getElementById('pdf-page-num').value = num;
    },

    /**
     * Queue page render
     */
    queuePage: function(num) {
        if (num >= 1 && num <= this.pdfDoc.numPages) {
            this.pageNum = num;
            this.renderPage(num);
        }
    },

    /**
     * Previous page
     */
    prevPage: function() {
        this.queuePage(this.pageNum - 1);
    },

    /**
     * Next page
     */
    nextPage: function() {
        this.queuePage(this.pageNum + 1);
    },

    /**
     * Zoom in
     */
    zoomIn: function() {
        this.scale += 0.2;
        document.getElementById('pdf-scale').textContent = Math.round(this.scale * 100) + '%';
        this.renderPage(this.pageNum);
    },

    /**
     * Zoom out
     */
    zoomOut: function() {
        if (this.scale > 0.5) {
            this.scale -= 0.2;
            document.getElementById('pdf-scale').textContent = Math.round(this.scale * 100) + '%';
            this.renderPage(this.pageNum);
        }
    },

    /**
     * Show error message
     */
    showError: function(message) {
        const container = document.getElementById('pdf-viewer-container');
        if (container) {
            container.innerHTML = '<div class="preview-error"><p>' + message + '</p></div>';
        }
    }
};

// Auto-initialize PDF viewer if elements exist
document.addEventListener('DOMContentLoaded', function() {
    // PDF viewer is initialized in the blade template itself
    // This file just provides helper functions
});
