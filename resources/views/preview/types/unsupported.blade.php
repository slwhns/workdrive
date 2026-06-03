<div class="preview-viewer unsupported-viewer" id="unsupported-viewer-container">
    <div class="unsupported-message">
        <div class="unsupported-icon">📄</div>
        <h2>Preview Not Available</h2>
        <p>{{ $data['message'] ?? 'This file type cannot be previewed in the browser' }}</p>
        
        <div class="file-info">
            <p><strong>File:</strong> {{ $data['filename'] ?? 'Unknown' }}</p>
            <p><strong>Type:</strong> {{ $data['mime_type'] ?? $data['extension'] ?? 'Unknown' }}</p>
            @if(isset($data['size']))
                <p><strong>Size:</strong> {{ $data['size'] }}</p>
            @endif
        </div>

        <div class="unsupported-actions">
            <a href="{{ route('drive.files.download', ['file' => $file->id]) }}" class="btn btn-primary">
                Download File
            </a>
        </div>
    </div>
</div>

<style>
    .unsupported-viewer {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100%;
        background: #f5f5f5;
    }

    .unsupported-message {
        text-align: center;
        padding: 40px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        max-width: 400px;
    }

    .unsupported-icon {
        font-size: 60px;
        margin-bottom: 20px;
    }

    .unsupported-message h2 {
        margin: 0 0 10px 0;
        color: #333;
    }

    .unsupported-message p {
        color: #666;
        margin: 10px 0;
    }

    .file-info {
        text-align: left;
        background: #f9f9f9;
        padding: 15px;
        border-radius: 4px;
        margin: 20px 0;
        font-size: 13px;
    }

    .file-info p {
        margin: 5px 0;
        color: #555;
    }

    .unsupported-actions {
        margin-top: 20px;
    }

    .unsupported-actions .btn {
        display: inline-block;
        padding: 10px 20px;
        background: #667eea;
        color: white;
        text-decoration: none;
        border-radius: 4px;
        transition: background 0.2s;
    }

    .unsupported-actions .btn:hover {
        background: #5568d3;
    }
</style>
