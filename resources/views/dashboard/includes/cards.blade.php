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
        <div class="card dashboard-card bg-gradient-yellow h-100 text-white">
            <div class="card-body text-center">
                <div class="icon mb-2"><i class="fas fa-user-plus"></i></div>
                <div class="card-title">New Members</div>
                <div class="card-number">{{ \App\Models\User::role('User')->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count() }}</div>
            </div>
        </div>
    </div>
</div>
