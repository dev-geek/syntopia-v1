@php
use App\Models\UserLog;
$userLogs = UserLog::latest()->get(); // Fetch all logs without a limit
@endphp

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard</title>

    <!-- Favicon -->
    <link rel="icon" type="image/webp" href="{{ asset('syntopia-logo.webp') }}">
    <link rel="shortcut icon" type="image/webp" href="{{ asset('syntopia-logo.webp') }}">

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="{{ asset('plugins/fontawesome-free/css/all.min.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- overlayScrollbars -->
    <link rel="stylesheet" href="{{ asset('plugins/overlayScrollbars/css/OverlayScrollbars.min.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/datatables-bs4/css/dataTables.bootstrap4.min.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/datatables-responsive/css/responsive.bootstrap4.min.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/datatables-buttons/css/buttons.bootstrap4.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/datatables-custom.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <!-- Theme style -->
    <link rel="stylesheet" href="{{ asset('dist/css/adminlte.min.css') }}">
    <!-- Password Toggle CSS -->
    <link rel="stylesheet" href="{{ asset('css/password-toggle.css') }}">
    <!-- Dashboard CSS -->
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <!-- Dashboard Header CSS -->
    <link rel="stylesheet" href="{{ asset('css/dashboard-header.css') }}">

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{ asset('js/swal-utils.js') }}"></script>

    <!-- Tracking Components -->
    <x-facebook-pixel />
    <x-tiktok-pixel />

 </head>

 <body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed">
    @include('components.spinner-overlay')

    <div class="wrapper">
        <!-- Navbar -->
        <nav class="main-header navbar navbar-expand navbar-white navbar-light">
            <!-- Left navbar links -->
            <ul class="navbar-nav">
                <li class="nav-item d-lg-none">
                    <button class="btn btn-outline-primary border-0" type="button" data-bs-toggle="offcanvas"
                        data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas">
                        <i class="bi bi-list" style="font-size: 1.7rem;"></i>
                    </button>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
                </li>
            </ul>

            <!-- Right navbar links -->
            <ul class="navbar-nav ml-auto">
                @if (!Auth::user()->hasRole('Super Admin'))
                <li class="nav-item">
                    <a href="#" class="btn btn-primary header-software-btn" id="accessSoftwareBtn"
                        onclick="checkPasswordAndAccess()">
                        <i class="bi bi-box-arrow-up-right"></i>
                        <span class="d-none d-md-inline">ACCESS THE SOFTWARE</span>
                    </a>
                </li>

                @if (!Auth::user()->hasValidSubscriberPassword())
                <li class="nav-item">
                    <div class="header-notification-container">
                        <div class="header-notification-box">
                            <div class="header-notification-content">
                                <div class="header-notification-icon">
                                    <i class="fas fa-shield-alt"></i>
                                </div>
                                <div class="header-notification-text">
                                    <span class="header-notification-message">Please login to the software using your
                                        email and password.</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </li>
                @endif
                @endif

                @if (!Auth::user()->hasValidSubscriberPassword())
                {{-- Custom modal overlay for password setup --}}
                <div id="passwordModalOverlay" class="password-modal-overlay" style="display: none;">
                    <div class="password-modal-container">
                        <div class="password-modal-box">
                            <div class="password-modal-header">
                                <h5 class="password-modal-title">
                                    <i class="fas fa-shield-alt me-2"></i>
                                    Set Your Password
                                </h5>
                            </div>
                            <div class="password-modal-body">
                                <p>You haven't set a password yet. Please set a password to continue.</p>
                                <button type="button" class="btn-set-password">
                                    <i class="fas fa-key"></i>
                                    Set Password
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </ul>
        </nav>
        <!-- /.navbar -->
