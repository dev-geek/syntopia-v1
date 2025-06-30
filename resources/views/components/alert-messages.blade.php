@if (session('success') || session('error') || session('warning') || session('info') || $errors->any())
    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
            (function () {
                let retryCount = 0;
                const maxRetries = 10; // Prevent infinite retries

                function showAlert() {
                    // Check if SweetAlert2 is loaded
                    if (typeof Swal === 'undefined') {
                        if (retryCount < maxRetries) {
                            retryCount++;
                            console.warn(`SweetAlert2 not loaded, retrying (${retryCount}/${maxRetries})...`);
                            setTimeout(showAlert, 100);
                        } else {
                            console.error('SweetAlert2 failed to load after maximum retries.');
                            // Optionally show a fallback alert
                            alert('An error occurred while loading the alert system. Please try again later.');
                        }
                        return;
                    }

                    // Success message
                    @if (session('success'))
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: '{{ session('success') }}',
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#28a745'
                        });
                    @elseif (session('error'))
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: '{{ session('error') }}',
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#dc3545'
                        });
                    @elseif (session('warning'))
                        Swal.fire({
                            icon: 'warning',
                            title: 'Warning!',
                            text: '{{ session('warning') }}',
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#ffc107'
                        });
                    @elseif (session('info'))
                        Swal.fire({
                            icon: 'info',
                            title: 'Information',
                            text: '{{ session('info') }}',
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#17a2b8'
                        });
                    @elseif ($errors->any())
                        Swal.fire({
                            icon: 'error',
                            title: 'Validation Errors',
                            html: '<ul style="text-align: left; padding-left: 20px;">' +
                                @foreach ($errors->all() as $error)
                                    '<li>{{ $error }}</li>' +
                                @endforeach
                                '</ul>',
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#dc3545'
                        });
                    @endif
                }

                // Run when DOM is fully loaded
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', showAlert);
                } else {
                    showAlert();
                }
            })();
        </script>
    @endpush
@endif