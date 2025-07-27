/**
 * Software Auto Login Script
 * This script handles automatic login credential filling from encrypted tokens
 */

(function() {
    'use strict';

    // Function to get URL parameters
    function getUrlParameter(name) {
        name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
        var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
        var results = regex.exec(location.search);
        return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
    }

    // Function to decrypt token (this would need to be implemented on the server side)
    async function decryptToken(encryptedToken) {
        try {
            // Use the main dashboard domain for API calls
            const apiBaseUrl = 'https://syntopia-main.test';
            const response = await fetch(`${apiBaseUrl}/api/token/decrypt`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({ token: encryptedToken })
            });

            if (!response.ok) {
                throw new Error('Failed to decrypt token');
            }

            const data = await response.json();
            return data.credentials;
        } catch (error) {
            console.error('Error decrypting token:', error);
            return null;
        }
    }

    // Function to auto-fill login form
    function autoFillLoginForm(credentials) {
        const emailField = document.querySelector('input[type="email"], input[name="email"], #email');
        const passwordField = document.querySelector('input[type="password"], input[name="password"], #password');

        if (emailField && credentials.email) {
            emailField.value = credentials.email;
            // Trigger change event
            emailField.dispatchEvent(new Event('input', { bubbles: true }));
            emailField.dispatchEvent(new Event('change', { bubbles: true }));
        }

        if (passwordField && credentials.password) {
            passwordField.value = credentials.password;
            // Trigger change event
            passwordField.dispatchEvent(new Event('input', { bubbles: true }));
            passwordField.dispatchEvent(new Event('change', { bubbles: true }));
        }

        // Show success message
        showAutoFillMessage('Login credentials have been pre-filled for you!', 'success');
    }

    // Function to show auto-fill message
    function showAutoFillMessage(message, type = 'info') {
        const messageDiv = document.createElement('div');
        messageDiv.className = `alert alert-${type} alert-dismissible fade show`;
        messageDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 400px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border-radius: 8px;
        `;

        messageDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;

        document.body.appendChild(messageDiv);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (messageDiv.parentNode) {
                messageDiv.remove();
            }
        }, 5000);
    }

    // Function to clear token from URL
    function clearTokenFromUrl() {
        const url = new URL(window.location);
        url.searchParams.delete('token');
        window.history.replaceState({}, document.title, url.toString());
    }

    // Main function to handle auto-login
    async function handleAutoLogin() {
        const token = getUrlParameter('token');

        if (!token) {
            return; // No token, exit
        }

        try {
            // Show loading message
            showAutoFillMessage('Setting up your login credentials...', 'info');

            // Decrypt token and get credentials
            const credentials = await decryptToken(token);

            if (credentials) {
                // Auto-fill the form
                autoFillLoginForm(credentials);

                // Clear token from URL for security
                clearTokenFromUrl();
            } else {
                showAutoFillMessage('Unable to retrieve login credentials. Please login manually.', 'warning');
            }
        } catch (error) {
            console.error('Auto-login error:', error);
            showAutoFillMessage('Auto-login failed. Please login manually.', 'danger');
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', handleAutoLogin);
    } else {
        handleAutoLogin();
    }

    // Alternative: Simple client-side token handling (less secure, for demo purposes)
    function handleSimpleToken() {
        const token = getUrlParameter('token');

        if (!token) {
            return;
        }

        try {
            // This is a simplified version - in production, this should be handled server-side
            // For demo purposes, we'll show a message about the token
            showAutoFillMessage('Login token detected. Please contact support for proper integration.', 'info');

            // Clear token from URL
            clearTokenFromUrl();
        } catch (error) {
            console.error('Token handling error:', error);
        }
    }

    // Export functions for external use
    window.SoftwareAutoLogin = {
        handleAutoLogin,
        handleSimpleToken,
        autoFillLoginForm,
        showAutoFillMessage
    };

})();
