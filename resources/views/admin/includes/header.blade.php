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
    <!-- Theme style -->
    <link rel="stylesheet" href="{{ asset('dist/css/adminlte.min.css') }}">
    <style>
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
                    <a href="{{ route('admin.index') }}" class="nav-link">Home</a>
                </li>
                <li class="nav-item d-none d-sm-inline-block">
                    <a href="{{ route('admin.users') }}" class="nav-link">Users</a>
                </li>
                <li class="nav-item d-none d-sm-inline-block">
                    <a href="{{ route('admin.orders') }}" class="nav-link">Subscriptions</a>
                </li>
                @if (Auth::user()->role == 1)
                    <li class="nav-item d-none d-sm-inline-block">
                        <a href="{{ route('add-users') }}" class="nav-link">Add Users</a>
                    </li>
                    <li class="nav-item d-none d-sm-inline-block">
                        <a href="{{ route('subadmins') }}" class="nav-link">Sub Admin</a>
                    </li>
                    <li class="nav-item d-none d-sm-inline-block">
                        <a href="{{ route('payment-gateways.index') }}" class="nav-link">Payment Gateways</a>
                    </li>
                @endif
                <li class="nav-item d-none d-sm-inline-block">
                    <a href="{{ route('users.logs') }}" class="nav-link">User Logs</a>
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

                        <a href="{{ route('users.logs') }}" class="dropdown-item dropdown-footer">See All
                            Notifications</a>
                    </div>
                </li>

                <!-- Notifications Dropdown Menu -->
                <li class="nav-item dropdown">
                    <a class="nav-link" data-toggle="dropdown" href="#">
                        {{ Auth::user()->name }} <i class="fas fa-caret-down"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                        <div class="dropdown-divider"></div>
                        {{-- <a href="{{route('admin.profile')}}" class="dropdown-item text-center"> Edit Profile --}}
                        <a href="{{ route('admin.profile') }}" class="dropdown-item"> Edit Profile
                        </a>
                        {{-- <div class="dropdown-divider"></div> --}}
                        {{-- <a href="#" class="dropdown-item"> --}}
                        {{-- <i class="fas fa-users mr-2"></i> 8 friend requests --}}
                        {{-- <span class="float-right text-muted text-sm">12 hours</span> --}}
                        {{-- </a> --}}
                        {{-- <div class="dropdown-divider"></div> --}}
                        {{-- <a href="#" class="dropdown-item"> --}}
                        {{-- <i class="fas fa-file mr-2"></i> 3 new reports --}}
                        {{-- <span class="float-right text-muted text-sm">2 days</span> --}}
                        {{-- </a> --}}
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
