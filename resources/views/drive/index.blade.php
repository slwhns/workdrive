@extends('layouts.app')

@section('title', 'My Drive')

@section('content')
<div class="drive-view">
    <div class="drive-header d-flex jc-between ai-center mg-b-20">
        <div>
            <h1 class="fs-24 fw-700">My Drive</h1>
            <div class="fs-12 clr-grey1">Organize and access your files</div>
        </div>

        <div class="drive-actions d-flex ai-center gap-10">
            <form method="GET" action="{{ route('drive.search') }}" class="drive-search">
                <input type="search" name="q" placeholder="Search files and folders" class="drive-search-input">
            </form>

            <button class="btn btn-outline" id="view-toggle-list" title="List view"><i class="ri-list-unordered"></i></button>
            <button class="btn btn-outline" id="view-toggle-grid" title="Grid view"><i class="ri-layout-grid-line"></i></button>
            <a href="{{ route('drive.index') }}" class="btn btn-primary">Refresh</a>
        </div>
    </div>

    <div class="drive-toolbar d-flex jc-between ai-center mg-b-15">
        <div class="drive-toolbar-left d-flex ai-center gap-8">
            <button class="btn btn-secondary" onclick="document.getElementById('app-new-trigger').click()"><i class="ri-add-line"></i> Create</button>
            <label class="btn btn-secondary" for="upload-files-input" style="margin-left:6px;"><i class="ri-upload-2-line"></i> Upload</label>
        </div>

        <div class="drive-toolbar-right fs-12 clr-grey2">
            <span>Last modified</span>
        </div>
    </div>

    <div class="drive-grid" id="drive-grid">
        <div class="drive-list-header" role="rowgroup">
            <div class="col-name">Name</div>
            <div class="col-modified">Last modified</div>
            <div class="col-size">Size</div>
            <div class="col-actions" aria-hidden="true"></div>
        </div>
        @php
            // Placeholder example cards — replace with real data in controller
            $examples = [
                ['title' => 'Documents', 'icon' => 'ri-folder-3-line'],
                ['title' => 'Images', 'icon' => 'ri-image-2-line'],
                ['title' => 'Spreadsheets', 'icon' => 'ri-file-excel-line'],
                ['title' => 'Presentations', 'icon' => 'ri-slideshow-line'],
            ];
        @endphp

        @foreach($examples as $card)
            <div class="drive-card">
                <div class="drive-card-icon" style="color: {{ isset($card['color']) ? $card['color'] : '#FFD600' }};">
                    <i class="{{ $card['icon'] }}"></i>
                </div>
                @if(isset($card['thumb']))
                    <img src="{{ $card['thumb'] }}" alt="{{ $card['title'] }}" class="drive-card-thumb" style="width:100%;border-radius:8px;margin-bottom:10px;" />
                @endif
                <div class="drive-card-title">{{ $card['title'] }}</div>
            </div>
        @endforeach

        <div class="drive-empty-card">
            <div class="fs-18 fw-600">No files yet</div>
            <div class="fs-13 clr-grey2 mg-t-8">Start by creating a folder, uploading files, or using OnlyOffice to create documents.</div>
            <div class="mg-t-12">
                <button class="btn btn-primary" onclick="document.getElementById('app-new-trigger').click()">Create</button>
                <label for="upload-files-input" class="btn btn-outline" style="margin-left:8px;">Upload</label>
            </div>
        </div>
    </div>
</div>
@endsection
