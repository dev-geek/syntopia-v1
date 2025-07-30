@if (session('success') || session('error') || session('warning') || session('info') || session('swal_error') || $errors->any())
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Success message
                @if (session('success'))
                    @if (str_contains(session('success'), 'Subscription bought successfully'))
                        Swal.fire({
                            icon: 'success',
                            title: '🎉 Subscription Successful!',
                            html: `
                                <div style="text-align: center;">
                                    <p style="font-size: 18px; margin-bottom: 15px;">{{ session('success') }}</p>
                                    <p style="color: #666; font-size: 14px;">Your subscription is now active and ready to use!</p>
                                    <div style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #28a745;">
                                        <p style="margin: 0; font-size: 14px; color: #495057;">
                                            <strong>What's next?</strong><br>
                                            You can now access all the features included in your subscription.
                                        </p>
                                    </div>
                                </div>
                            `,
                            confirmButtonText: 'Continue to Dashboard',
                            confirmButtonColor: '#28a745',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            width: '500px',
                            showClass: {
                                popup: 'animate__animated animate__fadeInDown'
                            },
                            hideClass: {
                                popup: 'animate__animated animate__fadeOutUp'
                            }
                        });
                    @else
                        SwalUtils.showSuccess('{{ session('success') }}');
                    @endif
                @endif

                // Error message
                @if (session('error'))
                    SwalUtils.showError('{{ session('error') }}');
                @endif

                // SWAL Error message
                @if (session('swal_error'))
                    SwalUtils.showError('{{ session('swal_error') }}');
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
