<?php

namespace App\Services\Preview;

use App\Models\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class FilePreviewService
{
    /**
     * Get the preview data for a file
     */
    public function getPreviewData(File $file): array
    {
        if ($file->is_folder) {
            return [
                'type' => 'folder',
                'error' => 'Cannot preview a folder'
            ];
        }

        $ext = strtolower(pathinfo($file->name, PATHINFO_EXTENSION));
        $mimeType = $file->mime_type ?? $this->getMimeType($ext);

        // Determine preview type based on file extension or mime type
        $previewType = $this->getPreviewType($ext, $mimeType);

        return match ($previewType) {
            'office' => $this->getOfficePreview($file, $ext),
            'image' => $this->getImagePreview($file),
            'pdf' => $this->getPdfPreview($file),
            'text' => $this->getTextPreview($file),
            'code' => $this->getCodePreview($file),
            'video' => $this->getVideoPreview($file),
            'audio' => $this->getAudioPreview($file),
            default => [
                'type' => 'unsupported',
                'filename' => $file->name,
                'size' => $this->formatFileSize($file->size),
                'mime_type' => $mimeType,
                'message' => 'Preview not available for this file type'
            ]
        };
    }

    /**
     * Determine the preview type based on file extension and mime type
     */
    private function getPreviewType(string $ext, string $mimeType): string
    {
        // Office documents
        $officeExts = ['doc', 'docx', 'odt', 'xls', 'xlsx', 'ods', 'ppt', 'pptx', 'odp'];
        if (in_array($ext, $officeExts)) {
            return 'office';
        }

        // Images
        $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'];
        if (in_array($ext, $imageExts)) {
            return 'image';
        }

        // PDF
        if ($ext === 'pdf' || strpos($mimeType, 'pdf') !== false) {
            return 'pdf';
        }

        // Video
        $videoExts = ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv'];
        if (in_array($ext, $videoExts)) {
            return 'video';
        }

        // Audio
        $audioExts = ['mp3', 'wav', 'ogg', 'flac', 'm4a', 'aac'];
        if (in_array($ext, $audioExts)) {
            return 'audio';
        }

        // Code files
        $codeExts = ['js', 'php', 'py', 'java', 'cpp', 'c', 'html', 'css', 'json', 'xml', 'yml', 'yaml'];
        if (in_array($ext, $codeExts)) {
            return 'code';
        }

        // Text files
        $textExts = ['txt', 'md', 'log', 'csv', 'sql'];
        if (in_array($ext, $textExts)) {
            return 'text';
        }

        return 'unsupported';
    }

    /**
     * Get Office document preview (using OnlyOffice viewer)
     */
    private function getOfficePreview(File $file, string $ext): array
    {
        $downloadUrl = URL::temporarySignedRoute(
            'onlyoffice.download',
            now()->addHours(12),
            ['file' => $file->id]
        );

        $docType = $this->getDocType($ext);

        return [
            'type' => 'office',
            'filename' => $file->name,
            'extension' => $ext,
            'doc_type' => $docType,
            'download_url' => $downloadUrl,
            'size' => $this->formatFileSize($file->size),
            'created_at' => $file->created_at->format('M d, Y'),
            'updated_at' => $file->updated_at->format('M d, Y H:i'),
            'created_by' => $file->creator->name ?? 'Unknown',
            'config_url' => route('onlyoffice.config', ['file' => $file->id])
        ];
    }

    /**
     * Get image preview
     */
    private function getImagePreview(File $file): array
    {
        $inlineUrl = route('drive.files.inline', ['file' => $file->id]);

        return [
            'type' => 'image',
            'filename' => $file->name,
            'url' => $inlineUrl,
            'size' => $this->formatFileSize($file->size),
            'created_at' => $file->created_at->format('M d, Y'),
            'updated_at' => $file->updated_at->format('M d, Y H:i'),
            'created_by' => $file->creator->name ?? 'Unknown',
            'mime_type' => $file->mime_type
        ];
    }

    /**
     * Get PDF preview
     */
    private function getPdfPreview(File $file): array
    {
        $inlineUrl = route('drive.files.inline', ['file' => $file->id]);

        return [
            'type' => 'pdf',
            'filename' => $file->name,
            'url' => $inlineUrl,
            'size' => $this->formatFileSize($file->size),
            'created_at' => $file->created_at->format('M d, Y'),
            'updated_at' => $file->updated_at->format('M d, Y H:i'),
            'created_by' => $file->creator->name ?? 'Unknown'
        ];
    }

    /**
     * Get text file preview
     */
    private function getTextPreview(File $file): array
    {
        try {
            $content = '';
            if (Storage::disk('public')->exists($file->storage_path)) {
                $content = Storage::disk('public')->get($file->storage_path);
                // Limit to 50KB for preview
                if (strlen($content) > 51200) {
                    $content = substr($content, 0, 51200) . "\n\n[Content truncated...]";
                }
            }

            return [
                'type' => 'text',
                'filename' => $file->name,
                'content' => $content,
                'size' => $this->formatFileSize($file->size),
                'created_at' => $file->created_at->format('M d, Y'),
                'updated_at' => $file->updated_at->format('M d, Y H:i'),
                'created_by' => $file->creator->name ?? 'Unknown',
                'truncated' => strlen($content) > 51200
            ];
        } catch (\Exception $e) {
            return [
                'type' => 'text',
                'filename' => $file->name,
                'error' => 'Unable to read file content',
                'size' => $this->formatFileSize($file->size)
            ];
        }
    }

    /**
     * Get code file preview with syntax highlighting
     */
    private function getCodePreview(File $file): array
    {
        try {
            $content = '';
            if (Storage::disk('public')->exists($file->storage_path)) {
                $content = Storage::disk('public')->get($file->storage_path);
                // Limit to 50KB for preview
                if (strlen($content) > 51200) {
                    $content = substr($content, 0, 51200) . "\n\n[Content truncated...]";
                }
            }

            $ext = strtolower(pathinfo($file->name, PATHINFO_EXTENSION));

            return [
                'type' => 'code',
                'filename' => $file->name,
                'content' => $content,
                'language' => $this->getLanguage($ext),
                'size' => $this->formatFileSize($file->size),
                'created_at' => $file->created_at->format('M d, Y'),
                'updated_at' => $file->updated_at->format('M d, Y H:i'),
                'created_by' => $file->creator->name ?? 'Unknown',
                'truncated' => strlen($content) > 51200
            ];
        } catch (\Exception $e) {
            return [
                'type' => 'code',
                'filename' => $file->name,
                'error' => 'Unable to read file content',
                'size' => $this->formatFileSize($file->size)
            ];
        }
    }

    /**
     * Get video preview
     */
    private function getVideoPreview(File $file): array
    {
        $inlineUrl = route('drive.files.inline', ['file' => $file->id]);

        return [
            'type' => 'video',
            'filename' => $file->name,
            'url' => $inlineUrl,
            'size' => $this->formatFileSize($file->size),
            'created_at' => $file->created_at->format('M d, Y'),
            'updated_at' => $file->updated_at->format('M d, Y H:i'),
            'created_by' => $file->creator->name ?? 'Unknown',
            'mime_type' => $file->mime_type
        ];
    }

    /**
     * Get audio preview
     */
    private function getAudioPreview(File $file): array
    {
        $inlineUrl = route('drive.files.inline', ['file' => $file->id]);

        return [
            'type' => 'audio',
            'filename' => $file->name,
            'url' => $inlineUrl,
            'size' => $this->formatFileSize($file->size),
            'created_at' => $file->created_at->format('M d, Y'),
            'updated_at' => $file->updated_at->format('M d, Y H:i'),
            'created_by' => $file->creator->name ?? 'Unknown',
            'mime_type' => $file->mime_type
        ];
    }

    /**
     * Get document type for OnlyOffice
     */
    private function getDocType(string $ext): string
    {
        $docTypes = [
            'doc' => 'text', 'docx' => 'text', 'odt' => 'text',
            'xls' => 'spreadsheet', 'xlsx' => 'spreadsheet', 'ods' => 'spreadsheet',
            'ppt' => 'presentation', 'pptx' => 'presentation', 'odp' => 'presentation',
        ];

        return $docTypes[$ext] ?? 'text';
    }

    /**
     * Get programming language for syntax highlighting
     */
    private function getLanguage(string $ext): string
    {
        $languages = [
            'js' => 'javascript', 'jsx' => 'javascript',
            'ts' => 'typescript', 'tsx' => 'typescript',
            'php' => 'php',
            'py' => 'python',
            'java' => 'java',
            'cpp' => 'cpp', 'c' => 'c', 'h' => 'c',
            'html' => 'html', 'htm' => 'html',
            'css' => 'css', 'scss' => 'scss', 'sass' => 'sass',
            'json' => 'json',
            'xml' => 'xml',
            'yml' => 'yaml', 'yaml' => 'yaml',
            'sql' => 'sql',
            'sh' => 'bash', 'bash' => 'bash',
            'rb' => 'ruby',
            'go' => 'go',
            'rs' => 'rust',
        ];

        return $languages[$ext] ?? 'plaintext';
    }

    /**
     * Get MIME type from extension
     */
    private function getMimeType(string $ext): string
    {
        $mimes = [
            'txt' => 'text/plain',
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'mp4' => 'video/mp4',
            'mp3' => 'audio/mpeg',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ];

        return $mimes[$ext] ?? 'application/octet-stream';
    }

    /**
     * Format file size to human-readable format
     */
    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
