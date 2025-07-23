<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Processing Error</title>
    <script defer src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Show error message
            Swal.fire({
                icon: 'error',
                title: 'System Error',
                text: 'There was a problem in the system while processing your payment, so your payment has been credited back to your account. Please try again in a while.',
                confirmButtonText: 'Go to Dashboard',
                allowOutsideClick: false,
                allowEscapeKey: false
            }).then(() => {
                window.location.href = '/user/dashboard';
            });
        });
    </script>
</head>
<body>
    <div style="display: flex; justify-content: center; align-items: center; height: 100vh; font-family: Arial, sans-serif;">
        <div style="text-align: center;">
            <h1>Payment Processing Error</h1>
            <p>There was a problem in the system while processing your payment, so your payment has been credited back to your account. Please try again in a while.</p>
            <p>You will be redirected to your dashboard shortly.</p>
        </div>
    </div>
</body>
</html>
