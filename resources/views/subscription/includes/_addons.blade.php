<div class="addons-wrapper">
        <div class="container">
            <div class="badge-wrapper">
                <div class="pricing-badge">Addons</div>
            </div>

            @if(isset($pageType) && ($pageType === 'upgrade' || $pageType === 'downgrade'))
                <h2 class="section-title">Bring Your Own Face & Voice to Life on Syntopia.</h2>
                <p class="section-subtitle">Want to go live as your own AI avatar on TikTok, YouTube, or your site? You'll need two customizations to match your appearance and voice:</p>

                <div class="addons-grid-wrapper">
                    <div class="addons-grid">
                        <!-- Avatar Customization -->
                        <div class="addon-card">
                            <h3>We create your AI avatar (your face, hairstyle, and outfit) so you can go live as you.</h3>
                            <p class="addon-price">$2800</p>
                            <button class="btn dark">Get Started</button>
                            <p class="included-title">What's required from you:</p>
                            <ul class="features">
                                <li><span class="icon"></span> 7 min of training video recorded</li>
                                <li><span class="icon"></span> You get 1 Digital avatar</li>
                                <li><span class="icon"></span> Step-by-step video recording guide included</li>
                                <li><span class="icon"></span> Minor imperfections may remain</li>
                                <li><span class="icon"></span> One-time setup, no annual fee</li>
                            </ul>
                        </div>

                        <!-- Voice Customization -->
                        <div class="addon-card">
                            <h3>Voice Customization</h3>
                            <p class="addon-price">$2200</p>
                            <button class="btn dark">Get Started</button>
                            <p class="included-title">What's required from you:</p>
                            <ul class="features">
                                <li><span class="icon"></span> 7 min of valid audio recorded</li>
                                <li><span class="icon"></span> Customer handles voice processing</li>
                                <li><span class="icon"></span> Guide provided for best results</li>
                                <li><span class="icon"></span> Natural flaws may occur (noise, tone)</li>
                                <li><span class="icon"></span> One-time setup, no usage fee</li>
                            </ul>
                        </div>
                    </div>
                </div>
            @else
                <h2 class="section-title">Customized Addons</h2>
                <div class="addons-grid-wrapper">
                    <div class="addons-grid">
                        <!-- Avatar Customization -->
                        <div class="addon-card">
                            <h3>Avatar Customization</h3>
                            <p class="addon-price">$2800</p>
                            <button class="btn dark">Get Started</button>
                            <p class="included-title">What's included</p>
                            <ul class="features">
                                <li><span class="icon"></span> 30+ min of training video recorded</li>
                                <li><span class="icon"></span> Digital avatar: 1 hairstyle, outfit</li>
                                <li><span class="icon"></span> Guide provided for video recording</li>
                                <li><span class="icon"></span> Customer handles processing & upload</li>
                                <li><span class="icon"></span> 1 optimization pass included</li>
                                <li><span class="icon"></span> Minor imperfections may remain</li>
                                <li><span class="icon"></span> One-time setup, no annual fee</li>
                            </ul>
                        </div>

                        <!-- Voice Customization -->
                        <div class="addon-card">
                            <h3>Voice Customization</h3>
                            <p class="addon-price">$2200</p>
                            <button class="btn dark">Get Started</button>
                            <p class="included-title">What's included</p>
                            <ul class="features">
                                <li><span class="icon"></span> 30+ min of valid audio recorded</li>
                                <li><span class="icon"></span> Customer handles voice processing</li>
                                <li><span class="icon"></span> Guide provided for best results</li>
                                <li><span class="icon"></span> Natural flaws may occur (noise, tone)</li>
                                <li><span class="icon"></span> One-time setup, no usage fee</li>
                            </ul>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
