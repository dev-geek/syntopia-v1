<div class="password-notification-container">
    <div class="password-notification-box">
        <div class="password-notification-content">
            <div class="notification-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <div class="notification-text">
                <p class="notification-message">Please login to the software using your email and password. Your email address is <b>{{ Auth::user()->email }}</b> and the password is <b>{{ Auth::user()->subscriber_password }}</b></p>
            </div>
        </div>
    </div>
</div>

<style>
.password-notification-container {
    margin: 0 0.5rem;
}

.password-notification-box {
    background: linear-gradient(135deg, #fff5f5 0%, #fef7f0 100%);
    border: 1px solid #fed7aa;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(254, 215, 170, 0.3);
    overflow: hidden;
    position: relative;
    max-width: 400px;
    min-width: 350px;
}

.password-notification-box::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, #f59e0b, #f97316, #ea580c);
}

.password-notification-content {
    display: flex;
    align-items: center;
    padding: 0.75rem;
    gap: 0.75rem;
}

.notification-icon {
    flex-shrink: 0;
    width: 32px;
    height: 32px;
    background: linear-gradient(135deg, #f59e0b, #f97316);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 6px rgba(245, 158, 11, 0.3);
}

.notification-icon i {
    color: white;
    font-size: 0.875rem;
}

.notification-text {
    flex: 1;
    min-width: 0;
}

.notification-message {
    color: #a16207;
    margin: 0;
    font-size: 0.8rem;
    line-height: 1.3;
    font-weight: 500;
}

/* Responsive Design */
@media (max-width: 768px) {
    .password-notification-container {
        margin: 0 0.25rem;
    }

    .password-notification-box {
        max-width: 320px;
        min-width: 280px;
    }

    .password-notification-content {
        padding: 0.5rem;
        gap: 0.5rem;
    }

    .notification-icon {
        width: 28px;
        height: 28px;
    }

    .notification-icon i {
        font-size: 0.75rem;
    }

    .notification-message {
        font-size: 0.75rem;
    }
}

@media (max-width: 576px) {
    .password-notification-box {
        max-width: 280px;
        min-width: 250px;
    }

    .notification-message {
        font-size: 0.7rem;
    }
}

/* Animation for better UX */
.password-notification-box {
    animation: slideInRight 0.4s ease-out;
}

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

/* Hover effects */
.password-notification-box:hover {
    box-shadow: 0 4px 12px rgba(254, 215, 170, 0.4);
    transform: translateY(-1px);
    transition: all 0.3s ease;
}

.notification-icon:hover {
    transform: scale(1.05);
    transition: transform 0.2s ease;
}
</style>
