@props(['id' => null, 'name' => null, 'placeholder' => 'Enter password', 'required' => false, 'class' => '', 'value' => ''])

<div class="password-field-wrapper position-relative">
    <input
        type="password"
        id="{{ $id }}"
        name="{{ $name }}"
        placeholder="{{ $placeholder }}"
        {{ $required ? 'required' : '' }}
        class="form-control {{ $class }}"
        value="{{ $value }}"
        {{ $attributes }}
    >
    <button type="button" class="password-toggle-btn" aria-label="Show password" title="Toggle password visibility">
        <i class="fas fa-eye"></i>
    </button>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const passwordField = document.getElementById('{{ $id }}');
    const toggleBtn = passwordField?.parentElement?.querySelector('.password-toggle-btn');

    if (passwordField && toggleBtn) {
        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);

            const icon = toggleBtn.querySelector('i');
            if (icon) {
                icon.className = type === 'text' ? 'fas fa-eye-slash' : 'fas fa-eye';
            }

            toggleBtn.setAttribute('aria-label', type === 'text' ? 'Hide password' : 'Show password');
            toggleBtn.setAttribute('title', type === 'text' ? 'Hide password' : 'Show password');
        });
    }
});
</script>
