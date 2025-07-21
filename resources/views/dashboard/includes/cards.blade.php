<!-- AOS Animate On Scroll CSS/JS -->
<link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
<script>document.addEventListener('DOMContentLoaded', function(){AOS.init({duration: 900, once: true});});</script>
<style>
    .dashboard-card {
        border: none;
        border-radius: 1.2rem;
        box-shadow: 0 4px 24px rgba(0,0,0,0.07);
        color: #fff;
        overflow: hidden;
        transition: transform 0.25s, box-shadow 0.25s;
        background: linear-gradient(135deg, #0d6efd 0%, #0dcaf0 100%);
        position: relative;
    }
    .dashboard-card .card-body {
        padding: 2rem 1.5rem 1.5rem 1.5rem;
    }
    .dashboard-card .icon {
        font-size: 2.5rem;
        margin-bottom: 1rem;
        display: inline-block;
        transition: transform 0.3s;
    }
    .dashboard-card .card-title {
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        letter-spacing: 0.5px;
    }
    .dashboard-card .card-number {
        font-size: 2.1rem;
        font-weight: 700;
        letter-spacing: 1px;
        margin-bottom: 0;
    }
    .dashboard-card.bg-gradient-blue { background: linear-gradient(135deg, #0d6efd 0%, #0dcaf0 100%); }
    .dashboard-card.bg-gradient-red { background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%); }
    .dashboard-card.bg-gradient-green { background: linear-gradient(135deg, #198754 0%, #20c997 100%); }
    .dashboard-card.bg-gradient-yellow { background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%); color: #222; }
    .dashboard-card:hover {
        transform: translateY(-6px) scale(1.03);
        box-shadow: 0 8px 32px rgba(0,0,0,0.13);
        z-index: 2;
    }
    .dashboard-card:hover .icon {
        transform: scale(1.18) rotate(-8deg);
    }
</style>
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
