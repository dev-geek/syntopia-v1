<!-- AOS Animate On Scroll CSS/JS -->
<link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
<script>document.addEventListener('DOMContentLoaded', function(){AOS.init({duration: 900, once: true});});</script>

<div class="row g-4">
    <div class="col-12 col-sm-6 col-md-3" data-aos="fade-up">
        <div class="card dashboard-card bg-gradient-blue h-100">
            <div class="card-body text-center">
                <div class="icon mb-2"><i class="fas fa-users"></i></div>
                <div class="card-title">All Users</div>
                <div class="card-number">{{ \App\Models\User::role('User')->count() }}</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-3" data-aos="fade-up" data-aos-delay="100">
        <div class="card dashboard-card bg-gradient-red h-100">
            <div class="card-body text-center">
                <div class="icon mb-2"><i class="fas fa-thumbs-up"></i></div>
                <div class="card-title">Active Users</div>
                <div class="card-number">{{ \App\Models\User::role('User')->where('status', 1)->count() }}</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-3" data-aos="fade-up" data-aos-delay="200">
        <div class="card dashboard-card bg-gradient-green h-100">
            <div class="card-body d-flex flex-column justify-content-between h-100">
                <div class="d-flex justify-content-between align-items-start w-100">
                    <div class="">
                        <div class="card-title text-start">Active Subscriptions</div>
                        <div class="card-number text-start">{{ \App\Models\Order::count() }}</div>
                    </div>
                    <div class="icon ms-2 text-end"><i class="fas fa-shopping-cart"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-3" data-aos="fade-up" data-aos-delay="300">
        <div class="card dashboard-card bg-gradient-purple h-100">
            <div class="card-body text-center">
                <div class="icon mb-2"><i class="fas fa-user-shield"></i></div>
                <div class="card-title">Sub Admin</div>
                <div class="card-number">{{ Auth::user()->is_active ? 'Active' : 'Inactive' }}</div>
            </div>
        </div>
    </div>
</div>

<!-- Welcome Message for Sub Admin -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm" data-aos="fade-up" data-aos-delay="400">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                            <i class="fas fa-user-shield text-white" style="font-size: 1.5rem;"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h5 class="card-title mb-1">Welcome, {{ Auth::user()->name }}!</h5>
                        <p class="card-text text-muted mb-0">You are logged in as a Sub Admin. You have limited access to manage users and view system statistics.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
