<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $file->name }} - WorkDrive Shared</title>
    <link rel="icon" href="{{ asset('images/workdrive.svg') }}" type="image/svg+xml">
    <link href="https://fonts.googleapis.com/css2?family=Jura:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        :root {
            --bg-color: #0c0e12;
            --card-bg: rgba(255, 255, 255, 0.03);
            --card-border: rgba(255, 255, 255, 0.07);
            --text-primary: #f3f4f6;
            --text-secondary: #9ca3af;
            --primary-glow: rgba(217, 166, 40, 0.15);
            --accent: #d9a628;
            --danger: #ef4444;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Jura', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow-x: hidden;
            background-image: 
                radial-gradient(at 0% 0%, rgba(217, 166, 40, 0.05) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(34, 54, 87, 0.2) 0px, transparent 50%);
        }

        .share-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        .share-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            width: 100%;
            max-width: 800px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4), 0 0 40px var(--primary-glow);
            overflow: hidden;
        }

        .share-header {
            padding: 30px;
            border-bottom: 1px solid var(--card-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(255, 255, 255, 0.01);
        }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: var(--text-primary);
        }

        .brand-logo img {
            width: 36px;
            height: 36px;
        }

        .brand-name {
            font-weight: 700;
            font-size: 18px;
            letter-spacing: 1px;
            background: linear-gradient(135deg, #ffffff 0%, #a1a1a1 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .share-body {
            padding: 40px 30px;
            text-align: center;
        }

        /* Password and Expired Views */
        .status-icon {
            font-size: 64px;
            margin-bottom: 20px;
            display: inline-block;
        }

        .status-icon.expired {
            color: var(--danger);
        }

        .status-icon.password {
            color: var(--accent);
        }

        .share-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 12px;
        }

        .share-desc {
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 24px;
            text-align: left;
            max-width: 400px;
            margin-inline: auto;
        }

        .form-label {
            display: block;
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 8px;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--card-border);
            border-radius: 10px;
            padding: 12px 16px;
            color: var(--text-primary);
            font-family: inherit;
            font-size: 15px;
            outline: none;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(217, 166, 40, 0.15);
        }

        .error-message {
            color: var(--danger);
            font-size: 13px;
            margin-top: 8px;
            text-align: left;
            max-width: 400px;
            margin-inline: auto;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 12px 24px;
            border-radius: 10px;
            font-family: inherit;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--accent);
            color: #000000;
        }

        .btn-primary:hover {
            background: #f1b72a;
            transform: translateY(-1px);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--card-border);
            color: var(--text-primary);
        }

        .btn-outline:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.15);
        }

        .btn-group {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-top: 10px;
            flex-wrap: wrap;
        }

        /* File Preview styling */
        .preview-icon-wrapper {
            width: 100px;
            height: 100px;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--card-border);
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            font-size: 48px;
            color: var(--accent);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }

        .file-meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            background: rgba(255, 255, 255, 0.01);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 15px;
            max-width: 500px;
            margin: 0 auto 30px;
            text-align: left;
        }

        .meta-item-label {
            font-size: 11px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .meta-item-value {
            font-size: 14px;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Folder Explorer Styling */
        .explorer-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            gap: 15px;
            flex-wrap: wrap;
        }

        .breadcrumbs {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: var(--text-secondary);
            flex-wrap: wrap;
        }

        .breadcrumbs a {
            color: var(--text-secondary);
            text-decoration: none;
            transition: color 0.2s;
        }

        .breadcrumbs a:hover {
            color: var(--accent);
        }

        .breadcrumbs i {
            font-size: 12px;
        }

        .breadcrumbs span {
            color: var(--text-primary);
            font-weight: 600;
        }

        .explorer-list {
            border: 1px solid var(--card-border);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.01);
            overflow: hidden;
            text-align: left;
            margin-bottom: 25px;
        }

        .explorer-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            padding: 12px 20px;
            border-bottom: 1px solid var(--card-border);
            align-items: center;
            font-size: 14px;
            transition: background 0.2s;
        }

        .explorer-row:last-child {
            border-bottom: none;
        }

        .explorer-row.header-row {
            background: rgba(255, 255, 255, 0.02);
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .explorer-row:not(.header-row):hover {
            background: rgba(255, 255, 255, 0.03);
        }

        .item-name-col {
            display: flex;
            align-items: center;
            gap: 12px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .item-name-col a {
            color: var(--text-primary);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }

        .item-name-col a:hover {
            color: var(--accent);
        }

        .item-icon {
            font-size: 20px;
            color: var(--accent);
        }

        .item-icon.file-icon {
            color: #60a5fa;
        }

        .item-actions-col {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
        }

        .action-icon-btn {
            background: transparent;
            border: none;
            color: var(--text-secondary);
            font-size: 18px;
            cursor: pointer;
            transition: color 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 4px;
            border-radius: 4px;
        }

        .action-icon-btn:hover {
            color: var(--accent);
            background: rgba(255, 255, 255, 0.05);
        }

        .action-icon-btn.btn-danger-hv:hover {
            color: var(--danger);
        }

        .empty-explorer {
            padding: 60px 20px;
            text-align: center;
            color: var(--text-secondary);
        }

        .empty-explorer i {
            font-size: 48px;
            margin-bottom: 16px;
            display: block;
        }

        footer {
            padding: 24px;
            text-align: center;
            font-size: 12px;
            color: var(--text-secondary);
            border-top: 1px solid var(--card-border);
        }

        @media (max-width: 640px) {
            .explorer-row {
                grid-template-columns: 1fr;
                gap: 8px;
                padding: 16px;
            }

            .explorer-row.header-row {
                display: none;
            }

            .item-actions-col {
                justify-content: flex-start;
                margin-top: 8px;
                padding-left: 32px;
            }

            .explorer-row .size-col,
            .explorer-row .date-col {
                padding-left: 32px;
                font-size: 12px;
                color: var(--text-secondary);
            }
        }
    </style>
</head>
<body>

    <div class="share-container">
        <div class="share-card">
            
            <div class="share-header">
                <a href="#" class="brand-logo">
                    <img src="{{ asset('images/workdrive.svg') }}" alt="WorkDrive Logo">
                    <span class="brand-name">WorkDrive</span>
                </a>
                <span style="font-size: 12px; color: var(--text-secondary);">Public Shared Space</span>
            </div>

            <div class="share-body">

                @if($status === 'expired')
                    <!-- Expired Link View -->
                    <div class="status-icon expired">
                        <i class="ri-time-line"></i>
                    </div>
                    <h1 class="share-title">Link Expired</h1>
                    <p class="share-desc">This shared link is no longer active because it has reached its expiration date.</p>
                    <a href="{{ route('login') }}" class="btn btn-outline">Go to Log In</a>

                @elseif($status === 'password_required')
                    <!-- Password Protected View -->
                    <div class="status-icon password">
                        <i class="ri-lock-password-line"></i>
                    </div>
                    <h1 class="share-title">Password Protected</h1>
                    <p class="share-desc">This link requires a password to access. Please enter the password below.</p>

                    <form action="{{ route('drive.public.share.password', ['token' => $file->share_token]) }}" method="POST">
                        @csrf
                        <div class="form-group">
                            <label class="form-label" for="password">Password</label>
                            <input type="password" name="password" id="password" class="form-control" placeholder="Enter password" required autofocus>
                        </div>
                        @if($errors->has('password'))
                            <div class="error-message">
                                <i class="ri-error-warning-line"></i> {{ $errors->first('password') }}
                            </div>
                        @endif
                        <div class="btn-group" style="margin-top: 24px;">
                            <button type="submit" class="btn btn-primary">Verify Password</button>
                        </div>
                    </form>

                @else
                    <!-- Main Sharing Display -->

                    @if($file->is_folder)
                        <!-- Folder Explorer View -->
                        @php
                            // Calculate breadcrumbs
                            $crumbs = [];
                            if (isset($currentFolder) && $currentFolder->id !== $file->id) {
                                $curr = $currentFolder;
                                while ($curr && $curr->id !== $file->id) {
                                    array_unshift($crumbs, $curr);
                                    $curr = \App\Models\File::find($curr->parent_id);
                                }
                            }
                        @endphp
                        
                        <div class="explorer-header">
                            <div class="breadcrumbs">
                                <a href="{{ route('drive.public.share', ['token' => $file->share_token]) }}">
                                    <i class="ri-folder-shared-line"></i> {{ $file->name }}
                                </a>
                                @foreach($crumbs as $crumb)
                                    <i class="ri-arrow-right-s-line"></i>
                                    @if($loop->last)
                                        <span>{{ $crumb->name }}</span>
                                    @else
                                        <a href="{{ route('drive.public.share', ['token' => $file->share_token, 'folder' => $crumb->id]) }}">{{ $crumb->name }}</a>
                                    @endif
                                @endforeach
                            </div>

                            <div class="explorer-actions">
                                @if($file->share_allow_download)
                                    <a href="{{ route('drive.public.share.download', ['token' => $file->share_token]) }}" class="btn btn-primary" title="Download folder as ZIP">
                                        <i class="ri-download-2-line"></i> Download Folder
                                    </a>
                                @endif
                                @if($file->share_allow_import)
                                    <form action="{{ route('drive.public.share.import', ['token' => $file->share_token]) }}" method="POST" style="display:inline;">
                                        @csrf
                                        <button type="submit" class="btn btn-outline" title="Import this folder to your Drive">
                                            <i class="ri-folder-add-line"></i> Import to Drive
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>

                        <div class="explorer-list">
                            <div class="explorer-row header-row">
                                <div>Name</div>
                                <div>Size</div>
                                <div style="text-align: right;">Actions</div>
                            </div>

                            @forelse($children as $child)
                                <div class="explorer-row">
                                    <div class="item-name-col">
                                        @if($child->is_folder)
                                            <i class="ri-folder-fill item-icon"></i>
                                            <a href="{{ route('drive.public.share', ['token' => $file->share_token, 'folder' => $child->id]) }}">{{ $child->name }}</a>
                                        @else
                                            <i class="ri-file-fill item-icon file-icon"></i>
                                            @if($file->share_allow_direct_access)
                                                <a href="{{ route('drive.public.share.subfile.inline', ['token' => $file->share_token, 'subfile' => $child->id]) }}" target="_blank">{{ $child->name }}</a>
                                            @else
                                                <span>{{ $child->name }}</span>
                                            @endif
                                        @endif
                                    </div>
                                    <div class="size-col">
                                        {{ $child->is_folder ? '-' : number_format($child->size / 1024 / 1024, 2) . ' MB' }}
                                    </div>
                                    <div class="item-actions-col">
                                        @if($file->share_allow_download)
                                            <a href="{{ route('drive.public.share.subfile.download', ['token' => $file->share_token, 'subfile' => $child->id]) }}" class="action-icon-btn" title="Download">
                                                <i class="ri-download-2-line"></i>
                                            </a>
                                        @endif
                                        @if($file->share_allow_import && auth()->check())
                                            <button onclick="importSubfile('{{ $child->id }}')" class="action-icon-btn" title="Import to Drive">
                                                <i class="ri-folder-add-line"></i>
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="empty-explorer">
                                    <i class="ri-folder-open-line"></i>
                                    <p>This folder is empty.</p>
                                </div>
                            @endforelse
                        </div>

                    @else
                        <!-- File View -->
                        <div class="preview-icon-wrapper">
                            @if(str_contains($file->mime_type, 'image'))
                                <i class="ri-image-fill"></i>
                            @elseif(str_contains($file->mime_type, 'pdf'))
                                <i class="ri-file-pdf-fill"></i>
                            @elseif(str_contains($file->mime_type, 'zip') || str_contains($file->mime_type, 'rar'))
                                <i class="ri-file-zip-fill"></i>
                            @else
                                <i class="ri-file-3-fill"></i>
                            @endif
                        </div>

                        <h1 class="share-title">{{ $file->name }}</h1>
                        <p class="share-desc">A file is shared with you</p>

                        <div class="file-meta-grid">
                            <div>
                                <div class="meta-item-label">Size</div>
                                <div class="meta-item-value">{{ number_format($file->size / 1024 / 1024, 2) }} MB</div>
                            </div>
                            <div>
                                <div class="meta-item-label">Type</div>
                                <div class="meta-item-value">{{ $file->mime_type ?? 'Unknown' }}</div>
                            </div>
                        </div>

                        <div class="btn-group">
                            @if($file->share_allow_download)
                                <a href="{{ route('drive.public.share.download', ['token' => $file->share_token]) }}" class="btn btn-primary">
                                    <i class="ri-download-2-line"></i> Download File
                                </a>
                            @endif
                            
                            @if($file->share_allow_direct_access)
                                <a href="{{ route('drive.public.share.download', ['token' => $file->share_token]) }}?direct=1" target="_blank" class="btn btn-outline">
                                    <i class="ri-eye-line"></i> Preview File
                                </a>
                            @endif

                            @if($file->share_allow_import)
                                <form action="{{ route('drive.public.share.import', ['token' => $file->share_token]) }}" method="POST" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-outline">
                                        <i class="ri-folder-add-line"></i> Import to Drive
                                    </button>
                                </form>
                            @endif
                        </div>
                    @endif

                @endif

            </div>

        </div>
    </div>

    <footer>
        <p>&copy; {{ date('Y') }} WorkDrive. Premium File Management System.</p>
    </footer>

    @if(auth()->check() && isset($file) && $file->share_allow_import)
    <script>
        function importSubfile(subfileId) {
            fetch(`/s/{{ $file->share_token }}/file/${subfileId}/import`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                } else {
                    alert('Failed to import file.');
                }
            })
            .catch(err => alert('Error importing item.'));
        }
    </script>
    @endif
</body>
</html>
