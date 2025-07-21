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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <!-- Theme style -->
    <link rel="stylesheet" href="{{ asset('dist/css/adminlte.min.css') }}">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #fff;
            color: #000;
        }

        .overview-wrapper {
            width: 100%;
            padding: 0;
            border-bottom: 1px solid #EFE7FB;
        }

        .container {
            max-width: 1300px;
            margin: 0 auto;
            padding: 50px 20px;
            border-left: 1px solid #EFE7FB;
            border-right: 1px solid #EFE7FB;
        }

        .overview-title {
            font-size: 42px;
            font-weight: 700;
            text-align: center;
            margin-bottom: 30px;
        }

        .overview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .overview-card {
            background: linear-gradient(180deg, white 0%, #F2F2F7 100%);
            border: 1px solid #EFE7FB;
            border-radius: 10px;
            padding: 24px;
        }

        .overview-card h4 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .stat-line {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 6px;
            font-size: 14px;
            color: #555;
            margin-bottom: 4px;
        }

        .stat-line .value {
            font-size: 50px;
            font-weight: 700;
            color: #e11d48;
        }

        .total-right {
            text-align: right;
            font-size: 12px;
            color: #888;
        }

        .progress-bar {
            margin-top: 14px;
            height: 6px;
            background-color: #eee;
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background-color: #e11d48;
            width: 100%;
        }

        .progress-fill.yellow {
            background-color: #facc15;
        }

        .progress-fill.gray {
            background-color: #c3c3c3;
        }

        .legend {
            margin-top: 10px;
            font-size: 12px;
            color: #555;
        }

        .dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 4px;
        }

        .dot.video {
            background: #facc15;
        }

        .dot.picture {
            background: #38bdf8;
        }

        .dot.audio {
            background: #a78bfa;
        }

        /* Custom Toast Centering */
        .toast-top-center {
            position: fixed !important;
            top: 50% !important;
            left: 50% !important;
            transform: translate(-50%, -50%) !important;
            z-index: 9999;
            width: auto;
            min-width: 300px;
        }

        .toast {
            border-radius: 10px;
            padding: 20px;
        }

        .toast button {
            padding: 5px 10px;
            font-size: 14px;
        }

        .toast .toast-title {
            font-weight: bold;
        }
        .header-bell {
            background: linear-gradient(135deg, #0d6efd 0%, #0dcaf0 100%);
            color: #fff;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(13,110,253,0.12);
            position: relative;
            transition: box-shadow 0.2s, background 0.2s;
            cursor: pointer;
        }
        .header-bell:hover, .header-bell.has-notifications {
            animation: bell-shake 0.7s;
            box-shadow: 0 4px 16px rgba(13,110,253,0.18);
            background: linear-gradient(135deg, #0dcaf0 0%, #0d6efd 100%);
        }
        @keyframes bell-shake {
            0%, 100% { transform: rotate(0); }
            20% { transform: rotate(-15deg); }
            40% { transform: rotate(10deg); }
            60% { transform: rotate(-8deg); }
            80% { transform: rotate(8deg); }
        }
        .header-bell .navbar-badge {
            position: absolute;
            top: 2px;
            right: 2px;
            font-size: 0.7rem;
            background: #dc3545;
            color: #fff;
            border-radius: 50%;
            padding: 2px 6px;
            font-weight: bold;
            box-shadow: 0 1px 4px rgba(220,53,69,0.18);
        }
        .header-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 8px;
            border: 2px solid #0d6efd;
            box-shadow: 0 2px 8px rgba(13,110,253,0.10);
        }
        .header-user-dropdown .dropdown-menu {
            animation: fadeInDown 0.35s;
            border-radius: 1rem;
            box-shadow: 0 8px 32px rgba(13,110,253,0.10);
            min-width: 200px;
        }
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-16px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .header-user-name {
            font-weight: bold;
            color: #0d6efd;
            margin-right: 6px;
        }
        .dropdown-menu.notifications-dropdown {
            background: #f8fafd;
            border-radius: 1.1rem;
            box-shadow: 0 8px 32px rgba(13,110,253,0.13);
            min-width: 320px;
            padding: 0.5rem 0;
            animation: fadeInDown 0.35s;
        }
        .notifications-dropdown .dropdown-header {
            font-weight: 700;
            color: #0d6efd;
            background: #e9f3ff;
            border-radius: 1.1rem 1.1rem 0 0;
            padding: 1rem 1.2rem 0.7rem 1.2rem;
            font-size: 1.1rem;
            border-bottom: 1px solid #e3eafc;
        }
        .notification-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 0.8rem 1.2rem;
            background: transparent;
            border: none;
            transition: background 0.18s;
            cursor: pointer;
        }
        .notification-item:hover {
            background: #e9f3ff;
        }
        .notification-icon {
            background: linear-gradient(135deg, #0d6efd 0%, #0dcaf0 100%);
            color: #fff;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(13,110,253,0.10);
        }
        .notification-content {
            flex: 1;
            min-width: 0;
        }
        .notification-activity {
            font-weight: 600;
            color: #222;
            font-size: 1rem;
            margin-bottom: 2px;
            white-space: normal;
            word-break: break-word;
        }
        .notification-time {
            color: #6c757d;
            font-size: 0.85rem;
            font-weight: 400;
        }
        .dropdown-footer.notifications-footer {
            text-align: center;
            padding: 0.7rem 1.2rem 0.9rem 1.2rem;
            color: #0d6efd;
            font-weight: 600;
            background: #e9f3ff;
            border-radius: 0 0 1.1rem 1.1rem;
            border-top: 1px solid #e3eafc;
            transition: background 0.18s;
        }
        .dropdown-footer.notifications-footer:hover {
            background: #d0e7ff;
            color: #0a58ca;
            text-decoration: underline;
        }
    </style>

</head>

<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed layout-footer-fixed">
    <div class="wrapper">
        <!-- Navbar -->
        <nav class="main-header navbar navbar-expand navbar-white navbar-light">
            <!-- Left navbar links -->
            <ul class="navbar-nav">
                <li class="nav-item d-lg-none">
                    <button class="btn btn-outline-primary border-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas">
                        <i class="bi bi-list" style="font-size: 1.7rem;"></i>
                    </button>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i
                            class="fas fa-bars"></i></a>
                </li>
            </ul>

            {{-- <!-- SEARCH FORM --> --}}
            {{-- <form class="form-inline ml-3"> --}}
            {{-- <div class="input-group input-group-sm"> --}}
            {{-- <input class="form-control form-control-navbar" type="search" placeholder="Search" --}}
            {{-- aria-label="Search"> --}}
            {{-- <div class="input-group-append"> --}}
            {{-- <button class="btn btn-navbar" type="submit"> --}}
            {{-- <i class="fas fa-search"></i> --}}
            {{-- </button> --}}
            {{-- </div> --}}
            {{-- </div> --}}
            {{-- </form> --}}

            <!-- Right navbar links -->
            <ul class="navbar-nav ml-auto">
                @if (!Auth::user()->hasRole('Sub Admin'))
                    <li class="nav-item dropdown">
                        <a class="nav-link position-relative header-bell @if($userLogs->count()) has-notifications @endif" data-toggle="dropdown" href="#">
                            <i class="far fa-bell"></i>
                            @if($userLogs->count())
                                <span class="badge navbar-badge">{{ $userLogs->count() }}</span>
                            @endif
                        </a>
                        <div class="dropdown-menu notifications-dropdown dropdown-menu-lg dropdown-menu-right">
                            <div class="dropdown-header">{{ $userLogs->count() }} Notifications</div>
                            <div class="dropdown-divider m-0"></div>
                            @foreach ($userLogs->take(5) as $log)
                                <div class="notification-item">
                                    <span class="notification-icon"><i class="fas fa-user"></i></span>
                                    <div class="notification-content">
                                        <div class="notification-activity">{{ $log->activity }}</div>
                                        <div class="notification-time">{{ $log->created_at->setTimezone('Asia/Karachi')->diffForHumans() }}</div>
                                    </div>
                                </div>
                                <div class="dropdown-divider m-0"></div>
                            @endforeach
                            <a href="{{ route('admin.users-logs') }}" class="dropdown-footer notifications-footer">See All Notifications</a>
                        </div>
                    </li>
                @endif
                <!-- User Dropdown -->
                <li class="nav-item dropdown header-user-dropdown">
                    <a class="nav-link d-flex align-items-center" data-toggle="dropdown" href="#">
                        <img src="{{ asset('dist/img/user2-160x160.jpg') }}" class="header-avatar" alt="User Avatar">
                        <span class="header-user-name">{{ Auth::user()->name }}</span>
                        <i class="fas fa-caret-down"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                        <div class="dropdown-divider"></div>
                        @if (auth()->user()->hasRole('Super Admin') || auth()->user()->hasRole('Sub Admin'))
                            <a href="{{ route('admin.profile') }}" class="dropdown-item">Edit Profile</a>
                        @else
                            <a href="{{ route('user.profile') }}" class="dropdown-item">Edit Profile</a>
                        @endif
                        <div class="dropdown-divider"></div>
                        <a href="{{ route('logout') }}" class="dropdown-item dropdown-footer"
                            onclick="event.preventDefault(); document.getElementById('logout-form').submit();">Logout</a>
                        <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                            @csrf
                        </form>
                    </div>
                </li>
            </ul>
        </nav>
        <!-- /.navbar -->

@push('scripts')
@endpush
