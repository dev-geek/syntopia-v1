<div class="login-to-software-notification-container">
    <div class="login-to-software-notification-box">
        <div class="login-to-software-notification-content">
            <div class="login-to-software-notification-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <div class="login-to-software-notification-text">
                <p class="login-to-software-notification-message">Please login to the software using your email and password. Your email address is <b>{{ Auth::user()->email }}</b> and the password is <b>{{ Auth::user()->subscriber_password }}</b></p>
            </div>
        </div>
    </div>
</div>
