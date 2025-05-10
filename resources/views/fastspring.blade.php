<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>FastSpring Checkout</title>

    <!-- FastSpring Store Builder Script -->
    <script
        id="fsc-api"
        src="https://sbl.onfastspring.com/sbl/1.0.3/fastspring-builder.min.js"
        type="text/javascript"
        data-storefront="livebuzzstudio.test.onfastspring.com/popup-check-paymet"
        data-popup-closed="onFSPopupClosed">
    </script>
</head>
<body>

    <h1>FastSpring Test Checkout</h1>

    <!-- Optional button in case user closes and wants to try again -->
    <!-- <button id="checkout-button">Checkout Now</button> -->

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const productPath = "starter-syntopia"; // Your product path

            // Add product to cart
            fastspring.builder.add(productPath);

            // Automatically trigger checkout
            fastspring.builder.checkout();

            // Optional: Manual retry button
            document.getElementById('checkout-button').addEventListener('click', function () {
                fastspring.builder.checkout();
            });
        });

        // Handle popup closed event
        function onFSPopupClosed(orderReference) {
            if (orderReference) {
                console.log("Order ID:", orderReference.id);
                fastspring.builder.reset(); // Clear the cart/session
                window.location.replace("{{ route('/') }}");


            } else {
                console.log("Checkout was closed without a completed transaction.");
            }
        }
    </script>

</body>
</html>
