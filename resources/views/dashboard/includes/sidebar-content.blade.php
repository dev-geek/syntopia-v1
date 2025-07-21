<style>
    .nav-link.active {
        background: linear-gradient(90deg, #0d6efd 0%, #0dcaf0 100%) !important;
        color: #fff !important;
        transition: background 0.4s, color 0.4s;
        position: relative;
        z-index: 1;
    }
    .nav-link.active .sidebar-animate-icon {
        animation: bounce 0.7s;
        color: #fff !important;
    }
    @keyframes bounce {
        0%, 100% { transform: translateY(0); }
        30% { transform: translateY(-8px); }
        50% { transform: translateY(0); }
        70% { transform: translateY(-4px); }
    }
</style>
<div class="sidebar-content p-3">
    <div class="d-flex flex-column align-items-center mb-4">
        <img src="{{ asset('dist/img/user2-160x160.jpg') }}" class="rounded-circle mb-2" alt="User Image" width="64" height="64">
        <div class="fw-bold text-center">{{ Auth::user()->name }}</div>
        <div class="text-muted small text-center">{{ Auth::user()->email }}</div>
    </div>
    <ul class="nav nav-pills flex-column mb-auto">
        @if(auth()->user()->hasRole('Super Admin') || auth()->user()->hasRole('Sub Admin'))
            <li class="nav-item mb-1">
                <a href="{{ route('admin.dashboard') }}" class="nav-link d-flex align-items-center mr-4 {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                    <i class="bi bi-house mr-4 sidebar-animate-icon{{ request()->routeIs('admin.dashboard') ? ' sidebar-animate-icon' : '' }}" style="font-size:1.2rem;"></i><span>Home</span>
                </a>
            </li>
            <li class="nav-item mb-1">
                <a href="{{ route('admin.users') }}" class="nav-link d-flex align-items-center {{ request()->routeIs('admin.users') ? 'active' : '' }}">
                    <i class="bi bi-people mr-4 sidebar-animate-icon{{ request()->routeIs('admin.users') ? ' sidebar-animate-icon' : '' }}" style="font-size:1.2rem;"></i><span class="ms-3">Users</span>
                </a>
            </li>
            <li class="nav-item mb-1">
                <a href="{{ route('admin.orders') }}" class="nav-link d-flex align-items-center {{ request()->routeIs('admin.orders') ? 'active' : '' }}">
                    <i class="bi bi-card-list mr-4 sidebar-animate-icon{{ request()->routeIs('admin.orders') ? ' sidebar-animate-icon' : '' }}" style="font-size:1.2rem;"></i><span class="ms-3">Subscriptions</span>
                </a>
            </li>
            @if(auth()->user()->hasRole('Super Admin'))
                <li class="nav-item mb-1">
                    <a href="{{ route('admin.subadmins') }}" class="nav-link d-flex align-items-center {{ request()->routeIs('admin.subadmins') ? 'active' : '' }}">
                        <i class="bi bi-person-badge mr-4 sidebar-animate-icon{{ request()->routeIs('admin.subadmins') ? ' sidebar-animate-icon' : '' }}" style="font-size:1.2rem;"></i><span class="ms-3">Sub Admin</span>
                    </a>
                </li>
                <li class="nav-item mb-1">
                    <a href="{{ route('admin.payment-gateways.index') }}" class="nav-link d-flex align-items-center {{ request()->routeIs('admin.payment-gateways.index') ? 'active' : '' }}">
                        <i class="bi bi-credit-card mr-4 sidebar-animate-icon{{ request()->routeIs('admin.payment-gateways.index') ? ' sidebar-animate-icon' : '' }}" style="font-size:1.2rem;"></i><span class="ms-3">Payment Gateways</span>
                    </a>
                </li>
            @endif
            <li class="nav-item mb-1">
                <a href="{{ route('admin.users-logs') }}" class="nav-link d-flex align-items-center {{ request()->routeIs('admin.users-logs') ? 'active' : '' }}">
                    <i class="bi bi-clock-history mr-4 sidebar-animate-icon{{ request()->routeIs('admin.users-logs') ? ' sidebar-animate-icon' : '' }}" style="font-size:1.2rem;"></i><span class="ms-3">User Logs</span>
                </a>
            </li>
        @else
            <li class="nav-item mb-1">
                <a href="{{ route('user.dashboard') }}" class="nav-link d-flex align-items-center {{ request()->routeIs('user.dashboard') ? 'active' : '' }}">
                    <i class="bi bi-house mr-4 sidebar-animate-icon{{ request()->routeIs('user.dashboard') ? ' sidebar-animate-icon' : '' }}" style="font-size:1.2rem;"></i><span class="ms-4">Home</span>
                </a>
            </li>
            <li class="nav-item mb-1">
                <a href="{{ route('orders.index') }}" class="nav-link d-flex align-items-center {{ request()->routeIs('orders.index') ? 'active' : '' }}">
                    <i class="bi bi-bag mr-4 sidebar-animate-icon{{ request()->routeIs('orders.index') ? ' sidebar-animate-icon' : '' }}" style="font-size:1.2rem;"></i><span class="ms-3">Orders</span>
                </a>
            </li>
            <li class="nav-item mb-1">
                <a href="{{ route('user.subscription.details') }}" class="nav-link d-flex align-items-center {{ request()->routeIs('user.subscription.details') ? 'active' : '' }}">
                    <i class="bi bi-card-list mr-4 sidebar-animate-icon{{ request()->routeIs('user.subscription.details') ? ' sidebar-animate-icon' : '' }}" style="font-size:1.2rem;"></i><span class="ms-3">Subscriptions</span>
                </a>
            </li>
            <li class="nav-item mb-1">
                <a href="https://live.syntopia.ai" class="nav-link d-flex align-items-center" target="_blank">
                    <i class="bi bi-box-arrow-up-right mr-4 sidebar-animate-icon" style="font-size:1.2rem;"></i><span class="ms-3">ACCESS THE SOFTWARE</span>
                </a>
            </li>
        @endif
        <li class="nav-item mb-1">
            <a href="{{ route(auth()->user()->hasRole('User') ? 'user.profile' : 'admin.profile') }}" class="nav-link d-flex align-items-center {{ request()->routeIs('user.profile') || request()->routeIs('admin.profile') ? 'active' : '' }}">
                <i class="bi bi-person mr-4 sidebar-animate-icon{{ (request()->routeIs('user.profile') || request()->routeIs('admin.profile')) ? ' sidebar-animate-icon' : '' }}" style="font-size:1.2rem;"></i><span class="ms-3">Profile</span>
            </a>
        </li>
        <li class="nav-item mt-3">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="nav-link d-flex align-items-center w-100 text-start bg-transparent border-0">
                    <i class="bi bi-box-arrow-right mr-4 sidebar-animate-icon" style="font-size:1.2rem;"></i><span class="ms-3">Logout</span>
                </button>
            </form>
        </li>
    </ul>
</div>
<!-- Bootstrap Icons CDN -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
