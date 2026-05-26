@extends('layouts.app')

@section('content')
<div class="drive-content">
    <div class="breadcrumbs">
        <a href="{{ route('drive.index') }}">My Drive</a>
    </div>

    <div class="files-container">
        <p class="empty-state">Your drive is empty. Start by uploading files or creating folders.</p>
    </div>
</div>
@endsection
