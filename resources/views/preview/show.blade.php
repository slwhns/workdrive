@extends('layouts.app')

@section('title', 'Preview - ' . $file->name)

@section('content')
@php
    $ext = strtolower(pathinfo($file->name, PATHINFO_EXTENSION));
    $iconClass = 'ri-file-3-line';
    $iconColor = '#a8b0c0';
    if (in_array($ext, ['doc', 'docx'])) { $iconClass = 'ri-file-word-2-fill'; $iconColor = '#4285f4'; }
    elseif (in_array($ext, ['xls', 'xlsx'])) { $iconClass = 'ri-file-excel-2-fill'; $iconColor = '#0f9d58'; }
    elseif (in_array($ext, ['ppt', 'pptx'])) { $iconClass = 'ri-slideshow-3-fill'; $iconColor = '#f4b400'; }
    elseif ($ext === 'pdf') { $iconClass = 'ri-file-pdf-fill'; $iconColor = '#db4437'; }
    elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) { $iconClass = 'ri-image-2-fill'; $iconColor = '#f4b400'; }
@endphp

<link rel="stylesheet" href="{{ asset('css/preview.css') }}?v={{ time() }}">

<div class="preview-container google-drive-preview">
    <!-- Preview Header -->
    <div class="preview-header">
        <div class="preview-header-left">
            <button class="btn-icon btn-back" id="btn-preview-back" title="Back">
                <i class="ri-arrow-left-line"></i>
            </button>
            <div class="preview-file-icon" style="color: {{ $iconColor }}; font-size: 24px; display: flex; align-items: center;">
                <i class="{{ $iconClass }}"></i>
            </div>
            <div class="preview-title">
                <h2>{{ $file->name }}</h2>
                <p class="preview-subtitle">{{ $previewData['size'] ?? 'Unknown size' }} • By {{ $previewData['created_by'] ?? 'Unknown' }}</p>
            </div>
        </div>

        <div class="preview-header-center">
            @if($previewData['type'] === 'office')
                <button class="btn btn-primary" id="btn-preview-open-editor" style="background: #1a73e8; border-color: #1a73e8; border-radius: 4px; font-weight: 500; display: inline-flex; align-items: center; gap: 8px;">
                    <i class="ri-edit-line"></i> Open with OnlyOffice
                </button>
            @endif
        </div>

        <div class="preview-header-right">
            <a href="{{ route('drive.files.download', ['file' => $file->id]) }}" class="btn btn-sm btn-outline" title="Download" style="display: inline-flex; align-items: center; gap: 6px;">
                <i class="ri-download-line"></i> Download
            </a>
            <button class="btn-icon btn-share" id="btn-preview-share" title="Share">
                <i class="ri-share-line"></i>
            </button>
            <button class="btn-icon" id="btn-preview-sidebar-toggle" title="Toggle details">
                <i class="ri-information-line"></i>
            </button>
        </div>
    </div>

    <!-- Preview Content Area -->
    <div class="preview-content" style="position: relative;">
        @switch($previewData['type'])
            @case('office')
                @include('preview.types.office', ['data' => $previewData, 'file' => $file])
                @break
            @case('image')
                @include('preview.types.image', ['data' => $previewData, 'file' => $file])
                @break
            @case('pdf')
                @include('preview.types.pdf', ['data' => $previewData, 'file' => $file])
                @break
            @case('text')
                @include('preview.types.text', ['data' => $previewData, 'file' => $file])
                @break
            @case('code')
                @include('preview.types.code', ['data' => $previewData, 'file' => $file])
                @break
            @case('video')
                @include('preview.types.video', ['data' => $previewData, 'file' => $file])
                @break
            @case('audio')
                @include('preview.types.audio', ['data' => $previewData, 'file' => $file])
                @break
            @default
                @include('preview.types.unsupported', ['data' => $previewData, 'file' => $file])
        @endswitch

        <!-- Navigation Buttons -->
        @if(isset($prevFileId))
            <a href="{{ route('preview.show', ['file' => $prevFileId]) }}" class="preview-nav-btn prev-btn" id="preview-prev-btn" title="Previous File">
                <i class="ri-arrow-left-s-line"></i>
            </a>
        @endif
        @if(isset($nextFileId))
            <a href="{{ route('preview.show', ['file' => $nextFileId]) }}" class="preview-nav-btn next-btn" id="preview-next-btn" title="Next File">
                <i class="ri-arrow-right-s-line"></i>
            </a>
        @endif

        <!-- Floating Zoom Panel -->
        @if($previewData['type'] === 'image')
            <div class="preview-zoom-panel" id="preview-zoom-panel">
                <button class="zoom-btn" id="btn-zoom-out" title="Zoom Out"><i class="ri-zoom-out-line"></i></button>
                <span class="zoom-value" id="zoom-value">100%</span>
                <button class="zoom-btn" id="btn-zoom-in" title="Zoom In"><i class="ri-zoom-in-line"></i></button>
                <div class="zoom-divider"></div>
                <button class="zoom-btn" id="btn-zoom-reset" title="Reset Zoom"><i class="ri-aspect-ratio-line"></i></button>
            </div>
        @endif
    </div>

    <!-- Preview Sidebar (File Info) -->
    <div class="preview-sidebar" id="preview-sidebar">
        <div class="sidebar-section">
            <h3>File Details</h3>
            <div class="file-details">
                <div class="detail-row">
                    <span class="detail-label">Type:</span>
                    <span class="detail-value">{{ $previewData['extension'] ?? $previewData['type'] }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Size:</span>
                    <span class="detail-value">{{ $previewData['size'] ?? 'Unknown' }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Created:</span>
                    <span class="detail-value">{{ $previewData['created_at'] ?? 'Unknown' }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Modified:</span>
                    <span class="detail-value">{{ $previewData['updated_at'] ?? 'Unknown' }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Owner:</span>
                    <span class="detail-value">{{ $previewData['created_by'] ?? 'Unknown' }}</span>
                </div>
            </div>
        </div>

        <div class="sidebar-section">
            <h3>Actions</h3>
            @if($previewData['type'] === 'office')
                <button class="btn btn-block btn-sm btn-primary" id="btn-sidebar-edit" style="margin-bottom: 8px;">
                    <i class="ri-edit-line"></i> Edit Document
                </button>
            @endif
            <a href="{{ route('drive.files.download', ['file' => $file->id]) }}" class="btn btn-block btn-sm btn-outline" style="margin-bottom: 8px; justify-content: center; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                <i class="ri-download-line"></i> Download
            </a>
            <button class="btn btn-block btn-sm btn-outline" id="btn-preview-share-btn">
                <i class="ri-share-line"></i> Share
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script src="{{ asset('js/preview.js') }}?v={{ time() }}"></script>
@if($previewData['type'] === 'code')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/atom-one-dark.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
@endif
@if($previewData['type'] === 'pdf')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script src="{{ asset('js/preview-pdf.js') }}"></script>
@endif
@endpush

@endsection
