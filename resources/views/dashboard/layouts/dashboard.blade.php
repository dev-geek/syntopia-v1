@php
use App\Models\UserLog;
$userLogs = UserLog::latest()->get(); // Fetch all logs without a limit
@endphp

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin Dashboard')</title>

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
    <x-firstpromoter-tracking />
    <x-facebook-pixel />
    <x-tiktok-pixel />

    @stack('styles')
</head>

<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed">
    <div class="wrapper">
        <div id="globalSpinner" class="global-spinner-overlay">
            <div class="global-spinner"></div>
        </div>
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

        @include('dashboard.includes.sidebar')

        <!-- Content Wrapper. Contains page content -->
        <div class="content-wrapper">
            <!-- Content Header (Page header) -->
            <div class="content-header">
            </div>
            <!-- /.content-header -->

            <!-- Main content -->
            <section class="content pt-5">
                <div class="container-fluid">
                    <!-- alert component -->
                    @include('components.alert-messages')

                    @yield('content')
                </div>
                <!--/. container-fluid -->
            </section>
            <!-- /.content -->
        </div>
        <!-- /.content-wrapper -->

        <!-- Control Sidebar -->
        <aside class="control-sidebar control-sidebar-dark">
            <!-- Control sidebar content goes here -->
        </aside>
        <!-- /.control-sidebar -->

        <!-- Main Footer -->
        @include('dashboard.includes.footer')
    </div>
    <!-- ./wrapper -->

    <!-- REQUIRED SCRIPTS -->
    <!-- jQuery -->
    <script src="{{ asset('plugins/jquery/jquery.min.js') }}"></script>
    <!-- Bootstrap -->
    <script src="{{ asset('plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <!-- overlayScrollbars -->
    <script src="{{ asset('plugins/overlayScrollbars/js/jquery.overlayScrollbars.min.js') }}"></script>
    <!-- AdminLTE App -->
    <script src="{{ asset('dist/js/adminlte.js') }}"></script>

    <!-- PAGE PLUGINS -->
    <!-- jQuery Mapael -->
    <script src="{{ asset('plugins/jquery-mousewheel/jquery.mousewheel.js') }}"></script>
    <script src="{{ asset('plugins/raphael/raphael.min.js') }}"></script>
    <script src="{{ asset('plugins/jquery-mapael/jquery.mapael.min.js') }}"></script>
    <script src="{{ asset('plugins/jquery-mapael/maps/usa_states.min.js') }}"></script>
    <!-- ChartJS -->
    <script src="{{ asset('plugins/chart.js/Chart.min.js') }}"></script>
    <!-- DataTables  & Plugins -->
    <script src="{{ asset('plugins/datatables/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('plugins/datatables-bs4/js/dataTables.bootstrap4.min.js') }}"></script>
    <script src="{{ asset('plugins/datatables-responsive/js/dataTables.responsive.min.js') }}"></script>
    <script src="{{ asset('plugins/datatables-responsive/js/responsive.bootstrap4.min.js') }}"></script>
    <script src="{{ asset('plugins/datatables-buttons/js/dataTables.buttons.min.js') }}"></script>
    <script src="{{ asset('plugins/datatables-buttons/js/buttons.bootstrap4.min.js') }}"></script>
    <script src="{{ asset('plugins/jszip/jszip.min.js') }}"></script>
    <script src="{{ asset('plugins/pdfmake/pdfmake.min.js') }}"></script>
    <script src="{{ asset('plugins/pdfmake/vfs_fonts.js') }}"></script>
    <script src="{{ asset('plugins/datatables-buttons/js/buttons.html5.min.js') }}"></script>
    <script src="{{ asset('plugins/datatables-buttons/js/buttons.print.min.js') }}"></script>
    <script src="{{ asset('plugins/datatables-buttons/js/buttons.colVis.min.js') }}"></script>
    <!-- AdminLTE App -->
    <script src="{{ asset('dist/js/adminlte.min.js') }}"></script>

    <!-- AdminLTE for demo purposes -->
    <script src="{{ asset('dist/js/demo.js') }}"></script>
    <!-- AdminLTE dashboard demo (This is only for demo purposes) -->
    <script src="{{ asset('dist/js/pages/dashboard2.js') }}"></script>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Bootstrap 5 Bundle JS (for Offcanvas) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Password Toggle Functionality -->
    <script src="{{ asset('js/password-toggle.js') }}"></script>

    <!-- Copy to Clipboard Utility -->
    <script src="{{ asset('js/copy-to-clipboard.js') }}"></script>

    <!-- Password Modal Script -->
    <script>
        (function() {
            const spinner = document.getElementById('globalSpinner');
            if (spinner) {
                let requestCount = 0;
                function show() { spinner.style.display = 'flex'; }
                function hide() { spinner.style.display = 'none'; }
                function inc() { requestCount++; show(); }
                function dec() { requestCount = Math.max(0, requestCount - 1); if (requestCount === 0) hide(); }

                // Hook fetch
                const originalFetch = window.fetch;
                window.fetch = function() {
                    inc();
                    return originalFetch.apply(this, arguments).finally(dec);
                };

                // Hook jQuery AJAX if available
                if (window.jQuery) {
                    $(document).ajaxStart(function() { inc(); });
                    $(document).ajaxStop(function() { dec(); });
                }

                // Show on form submits and internal navigation clicks
                document.addEventListener('submit', function(e) {
                    const form = e.target;
                    if (form && !form.hasAttribute('data-no-spinner')) {
                        show();
                    }
                }, true);

                document.addEventListener('click', function(e) {
                    const link = e.target.closest('a');
                    if (!link) return;
                    const href = link.getAttribute('href');
                    const target = link.getAttribute('target');
                    const noSpinner = link.hasAttribute('data-no-spinner');
                    if (!noSpinner && href && href.startsWith('/') && (!target || target === '_self')) {
                        show();
                    }
                }, true);

                window.addEventListener('pageshow', function() { hide(); });
            }
        })();

        function checkPasswordAndAccess() {
            @if (!Auth::user()->hasActiveSubscription())
                Swal.fire({
                    icon: 'error',
                    title: 'Subscription Required',
                    text: 'You need an active plan to access the software.',
                    showCancelButton: true,
                    confirmButtonText: 'View Plans',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#dc3545'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = '{{ route('subscription') }}';
                    }
                });
                return;
            @endif
            @if (!Auth::user()->hasValidSubscriberPassword())
                // User doesn't have a password, show modal
                showPasswordModal();
            @else
                // User has a password, redirect to software
                window.open('{{ route('software.access') }}', '_blank');
            @endif
        }

        function showPasswordModal() {
            console.log('Showing custom password modal...');

            const modalOverlay = document.getElementById('passwordModalOverlay');
            if (!modalOverlay) {
                console.error('Password modal overlay not found');
                return;
            }

            // Show the modal
            modalOverlay.style.display = 'flex';
            console.log('Custom password modal shown successfully');

            // Add click event listener to button
            const setPasswordBtn = modalOverlay.querySelector('.btn-set-password');
            if (setPasswordBtn) {
                console.log('Found Set Password button, adding click listener');
                setPasswordBtn.addEventListener('click', function(e) {
                    console.log('Set Password button clicked!');
                    e.stopPropagation();
                    window.open('/user/profile', '_self');
                });
            } else {
                console.error('Set Password button not found');
            }
        }
    </script>

    <!-- PaddleJS -->
    @paddleJS

    @stack('scripts')
</body>

</html>

