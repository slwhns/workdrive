@extends('layouts.app')

@section('content')
<div class="drive-content">
    <div class="breadcrumbs">
        <a href="{{ route('drive.index') }}">My Drive</a>
        <span>/</span>
        <span>Trash</span>
    </div>

    <div class="files-container">
        <p class="empty-state">Your trash is empty.</p>
    </div>
</div>
@endsection
