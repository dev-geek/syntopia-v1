<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="Content-Security-Policy"
        content="
            default-src 'self' data: gap: https://ssl.gstatic.com https://livebuzzstudio.test;
            style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://sbl.onfastspring.com https://cdn.paddle.com https://sandbox-cdn.paddle.com https://store.payproglobal.com https://secure.payproglobal.com;
            font-src 'self' https://fonts.gstatic.com;
            script-src 'self' https://livebuzzstudio.test https://somedomain.com https://sbl.onfastspring.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://cdn.paddle.com https://sandbox-cdn.paddle.com https://secure.payproglobal.com https://store.payproglobal.com 'unsafe-inline' 'unsafe-eval';
            img-src 'self' https://syntopia.ai https://sbl.onfastspring.com https://store.payproglobal.com data:;
            connect-src 'self' https://livebuzzstudio.test https://livebuzzstudio.test.onfastspring.com https://sbl.onfastspring.com https://sandbox-api.paddle.com https://sandbox-cdn.paddle.com https://store.payproglobal.com https://secure.payproglobal.com;
            frame-src 'self' https://buy.paddle.com https://livebuzzstudio.test https://livebuzzstudio.test.onfastspring.com https://sbl.onfastspring.com https://cdn.paddle.com https://sandbox-cdn.paddle.com https://sandbox-buy.paddle.com https://store.payproglobal.com https://secure.payproglobal.com;
            media-src 'self' data: https://sbl.onfastspring.com https://store.payproglobal.com;">
    <title>Syntopia Pricing</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Loading Spinner Styles -->
    <style>
        .loading-spinner {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            backdrop-filter: blur(5px);
        }

        .spinner-container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            text-align: center;
            max-width: 300px;
            width: 90%;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #5b0dd5;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        .spinner-text {
            color: #333;
            font-size: 16px;
            font-weight: 500;
            margin: 0;
        }

        .spinner-subtext {
            color: #666;
            font-size: 14px;
            margin: 5px 0 0 0;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .button-loading {
            position: relative;
            pointer-events: none;
            opacity: 0.7;
        }

        .button-loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid transparent;
            border-top: 2px solid #ffffff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .button-loading .btn-text {
            opacity: 0;
        }
    </style>

    <!-- Payment Gateway Scripts -->
    @php
        $activeGateways = isset($payment_gateways) ? $payment_gateways->pluck('name')->toArray() : [];
    @endphp
    @if (in_array('FastSpring', $activeGateways))
        <script src="https://sbl.onfastspring.com/js/checkout/button.js"
            data-button-id="{{ $currentLoggedInUserPaymentGateway ?? 'FastSpring' }}"></script>
    @endif
    @if (in_array('Paddle', $activeGateways))
        <script src="https://cdn.paddle.com/paddle/v2/paddle.js"></script>
    @endif
    @if (in_array('Pay Pro Global', $activeGateways))
        <script src="https://secure.payproglobal.com/js/custom/checkout.js"></script>
    @endif

    <!-- FastSpring Integration -->
    @if ($activeGateway && $activeGateway->name === 'FastSpring')
        <script id="fsc-api" src="https://sbl.onfastspring.com/sbl/1.0.3/fastspring-builder.min.js" type="text/javascript"
            data-storefront="livebuzzstudio.test.onfastspring.com/popup-test-87654-payment" data-popup-closed="onFSPopupClosed"
            data-debug="true"></script>
        <script>
            let currentProductPath = '';

            function processFastSpring(packageName, action = 'new') {
                console.log('=== processFastSpring ===', {
                    packageName,
                    action
                });
                try {
                    if (typeof fastspring === 'undefined' || !fastspring.builder) {
                        throw new Error('FastSpring is not properly initialized');
                    }

                    // Show spinner for FastSpring processing
                    showSpinner('Opening payment window...', 'Please wait while we connect to FastSpring');

                    fastspring.builder.reset();
                    console.log('FastSpring builder reset');

                    if (!packageName || typeof packageName !== 'string') {
                        throw new Error('Invalid package name: ' + packageName);
                    }

                    currentProductPath = packageName.toLowerCase();
                    sessionStorage.setItem('currentProductPath', currentProductPath);
                    fastspring.builder.add(currentProductPath);

                    if (action === 'upgrade' || action === 'downgrade') {
                        window.fastspringUpgradeContext = {
                            isUpgrade: action === 'upgrade',
                            isDowngrade: action === 'downgrade',
                            currentPackage: currentPackage,
                            targetPackage: packageName
                        };
                        console.log('FastSpring context set:', window.fastspringUpgradeContext);
                    }

                    setTimeout(() => {
                        fastspring.builder.checkout();
                        console.log('FastSpring checkout launched');
                        // Hide spinner when checkout is launched
                        hideSpinner();
                    }, 500);
                } catch (error) {
                    console.error('FastSpring processing error:', error);
                    hideSpinner(); // Hide spinner on error
                    showAlert('error', 'FastSpring Error', error.message || 'Failed to process checkout.');
                }
            }

            function onFSPopupClosed(orderData) {
                console.log('=== onFSPopupClosed ===', {
                    orderData: JSON.stringify(orderData, null, 2),
                    currentProductPath,
                    sessionProductPath: sessionStorage.getItem('currentProductPath')
                });

                try {
                    let packageName = '';
                    let subscriptionId = '';

                    if (orderData && orderData.items && orderData.items.length > 0) {
                        packageName = orderData.items[0].product
                            ?.toLowerCase()
                            .replace('-plan', '')
                            .replace(/^\w/, c => c.toUpperCase())
                            .trim() || '';
                        subscriptionId = orderData.groups[0].items[0].subscription || '';
                    }

                    if (!packageName && orderData && orderData.tags) {
                        const tags = typeof orderData.tags === 'string' ? JSON.parse(orderData.tags) : orderData.tags;
                        const possiblePackageNames = [
                            tags?.package,
                            tags?.package_name,
                            tags?.packageName,
                            tags?.packageId,
                            tags?.package_id
                        ];
                        for (const name of possiblePackageNames) {
                            if (name) {
                                packageName = name
                                    .toLowerCase()
                                    .replace('-plan', '')
                                    .replace(/^\w/, c => c.toUpperCase())
                                    .trim() || '';
                                break;
                            }
                        }
                        subscriptionId = orderData.groups[0].items[0].subscription || '';
                    }

                    if (!packageName) {
                        packageName = currentProductPath || sessionStorage.getItem('currentProductPath') || '';
                    }

                    if (!packageName) {
                        const urlParams = new URLSearchParams(window.location.search);
                        packageName = urlParams.get('package') || '';
                    }

                    if (!packageName) {
                        const formData = new FormData(document.querySelector('form'));
                        packageName = formData.get('package_name') || '';
                    }

                    if (!packageName) {
                        const packageBtn = document.querySelector('[data-package]');
                        packageName = packageBtn?.dataset.package || '';
                    }

                    if (!packageName || packageName.trim() === '') {
                        console.error('Final package name is invalid:', packageName);
                        showAlert('error', 'Package Error', 'Could not determine package name.', () => {
                            window.location.href = '/pricing?error=package';
                        });
                        return;
                    }

                    if (!orderData || (!orderData.id)) {
                        console.log('No order data or cancelled payment');
                        showAlert('info', 'Payment Cancelled', 'Your payment was cancelled.', () => {
                            window.location.href = '/pricing';
                        });
                        sessionStorage.removeItem('currentProductPath');
                        currentProductPath = '';
                        return;
                    }

                    sessionStorage.removeItem('currentProductPath');
                    currentProductPath = '';

                    const orderId = orderData.id;
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '/payments/success';
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                    if (csrfToken) {
                        const csrfInput = document.createElement('input');
                        csrfInput.type = 'hidden';
                        csrfInput.name = '_token';
                        csrfInput.value = csrfToken;
                        form.appendChild(csrfInput);
                    }
                    const gatewayInput = document.createElement('input');
                    gatewayInput.type = 'hidden';
                    gatewayInput.name = 'gateway';
                    gatewayInput.value = 'fastspring';
                    form.appendChild(gatewayInput);
                    const orderIdInput = document.createElement('input');
                    orderIdInput.type = 'hidden';
                    orderIdInput.name = 'orderId';
                    orderIdInput.value = orderId;
                    form.appendChild(orderIdInput);
                    const packageNameInput = document.createElement('input');
                    packageNameInput.type = 'hidden';
                    packageNameInput.name = 'package_name';
                    packageNameInput.value = packageName;
                    form.appendChild(packageNameInput);
                    const paymentGatewayIdInput = document.createElement('input');
                    paymentGatewayIdInput.type = 'hidden';
                    paymentGatewayIdInput.name = 'payment_gateway_id';
                    paymentGatewayIdInput.value = "{{ $activeGateway->id ?? '' }}";
                    form.appendChild(paymentGatewayIdInput);

                    document.body.appendChild(form);
                    console.log('Submitting form with data:', {
                        _token: csrfToken,
                        gateway: 'fastspring',
                        orderId: orderId,
                        package_name: packageName,
                        payment_gateway_id: "{{ $activeGateway->id ?? '' }}",
                    });
                    form.submit();
                } catch (err) {
                    console.error('Error in onFSPopupClosed:', err);
                    showAlert('error', 'Processing Error', 'There was an error processing your payment.', () => {
                        window.location.href = '/pricing?error=processing';
                    });
                    sessionStorage.removeItem('currentProductPath');
                    currentProductPath = '';
                }
            }
        </script>
    @endif

    <!-- Paddle Integration -->
    @if ($activeGateway && $activeGateway->name === 'Paddle')
        <script src="https://cdn.paddle.com/paddle/v2/paddle.js"></script>
        <script>
            // Global function to handle Paddle events
            window.handlePaddleEvent = function(eventData, action) {
                console.log('=== PADDLE EVENT HANDLER ===', {
                    eventData: eventData,
                    action: action
                });

                console.log('=== DETAILED EVENT DATA ANALYSIS ===');
                console.log('eventData type:', typeof eventData);
                console.log('eventData keys:', Object.keys(eventData || {}));
                console.log('Full eventData JSON:', JSON.stringify(eventData, null, 2));

                // Handle different event structures that Paddle might send
                let eventName = null;
                let transactionId = null;

                // Try different possible event structures
                if (eventData.data?.event?.name) {
                    eventName = eventData.data.event.name;
                    transactionId = eventData.data.event.data?.transaction_id;
                } else if (eventData.event?.name) {
                    eventName = eventData.event.name;
                    transactionId = eventData.event.data?.transaction_id;
                } else if (eventData.name) {
                    eventName = eventData.name;
                    transactionId = eventData.data?.transaction_id;
                } else if (eventData.type) {
                    eventName = eventData.type;
                    transactionId = eventData.data?.transaction_id || eventData.transaction_id;
                }

                // Fallback: Try to extract transaction ID from URL if not found in event data
                if (!transactionId && eventData.data) {
                    // Look for transaction ID in various possible locations
                    transactionId = eventData.data.transaction_id ||
                        eventData.data.id ||
                        eventData.data.transactionId ||
                        eventData.data.transactionId;
                }

                // Additional fallback: Extract from URL if still not found
                if (!transactionId) {
                    const urlParams = new URLSearchParams(window.location.search);
                    const ptxn = urlParams.get('_ptxn');
                    if (ptxn) {
                        transactionId = ptxn;
                        console.log('Extracted transaction ID from URL:', transactionId);
                    }
                }

                console.log('Parsed event data:', {
                    eventName: eventName,
                    transactionId: transactionId
                });

                console.log('Full eventData structure:', JSON.stringify(eventData, null, 2));

                if (eventName === 'checkout.completed' || eventName === 'transaction.completed') {
                    console.log('=== PADDLE CHECKOUT COMPLETED ===', {
                        eventName: eventName,
                        transactionId: transactionId,
                        action: action
                    });

                    // When Paddle checkout is completed, redirect to the success URL
                    // This will trigger the handleSuccess method in PaymentController
                    if (transactionId) {
                        console.log('Redirecting to success URL with transaction ID:', transactionId);
                        const successUrl = `/payments/success?gateway=paddle&transaction_id=${transactionId}`;
                        window.location.href = successUrl;
                    } else {
                        console.log('No transaction ID found in event data, trying fallback methods...');

                        // Try to get transaction ID from session storage as final fallback
                        const sessionTransactionId = sessionStorage.getItem('currentPaddleTransactionId');
                        if (sessionTransactionId) {
                            console.log('Using transaction ID from session storage:', sessionTransactionId);
                            const successUrl = `/payments/success?gateway=paddle&transaction_id=${sessionTransactionId}`;
                            window.location.href = successUrl;
                        } else {
                            console.log(
                                'No transaction ID found anywhere, redirecting to success URL without transaction ID');
                            console.log('This may cause issues - the backend will need to handle this case');
                            const successUrl = `/payments/success?gateway=paddle`;
                            window.location.href = successUrl;
                        }
                    }
                } else if (eventName === 'checkout.failed' || eventName === 'transaction.failed') {
                    window.showError(`${action.charAt(0).toUpperCase() + action.slice(1)} Failed`,
                        'Your action failed. Please try again.');
                } else if (eventName === 'transaction.cancelled') {
                    if (!eventData.success) {
                        window.showInfo(`${action.charAt(0).toUpperCase() + action.slice(1)} Cancelled`,
                            'Your action was cancelled.');
                    }
                } else {
                    console.log('Unhandled Paddle event:', eventName, eventData);
                }
            };

            document.addEventListener('DOMContentLoaded', function() {
                try {
                    Paddle.Environment.set('{{ config('payment.gateways.Paddle.environment', 'sandbox') }}');
                    Paddle.Setup({
                        token: '{{ config('payment.gateways.Paddle.client_side_token') }}',
                    });

                    // Add global event listener for Paddle events
                    window.addEventListener('message', function(event) {
                        if (event.origin.includes('paddle.com') || event.origin.includes('cdn.paddle.com')) {
                            console.log('Paddle message received:', event.data);

                            // Check if this is a Paddle checkout event
                            if (event.data && event.data.action === 'event' && event.data.event_name) {
                                // Try to determine the action from session storage or URL
                                const currentAction = sessionStorage.getItem('currentPaddleAction') || 'new';

                                // Handle the event based on event_name
                                if (event.data.event_name === 'checkout.completed') {
                                    console.log('=== PADDLE CHECKOUT.COMPLETED EVENT RECEIVED ===');
                                    console.log('Event data:', event.data);
                                    console.log('Callback data:', event.data.callback_data);
                                    console.log('Current action from session storage:', currentAction);

                                    // Extract transaction ID from callback data with comprehensive fallbacks
                                    let transactionId = null;

                                    console.log('=== TRANSACTION ID EXTRACTION DEBUG ===');
                                    console.log('Full event.data:', JSON.stringify(event.data, null, 2));
                                    console.log('event.data.callback_data:', event.data.callback_data);

                                    // Try multiple possible locations for transaction ID
                                    if (event.data.callback_data) {
                                        const callbackData = event.data.callback_data;
                                        console.log('Callback data structure:', JSON.stringify(callbackData,
                                            null, 2));

                                        // Try different possible field names
                                        transactionId = callbackData.transaction_id ||
                                            callbackData.id ||
                                            callbackData.transactionId ||
                                            callbackData.transactionId ||
                                            callbackData.transaction_id ||
                                            callbackData.order_id ||
                                            callbackData.orderId;
                                    }

                                    // If still not found, try the main event data
                                    if (!transactionId && event.data) {
                                        transactionId = event.data.transaction_id ||
                                            event.data.id ||
                                            event.data.transactionId ||
                                            event.data.order_id ||
                                            event.data.orderId;
                                    }

                                    // If still not found, try URL parameters
                                    if (!transactionId) {
                                        const urlParams = new URLSearchParams(window.location.search);
                                        const ptxn = urlParams.get('_ptxn');
                                        const txn = urlParams.get('txn');
                                        const transaction = urlParams.get('transaction');

                                        transactionId = ptxn || txn || transaction;
                                        console.log('URL parameters check:', {
                                            ptxn,
                                            txn,
                                            transaction
                                        });

                                        // Also check if we're on a Paddle success URL
                                        if (window.location.pathname.includes('/payments/success')) {
                                            console.log(
                                                'Currently on success URL, checking for transaction ID in URL'
                                            );
                                            const successUrlParams = new URLSearchParams(window.location
                                                .search);
                                            const successTransactionId = successUrlParams.get('transaction_id');
                                            if (successTransactionId) {
                                                console.log('Found transaction ID in success URL:',
                                                    successTransactionId);
                                                transactionId = successTransactionId;
                                            }
                                        }
                                    }

                                    // If still not found, try session storage
                                    if (!transactionId) {
                                        transactionId = sessionStorage.getItem('currentPaddleTransactionId');
                                        console.log('Session storage transaction ID:', transactionId);
                                    }

                                    console.log('Final extracted transaction ID:', transactionId);

                                    window.handlePaddleEvent({
                                        type: 'checkout.completed',
                                        data: event.data.callback_data
                                    }, currentAction);
                                } else if (event.data.event_name === 'checkout.failed') {
                                    console.log('Processing checkout.failed event');
                                    window.handlePaddleEvent({
                                        type: 'checkout.failed',
                                        data: event.data.callback_data
                                    }, currentAction);
                                }
                            }
                        }
                    });

                } catch (error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Payment System Error',
                        text: 'We cannot process payments at this moment.',
                        confirmButtonText: 'OK'
                    });
                }
            });
        </script>
    @endif

    <!-- PayProGlobal Integration -->
    @if ($activeGateway && $activeGateway->name === 'Pay Pro Global')
        <script>
            // Enhanced PayProGlobal popup monitoring
            let payProGlobalPopup = null;
            let popupCheckInterval = null;

            // Handle PayProGlobal popup communication
            window.addEventListener('message', function(event) {
                console.log('Received message:', event.data, 'Origin:', event.origin);

                // Check if message is from PayProGlobal
                if (event.origin.includes('payproglobal.com') || event.origin.includes('store.payproglobal.com')) {
                    console.log('PayProGlobal message received:', event.data);

                    if (event.data.type === 'payproglobal_success') {
                        const {
                            orderId,
                            userId,
                            packageName
                        } = event.data;
                        console.log('Processing PayProGlobal success:', {
                            orderId,
                            userId,
                            packageName
                        });

                        // Clear any intervals
                        if (popupCheckInterval) {
                            clearInterval(popupCheckInterval);
                        }

                        // Close popup if still open
                        if (payProGlobalPopup && !payProGlobalPopup.closed) {
                            payProGlobalPopup.close();
                        }

                        // Create form to submit to handleSuccess
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '/payments/success';

                        const csrfInput = document.createElement('input');
                        csrfInput.type = 'hidden';
                        csrfInput.name = '_token';
                        csrfInput.value = csrfToken;
                        form.appendChild(csrfInput);

                        const gatewayInput = document.createElement('input');
                        gatewayInput.type = 'hidden';
                        gatewayInput.name = 'gateway';
                        gatewayInput.value = 'payproglobal';
                        form.appendChild(gatewayInput);

                        const orderIdInput = document.createElement('input');
                        orderIdInput.type = 'hidden';
                        orderIdInput.name = 'OrderId'; // Use PayProGlobal's format
                        orderIdInput.value = orderId;
                        form.appendChild(orderIdInput);

                        const userIdInput = document.createElement('input');
                        userIdInput.type = 'hidden';
                        userIdInput.name = 'user_id';
                        userIdInput.value = userId;
                        form.appendChild(userIdInput);

                        const packageInput = document.createElement('input');
                        packageInput.type = 'hidden';
                        packageInput.name = 'package';
                        packageInput.value = packageName;
                        form.appendChild(packageInput);

                        const popupInput = document.createElement('input');
                        popupInput.type = 'hidden';
                        popupInput.name = 'popup';
                        popupInput.value = 'true';
                        form.appendChild(popupInput);

                        document.body.appendChild(form);
                        console.log('Submitting PayProGlobal success form');
                        form.submit();
                    }
                }
            });

            // Monitor PayProGlobal popup for URL changes
            function monitorPayProGlobalPopup(popup) {
                payProGlobalPopup = popup;
                popupCheckInterval = setInterval(() => {
                    try {
                        if (popup.closed) {
                            clearInterval(popupCheckInterval);
                            console.log('PayProGlobal popup closed');

                            // Check if we have a success flag
                            const successUrl = sessionStorage.getItem('payProGlobalSuccessUrl');
                            if (successUrl) {
                                console.log('Redirecting to success URL:', successUrl);
                                window.location.href = successUrl;
                            } else {
                                showInfo('Payment Cancelled', 'Your payment was cancelled or incomplete.');
                            }

                            // Clean up
                            sessionStorage.removeItem('payProGlobalSuccessUrl');
                            sessionStorage.removeItem('payProGlobalUserId');
                            sessionStorage.removeItem('payProGlobalPackageName');
                            return;
                        }

                        // Try to check the popup URL for the thank you page
                        try {
                            const popupUrl = popup.location.href;
                            if (popupUrl && popupUrl.includes('/thankyou')) {
                                console.log('PayProGlobal thank you page detected');

                                // Extract OrderId from the URL
                                const urlParams = new URLSearchParams(popup.location.search);
                                const orderId = urlParams.get('OrderId');

                                if (orderId) {
                                    console.log('Found OrderId in thank you URL:', orderId);
                                    sessionStorage.setItem('payProGlobalSuccessUrl',
                                        `/payments/success?gateway=payproglobal&order_id=${orderId}&user_id=${sessionStorage.getItem('payProGlobalUserId')}&package=${sessionStorage.getItem('payProGlobalPackageName')}`
                                    );

                                    clearInterval(popupCheckInterval);
                                    setTimeout(() => popup.close(), 1000);
                                }
                            }
                        } catch (e) {
                            // Cross-origin error expected, we'll rely on postMessage
                        }
                    } catch (error) {
                        console.error('Popup monitoring error:', error);
                        clearInterval(popupCheckInterval);
                    }
                }, 500);
            }
            // This script will run on the PayProGlobal domain and send the OrderId back
            const thankYouScript = `
                            <script>
                            (function() {
                                if (window.location.href.includes('/thankyou')) {
                                    const urlParams = new URLSearchParams(window.location.search);
                                    const orderId = urlParams.get('OrderId');
                                    if (orderId && window.opener) {
                                        window.opener.postMessage({
                                            type: 'payproglobal_success',
                                            orderId: orderId,
                                            userId: '${sessionStorage.getItem('payProGlobalUserId') || ''}',
                                            packageName: '${sessionStorage.getItem('payProGlobalPackageName') || ''}'
                                        }, '*');
                                    }
                                }
                            })();
        </script>`;
        </script>
    @endif

    <style>
        .btn.active {
            background: #22c55e !important;
            color: white !important;
            cursor: not-allowed !important;
            opacity: 0.9;
        }

        .btn.active:hover {
            background: #22c55e !important;
            color: white !important;
        }

        .btn.disabled {
            background: #6b7280 !important;
            color: white !important;
            cursor: not-allowed !important;
            opacity: 0.8;
        }

        .btn.cancel {
            background: #ef4444 !important;
            color: white !important;
        }

        .btn.cancel:hover {
            background: #dc2626 !important;
        }

        .ppg-checkout-modal {
            z-index: 99999;
            display: none;
            background-color: transparent;
            border: 0px none transparent;
            visibility: visible;
            margin: 0px;
            padding: 0px;
            position: fixed;
            left: 0px;
            top: 0px;
            width: 100%;
            height: 100%;
        }

        .ppg-checkout-modal.ppg-show {
            display: block;
        }

        .ppg-btn-close {
            position: absolute;
            display: none;
            align-items: center;
            justify-content: center;
            top: 20px;
            right: 35px;
            background: rgb(0 0 0 / 35%);
            height: 50px;
            width: 50px;
            border: none;
            cursor: pointer;
            z-index: 100000;
        }

        .ppg-btn-close.ppg-show {
            display: flex;
        }

        .ppg-btn-close img {
            width: 24px;
        }

        .ppg-iframe {
            width: 100%;
            height: 100%;
            border: 0;
            overflow-x: hidden;
            overflow-y: auto;
        }

        .ppg-loader {
            position: absolute;
            top: calc(50% - 24px);
            left: calc(50% - 24px);
            width: 48px;
            height: 48px;
            border: 5px solid #000;
            border-bottom-color: transparent;
            border-radius: 50%;
            display: inline-block;
            box-sizing: border-box;
            animation: ppg-rotation 1s linear infinite;
            z-index: 100000;
        }

        @keyframes ppg-rotation {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #fff;
            color: #000;
            overflow-x: hidden;
        }

        .pricing-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 40px;
            border-bottom: 1px solid #e5e7eb;
        }

        .pricing-header img {
            height: 32px;
        }

        .pricing-header button {
            font-size: 14px;
            font-weight: 500;
            color: #2563eb;
            background: transparent;
            border: none;
            cursor: pointer;
        }

        .pricing-header button:hover {
            text-decoration: underline;
        }

        .pricing-wrapper {
            width: 100%;
            padding: 0px;
            border-bottom: 1px solid #EFE7FB;
        }

        .container {
            max-width: 1300px;
            margin: 0 auto;
            padding: 50px 20px;
            border-left: 1px solid #EFE7FB;
            border-right: 1px solid #EFE7FB;
        }

        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 20px;
            margin-top: 40px;
        }

        .badge-wrapper {
            text-align: center;
        }

        .pricing-badge {
            display: inline-block;
            padding: 7px 15px;
            margin-bottom: 10px;
            font-size: 16px;
            font-weight: 600;
            color: #5b0dd5;
            background-color: #f5f1fe;
            border: 1px solid #5b0dd5;
            border-radius: 999px;
            text-transform: uppercase;
        }

        .pricing-wrapper h2 {
            font-size: 65px;
        }

        .card {
            border: 1px solid #EFE7FB;
            border-radius: 10px;
            padding: 15px;
        }

        .card-light {
            background: linear-gradient(180deg, white 0%, #F2F2F7 100%);
            color: black;
        }

        .card-dark {
            background: linear-gradient(180deg, #E0347E 0%, #6B83DD 100%);
            color: white;
        }

        .card-dark.last {
            background: linear-gradient(180deg, #6B83DD 0%, #E0347E 100%);
        }

        .section-title {
            text-align: center;
            font-size: 42px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .section-subtitle {
            text-align: center;
            max-width: 600px;
            margin: 0 auto 30px;
            font-size: 16px;
            color: #555;
        }

        .card h3 {
            font-size: 35px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .card p.price {
            font-size: 27px;
            font-weight: 600;
            color: black;
        }

        .card-dark p.price {
            color: white;
        }

        .per-month {
            font-size: 16px;
            color: #5b0dd5;
            font-weight: 400;
        }

        .card-dark .per-month {
            color: white;
        }

        .btn {
            font-family: 'Inter', sans-serif;
            display: block;
            width: 100%;
            padding: 13px 0;
            font-size: 15px;
            font-weight: 600;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            margin: 15px 0;
        }

        .btn.dark {
            background: black;
            color: white;
        }

        .btn.dark:hover {
            background: #5b0dd5;
        }

        .btn.purple {
            background: #5b0dd5;
            color: white;
        }

        .btn.purple:hover {
            background: white;
            color: #5b0dd5;
        }

        .btn.white {
            background: white;
            color: #5b0dd5;
        }

        .btn.white:hover {
            background: white;
        }

        .included-title {
            color: #5b0dd5;
            font-weight: 600;
            font-size: 15px;
            margin-top: 20px;
        }

        .card-dark .included-title {
            color: white;
        }

        .features {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .features li {
            font-size: 16px;
            font-weight: 400;
            line-height: 1.4;
            margin: 8px 0;
            display: flex;
            align-items: flex-start;
        }

        .icon::before {
            line-height: 1;
            margin-top: 3px;
        }

        .features li strong {
            font-weight: 700;
        }

        .icon::before {
            content: "✔";
            font-size: 18px;
            color: #5b0dd5;
            font-weight: bold;
            margin-right: 10px;
            line-height: 1.2;
        }

        .card-dark .icon::before {
            color: white;
        }

        .cancel-section {
            text-align: center;
            margin-top: 40px;
        }

        .cancel-section p {
            font-size: 16px;
            color: #555;
            margin-bottom: 20px;
        }

        @media (max-width: 1024px) {
            .pricing-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 640px) {
            .pricing-grid {
                grid-template-columns: 1fr;
            }
        }

        footer {
            text-align: center;
            font-size: 14px;
            color: #6b7280;
            padding: 40px 20px;
        }

        footer a {
            color: #2563eb;
            text-decoration: none;
        }

        footer a:hover {
            text-decoration: underline;
        }

        .dropdown {
            position: relative;
        }

        .dropdown-toggle {
            font-size: 14px;
            font-weight: 500;
            color: #2563eb;
            background: transparent;
            border: none;
            cursor: pointer;
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 10px;
            z-index: 10;
        }

        .dropdown-menu a {
            display: block;
            padding: 5px 10px;
            color: #2563eb;
            text-decoration: none;
        }

        .dropdown-menu a:hover {
            background: #f0f0f0;
        }
    </style>
</head>

<body>
    <!-- Loading Spinner -->
    <div id="loadingSpinner" class="loading-spinner">
        <div class="spinner-container">
            <div class="spinner"></div>
            <p class="spinner-text" id="spinnerText">Loading...</p>
            <p class="spinner-subtext" id="spinnerSubtext">Please wait while we prepare your payment</p>
        </div>
    </div>

    <div class="pricing-header">
        <img src="https://syntopia.ai/wp-content/uploads/2025/01/logo-syntopia-black-scaled.webp" alt="Syntopia Logo">
        <div class="dropdown">
            <button class="dropdown-toggle" type="button" aria-haspopup="true" aria-expanded="false">Account</button>
            <div class="dropdown-menu">
                <a href="{{ route('user.dashboard') }}">Dashboard</a>
                <a href="#"
                    onclick="event.preventDefault(); document.getElementById('logout-form').submit();">Logout</a>
            </div>
        </div>
        <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
            @csrf
        </form>
    </div>
    <div class="pricing-wrapper">
        <div class="container">
            <div class="badge-wrapper">
                <div class="pricing-badge">PRICING PLANS</div>
            </div>
            @include('components.alert-messages')
            <h2 class="section-title">
                @if (isset($isUpgrade) && $isUpgrade)
                    Upgrade Your Subscription
                @elseif (isset($pageType) && $pageType === 'downgrade')
                    Downgrade Your Subscription
                @else
                    Plans For Every Type of Business
                @endif
            </h2>
            <p class="section-subtitle">
                @if (isset($isUpgrade) && $isUpgrade)
                    Choose a higher-tier plan to unlock more features. Your current subscription will be prorated.
                @elseif (isset($pageType) && $pageType === 'downgrade')
                    Select a lower-tier plan. The change will take effect at the end of your current billing cycle.
                @else
                    SYNTOPIA creates hyperrealistic, interactive AI avatars that revolutionize how businesses and
                    individuals connect with their audiences.
                @endif
            </p>
            <div class="pricing-grid">
                @foreach ($packages as $package)
                    <div class="card {{ $loop->iteration % 2 == 1 ? 'card-dark' : 'card-light' }}">
                        <h3>{{ $package->name }}</h3>
                        <p class="price">${{ number_format($package->price, 0) }} <span
                                class="per-month">/{{ $package->duration }}</span></p>

                        <button class="btn {{ $currentPackage == $package->name ? 'active' : 'dark' }} checkout-button"
                            data-package="{{ $package->name }}"
                            data-action="{{ $currentPackage == $package->name ? 'current' : (isset($isUpgrade) && $isUpgrade && $package->price > $currentPackagePrice ? 'upgrade' : (isset($pageType) && $pageType === 'downgrade' && $package->price < $currentPackagePrice ? 'downgrade' : 'new')) }}"
                            {{ $currentPackage == $package->name || (isset($isUpgrade) && $isUpgrade && $package->price <= $currentPackagePrice && $package->name !== 'Enterprise') || (isset($pageType) && $pageType === 'downgrade' && $package->price >= $currentPackagePrice && $package->name !== 'Enterprise') ? 'disabled' : '' }}>
                            @if ($package->name == 'Enterprise')
                                Get in Touch
                            @elseif ($currentPackage == $package->name)
                                ✓ Current Plan
                            @elseif (isset($isUpgrade) && $isUpgrade && $package->price > $currentPackagePrice)
                                Upgrade to {{ $package->name }}
                            @elseif (isset($pageType) && $pageType === 'downgrade' && $package->price < $currentPackagePrice)
                                Downgrade to {{ $package->name }}
                            @else
                                Get Started
                            @endif
                        </button>

                        <p class="included-title">What's included</p>
                        <ul class="features">
                            @foreach ($package->features as $feature)
                                <li><span class="icon"></span> {{ $feature }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @include('subscription.includes._addons')
    <footer>
        Having trouble? Contact us at
        <a href="mailto:support@syntopia.ai">support@syntopia.ai</a>
    </footer>
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const currentPackage = "{{ $currentPackage ?? '' }}";
        const currentPackagePrice = parseFloat("{{ $currentPackagePrice ?? 0 }}");
        const userOriginalGateway = "{{ $userOriginalGateway ?? '' }}";
        const activeGatewaysByAdmin = @json($activeGatewaysByAdmin ?? []);
        const isUpgrade = '{{ isset($isUpgrade) && $isUpgrade ? 'true' : 'false' }}';
        const pageType = '{{ $pageType ?? 'new' }}';
        const hasActiveSubscription = '{{ isset($hasActiveSubscription) && $hasActiveSubscription ? 'true' : 'false' }}';
        const selectedPackage = @json($selectedPackage ?? null);

        document.addEventListener("DOMContentLoaded", function() {
            console.log('Page configuration:', {
                currentPackage,
                currentPackagePrice,
                userOriginalGateway,
                activeGatewaysByAdmin,
                isUpgrade,
                pageType,
                hasActiveSubscription
            });

            let selectedGateway = isUpgrade === 'true' && userOriginalGateway ?
                userOriginalGateway :
                activeGatewaysByAdmin.length > 0 ? activeGatewaysByAdmin[0] : null;
            console.log('Selected gateway:', selectedGateway);

            console.log(`[${isUpgrade === 'true' ? 'UPGRADE' : pageType.toUpperCase()}] Using gateway:`,
                selectedGateway);

            if (isUpgrade === 'true' || pageType === 'downgrade') {
                setupSubscriptionUI();
            }

            // Auto-select package if provided via URL
            if (selectedPackage) {
                const targetButton = document.querySelector(`[data-package="${selectedPackage.name}"]`);
                if (targetButton && !targetButton.disabled) {
                    console.log('Auto-selecting package:', selectedPackage.name);
                    // Highlight the selected package
                    targetButton.closest('.card').style.border = '2px solid #5b0dd5';
                    targetButton.closest('.card').style.transform = 'scale(1.02)';
                }
            }

            // Auto-popup functionality for package_name in URL
            const urlParams = new URLSearchParams(window.location.search);
            const packageNameFromUrl = urlParams.get('package_name');

            if (packageNameFromUrl) {
                console.log('=== AUTO-POPUP TRIGGERED ===');
                console.log('Package name from URL:', packageNameFromUrl);
                console.log('Current package:', currentPackage);
                console.log('Has active subscription:', hasActiveSubscription);
                console.log('Selected gateway:', selectedGateway);

                // Validate package name
                const validPackages = ['Free', 'Starter', 'Pro', 'Business', 'Enterprise'];
                const normalizedPackageName = packageNameFromUrl.charAt(0).toUpperCase() + packageNameFromUrl.slice(1).toLowerCase();

                console.log('Normalized package name:', normalizedPackageName);
                console.log('Valid packages:', validPackages);

                if (!validPackages.includes(normalizedPackageName)) {
                    console.error('Invalid package name in URL:', packageNameFromUrl);
                    showError('Invalid Package', `Package "${packageNameFromUrl}" is not valid. Available packages: ${validPackages.join(', ')}`);
                    return;
                }

                // Prevent multiple auto-popups
                if (sessionStorage.getItem('autoPopupTriggered') === 'true') {
                    console.log('Auto-popup already triggered, skipping');
                    return;
                }

                // Wait a bit for the page to fully load and payment gateways to initialize
                setTimeout(() => {
                    // Determine the action based on current subscription status
                    let action = 'new';
                    if (hasActiveSubscription === 'true' && currentPackage) {
                        // Compare packages to determine if it's an upgrade or downgrade
                        const packageOrder = ['Free', 'Starter', 'Pro', 'Business', 'Enterprise'];
                        const currentIndex = packageOrder.indexOf(currentPackage);
                        const targetIndex = packageOrder.indexOf(normalizedPackageName);

                        console.log('Package comparison:', {
                            currentPackage: currentPackage,
                            targetPackage: normalizedPackageName,
                            currentIndex: currentIndex,
                            targetIndex: targetIndex
                        });

                        if (targetIndex > currentIndex) {
                            action = 'upgrade';
                        } else if (targetIndex < currentIndex) {
                            action = 'downgrade';
                        }
                    }

                    console.log('Auto-triggering checkout with:', {
                        packageName: normalizedPackageName,
                        action: action,
                        selectedGateway: selectedGateway
                    });

                    // Mark as triggered to prevent multiple popups
                    sessionStorage.setItem('autoPopupTriggered', 'true');

                    // Trigger the checkout process
                    if (selectedGateway) {
                        // Show spinner for auto-popup
                        showSpinner('Preparing payment...', `Setting up ${normalizedPackageName} plan checkout`);
                        processCheckout(normalizedPackageName, action);
                    } else {
                        console.error('No payment gateway available for auto-popup');
                        showError('Payment Error', 'No payment gateway is currently available. Please try again later.');
                        // Clear the flag if there's an error so user can try again
                        sessionStorage.removeItem('autoPopupTriggered');
                    }
                }, 1000); // Wait 1 second for everything to load
            }

            document.querySelectorAll('.checkout-button').forEach(button => {
                button.addEventListener('click', function() {
                    const packageName = this.getAttribute('data-package');
                    const action = this.getAttribute('data-action');
                    if (this.disabled || this.classList.contains('active')) {
                        console.warn('Button click ignored - button is disabled or active', {
                            packageName,
                            action
                        });
                        return;
                    }

                    // Show loading state
                    setButtonLoading(this, true);
                    showSpinner('Preparing payment...', `Setting up ${packageName} plan checkout`);

                    processCheckout(packageName, action);

                    // Reset button after 3 seconds if no response
                    setTimeout(() => {
                        setButtonLoading(this, false);
                        hideSpinner();
                    }, 3000);
                });
            });

            if (hasActiveSubscription === 'true') {
                const cancelButton = document.getElementById('cancel-subscription');
                if (cancelButton) {
                    cancelButton.addEventListener('click', function() {
                        cancelSubscription();
                    });
                }
            }

            function processCheckout(packageName, action) {
                console.log('=== PROCESSING CHECKOUT ===', {
                    packageName,
                    action,
                    selectedGateway
                });
                try {
                    if (!selectedGateway) {
                        throw new Error('No payment gateway available');
                    }
                    if (action === 'upgrade' || action === 'downgrade') {
                        hideSpinner(); // Hide spinner for confirmation dialog
                        showConfirmation(packageName, action, selectedGateway);
                    } else {
                        executeCheckout(packageName, action);
                    }
                } catch (error) {
                    console.error('Checkout error:', error);
                    hideSpinner(); // Hide spinner on error
                    showError('Checkout Error', error.message || 'Failed to process checkout.');
                }
            }

            function showConfirmation(packageName, action, gateway) {
                const title = action === 'upgrade' ? 'Confirm Upgrade' : 'Confirm Downgrade';
                const text = action === 'upgrade' ?
                    `You're about to upgrade from <strong>${currentPackage}</strong> to <strong>${packageName}</strong>. Your current subscription will be prorated.` :
                    `You're about to downgrade from <strong>${currentPackage}</strong> to <strong>${packageName}</strong>. The change will take effect at the end of your current billing cycle.`;
                Swal.fire({
                    title: title,
                    html: text,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: `Proceed with ${action.charAt(0).toUpperCase() + action.slice(1)}`,
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#5b0dd5'
                }).then(result => {
                    if (result.isConfirmed) {
                        // Show spinner when user confirms
                        showSpinner('Processing...', `Setting up ${packageName} plan checkout`);
                        executeCheckout(packageName, action);
                    }
                });
            }

            function executeCheckout(packageName, action) {
                console.log('=== EXECUTING CHECKOUT ===', {
                    packageName,
                    action,
                    selectedGateway
                });

                // Show appropriate spinner message
                const spinnerText = action === 'upgrade' ? 'Processing upgrade...' :
                                  action === 'downgrade' ? 'Processing downgrade...' :
                                  'Setting up payment...';
                const spinnerSubtext = `Preparing ${packageName} plan checkout`;
                showSpinner(spinnerText, spinnerSubtext);

                let apiUrl;
                if (action === 'upgrade') {
                    apiUrl = `/api/payments/upgrade/${packageName}`;
                } else if (action === 'downgrade') {
                    apiUrl =
                        `/api/payments/payproglobal/checkout/${packageName}`; // Adjust if downgrade uses a different gateway
                } else {
                    apiUrl =
                        `/api/payments/${selectedGateway.toLowerCase().replace(/\s+/g, '')}/checkout/${packageName}`;
                }

                const requestBody = {
                    package: packageName,
                    is_upgrade: action === 'upgrade',
                    is_downgrade: action === 'downgrade'
                };

                fetch(apiUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-Is-Upgrade': action === 'upgrade' ? 'true' : 'false',
                            'X-Is-Downgrade': action === 'downgrade' ? 'false' : 'false'
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify(requestBody)
                    })
                    .then(response => {
                        if (!response.ok) {
                            return response.json().then(data => {
                                throw new Error(data.error || `HTTP ${response.status}`);
                            });
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (!data.success || !data.checkout_url) {
                            throw new Error(data.error || 'No checkout URL received');
                        }
                        console.log('Checkout URL received:', data.checkout_url);

                        // Hide spinner before opening payment popup
                        hideSpinner();

                        if (selectedGateway === 'FastSpring') {
                            processFastSpring(packageName, action);
                        } else if (selectedGateway === 'Paddle') {
                            console.log('Executing Paddle checkout...');
                            processPaddle(packageName, action);
                        } else if (selectedGateway === 'Pay Pro Global') {
                            console.log('Executing Paddle checkout...');
                            processPayProGlobal(packageName, action);
                        }
                    })
                    .catch(error => {
                        console.error(`${action.charAt(0).toUpperCase() + action.slice(1)} error:`, error);
                        hideSpinner(); // Hide spinner on error
                        showError(`${action.charAt(0).toUpperCase() + action.slice(1)} Failed`, error.message);
                    });
            }

            function processPaddle(packageName, action) {
                console.log('=== PADDLE PROCESSING ===', {
                    packageName,
                    action
                });

                // Show spinner for Paddle processing
                showSpinner('Opening payment window...', 'Please wait while we connect to Paddle');

                // Check if Paddle is properly initialized
                if (typeof Paddle === 'undefined') {
                    console.error('Paddle is not initialized');
                    hideSpinner(); // Hide spinner on error
                    showError('Payment Error',
                        'Payment system is not properly initialized. Please refresh the page and try again.');
                    return;
                }

                if (typeof Paddle.Checkout === 'undefined') {
                    console.error('Paddle.Checkout is not available');
                    hideSpinner(); // Hide spinner on error
                    showError('Payment Error',
                        'Payment checkout is not available. Please refresh the page and try again.');
                    return;
                }
                const apiUrl = `/api/payments/paddle/checkout/${packageName}`;
                const requestBody = {
                    package: packageName,
                    is_upgrade: action === 'upgrade',
                    is_downgrade: action === 'downgrade'
                };
                fetch(apiUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-Is-Upgrade': action === 'upgrade' ? 'true' : 'false',
                            'X-Is-Downgrade': action === 'downgrade' ? 'true' : 'false'
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify(requestBody)
                    })
                    .then(response => {
                        if (!response.ok) {
                            return response.json().then(data => {
                                throw new Error(data.error || `HTTP ${response.status}`);
                            });
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (!data.success || !data.transaction_id) {
                            throw new Error(data.error || 'No transaction ID provided');
                        }
                        console.log(data, 'data');

                        // {success: true, checkout_url: 'https://127.0.0.1:8000?_ptxn=txn_01jzd7cf1dae3sper0n1mj28fa', transaction_id: 'txn_01jzd7cf1dae3sper0n1mj28fa'}
                        // checkout_url
                        // :
                        // "https://127.0.0.1:8000?_ptxn=txn_01jzd7cf1dae3sper0n1mj28fa"
                        // success
                        // :
                        // true
                        // transaction_id
                        // :
                        // "txn_01jzd7cf1dae3sper0n1mj28fa"
                        // [[Prototype]]
                        // :
                        // Object
                        console.log('Opening Paddle checkout with transaction ID:', data.transaction_id);

                        // Store current action and transaction ID in session storage for global event listener
                        sessionStorage.setItem('currentPaddleAction', action);
                        sessionStorage.setItem('currentPaddleTransactionId', data.transaction_id);
                        console.log('Stored in session storage:', {
                            action: action,
                            transactionId: data.transaction_id
                        });

                        // Create a proper event callback function
                        const paddleEventCallback = function(eventData) {
                            console.log('Paddle event callback triggered:', eventData);
                            handlePaddleEvent(eventData, action);
                        };

                        // Open Paddle checkout with proper error handling
                        try {
                            Paddle.Checkout.open({
                                transactionId: data.transaction_id,
                                eventCallback: paddleEventCallback
                            });
                            // Hide spinner when checkout is opened
                            hideSpinner();
                        } catch (error) {
                            console.error('Error opening Paddle checkout:', error);
                            hideSpinner(); // Hide spinner on error
                            showError('Checkout Error', 'Failed to open payment checkout. Please try again.');
                        }
                    })
                    .catch(error => {
                        console.error('Paddle processing error:', error);
                        hideSpinner(); // Hide spinner on error
                        showError(`${action.charAt(0).toUpperCase() + action.slice(1)} Failed`, error.message);
                    });
            }

            let isProcessingPayProGlobal = false;

            function processPayProGlobal(packageName, action) {
                if (isProcessingPayProGlobal) {
                    console.log('PayProGlobal checkout already in progress');
                    return;
                }

                isProcessingPayProGlobal = true;
                console.log('=== PAYPROGLOBAL PROCESSING ===', {
                    packageName,
                    action
                });

                // Show spinner for PayProGlobal processing
                showSpinner('Opening payment window...', 'Please wait while we connect to PayProGlobal');

                const apiUrl = `/api/payments/payproglobal/checkout/${packageName}`;
                const requestBody = {
                    package: packageName,
                    is_upgrade: action === 'upgrade',
                    is_downgrade: action === 'downgrade'
                };

                console.log('Making request to:', apiUrl, 'with body:', requestBody);

                fetch(apiUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-Is-Upgrade': action === 'upgrade' ? 'true' : 'false',
                            'X-Is-Downgrade': action === 'downgrade' ? 'true' : 'false'
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify(requestBody)
                    })
                    .then(response => {
                        console.log('Response status:', response.status);
                        if (!response.ok) {
                            return response.json().then(data => {
                                console.error('API Error Response:', data);
                                throw new Error(data.error ||
                                    `HTTP ${response.status}: ${data.message || 'Unknown error'}`);
                            });
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('API Response:', data);

                        if (!data.success) {
                            throw new Error(data.error || 'API returned success: false');
                        }

                        if (!data.checkout_url) {
                            console.error('No checkout_url in response:', data);
                            throw new Error('No checkout URL received from server');
                        }

                        console.log('PayProGlobal checkout URL received:', data.checkout_url);

                        // Validate URL format
                        if (!data.checkout_url.includes('payproglobal.com')) {
                            console.error('Invalid checkout URL format:', data.checkout_url);
                            throw new Error('Invalid checkout URL format');
                        }

                        // Store user ID and package name in session storage for popup communication
                        const userId = "{{ Auth::id() ?? '' }}";
                        sessionStorage.setItem('payProGlobalUserId', userId);
                        sessionStorage.setItem('payProGlobalPackageName', packageName);
                        sessionStorage.setItem('payProGlobalAction', action);

                        console.log('Stored in session storage:', {
                            userId,
                            packageName,
                            action
                        });

                        // Open popup with better error handling
                        const popupFeatures =
                            'width=1200,height=1200,location=no,toolbar=no,menubar=no,scrollbars=yes,resizable=yes';
                        const popup = window.open(
                            data.checkout_url,
                            action === 'upgrade' ? 'PayProGlobal_Upgrade' :
                            action === 'downgrade' ? 'PayProGlobal_Downgrade' :
                            'PayProGlobal_Checkout',
                            popupFeatures
                        );

                        if (!popup) {
                            console.error('Popup was blocked');
                            hideSpinner(); // Hide spinner on error
                            showError('Popup Blocked',
                                'Please allow popups for this site and try again. You may need to click the popup blocker icon in your browser\'s address bar.'
                            );
                            return;
                        }

                        console.log('PayProGlobal popup opened successfully');
                        // Hide spinner when popup is opened
                        hideSpinner();
                    })
                    .catch(error => {
                        console.error('PayProGlobal processing error:', error);
                        hideSpinner(); // Hide spinner on error
                        showError(`${action.charAt(0).toUpperCase() + action.slice(1)} Failed`,
                            error.message || 'An unexpected error occurred. Please try again.');
                    })
                    .finally(() => {
                        isProcessingPayProGlobal = false;
                    });
            }

            function cancelSubscription() {
                console.log('=== CANCEL SUBSCRIPTION ===');
                Swal.fire({
                    title: 'Confirm Cancellation',
                    html: `Are you sure you want to cancel your <strong>${currentPackage}</strong> subscription? This action cannot be undone.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Cancel Subscription',
                    cancelButtonText: 'Keep Subscription',
                    confirmButtonColor: '#ef4444'
                }).then(result => {
                    if (result.isConfirmed) {
                        // Show spinner for cancellation
                        showSpinner('Cancelling subscription...', 'Please wait while we process your cancellation');

                        fetch('/payments/cancel-subscription', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken,
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                                credentials: 'same-origin'
                            })
                            .then(response => {
                                if (!response.ok) {
                                    return response.json().then(data => {
                                        throw new Error(data.error ||
                                            `HTTP ${response.status}`);
                                    });
                                }
                                return response.json();
                            })
                            .then(data => {
                                hideSpinner(); // Hide spinner on success
                                if (data.success) {
                                    showSuccess('Subscription Cancelled',
                                        'Your subscription has been cancelled.').then(() => {
                                        window.location.href = '/user/dashboard';
                                    });
                                } else {
                                    throw new Error(data.error || 'Cancellation failed.');
                                }
                            })
                            .catch(error => {
                                console.error('Cancellation error:', error);
                                hideSpinner(); // Hide spinner on error
                                showError('Cancellation Failed', error.message);
                            });
                    }
                });
            }

            function setupSubscriptionUI() {
                document.querySelectorAll('.checkout-button').forEach(button => {
                    const packageElement = button.closest('.card');
                    const packageName = button.getAttribute('data-package');
                    const action = button.getAttribute('data-action');
                    const priceElement = packageElement.querySelector('.price');
                    const priceText = priceElement.firstChild.nodeValue;
                    const packagePrice = parseFloat(priceText.replace(/[^0-9.]/g, ''));

                    if (action === 'current') {
                        button.classList.add('active');
                        button.disabled = true;
                        button.innerHTML = '<span class="current-package-text">Current Plan</span>';
                    } else if ((isUpgrade === 'true' && packagePrice <= currentPackagePrice &&
                            packageName !== 'Enterprise') ||
                        (pageType === 'downgrade' && packagePrice >= currentPackagePrice && packageName !==
                            'Enterprise')) {
                        button.classList.add('disabled');
                        button.disabled = true;
                        button.innerHTML = '<span class="not-upgrade-text">Not an ' + (isUpgrade ===
                            'true' ? 'Upgrade' : 'Downgrade') + '</span>';
                        packageElement.style.opacity = '0.6';
                    } else if (action === 'upgrade') {
                        button.innerHTML = '<span class="upgrade-text">Upgrade to ' + packageName +
                            '</span>';
                    } else if (action === 'downgrade') {
                        button.innerHTML = '<span class="downgrade-text">Downgrade to ' + packageName +
                            '</span>';
                    }
                });
            }

            function showSuccess(title, text) {
                console.log('Showing success message:', {
                    title,
                    text
                });
                return Swal.fire({
                    icon: 'success',
                    title: title,
                    text: text,
                    confirmButtonText: 'OK'
                });
            }

            function showError(title, text) {
                console.error('Showing error message:', {
                    title,
                    text
                });
                Swal.fire({
                    icon: 'error',
                    title: title,
                    text: text,
                    confirmButtonText: 'OK'
                });
            }

            function showInfo(title, text) {
                console.log('Showing info message:', {
                    title,
                    text
                });
                Swal.fire({
                    icon: 'info',
                    title: title,
                    text: text,
                    confirmButtonText: 'OK'
                });
            }

            // Spinner functions
            function showSpinner(text = 'Loading...', subtext = 'Please wait while we prepare your payment') {
                console.log('Showing spinner:', { text, subtext });
                const spinner = document.getElementById('loadingSpinner');
                const spinnerText = document.getElementById('spinnerText');
                const spinnerSubtext = document.getElementById('spinnerSubtext');

                if (spinnerText) spinnerText.textContent = text;
                if (spinnerSubtext) spinnerSubtext.textContent = subtext;
                if (spinner) spinner.style.display = 'flex';
            }

            function hideSpinner() {
                console.log('Hiding spinner');
                const spinner = document.getElementById('loadingSpinner');
                if (spinner) spinner.style.display = 'none';
            }

            function setButtonLoading(button, isLoading = true) {
                if (!button) return;

                if (isLoading) {
                    button.classList.add('button-loading');
                    button.disabled = true;
                    // Store original text
                    const originalText = button.innerHTML;
                    button.setAttribute('data-original-text', originalText);
                    button.innerHTML = '<span class="btn-text">' + originalText + '</span>';
                } else {
                    button.classList.remove('button-loading');
                    button.disabled = false;
                    // Restore original text
                    const originalText = button.getAttribute('data-original-text');
                    if (originalText) {
                        button.innerHTML = originalText;
                        button.removeAttribute('data-original-text');
                    }
                }
            }

            // Make helper functions globally accessible
            window.showSuccess = showSuccess;
            window.showError = showError;
            window.showInfo = showInfo;
            window.showSpinner = showSpinner;
            window.hideSpinner = hideSpinner;
            window.setButtonLoading = setButtonLoading;

            // Cleanup function to remove session storage when page is unloaded
            window.addEventListener('beforeunload', function() {
                sessionStorage.removeItem('currentPaddleAction');
                sessionStorage.removeItem('currentPaddleTransactionId');
                sessionStorage.removeItem('autoPopupTriggered');
            });
        });

        document.querySelector('.dropdown-toggle').addEventListener('click', function() {
            const menu = document.querySelector('.dropdown-menu');
            const isOpen = menu.style.display === 'block';
            menu.style.display = isOpen ? 'none' : 'block';
            this.setAttribute('aria-expanded', !isOpen);
        });

        document.addEventListener('click', function(event) {
            const dropdown = document.querySelector('.dropdown');
            if (!dropdown.contains(event.target)) {
                document.querySelector('.dropdown-menu').style.display = 'none';
                document.querySelector('.dropdown-toggle').setAttribute('aria-expanded', 'false');
            }
        });

        function showAlert(type, title, text, callback = null) {
            console.log(`Showing ${type} alert:`, {
                title,
                text
            });
            Swal.fire({
                icon: type,
                title: title,
                text: text,
                confirmButtonText: 'OK'
            }).then(() => {
                if (callback) callback();
            });
        }

        // Make injectPayProGlobalSuccessHandler globally available
        function injectPayProGlobalSuccessHandler(popup, userId, packageName) {
            console.log('Injecting PayProGlobal success handler');
            const scriptContent = `
                (function() {
                    console.log('PayProGlobal success handler script running');
                    // Check if we\'re on the PayProGlobal thank you page
                    if (window.location.href.includes('store.payproglobal.com/thankyou')) {
                        console.log('Detected PayProGlobal thank you page');
                        const urlParams = new URLSearchParams(window.location.search);
                        const orderId = urlParams.get('OrderId');
                        const externalOrderId = urlParams.get('ExternalOrderId');

                        if (orderId) {
                            console.log('Sending PayProGlobal success message to parent:', {
                                orderId: orderId,
                                userId: '${userId}',
                                packageName: '${packageName}'
                            });
                            window.opener.postMessage({
                                type: 'payproglobal_success',
                                orderId: orderId,
                                userId: '${userId}',
                                packageName: '${packageName}'
                            }, '*');
                            // Close the popup after sending the message
                            setTimeout(() => window.close(), 1000);
                        } else {
                            console.error('No OrderId found in thank you page URL');
                        }
                    }
                })();
            `;
            // Inject the script into the popup
            try {
                popup.document.write(`
                    <script>${scriptContent}<\/script>
                `);
            } catch (error) {
                console.error('Error injecting PayProGlobal success handler:', error);
            }
        }
    </script>
</body>

</html>
