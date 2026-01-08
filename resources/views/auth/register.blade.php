<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Signup - Syntopia</title>

    <!-- Favicon -->
    <link rel="icon" type="image/webp" href="{{ asset('syntopia-logo.webp') }}">
    <link rel="shortcut icon" type="image/webp" href="{{ asset('syntopia-logo.webp') }}">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('plugins/fontawesome-free/css/all.min.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/password-toggle.css') }}">
    <!-- SweetAlert2 -->
    <script defer src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script defer src="{{ asset('js/swal-utils.js') }}"></script>
    <!-- FingerprintJS -->
    <script defer src="{{ asset('js/fingerprint.js') }}"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- FirstPromoter Tracking Script Starts here --}}
<script>
    (function(w){w.fpr=w.fpr||function(){w.fpr.q = w.fpr.q||[];w.fpr.q[arguments[0]=='set'?'unshift':'push'](arguments);};})(window);
    fpr("init", {cid:"s5acbg16"});
    fpr("click");
</script>
<script src="https://cdn.firstpromoter.com/fpr.js" async></script>
{{-- FirstPromoter Tracking Script Ends here --}}


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

    <style>
         body {
            background: url('https://syntopia.ai/wp-content/uploads/2025/01/Clip-path-group.webp') no-repeat center center fixed;
            background-size: cover;
            height: 95vh;
            padding: 30px;
        }
        .heading-text{
            font-size: 24px;
            padding-bottom: 20px;
            font-weight: 500;
           color:#000;
        }

        .container-box {
            max-width: 100%;
            width: 520px;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
        }
        .email-text{
            font-size: 13px;
        }
        .logo-container {
            text-align: center;
            margin-top: 20px;
            justify-content: center;
            align-items: left;
            display: flex;
            flex-direction: column;
        }
        .logo-container img {
            width: 160px;
        }
        .form-control {
            background: #E7E7E9;
            font-size: 13px;
            border: none !important;
            padding: 0.5em !important;
        }
        .create-account {
            border: none;
            padding: 7px 0px !important;
        }
        .back a {
            color: black !important;
            text-decoration: none;
        }
        .back:hover {
            background-color: color-mix(in srgb, rgb(43, 46, 64) 6%, rgba(0, 0, 0, 0));
        }
        .d-flex{
            height: 80vh;
        }

        label {
            font-weight: 500;
            font-size: 13px;
            text-align: left;
            display: block;
            margin-bottom: 5px;
        }

        .footer-text{
            text-align: center;
        }
        .logo{
            width: 50px;
        }
        .primary-button {
            width: 100%;
            padding: 10px;
            font-size: 13px;
            font-weight: 500;
            background:rgb(62, 87, 218);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 20px;
        }
        .text-secondar{
            font-size: 13px;
            font-weight: 500;
            color: black !important;
        }
         .text-primary, .footer-text {
            font-size: 11px;
        }

        /* Password Toggle Styles - Imported from dedicated file */
        /* Note: password-toggle.css is included separately in layouts */
        @media (max-width: 768px){

        .d-flex{
            height: 75vh;
        }
        }

    </style>
 </head>
 <body>

    @include('components.spinner-overlay')

    <!-- Logo at the top -->
    <div class="logo-container">
        <x-logo />

    </div>

    <!-- Centered Signup Form -->
    <div class="d-flex justify-content-center align-items-center">
        <div class="container-box text-center">
            <h1 class="heading-text">Welcome To Syntopia</h1>
            <p class="email-text">You're setting up an account for {{ request()->get('email') }}</p>
            @include('components.alert-messages')
            <!-- Signup Form -->
            <form id="registerForm" method="POST" action="{{ route('register') }}">
            @csrf
            <input type="hidden" name="fingerprint_id" id="fingerprintId">
                <input type="hidden" name="email" value="{{ request()->get('email') }}">

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" class="form-control"
                            placeholder="Enter your first name" required value="{{ old('first_name') }}">
                        @error('first_name')
                            <span class="invalid-feedback">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" class="form-control"
                            placeholder="Enter your last name" required value="{{ old('last_name') }}">
                        @error('last_name')
                            <span class="invalid-feedback">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>
                </div>
                <div class="mb-3">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control"
                        value="{{ request()->get('email') }}" readonly required
                        style="background-color: #f8f9fa; cursor: not-allowed;"
                        autocomplete="off" data-lpignore="true">
                    @error('email')
                        <span class="invalid-feedback">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>
                <div class="mb-3">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control"
                        placeholder="Enter a strong password" required>
                    <small class="form-text text-muted" style="font-size: 11px; text-align: left; display: block; margin-top: 5px;">
                        <strong>Password requirements:</strong> At least 8 characters with uppercase, lowercase, number, and special character.<br>
                        <strong>Allowed special characters:</strong> , &lt; &gt; { } ~ ! @ # $ % ^ & _
                    </small>
                    @error('password')
                        <span class="invalid-feedback d-block">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>
                <button type="submit" class="primary-button">Create account</button>
            </form>

            <!-- Back Button -->
            <div class="back">
                <a href="{{ route('login') }}" class="text-secondary">Back</a>
            </div>

            <!-- Terms & Privacy -->
            <p class="text-muted mt-3" style="font-size: 11px;">
                By joining the workspace, you agree to our
                <a href="#" class="text-primary">User Terms of Services</a> and
                <a href="#" class="text-primary">Privacy Policy</a>.

            </p>

        </div>
    </div>
    <div class="footer-text ">
         Having trouble? Contact us at
                <a href="mailto:info@syntopia.ai" class="text-primary">info@syntopia.ai</a>.
    </div>


    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Password Toggle Script -->
    <script src="{{ asset('js/password-toggle.js') }}"></script>

    <!-- Global Spinner Utilities -->
    <script src="{{ asset('js/spinner-utils.js') }}"></script>

    <!-- SWAL Error Handling -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle SWAL errors from session
            @if(session('swal_error'))
                SwalUtils.showError(@json(session('swal_error')));
            @endif

            // Handle regular errors from session
            @if(session('error'))
                SwalUtils.showError(@json(session('error')));
            @endif

            // Handle success messages from session
            @if(session('success'))
                SwalUtils.showSuccess(@json(session('success')));
            @endif
        });
    </script>

    <script>
    // Ensure email field is completely locked and cannot be modified
    document.addEventListener('DOMContentLoaded', function() {
        const emailInput = document.getElementById('email');
        const originalEmail = emailInput.value;

        // Prevent any modifications to the email field
        emailInput.addEventListener('input', function(e) {
            if (this.value !== originalEmail) {
                this.value = originalEmail;
            }
        });

        emailInput.addEventListener('paste', function(e) {
            e.preventDefault();
            return false;
        });

        emailInput.addEventListener('keydown', function(e) {
            // Allow only navigation keys (arrow keys, home, end, etc.)
            const allowedKeys = [8, 9, 13, 16, 17, 18, 19, 20, 27, 33, 34, 35, 36, 37, 38, 39, 40, 45, 46];
            if (!allowedKeys.includes(e.keyCode) && !e.ctrlKey && !e.metaKey) {
                e.preventDefault();
                return false;
            }
        });

        // Prevent context menu on email field
        emailInput.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            return false;
        });

        // Ensure the value is always the original email
        emailInput.addEventListener('blur', function() {
            if (this.value !== originalEmail) {
                this.value = originalEmail;
            }
        });

        // Prevent form submission if email was somehow changed
        document.querySelector('form').addEventListener('submit', function(e) {
            if (emailInput.value !== originalEmail) {
                e.preventDefault();
                alert('Email address cannot be modified. Please use the email from the login page.');
                emailInput.value = originalEmail;
                return false;
            }
        });
    });
    </script>
</body>
</html>
