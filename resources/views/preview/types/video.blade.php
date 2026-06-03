<div class="preview-viewer video-viewer" id="video-viewer-container">
    <video id="preview-video" controls style="width: 100%; height: 100%; background: #000;">
        <source src="{{ $data['url'] }}" type="{{ $data['mime_type'] ?? 'video/mp4' }}">
        Your browser does not support the video tag.
    </video>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const video = document.getElementById('preview-video');
        
        // Handle video errors
        video.onerror = function() {
            document.getElementById('video-viewer-container').innerHTML = 
                '<div class="preview-error"><p>Failed to load video</p></div>';
        };
    });
</script>
