/**
 * Password Toggle Functionality - Reusable Component
 * Automatically adds password toggle buttons to all password fields
 * Supports both FontAwesome and Bootstrap Icons
 */

class PasswordToggle {
    constructor() {
        this.init();
    }

    init() {
        // Initialize on DOM ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setupPasswordToggles());
        } else {
            this.setupPasswordToggles();
        }

        // Watch for dynamically added password fields
        this.observePasswordFields();
    }

    setupPasswordToggles() {
        const passwordFields = document.querySelectorAll('input[type="password"]');
        passwordFields.forEach(field => this.addToggleButton(field));
    }

    addToggleButton(passwordField) {
        // Skip if already has toggle button
        if (passwordField.parentElement.querySelector('.password-toggle-btn')) {
            return;
        }

        // Create wrapper if it doesn't exist
        let wrapper = passwordField.parentElement;
        if (!wrapper.classList.contains('password-field-wrapper')) {
            wrapper = this.createWrapper(passwordField);
        }

        // Create toggle button
        const toggleBtn = this.createToggleButton();
        wrapper.appendChild(toggleBtn);

        // Add event listeners
        this.addEventListeners(toggleBtn, passwordField);
    }

    createWrapper(passwordField) {
        const wrapper = document.createElement('div');
        wrapper.className = 'password-field-wrapper position-relative';

        // Insert wrapper before password field
        passwordField.parentNode.insertBefore(wrapper, passwordField);
        wrapper.appendChild(passwordField);

        return wrapper;
    }

    createToggleButton() {
        const toggleBtn = document.createElement('button');
        toggleBtn.type = 'button';
        toggleBtn.className = 'password-toggle-btn';
        toggleBtn.setAttribute('aria-label', 'Show password');
        toggleBtn.setAttribute('title', 'Toggle password visibility');

        // Use FontAwesome if available, otherwise use text
        const icon = this.getIconElement('eye');
        toggleBtn.appendChild(icon);

        return toggleBtn;
    }

    getIconElement(type) {
        // Check if FontAwesome is available
        if (typeof FontAwesome !== 'undefined' || document.querySelector('.fa, .fas, .far')) {
            const icon = document.createElement('i');
            icon.className = type === 'eye' ? 'fas fa-eye' : 'fas fa-eye-slash';
            return icon;
        }

        // Fallback to text
        const span = document.createElement('span');
        span.textContent = type === 'eye' ? 'Show' : 'Hide';
        span.style.fontSize = '12px';
        span.style.fontWeight = 'bold';
        span.style.color = '#6c757d';
        return span;
    }

    addEventListeners(toggleBtn, passwordField) {
        // Click event
        toggleBtn.addEventListener('click', (e) => {
            e.preventDefault();
            this.togglePassword(toggleBtn, passwordField);
        });

        // Keyboard support
        toggleBtn.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.togglePassword(toggleBtn, passwordField);
            }
        });

        // Focus management
        passwordField.addEventListener('focus', () => {
            toggleBtn.classList.add('focused');
        });

        passwordField.addEventListener('blur', () => {
            toggleBtn.classList.remove('focused');
        });
    }

    togglePassword(toggleBtn, passwordField) {
        const isPassword = passwordField.getAttribute('type') === 'password';
        const newType = isPassword ? 'text' : 'password';

        // Update input type
        passwordField.setAttribute('type', newType);

        // Update icon
        const icon = toggleBtn.querySelector('i, span');
        if (icon) {
            if (newType === 'text') {
                icon.className = 'fas fa-eye-slash';
                toggleBtn.setAttribute('aria-label', 'Hide password');
                toggleBtn.setAttribute('title', 'Hide password');
            } else {
                icon.className = 'fas fa-eye';
                toggleBtn.setAttribute('aria-label', 'Show password');
                toggleBtn.setAttribute('title', 'Show password');
            }
        }

        // Add animation class
        toggleBtn.classList.add('toggled');
        setTimeout(() => toggleBtn.classList.remove('toggled'), 300);

        // Focus back to password field for better UX
        passwordField.focus();
    }

    observePasswordFields() {
        // Use MutationObserver to watch for dynamically added password fields
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        // Check if the added node is a password field
                        if (node.tagName === 'INPUT' && node.type === 'password') {
                            this.addToggleButton(node);
                        }

                        // Check for password fields within the added node
                        const passwordFields = node.querySelectorAll ? node.querySelectorAll('input[type="password"]') : [];
                        passwordFields.forEach(field => this.addToggleButton(field));
                    }
                });
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    // Public method to manually add toggle to a specific field
    static addToField(passwordField) {
        const instance = new PasswordToggle();
        instance.addToggleButton(passwordField);
    }

    // Public method to refresh all password toggles
    static refresh() {
        const instance = new PasswordToggle();
        instance.setupPasswordToggles();
    }
}

// Initialize password toggle functionality
new PasswordToggle();

// Make it globally available
window.PasswordToggle = PasswordToggle;
