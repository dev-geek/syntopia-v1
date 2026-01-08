<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Syntopia')</title>

    <!-- Favicon -->
    <link rel="icon" type="image/webp" href="{{ asset('syntopia-logo.webp') }}">
    <link rel="shortcut icon" type="image/webp" href="{{ asset('syntopia-logo.webp') }}">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    @stack('fontawesome')

    <!-- Password Toggle CSS -->
    @stack('password-toggle-css')

    <!-- SweetAlert2 -->
    <script defer src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script defer src="{{ asset('js/swal-utils.js') }}"></script>

    <!-- Tracking Components -->
    <x-facebook-pixel />
    <x-tiktok-pixel />

    <!-- Custom Styles -->
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
    @stack('styles')
 </head>
 <body>
    @include('components.spinner-overlay')

    @yield('content')

    <!-- Bootstrap JS -->
    @stack('bootstrap-js')

    <!-- Password Toggle Script -->
    @stack('password-toggle-js')

    <!-- Global Spinner Utilities -->
    <script src="{{ asset('js/spinner-utils.js') }}"></script>

    <!-- SWAL Error Handling -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle SWAL errors from session
            @if(session('swal_error'))
                SwalUtils.showError(@json(session('swal_error')));
            @endif

            // Handle regular errors from session
            @if(session('error'))
                SwalUtils.showError(@json(session('error')));
            @endif

            // Handle success messages from session
            @if(session('success'))
                SwalUtils.showSuccess(@json(session('success')));
            @endif

            // Handle login success messages
            @if(session('login_success'))
                SwalUtils.showSuccess(@json(session('login_success')));
            @endif
        });
    </script>

    @stack('scripts')
</body>
</html>

