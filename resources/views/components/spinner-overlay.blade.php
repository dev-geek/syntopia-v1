{{-- Universal Spinner Overlay Component --}}
<div class="spinner-overlay" id="spinnerOverlay">
    <div class="spinner-container">
        <div class="spinner"></div>
        <p class="spinner-text" id="spinnerText">Processing...</p>
    </div>
</div>

<style>
    /* Spinner Overlay Styles */
    .spinner-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        backdrop-filter: blur(4px);
    }

    .spinner-overlay.active {
        display: flex;
    }

    .spinner-container {
        background: white;
        padding: 30px 40px;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        text-align: center;
        max-width: 300px;
    }

    .spinner {
        border: 4px solid #f3f3f3;
        border-top: 4px solid #3e57da;
        border-radius: 50%;
        width: 50px;
        height: 50px;
        animation: spin 1s linear infinite;
        margin: 0 auto 20px;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    .spinner-text {
        color: #374151;
        font-size: 16px;
        font-weight: 500;
        margin: 0;
    }

    /* Button Spinner Styles */
    .button-spinner {
        display: none;
        margin-left: 8px;
        vertical-align: middle;
    }

    .button-spinner.active {
        display: inline-block;
    }

    .button-spinner svg {
        width: 16px;
        height: 16px;
        animation: spin 1s linear infinite;
    }

    /* Loading state for buttons */
    .btn-loading,
    .primary-button:disabled,
    button:disabled[data-loading="true"] {
        opacity: 0.7;
        cursor: not-allowed;
        pointer-events: none;
    }
</style>

