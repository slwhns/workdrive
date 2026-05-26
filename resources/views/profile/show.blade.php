@extends('layouts.app')

@section('title', 'Profile Settings')

@section('content')
@php
    $user = auth()->user();
    $displayName = strtoupper((string) ($user?->name ?? 'USER'));
    $avatarInitial = strtoupper(substr((string) ($user?->name ?? 'U'), 0, 1));
    $profilePhotoUrl = $user?->profile_photo_path ? \Illuminate\Support\Facades\Storage::url($user->profile_photo_path) : null;
    $employeeId = 'WD-' . str_pad((string) ($user?->id ?? 0), 4, '0', STR_PAD_LEFT);
@endphp

<div class="dash-title-wrap mg-b-20">
    <div class="d-flex fd-column ai-center jc-center gap-8 txt-center">
        <div class="d-flex ai-center gap-10 jc-center">
            <span class="dash-greeting-emoji"><i class="ri-user-3-line"></i></span>
            <div class="dash-greeting-text">Profile Settings</div>
        </div>
        <div class="dash-greeting-sub">Review your account information and update your profile details</div>
    </div>
</div>

<div class="profile-wrap">
    <div class="profile-card">
        <div class="profile-cover">
            <div class="profile-avatar">
                @if($profilePhotoUrl)
                    <img src="{{ $profilePhotoUrl }}" alt="{{ $displayName }} avatar" class="profile-avatar-image">
                @else
                    {{ $avatarInitial }}
                @endif
            </div>
        </div>

        <div class="profile-main">
            <h2 class="profile-name">{{ $displayName }}</h2>

            <div class="profile-grid">
                <div class="profile-label">Employee ID</div>
                <div class="profile-value">{{ $employeeId }}</div>

                <div class="profile-label">Email</div>
                <div class="profile-value">{{ $user?->email ?? '-' }}</div>

                <div class="profile-label">Name</div>
                <div class="profile-value">{{ $user?->name ?? '-' }}</div>

                <div class="profile-label">Phone Number</div>
                <div class="profile-value">{{ $user?->phone ?? '-' }}</div>

                <div class="profile-label">Company</div>
                <div class="profile-value">{{ $user?->company ?? '-' }}</div>

                <div class="profile-label">Member Since</div>
                <div class="profile-value">{{ $user?->created_at?->format('M d, Y') ?? '-' }}</div>
            </div>

            <div class="profile-actions">
                <a href="{{ route('drive.index') }}" class="profile-btn profile-btn-light">Back to Dashboard</a>
                <button type="button" class="profile-btn profile-btn-primary" onclick="openProfileEditModal()">Edit Profile</button>
            </div>
        </div>
    </div>
</div>

<button type="button" id="profile-edit-overlay" class="modal-overlay" onclick="closeProfileEditModal()" aria-label="Close modal"></button>

<div id="profile-edit-modal" class="modal-dialog" style="max-width: 560px;">
    <form id="profile-edit-form" class="modal-content" method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="modal-header">
            <h3>Edit Profile</h3>
            <button type="button" class="modal-close" onclick="closeProfileEditModal()">×</button>
        </div>

        <div class="modal-body">
            <div class="d-grid gap-15">
                <div>
                    <label for="profile-edit-name" class="fs-12 fw-bold mg-b-5 d-block">Name *</label>
                    <input id="profile-edit-name" type="text" name="name" class="pd-10 bdr-all-22 br-5 w-100" value="{{ $user?->name ?? '' }}" required>
                </div>

                <div>
                    <label for="profile-edit-email" class="fs-12 fw-bold mg-b-5 d-block">Email *</label>
                    <input id="profile-edit-email" type="email" name="email" class="pd-10 bdr-all-22 br-5 w-100" value="{{ $user?->email ?? '' }}" required>
                </div>

                <div>
                    <label for="profile-edit-phone" class="fs-12 fw-bold mg-b-5 d-block">Phone Number (Optional)</label>
                    <input id="profile-edit-phone" type="text" name="phone" class="pd-10 bdr-all-22 br-5 w-100" value="{{ $user?->phone ?? '' }}">
                </div>

                <div>
                    <label for="profile-edit-company" class="fs-12 fw-bold mg-b-5 d-block">Company Name (Optional)</label>
                    <input id="profile-edit-company" type="text" name="company" class="pd-10 bdr-all-22 br-5 w-100" value="{{ $user?->company ?? '' }}">
                </div>

                <div>
                    <label for="profile-edit-photo" class="fs-12 fw-bold mg-b-5 d-block">Profile Picture (Optional)</label>
                    <input id="profile-edit-photo" type="file" name="profile_photo" class="pd-10 bdr-all-22 br-5 w-100" accept="image/png,image/jpeg,image/webp">
                    <div class="fs-11 clr-grey1 mg-t-5">Allowed: JPG, PNG, WEBP. Max 2MB.</div>
                </div>

                <div style="border-top: 1px solid #e0e0e0; padding-top: 15px; margin-top: 15px;">
                    <h4 style="font-size: 14px; font-weight: 600; margin-bottom: 15px; color: #1d1d1f;">Change Password (Optional)</h4>
                    
                    <div>
                        <label for="current-password" class="fs-12 fw-bold mg-b-5 d-block">Current Password</label>
                        <div style="position: relative;">
                            <input id="current-password" type="password" name="current_password" class="pd-10 bdr-all-22 br-5 w-100" placeholder="Enter your current password" style="padding-right: 40px;">
                            <button type="button" class="password-toggle-btn" data-target="current-password" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #333333; font-size: 20px; padding: 0; width: 24px; height: 24px;">
                                <i class="ri-eye-line"></i>
                            </button>
                        </div>
                    </div>

                    <div style="margin-top: 12px;">
                        <label for="new-password" class="fs-12 fw-bold mg-b-5 d-block">New Password</label>
                        <div style="position: relative;">
                            <input id="new-password" type="password" name="password" class="pd-10 bdr-all-22 br-5 w-100" placeholder="Enter your new password" style="padding-right: 40px;">
                            <button type="button" class="password-toggle-btn" data-target="new-password" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #333333; font-size: 20px; padding: 0; width: 24px; height: 24px;">
                                <i class="ri-eye-line"></i>
                            </button>
                        </div>
                    </div>

                    <div style="margin-top: 12px;">
                        <label for="confirm-password" class="fs-12 fw-bold mg-b-5 d-block">Confirm New Password</label>
                        <div style="position: relative;">
                            <input id="confirm-password" type="password" name="password_confirmation" class="pd-10 bdr-all-22 br-5 w-100" placeholder="Confirm your new password" style="padding-right: 40px;">
                            <button type="button" class="password-toggle-btn" data-target="confirm-password" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #333333; font-size: 20px; padding: 0; width: 24px; height: 24px;">
                                <i class="ri-eye-line"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeProfileEditModal()">Cancel</button>
            <button type="submit" id="profile-edit-submit" class="btn-primary">Save Changes</button>
        </div>
    </form>
</div>

<script>
function openProfileEditModal() {
    const overlay = document.getElementById('profile-edit-overlay');
    const modal = document.getElementById('profile-edit-modal');
    if (overlay) overlay.classList.add('active');
    if (modal) modal.classList.add('active');
}

function closeProfileEditModal() {
    const overlay = document.getElementById('profile-edit-overlay');
    const modal = document.getElementById('profile-edit-modal');
    if (overlay) overlay.classList.remove('active');
    if (modal) modal.classList.remove('active');
}

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeProfileEditModal();
    }
});

// Password toggle functionality
document.querySelectorAll('.password-toggle-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const targetId = this.getAttribute('data-target');
        const passwordInput = document.getElementById(targetId);
        const icon = this.querySelector('i');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.className = 'ri-eye-off-line';
        } else {
            passwordInput.type = 'password';
            icon.className = 'ri-eye-line';
        }
    });
});
</script>
@endsection
