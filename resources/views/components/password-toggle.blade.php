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

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const passwordField = document.getElementById('{{ $id }}');
    if (passwordField) {
        PasswordToggle.addToField(passwordField);
    }
});
</script>
@endpush
