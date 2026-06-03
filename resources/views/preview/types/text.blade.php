<div class="preview-viewer text-viewer" id="text-viewer-container">
    <div class="text-content">
        <pre><code>{{ $data['content'] ?? 'No content available' }}</code></pre>
        @if($data['truncated'] ?? false)
            <div class="text-truncated-notice">
                <p>Content truncated. Download the file to view the full content.</p>
            </div>
        @endif
    </div>
</div>

<style>
    .text-viewer .text-content {
        padding: 20px;
        background: #f5f5f5;
        overflow: auto;
        height: 100%;
    }

    .text-viewer pre {
        margin: 0;
        font-family: 'Monaco', 'Courier New', monospace;
        font-size: 13px;
        line-height: 1.5;
        color: #333;
        white-space: pre-wrap;
        word-wrap: break-word;
    }

    .text-truncated-notice {
        margin-top: 20px;
        padding: 10px;
        background: #fff3cd;
        border: 1px solid #ffc107;
        border-radius: 4px;
        color: #856404;
    }
</style>
