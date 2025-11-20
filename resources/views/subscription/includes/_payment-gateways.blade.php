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
@if (($activeGateway && $activeGateway->name === 'FastSpring') || request()->has('adon'))
    <script id="fsc-api" src="https://sbl.onfastspring.com/sbl/1.0.3/fastspring-builder.min.js" type="text/javascript"
        data-storefront="{{ config('payment.gateways.FastSpring.storefront') }}" data-popup-closed="onFSPopupClosed"></script>
    <script>
        const fastspringStorefront = "{{ config('payment.gateways.FastSpring.storefront') }}";
        let currentProductPath = '';

        function addCsrfTokenToForm(form) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            if (csrfToken) {
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = '_token';
                csrfInput.value = csrfToken;
                form.appendChild(csrfInput);
            }
            return csrfToken;
        }

        function processFastSpring(packageName, action = 'new') {
            console.log('=== processFastSpring ===', {
                packageName,
                action
            });
            try {
                if (typeof fastspring === 'undefined' || !fastspring.builder) {
                    throw new Error('FastSpring is not properly initialized');
                }

                if (window.showSpinner) {
                    window.showSpinner('Opening payment window...', 'Please wait while we connect to FastSpring');
                }

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
                    if (window.hideSpinner) {
                        window.hideSpinner();
                    }
                }, 500);
            } catch (error) {
                console.error('FastSpring processing error:', error);
                if (window.hideSpinner) {
                    window.hideSpinner();
                }
                if (window.showAlert) {
                    window.showAlert('error', 'FastSpring Error', error.message || 'Failed to process checkout.');
                }
            }
        }

        function onFSPopupClosed(orderData) {
            if (window.isAddonCheckout === true) {
                try {
                    if (!orderData || !orderData.id) {
                        showAlert('info', 'Payment Cancelled', 'Your add-on payment was cancelled.', () => {
                            window.isAddonCheckout = false;
                        });
                        sessionStorage.removeItem('addonAutoPopupTriggered');
                        sessionStorage.removeItem('pendingAddon');
                        sessionStorage.removeItem('currentProductPath');
                        currentProductPath = '';
                        return;
                    }

                    const orderId = orderData.id;
                    fetch('/payments/addon-debug-log', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ message: 'Addon popup closed with order', context: { orderId } })
                    }).catch(() => {});
                    const addon = (new URLSearchParams(window.location.search).get('adon') || sessionStorage.getItem('pendingAddon') || '').toLowerCase();
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '/payments/addon-success';
                    addCsrfTokenToForm(form);

                    const orderInput = document.createElement('input');
                    orderInput.type = 'hidden';
                    orderInput.name = 'orderId';
                    orderInput.value = orderId;
                    form.appendChild(orderInput);

                    const addonInput = document.createElement('input');
                    addonInput.type = 'hidden';
                    addonInput.name = 'addon';
                    addonInput.value = addon;
                    form.appendChild(addonInput);

                    document.body.appendChild(form);
                    form.submit();
                } catch (e) {
                    console.error('Addon close handler error:', e);
                    showAlert('error', 'Processing Error', 'There was an error processing your add-on.');
                } finally {
                    window.isAddonCheckout = false;
                    sessionStorage.removeItem('addonAutoPopupTriggered');
                    sessionStorage.removeItem('pendingAddon');
                    sessionStorage.removeItem('currentProductPath');
                    currentProductPath = '';
                }
                return;
            }
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
                        window.location.href = '/user/dashboard?error=package';
                    });
                    return;
                }

                if (!orderData || (!orderData.id)) {
                    console.log('No order data or cancelled payment');
                    showAlert('info', 'Payment Cancelled', 'Your payment was cancelled.', () => {
                        window.location.href = '/user/dashboard';
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
                const csrfToken = addCsrfTokenToForm(form);
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
                fetch('/payments/addon-debug-log', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ message: 'Addon close handler error', context: { error: String(err) }, level: 'error' })
                }).catch(() => {});
                showAlert('error', 'Processing Error', 'There was an error processing your payment.', () => {
                    window.location.href = '/user/dashboard?error=processing';
                });
                sessionStorage.removeItem('currentProductPath');
                currentProductPath = '';
            }
        }
    </script>
@endif

<!-- Paddle Integration -->
@if ($activeGateway && $activeGateway->name === 'Paddle')
    <script>
        window.handlePaddleEvent = function(eventData, action) {
            console.log('=== PADDLE EVENT HANDLER ===', {
                eventData: eventData,
                action: action
            });

            console.log('=== DETAILED EVENT DATA ANALYSIS ===');
            console.log('eventData type:', typeof eventData);
            console.log('eventData keys:', Object.keys(eventData || {}));
            console.log('Full eventData JSON:', JSON.stringify(eventData, null, 2));

            let eventName = null;
            let transactionId = null;

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

            if (!transactionId && eventData.data) {
                transactionId = eventData.data.transaction_id ||
                    eventData.data.id ||
                    eventData.data.transactionId;
            }

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

                if (transactionId) {
                    console.log('Redirecting to success URL with transaction ID:', transactionId);
                    const successUrl = `/payments/success?gateway=paddle&transaction_id=${transactionId}`;
                    window.location.href = successUrl;
                } else {
                    console.log('No transaction ID found in event data, trying fallback methods...');

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

                window.addEventListener('message', function(event) {
                    if (event.origin.includes('paddle.com') || event.origin.includes('cdn.paddle.com')) {
                        console.log('Paddle message received:', event.data);

                        if (event.data && event.data.action === 'event' && event.data.event_name) {
                            const currentAction = sessionStorage.getItem('currentPaddleAction') || 'new';

                            if (event.data.event_name === 'checkout.completed') {
                                console.log('=== PADDLE CHECKOUT.COMPLETED EVENT RECEIVED ===');
                                console.log('Event data:', event.data);
                                console.log('Callback data:', event.data.callback_data);
                                console.log('Current action from session storage:', currentAction);

                                let transactionId = null;

                                console.log('=== TRANSACTION ID EXTRACTION DEBUG ===');
                                console.log('Full event.data:', JSON.stringify(event.data, null, 2));
                                console.log('event.data.callback_data:', event.data.callback_data);

                                if (event.data.callback_data) {
                                    const callbackData = event.data.callback_data;
                                    console.log('Callback data structure:', JSON.stringify(callbackData,
                                        null, 2));

                                    transactionId = callbackData.transaction_id ||
                                        callbackData.id ||
                                        callbackData.transactionId ||
                                        callbackData.order_id ||
                                        callbackData.orderId;
                                }

                                if (!transactionId && event.data) {
                                    transactionId = event.data.transaction_id ||
                                        event.data.id ||
                                        event.data.transactionId ||
                                        event.data.order_id ||
                                        event.data.orderId;
                                }

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
        let payProGlobalPopup = null;
        let popupCheckInterval = null;

        window.addEventListener('message', function(event) {
            console.log('Received message:', event.data, 'Origin:', event.origin);

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

                    if (popupCheckInterval) {
                        clearInterval(popupCheckInterval);
                    }

                    if (payProGlobalPopup && !payProGlobalPopup.closed) {
                        payProGlobalPopup.close();
                    }

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
                    gatewayInput.value = 'payproglobal';
                    form.appendChild(gatewayInput);

                    const orderIdInput = document.createElement('input');
                    orderIdInput.type = 'hidden';
                    orderIdInput.name = 'OrderId';
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

        function openPayProGlobalPopup(checkoutUrl, packageName, userId) {
            console.log('Opening PayProGlobal popup for URL:', checkoutUrl);

            sessionStorage.setItem('payProGlobalPackageName', packageName);
            sessionStorage.setItem('payProGlobalUserId', userId);

            const popup = window.open(checkoutUrl, '_blank', 'width=800,height=700,resizable=yes,scrollbars=yes');

            if (popup) {
                monitorPayProGlobalPopup(popup, packageName, userId);
                hideSpinner();
            } else {
                showError('Popup Blocked', 'Please allow popups for this site or you will be redirected to the payment page.');
                setTimeout(() => {
                    window.location.href = checkoutUrl;
                }, 3000);
            }
        }

        function monitorPayProGlobalPopup(popup, packageName, userId) {
            payProGlobalPopup = popup;
            let thankYouPageDetected = false;
            
            popupCheckInterval = setInterval(() => {
                try {
                    if (popup.closed) {
                        clearInterval(popupCheckInterval);
                        console.log('PayProGlobal popup closed');

                        const successUrl = sessionStorage.getItem('payProGlobalSuccessUrl');
                        if (successUrl) {
                            console.log('Redirecting to success URL:', successUrl);
                            window.location.href = successUrl;
                        } else if (!thankYouPageDetected) {
                            showInfo('Payment Cancelled', 'Your payment was cancelled or incomplete.');
                        }

                        sessionStorage.removeItem('payProGlobalSuccessUrl');
                        sessionStorage.removeItem('payProGlobalUserId');
                        sessionStorage.removeItem('payProGlobalPackageName');
                        return;
                    }

                    // Try to detect thank you page - this will fail with CORS for cross-origin, which is expected
                    try {
                        const popupUrl = popup.location.href;
                        if (popupUrl && popupUrl.includes('/thankyou')) {
                            console.log('PayProGlobal thank you page detected via URL access');
                            thankYouPageDetected = true;

                            const urlParams = new URLSearchParams(popup.location.search);
                            const orderId = urlParams.get('OrderId');
                            const externalOrderId = urlParams.get('ExternalOrderId');

                            if (orderId || externalOrderId) {
                                console.log('Found OrderId/ExternalOrderId in thank you URL:', { orderId, externalOrderId });
                                
                                // Redirect to our thank you handler which will process and redirect to subscription-details
                                const thankYouUrl = `/payments/payproglobal-thankyou?OrderId=${orderId || ''}&ExternalOrderId=${externalOrderId || ''}`;
                                
                                clearInterval(popupCheckInterval);
                                setTimeout(() => {
                                    popup.close();
                                    window.location.href = thankYouUrl;
                                }, 500);
                            }
                        }
                    } catch (e) {
                        // CORS error expected when popup is on different domain (store.payproglobal.com)
                        // This is normal - we cannot access cross-origin popup URLs
                        // We rely on:
                        // 1. PayProGlobal redirecting to our success URL (configured in backend)
                        // 2. postMessage API if PayProGlobal supports it (already handled in message listener)
                        // 3. User manually navigating from PayProGlobal thank you page to our handler
                    }
                } catch (error) {
                    console.error('Popup monitoring error:', error);
                    clearInterval(popupCheckInterval);
                }
            }, 500);
        }
    </script>
@endif

