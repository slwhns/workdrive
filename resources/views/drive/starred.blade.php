@extends('layouts.app')

@section('title', 'Starred')

@section('content')
<div class="drive-view">
    <div class="drive-header d-flex jc-between ai-center mg-b-20">
        <div>
            <h1 class="fs-24 fw-700">Starred</h1>
            <div class="fs-12 clr-grey1">Files you've starred will appear here</div>
        </div>

        <div class="drive-actions d-flex ai-center gap-10">
            <form method="GET" action="{{ route('drive.search') }}" class="drive-search">
                <input type="search" name="q" placeholder="Search starred files" class="drive-search-input">
            </form>

            <button class="btn btn-outline" id="view-toggle-list" title="List view"><i class="ri-list-unordered"></i></button>
            <button class="btn btn-outline" id="view-toggle-grid" title="Grid view"><i class="ri-layout-grid-line"></i></button>
        </div>
    </div>

    <div class="drive-toolbar d-flex jc-between ai-center mg-b-15">
        <div class="drive-toolbar-left d-flex ai-center gap-8">
            <span class="fs-13 clr-grey2">Showing your starred files</span>
        </div>

        <div class="drive-toolbar-right fs-12 clr-grey2">
            <span>Sorted by: Manual order</span>
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
            $examples = [
                ['title' => 'Product Roadmap.docx', 'icon' => 'ri-file-word-line'],
                ['title' => 'Team Photo.jpg', 'icon' => 'ri-image-2-line'],
                ['title' => 'Budget.xlsx', 'icon' => 'ri-file-excel-line'],
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
            <div class="fs-18 fw-600">Nothing starred yet</div>
            <div class="fs-13 clr-grey2 mg-t-8">Star files to keep them handy.</div>
        </div>
    </div>
</div>
@endsection
