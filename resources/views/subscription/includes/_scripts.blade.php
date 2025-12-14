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
    const currentPaymentGateway = "{{ $currentLoggedInUserPaymentGateway ?? '' }}";
    const loggedInUserId = "{{ Auth::id() ?? '' }}";
    const userEmail = "{{ Auth::user()->email ?? '' }}";

    function getFPTid() {
        return window.FPROM && window.FPROM.data && window.FPROM.data.tid;
    }

    document.addEventListener("DOMContentLoaded", function() {
        // Track Facebook Pixel CompleteRegistration event for newly verified users
        @if(session('success') && str_contains(session('success'), 'verified'))
            if (typeof fbq !== 'undefined') {
                fbq('track', 'CompleteRegistration', {
                    content_name: 'Email Verification Complete'
                });
                console.log('Facebook Pixel CompleteRegistration event tracked');
            }
        @endif

        // Track Facebook Pixel Lead event for new users without subscription
        if (typeof fbq !== 'undefined' && hasActiveSubscription === 'false' && pageType === 'new') {
            fbq('track', 'Lead', {
                content_name: 'Subscription Page Visit'
            });
            console.log('Facebook Pixel Lead event tracked for new user');
        }

        console.log('Page configuration:', {
            currentPackage,
            currentPackagePrice,
            userOriginalGateway,
            activeGatewaysByAdmin,
            isUpgrade,
            pageType,
            hasActiveSubscription
        });

        let selectedGateway = (isUpgrade === 'true' || pageType === 'downgrade') && userOriginalGateway ?
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

            // Check if the package is available for the current action
            const targetButton = document.querySelector(`[data-package="${normalizedPackageName}"]`);
            if (!targetButton || targetButton.disabled || targetButton.classList.contains('disabled')) {
                console.error('Package not available for current action:', normalizedPackageName);
                showError('Package Not Available', `Package "${normalizedPackageName}" is not available for ${isUpgrade === 'true' ? 'upgrade' : pageType === 'downgrade' ? 'downgrade' : 'subscription'}.`);
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

        // Add-on auto-checkout via FastSpring only
        const addonFromUrl = urlParams.get('adon');
        const pendingAddon = sessionStorage.getItem('pendingAddon');
        if (addonFromUrl) {
            try {
                // Ensure retries on reload when adon is present
                sessionStorage.removeItem('addonAutoPopupTriggered');
                logAddonDebug('Addon param detected on load. Reset trigger flag.', { addonFromUrl });
            } catch (_) {}
        }
        const addonKey = (addonFromUrl || pendingAddon || '').toLowerCase();

        function getAddonProductPath(adon) {
            if (!adon) return '';
            const normalized = adon.toLowerCase().trim();
            // Config-driven mapping injected from backend
            const configMap = @json(config('payment.gateways.FastSpring.addons', []));
            // Try with underscores and hyphens
            const underscoreKey = normalized.replace(/\s+/g, '_');
            const hyphenKey = normalized.replace(/\s+/g, '-');
            const resolved = configMap[underscoreKey] || configMap[hyphenKey] || hyphenKey;
            try {
                logAddonDebug('Addon product path resolved', {
                    adon,
                    normalized,
                    underscoreKey,
                    hyphenKey,
                    resolved,
                    keys: Object.keys(configMap || {})
                }, 'info');
            } catch (_) {}
            return resolved;
        }

        function ensureFastSpringLoaded(callback) {
            if (typeof fastspring !== 'undefined' && fastspring.builder) {
                callback();
                return;
            }
            // If script already being added, poll briefly
            if (document.getElementById('fsc-api')) {
                const waitForFS = setInterval(() => {
                    if (typeof fastspring !== 'undefined' && fastspring.builder) {
                        clearInterval(waitForFS);
                        callback();
                    }
                }, 100);
                setTimeout(() => clearInterval(waitForFS), 5000);
                return;
            }
            const fsScript = document.createElement('script');
            fsScript.id = 'fsc-api';
            fsScript.src = 'https://sbl.onfastspring.com/sbl/1.0.3/fastspring-builder.min.js';
            fsScript.type = 'text/javascript';
            fsScript.setAttribute('data-storefront', fastspringStorefront);
            fsScript.setAttribute('data-popup-closed', 'onFSPopupClosed');
            fsScript.onload = () => callback();
            document.head.appendChild(fsScript);
        }

        if (addonKey && !sessionStorage.getItem('addonAutoPopupTriggered')) {
            const productPath = getAddonProductPath(addonKey);
            sessionStorage.setItem('addonAutoPopupTriggered', 'true');
            sessionStorage.removeItem('pendingAddon');
            window.isAddonCheckout = true; // flag for close handler

            showSpinner('Opening add-on payment...', 'Connecting to FastSpring');

            ensureFastSpringLoaded(() => {
                try {
                    logAddonDebug('FastSpring loaded for addon', {
                        productPath,
                        addonKey,
                        storefront: fastspringStorefront
                    }, 'info');
                    if (!productPath) {
                        throw new Error('Missing add-on product mapping');
                    }
                    processFastSpring(productPath, 'addon');
                } catch (e) {
                    console.warn('Add-on checkout with specific product failed, attempting generic checkout...', e);
                    logAddonDebug('Addon checkout failed, attempting generic', {
                        error: String(e),
                        productPath
                    }, 'warning');
                    try {
                        fastspring.builder.reset();
                        // Fallback: open storefront popup without a specific product
                        setTimeout(() => {
                            fastspring.builder.checkout();
                            if (window.hideSpinner) {
                                window.hideSpinner();
                            }
                        }, 300);
                    } catch (fallbackErr) {
                        console.error('Fallback FastSpring checkout also failed:', fallbackErr);
                        logAddonDebug('Addon generic checkout failed', {
                            error: String(fallbackErr)
                        }, 'error');
                        if (window.hideSpinner) {
                            window.hideSpinner();
                        }
                        if (window.showError) {
                            window.showError('Payment Error', 'Could not start add-on checkout. Please configure the add-on product in FastSpring and try again.');
                        }
                        sessionStorage.removeItem('addonAutoPopupTriggered');
                        window.isAddonCheckout = false;
                    }
                }
            });
        }

        document.querySelectorAll('.checkout-button').forEach(button => {
            button.addEventListener('click', function() {
                console.log('=== BUTTON CLICKED ===', {
                    package: this.getAttribute('data-package'),
                    action: this.getAttribute('data-action'),
                    disabled: this.disabled,
                    hasActiveClass: this.classList.contains('active'),
                    hasDisabledClass: this.classList.contains('disabled')
                });
                const packageName = this.getAttribute('data-package');
                const action = this.getAttribute('data-action');

                if (packageName === 'Enterprise') {
                    console.log('Enterprise plan: Redirecting to contact page, no checkout.');
                    return;
                }

                if (this.disabled || this.classList.contains('active') || this.classList.contains('disabled')) {
                    console.warn('Button click ignored - button is disabled, active, or not available', {
                        packageName,
                        action,
                        disabled: this.disabled,
                        hasActiveClass: this.classList.contains('active'),
                        hasDisabledClass: this.classList.contains('disabled')
                    });
                    return;
                }

                // Track Facebook Pixel InitiateCheckout event
                if (typeof fbq !== 'undefined') {
                    const packagePrice = parseFloat(this.closest('.card')?.querySelector('.price')?.textContent?.replace(/[^0-9.]/g, '') || '0') || 0;
                    fbq('track', 'InitiateCheckout', {
                        value: packagePrice,
                        currency: 'USD',
                        content_name: packageName
                    });
                    console.log('Facebook Pixel InitiateCheckout event tracked:', {
                        value: packagePrice,
                        currency: 'USD',
                        content_name: packageName
                    });
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
            console.log('=== SHOW CONFIRMATION DIALOG ===', {
                packageName,
                action,
                gateway
            });
            const title = action === 'upgrade' ? 'Confirm Upgrade' : 'Confirm Downgrade';
            const text = action === 'upgrade' ?
                `You're about to upgrade from <strong>${currentPackage}</strong> to <strong>${packageName}</strong>. Your current subscription will be active only when current subscription expires.` :
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
                console.log('=== CONFIRMATION RESULT ===', {
                    isConfirmed: result.isConfirmed,
                    isDismissed: result.isDismissed,
                    dismiss: result.dismiss
                });
                if (result.isConfirmed) {
                    console.log('=== USER CONFIRMED, CALLING executeCheckout ===');
                    // Show spinner when user confirms
                    showSpinner('Processing...', `Setting up ${packageName} plan checkout`);
                    executeCheckout(packageName, action);
                } else {
                    console.log('=== USER CANCELLED OR DISMISSED ===');
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
                // Use the downgrade endpoint for all gateways
                apiUrl = `/api/payments/downgrade`;
                console.log('=== DOWNGRADE API CALL ===', {
                    url: apiUrl,
                    package: packageName,
                    gateway: selectedGateway
                });
            } else {
                apiUrl =
                    `/api/payments/${selectedGateway.toLowerCase().replace(/\s+/g, '')}/checkout/${packageName}`;
            }

            const requestBody = createRequestBody(packageName, action);
            console.log('=== FETCH REQUEST ===', {
                url: apiUrl,
                method: 'POST',
                body: requestBody,
                headers: getRequestHeaders(action === 'upgrade', action === 'downgrade')
            });

            fetch(apiUrl, {
                    method: 'POST',
                    headers: getRequestHeaders(action === 'upgrade', action === 'downgrade'),
                    credentials: 'same-origin',
                    body: JSON.stringify(requestBody)
                })
                .then(response => {
                    console.log('=== FETCH RESPONSE ===', {
                        status: response.status,
                        statusText: response.statusText,
                        url: response.url,
                        ok: response.ok
                    });
                    return handleFetchResponse(response);
                })
                .catch(error => {
                    console.error('=== FETCH ERROR ===', error);
                    throw error;
                })
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.error || 'Request failed');
                    }

                    // Handle free plan activation (no checkout needed)
                    if (handleFreePlanActivation(data)) {
                        return;
                    }

                    // Hide spinner before opening payment popup
                    hideSpinner();

                    if (action === 'downgrade') {
                        // For downgrades, handle based on gateway
                        if (selectedGateway === 'Paddle') {
                            console.log('Executing Paddle downgrade checkout with transaction ID from response...');
                            // Use the transaction ID from the downgrade response directly
                            if (data.transaction_id) {
                                openPaddleCheckout(data.transaction_id, action);
                            } else {
                                // Fallback to the old processPaddle method if no transaction_id
                                processPaddle(packageName, action);
                            }
                        } else if (selectedGateway === 'FastSpring') {
                            // For FastSpring downgrades, the backend returns a redirect URL (not a popup)
                            console.log('FastSpring downgrade API response:', data);
                            if (data.checkout_url) {
                                console.log('FastSpring downgrade successful, redirecting to:', data.checkout_url);
                                hideSpinner();
                                showSuccess(data.message || 'Downgrade Scheduled', 'Your downgrade has been scheduled successfully. It will take effect at the end of your current billing cycle.').then(() => {
                                    window.location.href = data.checkout_url;
                                });
                                return; // Exit after handling downgrade
                            } else {
                                console.error('FastSpring downgrade successful, but no checkout_url in response:', data);
                                throw new Error(data.error || 'FastSpring downgrade successful, but no redirect URL received.');
                            }
                        } else if (selectedGateway === 'Pay Pro Global') {
                            // For PayProGlobal downgrades, the backend directly returns a redirect_url
                            console.log('PayProGlobal downgrade API response:', data);
                            if (data.redirect_url) {
                                console.log('PayProGlobal downgrade successful, redirecting to:', data.redirect_url);
                                hideSpinner();
                                showSuccess(data.message || 'Downgrade Successful', 'Your plan has been successfully downgraded.').then(() => {
                                    window.location.href = data.redirect_url;
                                });
                                return; // Exit after handling downgrade
                            } else {
                                console.error('PayProGlobal downgrade successful, but no redirect_url in response:', data);
                                throw new Error(data.error || 'PayProGlobal downgrade successful, but no redirect URL received.');
                            }
                        } else if (data.checkout_url) {
                            // For other gateways with a checkout URL, open in popup (only if not FastSpring)
                            console.log('Opening downgrade checkout URL:', data.checkout_url);
                            const downgradePopup = window.open(data.checkout_url, 'downgrade_checkout', 'width=1200,height=800,scrollbars=yes,resizable=yes');

                            if (!downgradePopup) {
                                showError('Popup Blocked', 'Please allow popups for this site and try again. You may need to click the popup blocker icon in your browser\'s address bar.');
                                return;
                            }

                            // Monitor the downgrade popup (only if function exists)
                            if (typeof monitorDowngradePopup === 'function') {
                                monitorDowngradePopup(downgradePopup);
                            } else {
                                console.warn('monitorDowngradePopup function not defined, skipping popup monitoring');
                            }
                        } else {
                            throw new Error('No valid checkout method provided');
                        }
                    } else if (selectedGateway === 'FastSpring') {
                        processFastSpring(packageName, action);
                    } else if (selectedGateway === 'Paddle') {
                        console.log('Executing Paddle checkout with transaction ID from upgrade response...');
                        // Use the transaction ID from the upgrade response directly
                        if (data.transaction_id) {
                            openPaddleCheckout(data.transaction_id, action);
                        } else {
                            // Fallback to the old processPaddle method if no transaction_id
                            processPaddle(packageName, action);
                        }
                    } else if (selectedGateway === 'Pay Pro Global') {
                        console.log('Executing PayProGlobal checkout for action:', action);
                        processPayProGlobal(packageName, action);
                    }
                })
                .catch(error => {
                    console.error(`${action.charAt(0).toUpperCase() + action.slice(1)} error:`, error);
                    hideSpinner(); // Hide spinner on error
                    showError(`${action.charAt(0).toUpperCase() + action.slice(1)} Failed`, error.message);
                });
        }

        function openPaddleCheckout(transactionId, action) {
            console.log('Opening Paddle checkout with transaction ID:', transactionId);

            if (!validatePaddleInitialization()) {
                return;
            }

            // Store current action and transaction ID in session storage for global event listener
            sessionStorage.setItem('currentPaddleAction', action);
            sessionStorage.setItem('currentPaddleTransactionId', transactionId);
            console.log('Stored in session storage:', {
                action: action,
                transactionId: transactionId
            });

            // Get FirstPromoter tracking ID
            const fpTid = getFPTid();
            console.log('FirstPromoter tracking ID:', fpTid);

            // Prepare custom data for FirstPromoter tracking
            const customData = {};
            if (fpTid) {
                customData.fp_tid = fpTid;
            }
            if (userEmail) {
                customData.email = userEmail;
            }

            // Create a proper event callback function
            const paddleEventCallback = function(eventData) {
                console.log('Paddle event callback triggered:', eventData);

                // Track referral when checkout is completed
                if (eventData.name === 'checkout.completed' || eventData.type === 'checkout.completed') {
                    const email = eventData.data?.customer?.email || userEmail;
                    const uid = eventData.data?.customer?.id || loggedInUserId;

                    if (email && uid && typeof fpr !== 'undefined') {
                        console.log('Tracking FirstPromoter referral:', { email, uid });
                        fpr("referral", { email, uid });
                    }
                }

                handlePaddleEvent(eventData, action);
            };

            // Open Paddle checkout with proper error handling
            try {
                const checkoutOptions = {
                    transactionId: transactionId,
                    eventCallback: paddleEventCallback
                };

                // Add customData if we have FirstPromoter tracking ID or email
                if (Object.keys(customData).length > 0) {
                    checkoutOptions.customData = customData;
                    console.log('Paddle checkout with customData:', customData);
                }

                Paddle.Checkout.open(checkoutOptions);
            } catch (error) {
                console.error('Error opening Paddle checkout:', error);
                showError('Checkout Error', 'Failed to open payment checkout. Please try again.');
            }
        }

        function processPaddle(packageName, action) {
            console.log('=== PADDLE PROCESSING ===', {
                packageName,
                action
            });

            // Show spinner for Paddle processing
            showSpinner('Opening payment window...', 'Please wait while we connect to Paddle');

            if (!validatePaddleInitialization()) {
                hideSpinner();
                return;
            }

            const apiUrl = `/api/payments/paddle/checkout/${packageName}`;
            const requestBody = createRequestBody(packageName, action);

            fetch(apiUrl, {
                    method: 'POST',
                    headers: getRequestHeaders(action === 'upgrade', action === 'downgrade'),
                    credentials: 'same-origin',
                    body: JSON.stringify(requestBody)
                })
                .then(handleFetchResponse)
                .then(data => {
                    // Handle free plan activation (no checkout needed)
                    if (handleFreePlanActivation(data)) {
                        return;
                    }

                    if (!data.success || !data.transaction_id) {
                        throw new Error(data.error || 'No transaction ID provided');
                    }
                    console.log('Paddle checkout data received:', data);

                    // Hide spinner when checkout is opened
                    hideSpinner();

                    // Use the new openPaddleCheckout function
                    openPaddleCheckout(data.transaction_id, action);
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
            const requestBody = createRequestBody(packageName, action);

            console.log('Making request to:', apiUrl, 'with body:', requestBody);

            fetch(apiUrl, {
                    method: 'POST',
                    headers: getRequestHeaders(action === 'upgrade', action === 'downgrade'),
                    credentials: 'same-origin',
                    body: JSON.stringify(requestBody)
                })
                .then(response => {
                    console.log('Response status:', response.status);
                    return handleFetchResponse(response);
                })
                .then(data => {
                    console.log('API Response:', data);

                    if (!data.success) {
                        throw new Error(data.error || 'API returned success: false');
                    }

                    // Handle free plan activation (no checkout needed)
                    if (handleFreePlanActivation(data)) {
                        return;
                    }

                    // Handle downgrade action specifically
                    if (action === 'downgrade') {
                        console.log('PayProGlobal downgrade API response:', data); // Log full data for downgrade
                        const redirectUrl = data.redirect_url || data.checkout_url;
                        if (redirectUrl) {
                            console.log('PayProGlobal downgrade redirecting to:', redirectUrl);
                            hideSpinner();
                            showSuccess(data.message || 'Downgrade Successful', 'Your plan downgrade has been scheduled.').then(() => {
                                window.location.href = redirectUrl;
                            });
                            return; // Exit after handling downgrade
                        } else {
                            console.error('Downgrade response missing redirect URL:', data);
                            throw new Error(data.error || 'No redirect URL received.');
                        }
                    }

                    if (!data.checkout_url) {
                        console.error('No checkout_url in response:', data);
                        throw new Error('No checkout URL received from server');
                    }

                    console.log('PayProGlobal checkout URL received:', data.checkout_url);

                    // Validate URL format
                    if (!data.checkout_url.includes('payproglobal.com')) {
                        console.error('Invalid PayProGlobal checkout URL:', data.checkout_url);
                        hideSpinner();
                        showError('Payment Error', 'Received an invalid PayProGlobal checkout URL.');
                        return;
                    }

                    hideSpinner(); // Hide spinner before opening popup or redirecting

                    // Store user ID and package name in session storage for popup communication
                    const userId = "{{ Auth::id() ?? '' }}";
                    sessionStorage.setItem('payProGlobalUserId', userId);
                    sessionStorage.setItem('payProGlobalPackageName', packageName);
                    sessionStorage.setItem('payProGlobalAction', action);

                    // Store success URL for fallback redirect if PayPro Global redirects to marketplace
                    const successUrl = `/payments/success?gateway=payproglobal&user_id=${userId}&package=${packageName}&popup=true&pending_order_id=${data.pending_order_id}&action=${action}`;
                    sessionStorage.setItem('payProGlobalSuccessUrl', successUrl);

                    // Always open PayProGlobal checkout in the same tab
                    window.location.href = data.checkout_url;

                    console.log('Stored in session storage:', {
                        userId,
                        packageName,
                        action
                    });
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

            // Determine cancellation behavior based on payment gateway
            let cancellationInfo = '';
            if (currentPaymentGateway === 'fastspring' || currentPaymentGateway === 'paddle') {
                cancellationInfo = '<br><br><small class="text-muted">Your subscription will remain active until the end of your current billing period.</small>';
            } else if (currentPaymentGateway === 'payproglobal') {
                cancellationInfo = '<br><br><small class="text-muted">Your subscription will be cancelled immediately.</small>';
            }

            Swal.fire({
                title: 'Confirm Cancellation',
                html: `Are you sure you want to cancel your <strong>${currentPackage}</strong> subscription? This action cannot be undone.${cancellationInfo}`,
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
                            headers: getRequestHeaders(false, false),
                            credentials: 'same-origin'
                        })
                        .then(handleFetchResponse)
                        .then(data => {
                            hideSpinner(); // Hide spinner on success
                            if (data.success) {
                                // Handle different cancellation types with appropriate messages
                                if (data.cancellation_type === 'end_of_billing_period') {
                                    showInfo('Cancellation Scheduled',
                                        data.message || 'Your subscription cancellation has been scheduled. You will continue to have access until the end of your current billing period.').then(() => {
                                            window.location.href = '/user/dashboard';
                                        });
                                } else {
                                    // Immediate cancellation
                                    showSuccess('Subscription Cancelled',
                                        data.message || 'Your subscription has been cancelled successfully.').then(() => {
                                            window.location.href = '/user/dashboard';
                                        });
                                }
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

                if (action === 'current') {
                    button.classList.add('active');
                    button.disabled = true;
                    button.innerHTML = '<span class="current-package-text">Current Plan</span>';
                } else if (button.disabled) {
                    button.classList.add('disabled');
                    packageElement.classList.add('disabled-package');
                    // Keep the existing text from the server-side rendering
                } else if (action === 'upgrade') {
                    button.innerHTML = '<span class="upgrade-text">Upgrade to ' + packageName + '</span>';
                } else if (action === 'downgrade') {
                    button.innerHTML = '<span class="downgrade-text">Downgrade to ' + packageName + '</span>';
                }
            });
        }

        function validatePaddleInitialization() {
            if (typeof Paddle === 'undefined') {
                console.error('Paddle is not initialized');
                showError('Payment Error',
                    'Payment system is not properly initialized. Please refresh the page and try again.');
                return false;
            }

            if (typeof Paddle.Checkout === 'undefined') {
                console.error('Paddle.Checkout is not available');
                showError('Payment Error',
                    'Payment checkout is not available. Please refresh the page and try again.');
                return false;
            }

            return true;
        }

        function handleFetchResponse(response) {
            if (!response.ok) {
                return response.json().then(data => {
                    const msg = data.message || data.error || `HTTP ${response.status}`;
                    throw new Error(msg);
                });
            }
            return response.json();
        }

        function getRequestHeaders(isUpgrade = false, isDowngrade = false) {
            return {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'X-Is-Upgrade': isUpgrade ? 'true' : 'false',
                'X-Is-Downgrade': isDowngrade ? 'true' : 'false'
            };
        }

        function createRequestBody(packageName, action) {
            const fpTid = getFPTid();
            const body = {
                package: packageName,
                is_upgrade: action === 'upgrade',
                is_downgrade: action === 'downgrade'
            };

            if (action === 'downgrade') {
                return { package: packageName };
            }

            if (fpTid) {
                body.fp_tid = fpTid;
            }

            return body;
        }

        function logAddonDebug(message, context = {}, level = 'info') {
            try {
                fetch('/payments/addon-debug-log', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ message, context, level })
                }).catch(() => {});
            } catch (_) {}
        }

        function handleFreePlanActivation(data) {
            if (data.message && data.message.includes('Free plan activated successfully')) {
                console.log('Free plan activated successfully, redirecting...');
                hideSpinner();
                showSuccess('Free Plan Activated', data.message || 'Your free plan has been activated successfully!').then(() => {
                    window.location.href = '/user/subscription-details';
                });
                return true;
            }
            return false;
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

        // Spinner functions - using universal spinner component
        function showSpinner(text = 'Loading...', subtext = 'Please wait while we prepare your payment') {
            console.log('Showing spinner:', { text, subtext });
            const spinnerOverlay = document.getElementById('spinnerOverlay');
            const spinnerText = document.getElementById('spinnerText');

            // Use subtext if provided and different from default, otherwise use text
            const message = subtext && subtext !== 'Please wait while we prepare your payment'
                ? subtext
                : text;

            if (spinnerText) spinnerText.textContent = message;
            if (spinnerOverlay) {
                spinnerOverlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        }

        function hideSpinner() {
            console.log('Hiding spinner');
            const spinnerOverlay = document.getElementById('spinnerOverlay');
            if (spinnerOverlay) {
                spinnerOverlay.classList.remove('active');
                document.body.style.overflow = '';
            }
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
            sessionStorage.removeItem('downgradeSuccessUrl');
        });

        // Account dropdown toggle functionality
        const dropdownToggle = document.querySelector('.account-dropdown .dropdown-toggle');
        const dropdownMenu = document.querySelector('.account-dropdown .dropdown-menu');

        if (dropdownToggle && dropdownMenu) {
            dropdownToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                const isOpen = dropdownMenu.classList.contains('show');

                if (isOpen) {
                    dropdownMenu.classList.remove('show');
                    this.setAttribute('aria-expanded', 'false');
                } else {
                    dropdownMenu.classList.add('show');
                    this.setAttribute('aria-expanded', 'true');
                }
            });

            document.addEventListener('click', function(event) {
                const dropdown = document.querySelector('.account-dropdown');
                if (dropdown && !dropdown.contains(event.target)) {
                    dropdownMenu.classList.remove('show');
                    dropdownToggle.setAttribute('aria-expanded', 'false');
                }
            });
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

    // Note: injectPayProGlobalSuccessHandler is deprecated - cannot inject scripts into cross-origin popups due to CORS
    // Instead, we rely on:
    // 1. PayProGlobal redirecting to our thank you handler URL (configured in PaymentController)
    // 2. postMessage API if PayProGlobal supports it
    // 3. URL monitoring in monitorPayProGlobalPopup function
    function injectPayProGlobalSuccessHandler(popup, userId, packageName) {
        console.warn('injectPayProGlobalSuccessHandler: Cannot inject scripts into cross-origin popups. Using postMessage and URL monitoring instead.');
        // This function is kept for backwards compatibility but does nothing
        // Cross-origin script injection is blocked by browser security
    }
</script>

