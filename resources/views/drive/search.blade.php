@extends('layouts.app')

@section('content')
<div class="drive-content">
    <div class="breadcrumbs">
        <a href="{{ route('drive.index') }}">My Drive</a>
        <span>/</span>
        <span>Search Results</span>
    </div>

    <div class="files-container">
        <p class="empty-state">No search results found.</p>
    </div>
</div>
@endsection
