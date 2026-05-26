@extends('layouts.app')

@section('content')
<div class="drive-content">
    <div class="breadcrumbs">
        <a href="{{ route('drive.index') }}">My Drive</a>
        <span>/</span>
        <span>Shared with me</span>
    </div>

    <div class="files-container">
        <p class="empty-state">No files have been shared with you yet.</p>
    </div>
</div>
@endsection
