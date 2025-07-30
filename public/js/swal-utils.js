// SWAL Utilities for Syntopia Application
// Centralized SweetAlert2 functions for consistent messaging across the application

// Prevent multiple inclusions
if (typeof window.SwalUtils !== 'undefined') {
    console.warn('SwalUtils already loaded, skipping initialization');
} else {
    // Global SWAL configuration
    const SWAL_CONFIG = {
    confirmButtonColor: '#28a745',
    cancelButtonColor: '#dc3545',
    confirmButtonText: 'OK',
    cancelButtonText: 'Cancel',
    allowOutsideClick: false,
    allowEscapeKey: false,
    timer: null,
    timerProgressBar: false
};

// Success message
function showSuccess(message, title = 'Success!', options = {}) {
    return Swal.fire({
        icon: 'success',
        title: title,
        text: message,
        confirmButtonText: SWAL_CONFIG.confirmButtonText,
        confirmButtonColor: SWAL_CONFIG.confirmButtonColor,
        allowOutsideClick: SWAL_CONFIG.allowOutsideClick,
        allowEscapeKey: SWAL_CONFIG.allowEscapeKey,
        ...options
    });
}

// Error message
function showError(message, title = 'Error!', options = {}) {
    return Swal.fire({
        icon: 'error',
        title: title,
        text: message,
        confirmButtonText: SWAL_CONFIG.confirmButtonText,
        confirmButtonColor: SWAL_CONFIG.cancelButtonColor,
        allowOutsideClick: SWAL_CONFIG.allowOutsideClick,
        allowEscapeKey: SWAL_CONFIG.allowEscapeKey,
        ...options
    });
}

// Warning message
function showWarning(message, title = 'Warning!', options = {}) {
    return Swal.fire({
        icon: 'warning',
        title: title,
        text: message,
        confirmButtonText: SWAL_CONFIG.confirmButtonText,
        confirmButtonColor: '#ffc107',
        allowOutsideClick: SWAL_CONFIG.allowOutsideClick,
        allowEscapeKey: SWAL_CONFIG.allowEscapeKey,
        ...options
    });
}

// Info message
function showInfo(message, title = 'Information', options = {}) {
    return Swal.fire({
        icon: 'info',
        title: title,
        text: message,
        confirmButtonText: SWAL_CONFIG.confirmButtonText,
        confirmButtonColor: '#17a2b8',
        allowOutsideClick: SWAL_CONFIG.allowOutsideClick,
        allowEscapeKey: SWAL_CONFIG.allowEscapeKey,
        ...options
    });
}

// Question/Confirmation dialog
function showConfirm(message, title = 'Are you sure?', options = {}) {
    return Swal.fire({
        icon: 'question',
        title: title,
        text: message,
        showCancelButton: true,
        confirmButtonText: SWAL_CONFIG.confirmButtonText,
        cancelButtonText: SWAL_CONFIG.cancelButtonText,
        confirmButtonColor: SWAL_CONFIG.confirmButtonColor,
        cancelButtonColor: SWAL_CONFIG.cancelButtonColor,
        allowOutsideClick: SWAL_CONFIG.allowOutsideClick,
        allowEscapeKey: SWAL_CONFIG.allowEscapeKey,
        ...options
    });
}

// Delete confirmation dialog
function showDeleteConfirm(message = 'This action cannot be undone!', title = 'Are you sure?', options = {}) {
    return Swal.fire({
        icon: 'warning',
        title: title,
        text: message,
        showCancelButton: true,
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: SWAL_CONFIG.cancelButtonText,
        confirmButtonColor: SWAL_CONFIG.cancelButtonColor,
        cancelButtonColor: '#6c757d',
        allowOutsideClick: SWAL_CONFIG.allowOutsideClick,
        allowEscapeKey: SWAL_CONFIG.allowEscapeKey,
        ...options
    });
}

// Loading dialog
function showLoading(message = 'Please wait...', title = 'Loading') {
    return Swal.fire({
        title: title,
        text: message,
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        willOpen: () => {
            Swal.showLoading();
        }
    });
}

// Toast notification (auto-dismiss)
function showToast(message, type = 'success', duration = 3000) {
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: duration,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });

    return Toast.fire({
        icon: type,
        title: message
    });
}

// Validation errors display
function showValidationErrors(errors, title = 'Validation Errors') {
    let errorList = '';
    if (Array.isArray(errors)) {
        errorList = errors.map(error => `<li>${error}</li>`).join('');
    } else if (typeof errors === 'object') {
        errorList = Object.values(errors).flat().map(error => `<li>${error}</li>`).join('');
    } else {
        errorList = `<li>${errors}</li>`;
    }

    return Swal.fire({
        icon: 'error',
        title: title,
        html: `<ul style="text-align: left; padding-left: 20px; margin: 0;">${errorList}</ul>`,
        confirmButtonText: SWAL_CONFIG.confirmButtonText,
        confirmButtonColor: SWAL_CONFIG.cancelButtonColor,
        allowOutsideClick: SWAL_CONFIG.allowOutsideClick,
        allowEscapeKey: SWAL_CONFIG.allowEscapeKey
    });
}

// AJAX error handler
function handleAjaxError(xhr, status, error) {
    let message = 'An error occurred while processing your request.';

    if (xhr.responseJSON && xhr.responseJSON.message) {
        message = xhr.responseJSON.message;
    } else if (xhr.responseText) {
        try {
            const response = JSON.parse(xhr.responseText);
            if (response.message) {
                message = response.message;
            }
        } catch (e) {
            // If response is not JSON, use status text
            message = xhr.statusText || message;
        }
    }

    showError(message);
}

// Form submission handler with loading state
function handleFormSubmit(formElement, successCallback = null, errorCallback = null) {
    const form = typeof formElement === 'string' ? document.querySelector(formElement) : formElement;

    if (!form) {
        console.error('Form element not found');
        return;
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        const loadingDialog = showLoading('Submitting form...');

        // Get form data
        const formData = new FormData(form);
        const url = form.action;
        const method = form.method.toUpperCase();

        // Prepare fetch options
        const options = {
            method: method,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Accept': 'application/json',
            }
        };

        // Add body for POST/PUT/PATCH requests
        if (method !== 'GET') {
            options.body = formData;
        }

        // Make the request
        fetch(url, options)
            .then(response => response.json())
            .then(data => {
                loadingDialog.close();

                if (data.success) {
                    showSuccess(data.message || 'Operation completed successfully!');
                    if (successCallback) successCallback(data);
                } else {
                    showError(data.message || 'Operation failed!');
                    if (errorCallback) errorCallback(data);
                }
            })
            .catch(error => {
                loadingDialog.close();
                console.error('Error:', error);
                showError('An error occurred while processing your request.');
                if (errorCallback) errorCallback(error);
            });
    });
}

// Auto-initialize session messages
document.addEventListener('DOMContentLoaded', function() {
    // Check for session messages
    const successMessage = document.querySelector('[data-session-success]')?.getAttribute('data-session-success');
    const errorMessage = document.querySelector('[data-session-error]')?.getAttribute('data-session-error');
    const warningMessage = document.querySelector('[data-session-warning]')?.getAttribute('data-session-warning');
    const infoMessage = document.querySelector('[data-session-info]')?.getAttribute('data-session-info');

    if (successMessage) showSuccess(successMessage);
    if (errorMessage) showError(errorMessage);
    if (warningMessage) showWarning(warningMessage);
    if (infoMessage) showInfo(infoMessage);
});

    // Export functions for global use
    window.SwalUtils = {
        showSuccess,
        showError,
        showWarning,
        showInfo,
        showConfirm,
        showDeleteConfirm,
        showLoading,
        showToast,
        showValidationErrors,
        handleAjaxError,
        handleFormSubmit
    };
}
