<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Success</title>
{{-- FirstPromoter Tracking Script Starts here --}}
<script>
    (function(w){w.fpr=w.fpr||function(){w.fpr.q = w.fpr.q||[];w.fpr.q[arguments[0]=='set'?'unshift':'push'](arguments);};})(window);
    fpr("init", {cid:"s5acbg16"});
    fpr("click");
</script>
<script src="https://cdn.firstpromoter.com/fpr.js" async></script>
{{-- FirstPromoter Tracking Script Ends here --}}
    <!-- Facebook Pixel Code -->
    <script>
    !function(f,b,e,v,n,t,s)
    {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};
    if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
    n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;s=b.getElementsByTagName(e)[0];
    s.parentNode.insertBefore(t,s)}(window,document,'script',
    'https://connect.facebook.net/en_US/fbevents.js');
    fbq('init', '1908567163348981');
    fbq('track', 'PageView');
    </script>
    <noscript>
    <img height="1" width="1"
    src="https://www.facebook.com/tr?id=1908567163348981&ev=PageView
    &noscript=1"/>
    </noscript>
    <!-- End Facebook Pixel Code -->

    <!-- TikTok Pixel Code Start -->

<script>

!function (w, d, t) {

  w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie","holdConsent","revokeConsent","grantConsent"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(

var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var r="https://analytics.tiktok.com/i18n/pixel/events.js",o=n&&n.partner;ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=r,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};n=document.createElement("script")

;n.type="text/javascript",n.async=!0,n.src=r+"?sdkid="+e+"&lib="+t;e=document.getElementsByTagName("script")[0];e.parentNode.insertBefore(n,e)};





  ttq.load('D4I1AK3C77U1KRQJK2Q0');

  ttq.page();

}(window, document, 'ttq');

</script>

<!-- TikTok Pixel Code End -->

    <script defer src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Check if this is a popup redirect
            const urlParams = new URLSearchParams(window.location.search);
            const isPopup = urlParams.get('popup') === 'true';
            const gateway = urlParams.get('gateway');

            if (isPopup && gateway === 'payproglobal') {
                // Set success flag in parent window's sessionStorage
                if (window.opener) {
                    window.opener.sessionStorage.setItem('payProGlobalSuccess', 'true');
                    window.opener.postMessage({
                        type: 'payproglobal_success',
                        orderId: urlParams.get('order_id'),
                        userId: urlParams.get('user_id'),
                        packageName: urlParams.get('package')
                    }, '*');
                }
                // Close the popup after a short delay
                setTimeout(() => {
                    window.close();
                }, 1000);
            }

            // Check for error message first
            @if(session('error'))
                Swal.fire({
                    icon: 'error',
                    title: 'Payment Failed',
                    text: '{{ session("error") }}',
                    confirmButtonText: 'Go to Dashboard'
                }).then(() => {
                    window.location.href = '/user/dashboard';
                });
            @else
                // Track Facebook Pixel Purchase event on successful payment
                @if(session('success'))
                    if (typeof fbq !== 'undefined') {
                        const urlParams = new URLSearchParams(window.location.search);
                        const value = parseFloat('{{ session("order_amount", 0) }}') || 0;
                        const currency = 'USD';
                        const contentName = '{{ session("package_name", "Subscription") }}';

                        fbq('track', 'Purchase', {
                            value: value,
                            currency: currency,
                            content_name: contentName
                        });
                        console.log('Facebook Pixel Purchase event tracked:', {
                            value: value,
                            currency: currency,
                            content_name: contentName
                        });
                    }

                    // Track TikTok Pixel Purchase event on successful payment
                    if (typeof ttq !== 'undefined') {
                        const urlParams = new URLSearchParams(window.location.search);
                        const value = parseFloat('{{ session("order_amount", 0) }}') || 0;
                        const currency = 'USD';
                        const contentName = '{{ session("package_name", "Subscription") }}';

                        ttq.track('CompletePayment', {
                            value: value,
                            currency: currency,
                            content_name: contentName
                        });
                        console.log('TikTok Pixel Purchase event tracked:', {
                            value: value,
                            currency: currency,
                            content_name: contentName
                        });
                    }
                @endif

                // Show success message
                Swal.fire({
                    icon: 'success',
                    title: 'Payment Successful',
                    text: '{{ session("success", "Your payment has been processed successfully.") }}',
                    confirmButtonText: 'Go to Dashboard'
                }).then(() => {
                    window.location.href = '/user/dashboard';
                });
            @endif
        });
    </script>
</head>
<body>
    <h1>Payment Successful</h1>
    <p>Your payment has been processed. You will be redirected to your dashboard shortly.</p>
    <button onclick="window.close()">Close Window</button>
</body>
</html>
