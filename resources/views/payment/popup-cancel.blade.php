<!DOCTYPE html>
<html>
<head>
    <title>Payment Cancelled</title>
</head>
<body>
    <script>
        if (window.opener && window.opener.paymentCancelled) {
            window.opener.paymentCancelled();
        }
        window.close();
    </script>
</body>
</html>
