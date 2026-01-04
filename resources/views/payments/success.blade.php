<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Success</title>
    <x-firstpromoter-tracking />
    <x-facebook-pixel />

    <x-tiktok-pixel />

    <script defer src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Check if this is a popup redirect
            const urlParams = new URLSearchParams(window.location.search);
            const isPopup = urlParams.get('popup') === 'true';
            const gateway = urlParams.get('gateway');

            alert(urlParams.get('order_id'));
            alert(urlParams.get('user_id'));
            alert(urlParams.get('package'));
            alert(isPopup);
            alert(gateway);

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
                    alert('redirecting to dashboard');
                    // window.location.href = '/user/dashboard';
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
