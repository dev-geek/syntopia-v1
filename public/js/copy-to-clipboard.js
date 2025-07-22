/**
 * Copy to Clipboard Utility with SWAL Notifications
 * This utility provides a consistent way to copy text to clipboard across the application
 * using SweetAlert2 for user feedback.
 */

// Global copy to clipboard function
function copyToClipboard(text, options = {}) {
    const defaultOptions = {
        successTitle: 'Copied!',
        successText: 'Text copied to clipboard successfully!',
        errorTitle: 'Error',
        errorText: 'Failed to copy text. Please try again.',
        showSuccessIcon: true,
        showErrorIcon: true,
        timer: 2000,
        timerProgressBar: true,
        toast: false,
        position: 'top-end'
    };

    const config = { ...defaultOptions, ...options };

    // Modern clipboard API
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text)
            .then(() => {
                showCopySuccess(config);
            })
            .catch(err => {
                console.error('Failed to copy text: ', err);
                showCopyError(config);
            });
    } else {
        // Fallback for older browsers or non-secure contexts
        fallbackCopyToClipboard(text, config);
    }
}

// Copy from element by ID
function copyElementToClipboard(elementId, options = {}) {
    const element = document.getElementById(elementId);
    if (!element) {
        showCopyError({
            ...options,
            errorText: 'Element not found. Please try again.'
        });
        return;
    }

    let textToCopy = '';

    // Handle different element types
    if (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA') {
        textToCopy = element.value;
    } else {
        textToCopy = element.textContent || element.innerText;
    }

    if (!textToCopy.trim()) {
        showCopyError({
            ...options,
            errorText: 'No text to copy. Please try again.'
        });
        return;
    }

    copyToClipboard(textToCopy, options);
}

// Copy from element by selector
function copySelectorToClipboard(selector, options = {}) {
    const element = document.querySelector(selector);
    if (!element) {
        showCopyError({
            ...options,
            errorText: 'Element not found. Please try again.'
        });
        return;
    }

    let textToCopy = '';

    if (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA') {
        textToCopy = element.value;
    } else {
        textToCopy = element.textContent || element.innerText;
    }

    if (!textToCopy.trim()) {
        showCopyError({
            ...options,
            errorText: 'No text to copy. Please try again.'
        });
        return;
    }

    copyToClipboard(textToCopy, options);
}

// Fallback copy method for older browsers
function fallbackCopyToClipboard(text, config) {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.left = '-999999px';
    textArea.style.top = '-999999px';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();

    try {
        const successful = document.execCommand('copy');
        document.body.removeChild(textArea);

        if (successful) {
            showCopySuccess(config);
        } else {
            showCopyError(config);
        }
    } catch (err) {
        document.body.removeChild(textArea);
        console.error('Fallback copy failed: ', err);
        showCopyError(config);
    }
}

// Show success notification
function showCopySuccess(config) {
    if (config.toast) {
        Swal.fire({
            icon: config.showSuccessIcon ? 'success' : undefined,
            title: config.successTitle,
            text: config.successText,
            timer: config.timer,
            timerProgressBar: config.timerProgressBar,
            toast: true,
            position: config.position,
            showConfirmButton: false
        });
    } else {
        Swal.fire({
            icon: config.showSuccessIcon ? 'success' : undefined,
            title: config.successTitle,
            text: config.successText,
            timer: config.timer,
            timerProgressBar: config.timerProgressBar,
            showConfirmButton: false
        });
    }
}

// Show error notification
function showCopyError(config) {
    if (config.toast) {
        Swal.fire({
            icon: config.showErrorIcon ? 'error' : undefined,
            title: config.errorTitle,
            text: config.errorText,
            timer: config.timer,
            timerProgressBar: config.timerProgressBar,
            toast: true,
            position: config.position,
            showConfirmButton: false
        });
    } else {
        Swal.fire({
            icon: config.showErrorIcon ? 'error' : undefined,
            title: config.errorTitle,
            text: config.errorText,
            timer: config.timer,
            timerProgressBar: config.timerProgressBar,
            showConfirmButton: false
        });
    }
}

// Auto-initialize copy buttons on page load
document.addEventListener('DOMContentLoaded', function() {
    // Find all elements with data-copy attribute
    const copyButtons = document.querySelectorAll('[data-copy]');

    copyButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();

            const copyType = this.getAttribute('data-copy');
            const copyValue = this.getAttribute('data-copy-value');
            const copyElement = this.getAttribute('data-copy-element');
            const copySelector = this.getAttribute('data-copy-selector');

            // Custom options from data attributes
            const options = {
                successTitle: this.getAttribute('data-success-title') || 'Copied!',
                successText: this.getAttribute('data-success-text') || 'Text copied to clipboard successfully!',
                errorTitle: this.getAttribute('data-error-title') || 'Error',
                errorText: this.getAttribute('data-error-text') || 'Failed to copy text. Please try again.',
                toast: this.getAttribute('data-toast') === 'true',
                timer: parseInt(this.getAttribute('data-timer')) || 2000
            };

            if (copyValue) {
                copyToClipboard(copyValue, options);
            } else if (copyElement) {
                copyElementToClipboard(copyElement, options);
            } else if (copySelector) {
                copySelectorToClipboard(copySelector, options);
            } else if (copyType === 'text') {
                const text = this.getAttribute('data-text') || this.textContent;
                copyToClipboard(text, options);
            }
        });
    });
});

// Export functions for global use
window.copyToClipboard = copyToClipboard;
window.copyElementToClipboard = copyElementToClipboard;
window.copySelectorToClipboard = copySelectorToClipboard;
