javascript:(function(){
    // Auto-login bookmarklet for testing
    const CONFIG = {
        API_BASE_URL: 'https://syntopia-main.test',
        TOKEN_PARAM: 'token'
    };

    function getUrlParameter(name) {
        name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
        var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
        var results = regex.exec(location.search);
        return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
    }

    function showMessage(message, type = 'info') {
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

        switch(type) {
            case 'success': notification.style.background = '#4caf50'; break;
            case 'error': notification.style.background = '#f44336'; break;
            case 'warning': notification.style.background = '#ff9800'; break;
            default: notification.style.background = '#2196f3';
        }

        notification.textContent = message;
        document.body.appendChild(notification);

        setTimeout(() => {
            if (notification.parentNode) notification.remove();
        }, 5000);
    }

    function findFormFields() {
        const selectors = [
            'input[type="email"]',
            'input[name="email"]',
            '#email',
            '#login-email',
            'input[placeholder*="email" i]',
            'input[placeholder*="Email" i]'
        ];

        const passwordSelectors = [
            'input[type="password"]',
            'input[name="password"]',
            '#password',
            '#login-password',
            'input[placeholder*="password" i]',
            'input[placeholder*="Password" i]'
        ];

        let emailField = null;
        let passwordField = null;

        for (let selector of selectors) {
            emailField = document.querySelector(selector);
            if (emailField) break;
        }

        for (let selector of passwordSelectors) {
            passwordField = document.querySelector(selector);
            if (passwordField) break;
        }

        return { emailField, passwordField };
    }

    function fillFormFields(credentials) {
        const { emailField, passwordField } = findFormFields();

        if (emailField && credentials.email) {
            emailField.value = credentials.email;
            emailField.dispatchEvent(new Event('input', { bubbles: true }));
            emailField.dispatchEvent(new Event('change', { bubbles: true }));
        }

        if (passwordField && credentials.password) {
            passwordField.value = credentials.password;
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
            showMessage('No token found in URL', 'warning');
            return;
        }

        try {
            showMessage('Decrypting token and filling form...', 'info');

            const credentials = await decryptToken(token);

            if (credentials) {
                const { emailField, passwordField } = fillFormFields(credentials);

                if (emailField && passwordField) {
                    showMessage('✅ Login credentials filled successfully!', 'success');
                } else {
                    showMessage('⚠️ Could not find form fields. Please check the page structure.', 'warning');
                }
            } else {
                showMessage('❌ Unable to retrieve credentials', 'error');
            }
        } catch (error) {
            console.error('Auto-login error:', error);
            showMessage('❌ Auto-login failed: ' + error.message, 'error');
        }
    }

    // Execute the auto-login
    handleAutoLogin();
})();
