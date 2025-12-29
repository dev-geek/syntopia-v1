<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', 'Admin Panel')</title>

  <!-- Favicon -->
  <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
  <link rel="shortcut icon" type="image/png" href="{{ asset('favicon.png') }}">

  <!-- Google Font: Source Sans Pro -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  
  <!-- Font Awesome -->
  <link rel="stylesheet" href="{{ asset('plugins/fontawesome-free/css/all.min.css') }}">
  
  <!-- icheck bootstrap -->
  <link rel="stylesheet" href="{{ asset('plugins/icheck-bootstrap/icheck-bootstrap.min.css') }}">
  
  <!-- Theme style -->
  <link rel="stylesheet" href="{{ asset('dist/css/adminlte.min.css') }}">
  
  <!-- Admin CSS -->
  <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
  
  <!-- Password Toggle CSS -->
  @stack('password-toggle-css')
  
  <!-- Tracking Components -->
  <x-firstpromoter-tracking />
  <x-facebook-pixel />
  <x-tiktok-pixel />
  
  @stack('styles')
  
  <!-- Ensure Admin Login Styles Override AdminLTE -->
  @if(request()->is('admin-login'))
  <style>
    /* Force override AdminLTE login page styles */
    body.hold-transition.login-page,
    body.login-page {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
      background-image: url('https://syntopia.ai/wp-content/uploads/2025/01/Clip-path-group.webp') !important;
      background-size: cover !important;
      background-position: center !important;
      background-repeat: no-repeat !important;
      background-attachment: fixed !important;
    }
    
    body.hold-transition.login-page .login-box .card,
    body.login-page .login-box .card {
      display: none !important;
    }
  </style>
  @endif
</head>
<body class="hold-transition @yield('body-class', 'login-page')">
  @yield('content')

  <!-- jQuery -->
  <script src="{{ asset('plugins/jquery/jquery.min.js') }}"></script>
  
  <!-- Bootstrap 4 -->
  <script src="{{ asset('plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
  
  <!-- AdminLTE App -->
  <script src="{{ asset('dist/js/adminlte.min.js') }}"></script>
  
  <!-- Password Toggle Script -->
  @stack('password-toggle-js')
  
  @stack('scripts')
</body>
</html>

