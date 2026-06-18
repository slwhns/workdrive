<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="user-id" content="{{ auth()->id() }}">
    <title>WD | @yield('title', 'WorkDrive')</title>

    <!-- Favicon -->
    <link rel="icon" href="{{ asset('images/workdrive.svg') }}" type="image/svg+xml">

    <link href="https://fonts.googleapis.com/css2?family=Jura:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.css" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('css/layout.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('css/custom-overrides.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('css/profile-page.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('css/profile-settings.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('css/sidebar-nav-premium.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('css/drive.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('css/drive-premium.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('css/preview.css') }}?v={{ time() }}">
    @stack('styles')
</head>
<body class="bg-white3 app-theme" data-user-role="{{ auth()->user()->role ?? 'user' }}" data-user-company="{{ auth()->user()->company ?? '' }}">
@php
    $currentUser = auth()->user();
    $displayName = $currentUser?->name ?? 'User';
    $avatarInitial = strtoupper(substr($displayName, 0, 1));
    $avatarUrl = $currentUser?->profile_photo_path ? \Illuminate\Support\Facades\Storage::url($currentUser->profile_photo_path) : null;
@endphp

<div class="d-flex app-shell">
    <div class="app-sidebar">
        <div class="app-sidebar-inner">
            <div class="app-brand-wrap mg-b-10" style="margin-bottom: 12px;">
                <div class="app-brand">
                    <img 
                        src="{{ asset('images/workdrive.svg') }}" 
                        alt="RFQ Management System Logo" 
                        class="app-brand-logo"
                    >
                </div>
            </div>

            <div class="app-new-wrap">
                <button type="button" class="nav-btn app-new-trigger cursor-pointer" id="app-new-trigger" aria-haspopup="true" aria-expanded="false">
                    <span class="app-nav-icon">
                        <svg viewBox="0 0 36 36" width="36" height="36" class="google-plus-svg">
                            <path fill="#EA4335" d="M16 16v14h4V20h10v-4H20V6h-4v10H6v4h10z"/>
                            <path fill="#4285F4" d="M30 16H20v4h10z"/>
                            <path fill="#FBBC05" d="M6 16v4h10v-4z"/>
                            <path fill="#34A853" d="M20 16V6h-4v10z"/>
                        </svg>
                    </span>
                    <span class="app-nav-text">
                        <span class="app-nav-text-short">New</span>
                        <span class="app-nav-text-full">New</span>
                    </span>
                </button>

                <div class="app-new-menu" id="app-new-menu" aria-hidden="true">
                    <form id="new-folder-form" method="POST" action="{{ route('drive.folders.store') }}" class="app-new-menu-form">
                        @csrf
                        <input type="hidden" name="name" id="new-folder-name">
                        <button type="button" class="app-new-menu-item" data-new-action="folder">
                            <i class="ri-folder-add-line menu-icon-folder"></i>
                            <span>New Folder</span>
                        </button>
                    </form>

                    <form method="POST" action="{{ route('drive.upload.files') }}" enctype="multipart/form-data" class="app-new-menu-form">
                        @csrf
                        <label class="app-new-menu-item" for="upload-files-input">
                            <i class="ri-upload-2-line menu-icon-upload"></i>
                            <span>Upload Files</span>
                        </label>
                        <input id="upload-files-input" type="file" name="files[]" class="app-menu-file-input" multiple>
                    </form>

                    <form method="POST" action="{{ route('drive.upload.folder') }}" enctype="multipart/form-data" class="app-new-menu-form">
                        @csrf
                        <label class="app-new-menu-item" for="upload-folder-input">
                            <i class="ri-folder-upload-line menu-icon-upload"></i>
                            <span>Upload Folder</span>
                        </label>
                        <input id="upload-folder-input" type="file" name="folder_files[]" class="app-menu-file-input" multiple webkitdirectory directory>
                    </form>

                    <div class="app-new-menu-divider"></div>

                    <form method="POST" action="{{ route('drive.office.create', ['kind' => 'document']) }}" class="app-new-menu-form">
                        @csrf
                        <button type="submit" class="app-new-menu-item">
                            <i class="ri-file-word-fill menu-icon-doc"></i>
                            <span>New Doc</span>
                        </button>
                    </form>

                    <form method="POST" action="{{ route('drive.office.create', ['kind' => 'spreadsheet']) }}" class="app-new-menu-form">
                        @csrf
                        <button type="submit" class="app-new-menu-item">
                            <i class="ri-file-excel-fill menu-icon-sheet"></i>
                            <span>New Spreadsheet</span>
                        </button>
                    </form>

                    <form method="POST" action="{{ route('drive.office.create', ['kind' => 'presentation']) }}" class="app-new-menu-form">
                        @csrf
                        <button type="submit" class="app-new-menu-item">
                            <i class="ri-slideshow-3-fill menu-icon-slide"></i>
                            <span>New Presentation</span>
                        </button>
                    </form>
                </div>
            </div>

            <div class="app-nav-group-label" style="margin-top: 5px; margin-bottom: 2px;">
                <span class="app-nav-group-label-short">Main</span>
                <span class="app-nav-group-label-full">Main</span>
            </div>

            <a href="{{ route('drive.index') }}" class="nav-btn pd-6 br-5 mg-b-4 txt-none cursor-pointer {{ request()->routeIs('drive.index', 'drive.list') ? 'app-nav-active' : '' }}">
                <span class="app-nav-icon"><i class="ri-home-2-line"></i></span>
                <span class="app-nav-text">
                    <span class="app-nav-text-short">Home</span>
                    <span class="app-nav-text-full">My Drive</span>
                </span>
            </a>

            <div class="app-nav-group-label" style="margin-top: 5px; margin-bottom: 2px;">
                <span class="app-nav-group-label-short">Man.</span>
                <span class="app-nav-group-label-full">Management</span>
            </div>

            <a href="{{ route('drive.shared') }}" class="nav-btn pd-6 br-5 mg-b-4 txt-none cursor-pointer {{ request()->routeIs('drive.shared') ? 'app-nav-active' : '' }}">
                <span class="app-nav-icon"><i class="ri-share-forward-line"></i></span>
                <span class="app-nav-text">
                    <span class="app-nav-text-short">Share</span>
                    <span class="app-nav-text-full">Shared with Me</span>
                </span>
            </a>

            <a href="{{ route('drive.recents') }}" class="nav-btn pd-6 br-5 mg-b-4 txt-none cursor-pointer {{ request()->routeIs('drive.recents') ? 'app-nav-active' : '' }}">
                <span class="app-nav-icon"><i class="ri-history-line"></i></span>
                <span class="app-nav-text">
                    <span class="app-nav-text-short">Recents</span>
                    <span class="app-nav-text-full">Recently Accessed</span>
                </span>
            </a>

            <a href="{{ route('drive.starred') }}" class="nav-btn pd-6 br-5 mg-b-4 txt-none cursor-pointer {{ request()->routeIs('drive.starred') ? 'app-nav-active' : '' }}">
                <span class="app-nav-icon"><i class="ri-star-line"></i></i></span>
                <span class="app-nav-text">
                    <span class="app-nav-text-short">Starred</span>
                    <span class="app-nav-text-full">Starred Files</span>
                </span>
            </a>

            <a href="{{ route('drive.trash') }}" class="nav-btn pd-6 br-5 mg-b-4 txt-none cursor-pointer {{ request()->routeIs('drive.trash') ? 'app-nav-active' : '' }}">
                <span class="app-nav-icon"><i class="ri-delete-bin-6-line"></i></span>
                <span class="app-nav-text">
                    <span class="app-nav-text-short">Trash</span>
                    <span class="app-nav-text-full">Trash</span>
                </span>
            </a>

            <div class="app-nav-group-label" style="margin-top: 5px; margin-bottom: 2px;">
                <span class="app-nav-group-label-short">Tags</span>
                <span class="app-nav-group-label-full">Tags</span>
            </div>

            <div class="sidebar-tags-wrap" style="display: flex; flex-direction: column; gap: 1px;">
                <a href="{{ route('drive.tag', ['tag' => 'Red']) }}" class="nav-btn pd-5 br-5 txt-none cursor-pointer sidebar-tag-link" data-tag="Red" style="margin-bottom: 1px;">
                    <span class="app-nav-icon"><span class="tag-dot tag-dot-red"></span></span>
                    <span class="app-nav-text">
                        <span class="app-nav-text-short">Red</span>
                        <span class="app-nav-text-full">Red</span>
                    </span>
                </a>
                <a href="{{ route('drive.tag', ['tag' => 'Orange']) }}" class="nav-btn pd-5 br-5 txt-none cursor-pointer sidebar-tag-link" data-tag="Orange" style="margin-bottom: 1px;">
                    <span class="app-nav-icon"><span class="tag-dot tag-dot-orange"></span></span>
                    <span class="app-nav-text">
                        <span class="app-nav-text-short">Orange</span>
                        <span class="app-nav-text-full">Orange</span>
                    </span>
                </a>
                <a href="{{ route('drive.tag', ['tag' => 'Yellow']) }}" class="nav-btn pd-5 br-5 txt-none cursor-pointer sidebar-tag-link" data-tag="Yellow" style="margin-bottom: 1px;">
                    <span class="app-nav-icon"><span class="tag-dot tag-dot-yellow"></span></span>
                    <span class="app-nav-text">
                        <span class="app-nav-text-short">Yellow</span>
                        <span class="app-nav-text-full">Yellow</span>
                    </span>
                </a>
                <a href="{{ route('drive.tag', ['tag' => 'Green']) }}" class="nav-btn pd-5 br-5 txt-none cursor-pointer sidebar-tag-link" data-tag="Green" style="margin-bottom: 1px;">
                    <span class="app-nav-icon"><span class="tag-dot tag-dot-green"></span></span>
                    <span class="app-nav-text">
                        <span class="app-nav-text-short">Green</span>
                        <span class="app-nav-text-full">Green</span>
                    </span>
                </a>
                <a href="{{ route('drive.tag', ['tag' => 'Blue']) }}" class="nav-btn pd-5 br-5 txt-none cursor-pointer sidebar-tag-link" data-tag="Blue" style="margin-bottom: 1px;">
                    <span class="app-nav-icon"><span class="tag-dot tag-dot-blue"></span></span>
                    <span class="app-nav-text">
                        <span class="app-nav-text-short">Blue</span>
                        <span class="app-nav-text-full">Blue</span>
                    </span>
                </a>
                <a href="{{ route('drive.tag', ['tag' => 'Purple']) }}" class="nav-btn pd-5 br-5 txt-none cursor-pointer sidebar-tag-link" data-tag="Purple" style="margin-bottom: 1px;">
                    <span class="app-nav-icon"><span class="tag-dot tag-dot-purple"></span></span>
                    <span class="app-nav-text">
                        <span class="app-nav-text-short">Purple</span>
                        <span class="app-nav-text-full">Purple</span>
                    </span>
                </a>
                <a href="{{ route('drive.tag', ['tag' => 'Grey']) }}" class="nav-btn pd-5 br-5 txt-none cursor-pointer sidebar-tag-link" data-tag="Grey" style="margin-bottom: 1px;">
                    <span class="app-nav-icon"><span class="tag-dot tag-dot-grey"></span></span>
                    <span class="app-nav-text">
                        <span class="app-nav-text-short">Grey</span>
                        <span class="app-nav-text-full">Grey</span>
                    </span>
                </a>
                <a href="{{ route('drive.tag', ['tag' => 'all']) }}" class="nav-btn pd-5 br-5 txt-none cursor-pointer sidebar-tag-link" data-tag="all" style="margin-bottom: 1px;">
                    <span class="app-nav-icon"><i class="ri-price-tag-3-line"></i></span>
                    <span class="app-nav-text">
                        <span class="app-nav-text-short">All Tags</span>
                        <span class="app-nav-text-full">All Tags...</span>
                    </span>
                </a>
            </div>

            <hr class="sidebar-divider">
        </div>
    </div>

    <div class="fg-1 d-flex fd-column app-content">
        <div class="header d-flex jc-between ai-center pd-15 bg-white5 bdr-bottom-22 box-shadow-basic app-header">
            <div>
                <div class="fs-18 fw-bold header-title">WorkDrive System</div>
                <div class="fs-12 header-subtitle">Company Internal Drive</div>
            </div>

            <div class="d-flex ai-center">
                <!-- Custom Drive Scope Selector Dropdown -->
                <div class="header-drive-selector dropdown">
                    <button type="button" class="drive-select-trigger" id="drive-select-trigger" aria-haspopup="true" aria-expanded="false">
                        <i class="ri-user-line" id="drive-scope-icon"></i>
                        <span id="selected-drive-type">Personal</span>
                        <i class="ri-arrow-down-s-line"></i>
                    </button>
                    <div class="drive-select-menu" id="drive-select-menu" aria-hidden="true">
                        <button type="button" class="drive-select-option" data-scope="personal">
                            <i class="ri-user-line"></i>
                            <span>Personal</span>
                        </button>
                        <button type="button" class="drive-select-option" data-scope="project">
                            <i class="ri-folder-shared-line"></i>
                            <span>Team / Project</span>
                        </button>
                        <button type="button" class="drive-select-option" data-scope="organization">
                            <i class="ri-building-line"></i>
                            <span>Organization</span>
                        </button>
                        @if(auth()->user() && (auth()->user()->role === 'superadmin' || auth()->user()->role === 'admin'))
                            <button type="button" class="drive-select-option" data-scope="admin">
                                <i class="ri-shield-user-line"></i>
                                <span>Admin</span>
                            </button>
                        @endif
                    </div>
                </div>

                <button type="button" class="header-profile-trigger" id="header-profile-trigger" aria-label="Open profile panel">
                    <div class="fs-13 header-user">{{ strtoupper($displayName) }}</div>
                    <span class="header-profile-avatar">
                        @if($avatarUrl)
                            <img src="{{ $avatarUrl }}" alt="{{ $displayName }} avatar" class="header-profile-avatar-image">
                        @else
                            {{ $avatarInitial }}
                        @endif
                    </span>
                </button>
            </div>
        </div>

        <div class="bg-orbs" aria-hidden="true">
            <div class="bg-orb bg-orb-1"></div>
            <div class="bg-orb bg-orb-2"></div>
            <div class="bg-orb bg-orb-3"></div>
        </div>

        <div class="pd-20 app-main" id="main-content">
            @yield('content')
        </div>
    </div>
</div>

<div class="profile-rail-overlay" id="profile-rail-overlay" aria-hidden="true"></div>
<div class="profile-rail-hitarea" id="profile-rail-hitarea" aria-hidden="true"></div>

<aside class="profile-rail" id="profile-rail" aria-hidden="true">
    <div class="profile-rail-top">
        <span class="header-profile-avatar">
            @if($avatarUrl)
                <img src="{{ $avatarUrl }}" alt="{{ $displayName }} avatar" class="header-profile-avatar-image">
            @else
                {{ $avatarInitial }}
            @endif
        </span>

        <div class="profile-rail-actions">
            <a href="{{ route('profile.show') }}" class="profile-rail-btn" aria-label="Profile settings" title="Profile Settings">
                <i class="ri-user-3-line"></i>
            </a>

            <button type="button" id="theme-toggle-btn" class="profile-rail-btn" aria-label="Toggle theme" title="Switch Theme">
                <i class="ri-sun-line" id="theme-icon"></i>
            </button>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="profile-rail-btn profile-rail-logout" title="Logout" aria-label="Logout">
                    <i class="ri-logout-box-r-line"></i>
                </button>
            </form>
        </div>
    </div>
</aside>

<div class="drawer-backdrop collapsed" id="details-drawer-backdrop"></div>
<div class="drive-details-drawer collapsed" id="details-drawer"></div>

@include('components.preview-modal')

<script src="{{ asset('js/workdrive-layout.js') }}?v={{ time() }}"></script>
<script src="{{ asset('js/app.js') }}?v={{ time() }}"></script>
<script src="{{ asset('js/drive-premium.js') }}?v={{ time() }}"></script>
<script src="{{ asset('js/preview.js') }}?v={{ time() }}"></script>
@stack('scripts')
</body>
</html>
