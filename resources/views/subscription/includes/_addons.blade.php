<div class="addons-wrapper">
        <div class="container">
            <div class="badge-wrapper">
                <div class="pricing-badge">Addons</div>
            </div>

                <h2 class="section-title">Customized Addons</h2>
                <div class="addons-grid-wrapper">
                    <div class="addons-grid addons-centered">
                    <!-- Avatar Customization (Clone Yourself) -->
                        <div class="addon-card">
                            <h3>
                            Avatar Customization (Clone Yourself)
                                @if(!empty($activeAddonSlugs) && in_array('avatar_customization', $activeAddonSlugs))
                                    <span class="badge badge-success" style="margin-left:8px;">Active</span>
                                @endif
                            </h3>
                        <p class="addon-price">$1380</p>
                            <button class="btn dark" @if(!empty($hasActiveAddon) && $hasActiveAddon) disabled @endif onclick="window.location.href = '{{ route('subscription', ['adon' => 'avatar_customization']) }}'">Get Started</button>
                        <p class="included-title">What's required from you:</p>
                            <ul class="features">
                            <li><span class="icon"></span> 7 min of training video recorded required from you</li>
                            <li><span class="icon"></span> You get 1 Digital avatar</li>
                            <li><span class="icon"></span> Step-by-step video recording guide will be provided - <a href="https://syntopia.ai/custom-avatar-shooting-guide/" target="_blank" style="color: #5b0dd5; text-decoration: underline;">https://syntopia.ai/custom-avatar-shooting-guide/</a></li>
                                <li><span class="icon"></span> Minor imperfections may remain</li>
                                <li><span class="icon"></span> One-time setup, no annual fee</li>
                            </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

<style>
.addons-wrapper {
    width: 100%;
    padding: 0px;
    border-bottom: 1px solid #EFE7FB;
}

.addons-grid-wrapper {
    display: flex;
    justify-content: center;
    margin-top: 40px;
}

.addons-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 30px;
    max-width: 500px;
    width: 100%;
}

.addons-centered {
    justify-content: center;
    margin: 0 auto;
}

.addon-card {
    border: 1px solid #EFE7FB;
    border-radius: 10px;
    padding: 25px;
    background: linear-gradient(180deg, white 0%, #F2F2F7 100%);
    color: black;
    text-align: center;
}

.addon-card h3 {
    font-size: 24px;
    font-weight: 700;
    margin-bottom: 20px;
    line-height: 1.3;
}

.addon-price {
    font-size: 32px;
    font-weight: 700;
    color: #5b0dd5;
    margin-bottom: 25px;
}

.addon-card .btn {
    margin: 25px 0;
    font-size: 16px;
    padding: 15px 0;
}

.addon-card .included-title {
    color: #5b0dd5;
    font-weight: 600;
    font-size: 16px;
    margin-top: 25px;
    margin-bottom: 15px;
}

.addon-card .features {
    text-align: left;
}

.addon-card .features li {
    font-size: 15px;
    margin: 10px 0;
}

/* Simple badge styles for Active indicator */
.badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
}
.badge-success {
    background: #22c55e;
    color: #fff;
}

@media (max-width: 768px) {
    .addons-grid {
        grid-template-columns: 1fr;
        max-width: 500px;
        gap: 20px;
    }

    .addon-card {
        padding: 20px;
    }

    .addon-card h3 {
        font-size: 20px;
    }

    .addon-price {
        font-size: 28px;
    }
}
</style>
