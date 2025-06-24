<!DOCTYPE html>
<html>
<head>
    <title>Payment Successful</title>
</head>
<body>
    <script>
        if (window.opener && window.opener.paymentSuccess) {
            window.opener.paymentSuccess();
        }
        window.close();
    </script>
</body>
</html>
