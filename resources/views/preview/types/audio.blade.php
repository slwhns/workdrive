<div class="preview-viewer audio-viewer" id="audio-viewer-container">
    <div class="audio-player">
        <audio id="preview-audio" controls style="width: 100%;">
            <source src="{{ $data['url'] }}" type="{{ $data['mime_type'] ?? 'audio/mpeg' }}">
            Your browser does not support the audio element.
        </audio>
    </div>
    <div class="audio-info">
        <h3>{{ $data['filename'] ?? 'Audio File' }}</h3>
        <p class="audio-details">
            <span>Size: {{ $data['size'] ?? 'Unknown' }}</span>
            <span>•</span>
            <span>Type: {{ $data['mime_type'] ?? 'Unknown' }}</span>
        </p>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const audio = document.getElementById('preview-audio');
        
        // Handle audio errors
        audio.onerror = function() {
            document.getElementById('audio-viewer-container').innerHTML = 
                '<div class="preview-error"><p>Failed to load audio</p></div>';
        };
    });
</script>

<style>
    .audio-viewer {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 20px;
        padding: 40px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        height: 100%;
    }

    .audio-player {
        width: 100%;
        max-width: 400px;
    }

    .audio-info {
        text-align: center;
        color: white;
    }

    .audio-info h3 {
        margin: 0 0 10px 0;
        font-size: 18px;
    }

    .audio-details {
        margin: 0;
        font-size: 13px;
        opacity: 0.9;
    }
</style>
