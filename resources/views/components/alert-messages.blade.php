<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    @if (session('success'))
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: '{{ session('success') }}',
            confirmButtonText: 'OK'
        });
    @elseif (session('error'))
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: '{{ session('error') }}',
            confirmButtonText: 'OK'
        });
    @elseif (session('warning'))
        Swal.fire({
            icon: 'warning',
            title: 'Warning',
            text: '{{ session('warning') }}',
            confirmButtonText: 'OK'
        });
    @elseif (session('info'))
        Swal.fire({
            icon: 'info',
            title: 'Note',
            text: '{{ session('info') }}',
            confirmButtonText: 'OK'
        });
    @endif
</script>
