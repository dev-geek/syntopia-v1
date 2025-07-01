@if (session('success') || session('error') || session('warning') || session('info'))
    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                @if(session('success'))
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: '{{ addslashes(session('success')) }}',
                        confirmButtonText: 'OK'
                    });
                @elseif(session('error'))
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: '{{ addslashes(session('error')) }}',
                        confirmButtonText: 'OK'
                    });
                @elseif(session('warning'))
                    Swal.fire({
                        icon: 'warning',
                        title: 'Warning',
                        text: '{{ addslashes(session('warning')) }}',
                        confirmButtonText: 'OK'
                    });
                @elseif(session('info'))
                    Swal.fire({
                        icon: 'info',
                        title: 'Information',
                        text: '{{ addslashes(session('info')) }}',
                        confirmButtonText: 'OK'
                    });
                @elseif($errors->any())
                    Swal.fire({
                        icon: 'error',
                        title: 'Validation Error',
                        html: '{!! addslashes(implode('<br>', $errors->all())) !!}',
                        confirmButtonText: 'OK'
                    });
                @endif
            });
        </script>
    @endpush
@endif
