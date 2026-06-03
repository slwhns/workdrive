<div class="preview-viewer code-viewer" id="code-viewer-container">
    <div class="code-toolbar">
        <span class="code-language">{{ $data['language'] ?? 'text' }}</span>
    </div>
    <div class="code-content">
        <pre><code class="language-{{ $data['language'] ?? 'plaintext' }}" id="code-block">{{ $data['content'] ?? 'No content available' }}</code></pre>
        @if($data['truncated'] ?? false)
            <div class="code-truncated-notice">
                <p>Content truncated. Download the file to view the full content.</p>
            </div>
        @endif
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Apply syntax highlighting
        const codeBlock = document.getElementById('code-block');
        if (codeBlock && window.hljs) {
            hljs.highlightElement(codeBlock);
        }
    });
</script>

<style>
    .code-viewer {
        display: flex;
        flex-direction: column;
        height: 100%;
        background: #282c34;
    }

    .code-viewer .code-toolbar {
        padding: 10px 20px;
        background: #1e1f26;
        border-bottom: 1px solid #444;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .code-viewer .code-language {
        color: #888;
        font-size: 12px;
        text-transform: uppercase;
    }

    .code-viewer .code-content {
        flex: 1;
        overflow: auto;
        padding: 20px;
    }

    .code-viewer pre {
        margin: 0;
        padding: 0;
        font-family: 'Monaco', 'Courier New', monospace;
        font-size: 13px;
        line-height: 1.6;
        color: #abb2bf;
    }

    .code-viewer pre code {
        background: none;
        padding: 0;
    }

    .code-truncated-notice {
        margin-top: 20px;
        padding: 10px;
        background: #ffc107;
        border-radius: 4px;
        color: #000;
    }
</style>
