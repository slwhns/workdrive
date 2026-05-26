@extends('layouts.app')

@section('title', 'My Drive')

@section('content')
<div id="spa-content-area">
    <!-- The premium SPA engine will render everything here dynamically -->
    <div class="d-flex fd-column ai-center jc-center" style="min-height: 350px;">
        <div class="spinner"></div>
        <div class="fs-13 clr-grey2">Loading your Workspace...</div>
    </div>
</div>
@endsection
