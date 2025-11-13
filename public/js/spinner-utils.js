/**
 * Universal Spinner Utility
 * Provides easy-to-use functions for showing/hiding loading spinners across the application
 */

(function() {
    'use strict';

    // Get spinner elements
    let spinnerOverlay = null;
    let spinnerText = null;

    // Initialize spinner elements
    function initSpinner() {
        if (!spinnerOverlay) {
            spinnerOverlay = document.getElementById('spinnerOverlay');
            spinnerText = document.getElementById('spinnerText');
        }
        return spinnerOverlay !== null;
    }

    /**
     * Show the spinner overlay with optional message
     * @param {string} message - Message to display (default: 'Processing...')
     */
    function showSpinner(message = 'Processing...') {
        if (!initSpinner()) {
            console.warn('Spinner overlay not found. Make sure to include the spinner-overlay component.');
            return;
        }

        if (spinnerText) {
            spinnerText.textContent = message;
        }

        if (spinnerOverlay) {
            spinnerOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }

    /**
     * Hide the spinner overlay
     */
    function hideSpinner() {
        if (!initSpinner()) {
            return;
        }

        if (spinnerOverlay) {
            spinnerOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }
    }

    /**
     * Show button spinner and disable button
     * @param {HTMLElement|string} button - Button element or selector
     * @param {string} loadingText - Text to show while loading (optional)
     */
    function showButtonSpinner(button, loadingText = null) {
        const btn = typeof button === 'string' ? document.querySelector(button) : button;
        if (!btn) return;

        const originalText = btn.textContent.trim();
        const spinnerId = btn.id ? `${btn.id}Spinner` : `spinner_${Date.now()}`;

        // Check if spinner already exists
        let spinner = btn.querySelector('.button-spinner');
        if (!spinner) {
            spinner = document.createElement('span');
            spinner.className = 'button-spinner';
            spinner.id = spinnerId;
            spinner.innerHTML = `
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" stroke-opacity="0.25"/>
                    <path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                </svg>
            `;
        }

        // Store original text if not already stored
        if (!btn.dataset.originalText) {
            btn.dataset.originalText = originalText;
        }

        // Update button text if loading text provided
        if (loadingText) {
            const textSpan = btn.querySelector('span:not(.button-spinner)') || btn.firstChild;
            if (textSpan && textSpan.nodeType === Node.TEXT_NODE) {
                textSpan.textContent = loadingText;
            } else if (textSpan && textSpan.tagName === 'SPAN') {
                textSpan.textContent = loadingText;
            } else {
                btn.insertBefore(document.createTextNode(loadingText), spinner);
            }
        }

        spinner.classList.add('active');
        if (!spinner.parentNode) {
            btn.appendChild(spinner);
        }

        btn.disabled = true;
        btn.setAttribute('data-loading', 'true');
        btn.classList.add('btn-loading');
    }

    /**
     * Hide button spinner and re-enable button
     * @param {HTMLElement|string} button - Button element or selector
     */
    function hideButtonSpinner(button) {
        const btn = typeof button === 'string' ? document.querySelector(button) : button;
        if (!btn) return;

        const spinner = btn.querySelector('.button-spinner');
        if (spinner) {
            spinner.classList.remove('active');
        }

        // Restore original text
        if (btn.dataset.originalText) {
            const textSpan = btn.querySelector('span:not(.button-spinner)') || btn.firstChild;
            if (textSpan && textSpan.nodeType === Node.TEXT_NODE) {
                textSpan.textContent = btn.dataset.originalText;
            } else if (textSpan && textSpan.tagName === 'SPAN') {
                textSpan.textContent = btn.dataset.originalText;
            }
        }

        btn.disabled = false;
        btn.removeAttribute('data-loading');
        btn.classList.remove('btn-loading');
    }

    /**
     * Auto-attach spinner to form submissions
     * @param {HTMLElement|string} form - Form element or selector
     * @param {string} message - Message to show (optional)
     * @param {string} buttonSelector - Specific button selector to show spinner on (optional)
     */
    function attachFormSpinner(form, message = null, buttonSelector = null) {
        const formEl = typeof form === 'string' ? document.querySelector(form) : form;
        if (!formEl) return;

        formEl.addEventListener('submit', function(e) {
            const submitButton = buttonSelector
                ? formEl.querySelector(buttonSelector)
                : formEl.querySelector('button[type="submit"]') || formEl.querySelector('input[type="submit"]');

            if (submitButton) {
                const buttonText = submitButton.textContent || submitButton.value || 'Submitting...';
                showButtonSpinner(submitButton, buttonText.includes('...') ? buttonText : buttonText + '...');
            }

            if (message) {
                showSpinner(message);
            } else {
                const formMessage = formEl.dataset.spinnerMessage || 'Processing your request...';
                showSpinner(formMessage);
            }
        });
    }

    /**
     * Auto-attach spinner to all forms on page load
     */
    function attachAllFormSpinners() {
        document.querySelectorAll('form[data-spinner]').forEach(form => {
            const message = form.dataset.spinnerMessage || null;
            const buttonSelector = form.dataset.spinnerButton || null;
            attachFormSpinner(form, message, buttonSelector);
        });
    }

    // Auto-hide spinner on page load (in case of validation errors or redirects)
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            hideSpinner();
            attachAllFormSpinners();
        });
    } else {
        hideSpinner();
        attachAllFormSpinners();
    }

    // Hide spinner on page unload
    window.addEventListener('beforeunload', hideSpinner);

    // Hide spinner on visibility change (user switches tabs)
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            hideSpinner();
        }
    });

    // Export to global scope
    window.SpinnerUtils = {
        show: showSpinner,
        hide: hideSpinner,
        showButton: showButtonSpinner,
        hideButton: hideButtonSpinner,
        attachForm: attachFormSpinner,
        attachAll: attachAllFormSpinners
    };

})();

