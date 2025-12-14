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
                        if (typeof SwalUtils !== 'undefined' && SwalUtils.showSuccess) {
                            SwalUtils.showSuccess('{{ session('success') }}');
                        } else {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success',
                                text: '{{ session('success') }}',
                                confirmButtonText: 'OK',
                                confirmButtonColor: '#28a745'
                            });
                        }
                    @endif
                @endif

                // Error message
                @if (session('error'))
                    if (typeof SwalUtils !== 'undefined' && SwalUtils.showError) {
                        SwalUtils.showError('{{ session('error') }}');
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: '{{ session('error') }}',
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#dc3545'
                        });
                    }
                @endif

                // SWAL Error message
                @if (session('swal_error'))
                    if (typeof SwalUtils !== 'undefined' && SwalUtils.showError) {
                        SwalUtils.showError('{{ session('swal_error') }}');
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: '{{ session('swal_error') }}',
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#dc3545'
                        });
                    }
                @endif

                // Warning message
                @if (session('warning'))
                    if (typeof SwalUtils !== 'undefined' && SwalUtils.showWarning) {
                        SwalUtils.showWarning('{{ session('warning') }}');
                    } else {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Warning',
                            text: '{{ session('warning') }}',
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#ffc107'
                        });
                    }
                @endif

                // Info message
                @if (session('info'))
                    if (typeof SwalUtils !== 'undefined' && SwalUtils.showInfo) {
                        SwalUtils.showInfo('{{ session('info') }}');
                    } else {
                        Swal.fire({
                            icon: 'info',
                            title: 'Information',
                            text: '{{ session('info') }}',
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#17a2b8'
                        });
                    }
                @endif

                // Validation errors
                @if ($errors->any())
                    if (typeof SwalUtils !== 'undefined' && SwalUtils.showValidationErrors) {
                        SwalUtils.showValidationErrors([
                            @foreach ($errors->all() as $error)
                                '{{ $error }}'@if (!$loop->last),@endif
                            @endforeach
                        ]);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Validation Error',
                            html: '<ul style="text-align: left;">' +
                                @foreach ($errors->all() as $error)
                                    '<li>{{ $error }}</li>' +
                                @endforeach
                                '</ul>',
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#dc3545'
                        });
                    }
                @endif
            });
        </script>
@endif
