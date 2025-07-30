<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Success</title>
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
