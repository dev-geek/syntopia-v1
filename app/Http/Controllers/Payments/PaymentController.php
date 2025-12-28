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
                Log::info('[PaymentController::gatewayCheckout] Using user original gateway instead of requested gateway', [
                    'user_id' => $user->id,
                    'requested_gateway' => $gateway,
                    'user_original_gateway' => $gatewayToUse,
                ]);
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

}
