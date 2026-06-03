<div class="preview-viewer pdf-viewer" id="pdf-viewer-container">
    <div class="pdf-toolbar">
        <button class="btn-icon" id="pdf-prev" title="Previous page">
            <span class="icon-chevron-left"></span>
        </button>
        <span class="pdf-page-info">
            <input type="number" id="pdf-page-num" value="1" min="1" class="pdf-page-input">
            <span>of <span id="pdf-page-count">0</span></span>
        </span>
        <button class="btn-icon" id="pdf-next" title="Next page">
            <span class="icon-chevron-right"></span>
        </button>
        <button class="btn-icon" id="pdf-zoom-out" title="Zoom out">
            <span class="icon-zoom-out"></span>
        </button>
        <span id="pdf-scale" class="pdf-scale">100%</span>
        <button class="btn-icon" id="pdf-zoom-in" title="Zoom in">
            <span class="icon-zoom-in"></span>
        </button>
    </div>
    <div class="pdf-canvas-container" id="pdf-canvas-container">
        <canvas id="pdf-canvas"></canvas>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const pdfUrl = '{{ $data['url'] }}';
        let pdfDoc = null;
        let pageNum = 1;
        let pageRendering = false;
        let pageNumPending = null;
        let scale = 1.5;

        // Initialize PDF.js
        pdfjsLib.getDocument(pdfUrl).promise.then(function(pdf) {
            pdfDoc = pdf;
            document.getElementById('pdf-page-count').textContent = pdf.numPages;
            renderPage(pageNum);
        }).catch(function(error) {
            console.error('PDF loading error:', error);
            document.getElementById('pdf-viewer-container').innerHTML = 
                '<div class="preview-error"><p>Failed to load PDF</p></div>';
        });

        function renderPage(num) {
            if (pageRendering) {
                pageNumPending = num;
                return;
            }
            pageRendering = true;

            pdfDoc.getPage(num).then(function(page) {
                const viewport = page.getViewport({scale: scale});
                const canvas = document.getElementById('pdf-canvas');
                canvas.height = viewport.height;
                canvas.width = viewport.width;

                page.render({
                    canvasContext: canvas.getContext('2d'),
                    viewport: viewport
                }).promise.then(function() {
                    pageRendering = false;
                    if (pageNumPending !== null) {
                        renderPage(pageNumPending);
                        pageNumPending = null;
                    }
                });
            });

            document.getElementById('pdf-page-num').value = num;
        }

        function queuePage(num) {
            if (num >= 1 && num <= pdfDoc.numPages) {
                pageNum = num;
                renderPage(num);
            }
        }

        document.getElementById('pdf-prev').addEventListener('click', function() {
            queuePage(pageNum - 1);
        });

        document.getElementById('pdf-next').addEventListener('click', function() {
            queuePage(pageNum + 1);
        });

        document.getElementById('pdf-page-num').addEventListener('change', function() {
            queuePage(parseInt(this.value) || 1);
        });

        document.getElementById('pdf-zoom-in').addEventListener('click', function() {
            scale += 0.2;
            document.getElementById('pdf-scale').textContent = Math.round(scale * 100) + '%';
            renderPage(pageNum);
        });

        document.getElementById('pdf-zoom-out').addEventListener('click', function() {
            if (scale > 0.5) {
                scale -= 0.2;
                document.getElementById('pdf-scale').textContent = Math.round(scale * 100) + '%';
                renderPage(pageNum);
            }
        });
    });
</script>
