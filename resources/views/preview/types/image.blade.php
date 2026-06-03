<div class="preview-viewer image-viewer" id="image-viewer-container">
    <img src="{{ $data['url'] }}" alt="{{ $data['filename'] }}" class="preview-image" id="preview-image">
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const image = document.getElementById('preview-image');
        
        // Handle image errors
        image.onerror = function() {
            document.getElementById('image-viewer-container').innerHTML = 
                '<div class="preview-error"><p>Failed to load image</p></div>';
        };
    });
</script>
