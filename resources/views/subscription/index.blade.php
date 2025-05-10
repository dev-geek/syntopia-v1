<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Syntopia Pricing</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Inter', sans-serif;
      background: #fff;
      color: #000;
      overflow-x: hidden;
    }
    .pricing-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 20px 40px;
      border-bottom: 1px solid #e5e7eb;
    }
    .pricing-header img {
      height: 32px;
    }
    .pricing-header button {
      font-size: 14px;
      font-weight: 500;
      color: #2563eb;
      background: transparent;
      border: none;
      cursor: pointer;
    }
    .pricing-header button:hover {
      text-decoration: underline;
    }

    .pricing-wrapper {
      width: 100%;
      padding: 0px;
      border-bottom: 1px solid  #EFE7FB;
    }
    .container {
      max-width: 1300px;
      margin: 0 auto;
      padding: 50px 20px;
      border-left: 1px solid  #EFE7FB;
      border-right: 1px solid  #EFE7FB;
    }
    .pricing-grid {
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      gap: 20px;
      margin-top: 40px;
    }
    .badge-wrapper {
     text-align: center;
    }

    .pricing-badge {
        display: inline-block;
        padding: 7px 15px;
        margin-bottom: 10px;
        font-size: 16px;
        font-weight: 600;
        color: #5b0dd5;
        background-color: #f5f1fe;
        border: 1px solid #5b0dd5;
        border-radius: 999px;
        text-transform: uppercase;
    }

    .pricing-wrapper h2{
        font-size: 65px ;
        
    }
    .card {
      border: 1px solid #EFE7FB;
      border-radius: 10px;
      padding: 15px;
    }
    .card-light {
      background: linear-gradient(180deg, white 0%, #F2F2F7 100%);
      color: black;
    }
    .card-dark {
      background: linear-gradient(180deg, #E0347E 0%, #6B83DD 100%);
      color: white;
    }
    .card-dark.last {
  background: linear-gradient(180deg, #6B83DD 0%, #E0347E 100%);
    }   
    .section-title {
      text-align: center;
      font-size: 42px;
      font-weight: 700;
      margin-bottom: 10px;
    }
    .section-subtitle {
      text-align: center;
      max-width: 600px;
      margin: 0 auto 30px;
      font-size: 16px;
      color: #555;
    }
    .card h3 {
      font-size: 35px;
      font-weight: 700;
      margin-bottom: 10px;
    }
    .card p.price {
      font-size: 27px;
      font-weight: 600;
      color: black;
    }
    .card-dark p.price {
      color: white;
    }
    .per-month {
      font-size: 16px;
      color: #5b0dd5;
      font-weight: 400;
    }
    .card-dark .per-month {
      color: white;
    }
    .btn {
      font-family: 'Inter', sans-serif;
      display: block;
      width: 100%;
      padding: 13px 0;
      font-size: 15px;
      font-weight: 600;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      margin: 15px 0;
    }
    .btn.dark { background: black; color: white; }
    .btn.dark:hover { background: #5b0dd5; }
    .btn.purple { background: #5b0dd5; color: white; }
    .btn.purple:hover { background: white; color: #5b0dd5; }
    .btn.white { background: white; color: #5b0dd5; }
    .btn.white:hover { background: white; }
    .included-title {
      color: #5b0dd5;
      font-weight: 600;
      font-size: 15px;
      margin-top: 20px;
    }
    .card-dark .included-title { color: white; }
    .features { list-style: none; padding: 0; margin: 0; }
    .features li {
      font-size: 16px;
      font-weight: 400;
      line-height: 1.4;
      margin: 8px 0;
      display: flex;
      align-items: flex-start;
    }
    .icon::before {
  line-height: 1;
  margin-top: 3px;
}
    .features li strong { font-weight: 700; }
    .icon::before {
      content: "✔";
      font-size: 18px;
      color: #5b0dd5;
      font-weight: bold;
      margin-right: 10px;
      line-height: 1.2;
    }
    .card-dark .icon::before { color: white; }
    @media (max-width: 1024px) {
      .pricing-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 640px) {
      .pricing-grid { grid-template-columns: 1fr; }
    }
    footer {
      text-align: center;
      font-size: 14px;
      color: #6b7280;
      padding: 40px 20px;
    }
    footer a {
      color: #2563eb;
      text-decoration: none;
    }
    footer a:hover {
      text-decoration: underline;
    }

    /* addons style  */

    .addons-wrapper {
  width: 100%;
  padding: 0px;
  border-bottom: 1px solid #EFE7FB;
}

.addons-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20px;
  margin-top: 40px;
}
.addon-card {
    background: linear-gradient(180deg, white 0%, #F2F2F7 100%);
    color: black;
  border: 1px solid #EFE7FB;
  border-radius: 10px;
  padding: 24px;
}
.addon-card h3 {
  font-size: 26px;
  font-weight: 700;
  margin-bottom: 6px;
}
.addon-price {
  font-size: 22px;
  font-weight: 700;
  margin-bottom: 12px;
}
.addons-grid-wrapper {
  max-width: 65%;
  margin: 0 auto;
}
@media (max-width: 768px) {
  .addons-grid {
    grid-template-columns: 1fr;
  }
  .addons-grid-wrapper {
    max-width: 100%;
  }
  .pricing-wrapper h2{
        font-size: 40px ;
        
    }
}





  </style>
<script
        id="fsc-api"
        src="https://sbl.onfastspring.com/sbl/1.0.3/fastspring-builder.min.js"
        type="text/javascript"
        data-storefront="livebuzzstudio.test.onfastspring.com/popup-check-paymet"
        data-popup-closed="onFSPopupClosed">
    </script>


</head>
<body >
<script>
    let currentSelectedPackage = "{{ $package ?? '' }}"; // default from URL

    document.addEventListener("DOMContentLoaded", function () {
        const packageFromURL = "{{ $package ?? '' }}";

        // If coming directly from URL
        if (packageFromURL !== '') {
            const productPath = packageFromURL + "-plan";

            fastspring.builder.add(productPath);

            setTimeout(function() {
                fastspring.builder.checkout();
            }, 100);
        }

        // Set up button clicks
        document.querySelectorAll('.checkout-button').forEach(function(button) {
            button.addEventListener('click', function () {
                const productPath = this.getAttribute('data-package');
                
                // Update the current selected package
                currentSelectedPackage = productPath.replace('-plan', '');

                fastspring.builder.add(productPath);

                setTimeout(function() {
                    fastspring.builder.checkout();
                }, 100);
            });
        });
    });

    function onFSPopupClosed(orderReference) {
        if (orderReference) {
            console.log("Order ID:", orderReference.id);
            fastspring.builder.reset();

            // Correct dynamic redirection based on user selection
            window.location.replace("/package/" + currentSelectedPackage);
        } else {
            console.log("Checkout was closed without a completed transaction.");
        }
    }
</script>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        document.getElementById('get-started-free').addEventListener('click', function() {
            if (!this.disabled) {
                window.location.href = "{{ route('subscription.general') }}?package_name=free";
            }
        });
    });
</script>



<div class="pricing-header">
    <img src="https://syntopia.ai/wp-content/uploads/2025/01/logo-syntopia-black-scaled.webp" alt="Syntopia Logo">
    <button type="button" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
    Log out
</button>

<form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
    @csrf
</form>

  </div>
  <div class="pricing-wrapper">
    <div class="container">
        <div class="badge-wrapper">
            <div class="pricing-badge">PRICING PLANS</div>
          </div>
          
      <h2 class="section-title">Plans For Every Type of Business</h2>
      <p class="section-subtitle">SYNTOPIA creates hyperrealistic, interactive AI avatars that revolutionize how businesses and individuals connect with their audiences. Our avatars can:</p>
      <div class="pricing-grid">
        <!-- Free Plan -->
        <div class="card card-light">
          <h3>Free</h3>
          <p class="price">$0 <span class="per-month">/month</span></p>
          <button class="btn dark" id="get-started-free" 
          {{ $latest_order_package == 'Free' ? 'disabled' : '' }}>
          {{ $latest_order_package == 'Free' ? 'Activated' : 'Get Started' }}
        </button>
          </button>
          <p class="included-title">What's included</p>
          <ul class="features">
            <li><span class="icon"></span> 1 user</li>
            <li><span class="icon"></span> 1 livestream room</li>
            <li><span class="icon"></span> 1 live broadcast (single anchor)</li>
            <li><span class="icon"></span> <strong>Lite Live Stream (one anchor)</strong></li>
            <li><span class="icon"></span> 1 Q&A base</li>
            <li><span class="icon"></span> 10 min live stream duration</li>
            <li><span class="icon"></span> 5MB storage</li>
            <li><span class="icon"></span> 5 min video synthesis</li>
          </ul>
        </div>

        <!-- Starter Plan -->
        <div class="card card-light">
          <h3>Starter</h3>
          <p class="price">$390 <span class="per-month">/60hrs a month</span></p>
          <button class="btn dark checkout-button" data-package="starter-plan" 
          {{ $latest_order_package == 'Starter' ? 'disabled' : '' }}>
          {{ $latest_order_package == 'Starter' ? 'Activated' : 'Get Started' }}
        </button>
        

          <p class="included-title">What's included</p>
          <ul class="features">
            <li><span class="icon"></span> 1 user</li>
            <li><span class="icon"></span> 1 livestream room</li>
            <li><span class="icon"></span> 1 live broadcast (single anchor)</li>
            <li><span class="icon"></span> <strong>Lite Live Stream (one anchor)</strong></li>
            <li><span class="icon"></span> 1 livestream account</li>
            <li><span class="icon"></span> 1 Q&A base</li>
            <li><span class="icon"></span> 60 hrs streaming</li>
            <li><span class="icon"></span> 5MB storage</li>
            <li><span class="icon"></span> AI: 10 creations, 10 rewrites</li>
            <li><span class="icon"></span> 5 min video synthesis</li>
          </ul>
        </div>

        <!-- Pro Plan -->
        <div class="card card-dark">
          <h3>Pro</h3>
          <p class="price">$780 <span class="per-month">/120hrs a month</span></p>
<!-- Button with Conditional Text and Disable Logic for Pro Plan -->
<button class="btn purple checkout-button" data-package="pro-plan" 
  {{ $latest_order_package == 'Pro' ? 'disabled' : '' }}>
  {{ $latest_order_package == 'Pro' ? 'Activated' : 'Get Started' }}
</button>
          
          <p class="included-title">What's included</p>
          <ul class="features">
            <li><span class="icon"></span> 2 users</li>
            <li><span class="icon"></span> 3 livestream rooms</li>
            <li><span class="icon"></span> 3 live broadcasts (single anchor)</li>
            <li><span class="icon"></span> <strong>Dual Live Stream (two anchor in one live room)</strong></li>
            <li><span class="icon"></span> Pro Live Stream</li>
            <li><span class="icon"></span> 3 livestream accounts</li>
            <li><span class="icon"></span> 3 Q&A base</li>
            <li><span class="icon"></span> 120 hrs streaming</li>
            <li><span class="icon"></span> 5MB storage</li>
            <li><span class="icon"></span> AI: 30 creations, 30 rewrites</li>
            <li><span class="icon"></span> 20 min video synthesis</li>
          </ul>
        </div>

        <!-- Business Plan -->
        <div class="card card-light">
          <h3>Business</h3>
          <p class="price">$2800 <span class="per-month">/unlimited</span></p>
          <button class="btn dark checkout-button" data-package="business-plan" 
            {{ $latest_order_package == 'Business' ? 'disabled' : '' }}>
            {{ $latest_order_package == 'Business' ? 'Activated' : 'Get Started' }}
          </button>

          <p class="included-title">What's included</p>
          <ul class="features">
            <li><span class="icon"></span> 3 users</li>
            <li><span class="icon"></span> 1 livestream room</li>
            <li><span class="icon"></span> 1 live broadcast</li>
            <li><span class="icon"></span> <strong>Dual Live Stream (two anchor in one live room)</strong></li>
            <li><span class="icon"></span> Pro Live Stream</li>
            <li><span class="icon"></span> Video Live Stream</li>
            <li><span class="icon"></span> 3 livestream accounts</li>
            <li><span class="icon"></span> 3 Q&A base</li>
            <li><span class="icon"></span> Unlimited streaming</li>
            <li><span class="icon"></span> 5MB storage</li>
            <li><span class="icon"></span> AI: 90 creations, 90 rewrites</li>
            <li><span class="icon"></span> 60 min video synthesis</li>
          </ul>
        </div>

        <!-- Enterprise Plan -->
        <div class="card card-dark last">
          <h3>Enterprise</h3>
          <p class="price">Custom</p>
          <button class="btn white">Get in Touch</button>
          <p class="included-title">What's included</p>
          <ul class="features">
            <li><span class="icon"></span> Custom users & rooms</li>
            <li><span class="icon"></span> Custom livestream features</li>
            <li><span class="icon"></span> Custom Q&A bases</li>
            <li><span class="icon"></span> Custom AI & video tools</li>
            <li><span class="icon"></span> Unlimited resources</li>
            <li><span class="icon"></span> Tailored support & solutions</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
  <div class="addons-wrapper">
    <div class="container">
      <div class="badge-wrapper">
        <div class="pricing-badge">Addons</div>
      </div>
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
      
      
    </div>
  </div>
  
  <footer>
    Having trouble? Contact us at
    <a href="mailto:support@syntopia.ai">support@syntopia.ai</a>
  </footer>

</body>
</html>
