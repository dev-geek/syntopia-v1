<div class="password-notification-container">
    <div class="password-notification-box">
        <div class="password-notification-content">
            <div class="notification-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <div class="notification-text">
                <h5 class="notification-title">Important Notice</h5>
                <p class="notification-message">Please login to the software using your email and password.</p>
            </div>
        </div>
    </div>
</div>

<style>
.password-notification-container {
    margin: 1.5rem 0;
}

.password-notification-box {
    background: linear-gradient(135deg, #fff5f5 0%, #fef7f0 100%);
    border: 1px solid #fed7aa;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(254, 215, 170, 0.3);
    overflow: hidden;
    position: relative;
}

.password-notification-box::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #f59e0b, #f97316, #ea580c);
}

.password-notification-content {
    display: flex;
    align-items: center;
    padding: 1.5rem;
    gap: 1rem;
}

.notification-icon {
    flex-shrink: 0;
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #f59e0b, #f97316);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
}

.notification-icon i {
    color: white;
    font-size: 1.25rem;
}

.notification-text {
    flex: 1;
    min-width: 0;
}

.notification-title {
    color: #92400e;
    font-weight: 600;
    font-size: 1.1rem;
    margin: 0 0 0.5rem 0;
    line-height: 1.3;
}

.notification-message {
    color: #a16207;
    margin: 0;
    font-size: 0.95rem;
    line-height: 1.5;
}

.notification-action {
    flex-shrink: 0;
}

.btn-set-password {
    background: linear-gradient(135deg, #0d6efd, #0b5ed7);
    border: none;
    border-radius: 8px;
    padding: 0.75rem 1.5rem;
    font-weight: 500;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
}

.btn-set-password:hover {
    background: linear-gradient(135deg, #0b5ed7, #0a58ca);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(13, 110, 253, 0.4);
    color: white;
    text-decoration: none;
}

.btn-set-password:active {
    transform: translateY(0);
    box-shadow: 0 2px 8px rgba(13, 110, 253, 0.3);
}

.btn-set-password i {
    font-size: 0.9rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .password-notification-content {
        flex-direction: column;
        text-align: center;
        padding: 1.25rem;
        gap: 1rem;
    }

    .notification-icon {
        width: 40px;
        height: 40px;
    }

    .notification-icon i {
        font-size: 1rem;
    }

    .notification-title {
        font-size: 1rem;
    }

    .notification-message {
        font-size: 0.9rem;
    }

    .btn-set-password {
        width: 100%;
        justify-content: center;
        padding: 0.875rem 1.25rem;
    }
}

@media (max-width: 480px) {
    .password-notification-box {
        border-radius: 8px;
    }

    .password-notification-content {
        padding: 1rem;
    }

    .notification-icon {
        width: 36px;
        height: 36px;
    }

    .notification-icon i {
        font-size: 0.9rem;
    }
}

/* Animation for better UX */
.password-notification-box {
    animation: slideInUp 0.5s ease-out;
}

@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Hover effects */
.password-notification-box:hover {
    box-shadow: 0 6px 25px rgba(254, 215, 170, 0.4);
    transform: translateY(-2px);
    transition: all 0.3s ease;
}

.notification-icon:hover {
    transform: scale(1.05);
    transition: transform 0.2s ease;
}
</style>
