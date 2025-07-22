@if (session('success') || session('error') || session('warning') || session('info') || $errors->any())
    @push('scripts')
        <script src="{{ asset('js/swal-utils.js') }}"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Success message
                @if (session('success'))
                    SwalUtils.showSuccess('{{ session('success') }}');
                @endif

                // Error message
                @if (session('error'))
                    SwalUtils.showError('{{ session('error') }}');
                @endif

                // Warning message
                @if (session('warning'))
                    SwalUtils.showWarning('{{ session('warning') }}');
                @endif

                // Info message
                @if (session('info'))
                    SwalUtils.showInfo('{{ session('info') }}');
                @endif

                // Validation errors
                @if ($errors->any())
                    SwalUtils.showValidationErrors([
                        @foreach ($errors->all() as $error)
                            '{{ $error }}'@if (!$loop->last),@endif
                        @endforeach
                    ]);
                @endif
            });
        </script>
    @endpush
@endif
