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
    </style>

</head>

<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed layout-footer-fixed">
    <div class="wrapper">
        <!-- Navbar -->
        <nav class="main-header navbar navbar-expand navbar-white navbar-light">
            <!-- Left navbar links -->
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i
                            class="fas fa-bars"></i></a>
                </li>
                <li class="nav-item d-none d-sm-inline-block">
                    @if (Auth::user()->hasRole('Super Admin') || Auth::user()->hasRole('Sub Admin'))
                        <a href="{{ route('admin.dashboard') }}" class="nav-link">Home</a>
                    @else
                        <a href="{{ route('user.dashboard') }}" class="nav-link">Home</a>
                    @endif
                </li>
                @if (Auth::user()->hasRole('Super Admin') || Auth::user()->hasRole('Sub Admin'))
                    <li class="nav-item d-none d-sm-inline-block">
                        <a href="{{ route('admin.users') }}" class="nav-link">Users</a>
                    </li>
                    <li class="nav-item d-none d-sm-inline-block">
                        <a href="{{ route('admin.orders') }}" class="nav-link">Subscriptions</a>
                    </li>
                @endif
                @if (Auth::user()->hasRole('Super Admin'))
                    <li class="nav-item d-none d-sm-inline-block">
                        <a href="{{ route('admin.subadmins') }}" class="nav-link">Sub Admin</a>
                    </li>
                    <li class="nav-item d-none d-sm-inline-block">
                        <a href="{{ route('admin.payment-gateways.index') }}" class="nav-link">Payment Gateways</a>
                    </li>
                @endif
                @if (Auth::user()->hasRole('Super Admin') || Auth::user()->hasRole('Sub Admin'))
                    <li class="nav-item d-none d-sm-inline-block">
                        <a href="{{ route('admin.users-logs') }}" class="nav-link">User Logs</a>
                    </li>
                @endif
                @if (Auth::user()->hasRole('User'))
                    <li class="nav-item d-none d-sm-inline-block">
                        <a href="{{ route('orders.index') }}" class="nav-link">Orders</a>
                    </li>

                @endif
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
                        <a class="nav-link" data-toggle="dropdown" href="#">
                            <i class="far fa-bell"></i>
                            <span class="badge badge-danger navbar-badge">{{ $userLogs->count() }}</span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                            <span class="dropdown-item dropdown-header">{{ $userLogs->count() }} Notifications</span>
                            <div class="dropdown-divider"></div>

                            @foreach ($userLogs->take(5) as $log)
                                <!-- Show latest 5 logs -->
                                <a href="#" class="dropdown-item">
                                    <i class="fas fa-user mr-2"></i> {{ $log->activity }}
                                    <span class="float-right text-muted text-sm">
                                        {{ $log->created_at->setTimezone('Asia/Karachi')->diffForHumans() }}
                                    </span>
                                </a>
                                <div class="dropdown-divider"></div>
                            @endforeach

                            <a href="{{ route('admin.users-logs') }}" class="dropdown-item dropdown-footer">See All
                                Notifications</a>
                        </div>
                    </li>
                @endif

                <!-- Notifications Dropdown Menu -->
                <li class="nav-item dropdown">
                    <a class="nav-link" data-toggle="dropdown" href="#">
                        {{ Auth::user()->name }} <i class="fas fa-caret-down"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                        <div class="dropdown-divider"></div>
                        @if(auth()->user()->hasRole('Super Admin') || auth()->user()->hasRole('Sub Admin'))
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
