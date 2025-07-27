/**
 * Syntopia Auto-Login Integration Script
 * Add this script to https://live.syntopia.ai/login to enable auto-fill functionality
 */

(function() {
    'use strict';

    // Configuration
    const CONFIG = {
        API_BASE_URL: 'https://syntopia-main.test',
        TOKEN_PARAM: 'token',
        FIELD_SELECTORS: {
            email: 'input[type="email"], input[name="email"], #email, #login-email',
            password: 'input[type="password"], input[name="password"], #password, #login-password'
        }
    };

    // Utility functions
    function getUrlParameter(name) {
        name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
        var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
        var results = regex.exec(location.search);
        return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
    }

    function showMessage(message, type = 'info') {
        // Create a simple notification
        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            font-family: Arial, sans-serif;
            font-size: 14px;
            font-weight: 500;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            max-width: 400px;
            word-wrap: break-word;
        `;

        // Set background color based on type
        switch(type) {
            case 'success':
                notification.style.background = '#4caf50';
                break;
            case 'error':
                notification.style.background = '#f44336';
                break;
            case 'warning':
                notification.style.background = '#ff9800';
                break;
            default:
                notification.style.background = '#2196f3';
        }

        notification.textContent = message;
        document.body.appendChild(notification);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }

    function clearTokenFromUrl() {
        const url = new URL(window.location);
        url.searchParams.delete(CONFIG.TOKEN_PARAM);
        window.history.replaceState({}, document.title, url.toString());
    }

    function findFormFields() {
        const emailField = document.querySelector(CONFIG.FIELD_SELECTORS.email);
        const passwordField = document.querySelector(CONFIG.FIELD_SELECTORS.password);

        return { emailField, passwordField };
    }

    function fillFormFields(credentials) {
        const { emailField, passwordField } = findFormFields();

        if (emailField && credentials.email) {
            emailField.value = credentials.email;
            // Trigger events
            emailField.dispatchEvent(new Event('input', { bubbles: true }));
            emailField.dispatchEvent(new Event('change', { bubbles: true }));
        }

        if (passwordField && credentials.password) {
            passwordField.value = credentials.password;
            // Trigger events
            passwordField.dispatchEvent(new Event('input', { bubbles: true }));
            passwordField.dispatchEvent(new Event('change', { bubbles: true }));
        }

        return { emailField, passwordField };
    }

    async function decryptToken(encryptedToken) {
        try {
            const response = await fetch(`${CONFIG.API_BASE_URL}/api/token/decrypt`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ token: encryptedToken })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (data.success && data.credentials) {
                return data.credentials;
            } else {
                throw new Error(data.error || 'Failed to decrypt token');
            }
        } catch (error) {
            console.error('Token decryption error:', error);
            throw error;
        }
    }

    async function handleAutoLogin() {
        const token = getUrlParameter(CONFIG.TOKEN_PARAM);

        if (!token) {
            return; // No token, exit silently
        }

        try {
            showMessage('Setting up your login credentials...', 'info');

            // Decrypt token and get credentials
            const credentials = await decryptToken(token);

            if (credentials) {
                // Fill the form
                const { emailField, passwordField } = fillFormFields(credentials);

                if (emailField && passwordField) {
                    showMessage('Login credentials have been pre-filled!', 'success');
                } else {
                    showMessage('Could not find login form fields. Please fill manually.', 'warning');
                }

                // Clear token from URL for security
                clearTokenFromUrl();
            } else {
                showMessage('Unable to retrieve login credentials. Please login manually.', 'error');
            }
        } catch (error) {
            console.error('Auto-login error:', error);
            showMessage('Auto-login failed. Please login manually.', 'error');
        }
    }

    // Initialize when DOM is ready
    function init() {
        // Wait a bit for the page to fully load
        setTimeout(handleAutoLogin, 500);
    }

    // Start the auto-login process
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Export for external use
    window.SyntopiaAutoLogin = {
        handleAutoLogin,
        fillFormFields,
        showMessage
    };

})();
