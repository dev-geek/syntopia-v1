<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\Payment\PaymentService;
use App\Http\Requests\Payments\CheckoutRequest;
use App\Http\Requests\Payments\SuccessCallbackRequest;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
    ) {}

    public function gatewayCheckout(CheckoutRequest $request, string $gateway, string $package)
    {
        try {
            $user = Auth::user();

            $gatewayToUse = $gateway;
            if ($user->paymentGateway) {
                $gatewayToUse = $user->paymentGateway->name;
            }

            $result = $this->paymentService->processPayment([
                'package' => $package,
                'user' => $user,
            ], $gatewayToUse, true);

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Gateway checkout error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'package' => $package,
                'user_id' => Auth::id(),
                'gateway' => $gateway
            ]);

            return response()->json([
                'success' => false,
                'error' => $this->paymentService->getUserFriendlyErrorMessage($e->getMessage())
            ], $this->paymentService->getHttpStatusCode($e->getMessage()));
        }
    }

    public function handleSuccess(SuccessCallbackRequest $request)
    {
        try {
            $result = $this->paymentService->processSuccessCallbackWithAuth(
                $request->all(),
                $request->query(),
                $request->input('success-url'),
                $request
            );

            return $this->paymentService->handleSuccessResponse($result, $result['gateway']);
        } catch (\Exception $e) {
            Log::error('Payment success callback error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_all' => $request->all()
            ]);

            $errorMessage = $this->paymentService->getUserFriendlyErrorMessage($e->getMessage());
            return redirect()->route('subscription')->with('error', $errorMessage);
        }
    }

    public function handleAddonSuccess(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return redirect()->route('login')->with('error', 'Please log in to complete your add-on purchase');
            }

            $result = $this->paymentService->processAddonSuccessWithValidation(
                $user,
                $request->input('orderId'),
                $request->input('addon')
            );

            if ($result['success']) {
                return redirect()->route('user.dashboard')->with('success', $result['message']);
            } else {
                return redirect()->route('subscription')->with('error', $result['error']);
            }
        } catch (\Exception $e) {
            Log::error('Add-on success processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect()->route('subscription')->with('error', 'Failed to process add-on purchase');
        }
    }

    public function handlePayProGlobalThankYou(Request $request)
    {

        $orderId = $request->query('OrderId')
            ?? $request->query('orderId')
            ?? $request->query('ORDER_ID')
            ?? $request->input('OrderId')
            ?? $request->input('orderId')
            ?? $request->input('ORDER_ID');

        $externalOrderId = $request->query('ExternalOrderId')
            ?? $request->query('externalOrderId')
            ?? $request->query('EXTERNAL_ORDER_ID')
            ?? $request->input('ExternalOrderId')
            ?? $request->input('externalOrderId')
            ?? $request->input('EXTERNAL_ORDER_ID');

        if (!$orderId && !$externalOrderId) {
            Log::warning('[PaymentController::handlePayProGlobalThankYou] No OrderId or ExternalOrderId found', [
                'query_params' => $request->query(),
                'all_input' => $request->all(),
            ]);
            return redirect()->route('subscription')->with('error', 'Invalid payment confirmation. Please contact support.');
        }

        $successParams = [
            'gateway' => 'payproglobal',
        ];

        if ($orderId) {
            $successParams['OrderId'] = $orderId;
        }
        if ($externalOrderId) {
            $successParams['ExternalOrderId'] = $externalOrderId;
        }

        return redirect()->route('payments.success', $successParams);
    }

}
