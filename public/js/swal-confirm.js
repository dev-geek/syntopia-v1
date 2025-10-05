/**
 * General SweetAlert Confirmation System
 * Usage: Add data attributes to buttons/forms and call initSwalConfirm()
 */

// Initialize SweetAlert confirmations
function initSwalConfirm() {
    // Toggle status confirmations
    document.querySelectorAll('[data-swal-toggle]').forEach(element => {
        element.addEventListener('click', function(e) {
            e.preventDefault();

            const isActive = this.dataset.isActive === 'true';
            const action = isActive ? 'deactivate' : 'activate';
            const actionCapitalized = isActive ? 'Deactivate' : 'Activate';
            const form = this.closest('form');

            Swal.fire({
                title: `Are you sure you want to ${action} this Sub Admin?`,
                text: `This will ${action} the Sub Admin account and ${isActive ? 'prevent' : 'allow'} them from logging in.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: isActive ? '#6c757d' : '#28a745',
                cancelButtonColor: '#d33',
                confirmButtonText: `Yes, ${actionCapitalized}!`,
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed && form) {
                    form.submit();
                }
            });
        });
    });

    // Delete confirmations
    document.querySelectorAll('[data-swal-delete]').forEach(element => {
        element.addEventListener('click', function(e) {
            e.preventDefault();

            const form = this.closest('form');
            const itemName = this.dataset.itemName || 'item';

            Swal.fire({
                title: `Are you sure you want to delete this ${itemName}?`,
                text: "This action cannot be undone! The item will be permanently removed from the system.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed && form) {
                    form.submit();
                }
            });
        });
    });

    // Custom confirmations
    document.querySelectorAll('[data-swal-confirm]').forEach(element => {
        element.addEventListener('click', function(e) {
            e.preventDefault();

            const title = this.dataset.swalTitle || 'Are you sure?';
            const text = this.dataset.swalText || 'This action cannot be undone.';
            const confirmText = this.dataset.swalConfirmText || 'Yes, do it!';
            const cancelText = this.dataset.swalCancelText || 'Cancel';
            const confirmColor = this.dataset.swalConfirmColor || '#3085d6';
            const cancelColor = this.dataset.swalCancelColor || '#d33';
            const icon = this.dataset.swalIcon || 'warning';
            const form = this.closest('form');
            const url = this.dataset.swalUrl;

            Swal.fire({
                title: title,
                text: text,
                icon: icon,
                showCancelButton: true,
                confirmButtonColor: confirmColor,
                cancelButtonColor: cancelColor,
                confirmButtonText: confirmText,
                cancelButtonText: cancelText
            }).then((result) => {
                if (result.isConfirmed) {
                    if (form) {
                        form.submit();
                    } else if (url) {
                        window.location.href = url;
                    }
                }
            });
        });
    });
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initSwalConfirm();
});
