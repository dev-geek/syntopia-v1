// Password Toggle Functionality
document.addEventListener('DOMContentLoaded', function() {
    // Find all password fields
    const passwordFields = document.querySelectorAll('input[type="password"]');

    passwordFields.forEach(function(passwordField) {
        // Create wrapper div if it doesn't exist
        let wrapper = passwordField.parentElement;
        if (!wrapper.classList.contains('password-field-wrapper')) {
            wrapper = document.createElement('div');
            wrapper.className = 'password-field-wrapper position-relative';
            passwordField.parentNode.insertBefore(wrapper, passwordField);
            wrapper.appendChild(passwordField);
        }

        // Create toggle button
        const toggleBtn = document.createElement('button');
        toggleBtn.type = 'button';
        toggleBtn.className = 'password-toggle-btn';
        toggleBtn.innerHTML = '<i class="fas fa-eye"></i>';
        toggleBtn.setAttribute('aria-label', 'Toggle password visibility');

        // Add toggle button to wrapper
        wrapper.appendChild(toggleBtn);

        // Add click event
        toggleBtn.addEventListener('click', function() {
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);

            // Update icon
            const icon = toggleBtn.querySelector('i');
            if (type === 'text') {
                icon.className = 'fas fa-eye-slash';
                toggleBtn.setAttribute('aria-label', 'Hide password');
            } else {
                icon.className = 'fas fa-eye';
                toggleBtn.setAttribute('aria-label', 'Show password');
            }
        });
    });
});
