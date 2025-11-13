@if (session('success') || session('error') || session('warning') || session('info') || session('swal_error') || $errors->any())
    <script>
        console.log('Alert messages component loaded');
        console.log('Session success:', @json(session('success')));
        console.log('Session error:', @json(session('error')));
        console.log('Session warning:', @json(session('warning')));
        console.log('Session info:', @json(session('info')));

        document.addEventListener('DOMContentLoaded', function() {
                console.log('DOM loaded, checking for session messages');
                // Success message
                @if (session('success'))
                    console.log('Success message found:', @json(session('success')));
                    @if (str_contains(session('success'), 'Subscription') && (str_contains(session('success'), 'bought successfully') || str_contains(session('success'), 'upgraded successfully') || str_contains(session('success'), 'active')))
                        console.log('Subscription success detected, showing SWAL');
                        Swal.fire({
                            icon: 'success',
                            title: 'Subscription Successful!',
                            html: `
                                <div style="text-align: center;">
                                    <p style="font-size: 18px; margin-bottom: 15px;">{{ session('success') }}</p>
                                </div>
                            `,
                            confirmButtonText: 'Ok',
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
                    @elseif (str_contains(session('success'), 'Subscription') && str_contains(session('success'), 'cancelled successfully'))
                        Swal.fire({
                            icon: 'info',
                            title: 'Subscription Cancelled',
                            html: `
                                <div style="text-align: center;">
                                    <p style="font-size: 18px; margin-bottom: 15px;">{{ session('success') }}</p>
                                </div>
                            `,
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#17a2b8',
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
                        console.log('Regular success message, using SwalUtils');
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

                // Registration error (specific key)
                @if ($errors->has('registration_error'))
                    SwalUtils.showError('{{ $errors->first('registration_error') }}');
                @endif

                // Validation errors
                @if ($errors->any() && !$errors->has('registration_error'))
                    SwalUtils.showValidationErrors([
                        @foreach ($errors->all() as $error)
                            '{{ $error }}'@if (!$loop->last),@endif
                        @endforeach
                    ]);
                @endif
            });
        </script>
@endif
