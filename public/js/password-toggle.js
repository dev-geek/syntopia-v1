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

    isRegisterPage() {
        // Check if we're on the register page by looking for specific elements or URL
        return window.location.pathname.includes('/register') ||
               document.querySelector('form[action*="register"]') ||
               document.querySelector('input[name="first_name"]') ||
               document.querySelector('input[name="last_name"]');
    }

    getIconElement(type) {
        // Check if we're on the register page and use SVG icons
        if (this.isRegisterPage()) {
            return this.createSVGIcon(type);
        }

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

    createSVGIcon(type) {
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('width', '16');
        svg.setAttribute('height', '16');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('fill', 'none');
        svg.setAttribute('stroke', 'currentColor');
        svg.setAttribute('stroke-width', '2');
        svg.setAttribute('stroke-linecap', 'round');
        svg.setAttribute('stroke-linejoin', 'round');
        svg.style.transition = 'all 0.2s ease';

        if (type === 'eye') {
            // Eye icon
            svg.innerHTML = `
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                <circle cx="12" cy="12" r="3"></circle>
            `;
        } else {
            // Eye-slash icon
            svg.innerHTML = `
                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                <line x1="1" y1="1" x2="23" y2="23"></line>
            `;
        }

        return svg;
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
        const icon = toggleBtn.querySelector('i, span, svg');
        if (icon) {
            if (this.isRegisterPage()) {
                // Update SVG icon
                const newSvg = this.createSVGIcon(newType === 'text' ? 'eye-slash' : 'eye');
                icon.replaceWith(newSvg);
            } else {
                // Update FontAwesome icon
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
