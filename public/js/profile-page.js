(function () {
    function openProfileEditModal() {
        document.getElementById('profile-edit-overlay')?.classList.add('active');
        document.getElementById('profile-edit-modal')?.classList.add('active');
        document.getElementById('profile-edit-name')?.focus();
    }

    function closeProfileEditModal() {
        document.getElementById('profile-edit-overlay')?.classList.remove('active');
        document.getElementById('profile-edit-modal')?.classList.remove('active');
    }

    async function submitProfileEditForm(event) {
        event.preventDefault();

        const form = document.getElementById('profile-edit-form');
        const submitButton = document.getElementById('profile-edit-submit');
        if (!form || !submitButton) {
            return;
        }

        const originalLabel = submitButton.textContent;
        submitButton.disabled = true;
        submitButton.textContent = 'Saving...';

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                body: new FormData(form),
            });

            const data = await response.json();

            if (!response.ok) {
                if (data?.errors && typeof data.errors === 'object') {
                    const messages = Object.values(data.errors).flat().map(String);
                    globalThis.show_popup_temp?.('error', 'Validation Error', messages);
                } else {
                    globalThis.show_popup_temp?.('error', 'Error', [data?.message || 'Failed to update profile']);
                }

                return;
            }

            globalThis.show_popup_temp?.('success', 'Success', [data?.message || 'Profile updated']);
            closeProfileEditModal();
            globalThis.location.reload();
        } catch (error) {
            console.error(error);
            globalThis.show_popup_temp?.('error', 'Error', ['Failed to update profile']);
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = originalLabel;
        }
    }

    function bindProfileModalEvents() {
        const form = document.getElementById('profile-edit-form');
        if (!form) {
            return;
        }

        if (form.dataset.profileBound === 'true') {
            return;
        }
        form.dataset.profileBound = 'true';

        form.addEventListener('submit', submitProfileEditForm);

        const passwordToggle = form.querySelector('[data-profile-password-toggle]');
        if (passwordToggle) {
            passwordToggle.addEventListener('change', () => {
                const showPasswords = Boolean(passwordToggle.checked);
                const passwordInputs = form.querySelectorAll('[data-profile-password-input]');
                passwordInputs.forEach((input) => {
                    input.type = showPasswords ? 'text' : 'password';
                });
            });
        }
    }

    function initProfilePage() {
        bindProfileModalEvents();
    }

    globalThis.openProfileEditModal = openProfileEditModal;
    globalThis.closeProfileEditModal = closeProfileEditModal;
    globalThis.initProfilePage = initProfilePage;

    document.addEventListener('DOMContentLoaded', initProfilePage);
})();