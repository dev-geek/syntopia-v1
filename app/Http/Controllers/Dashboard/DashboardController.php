<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Payments\PaymentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    public function __construct(
        private PaymentController $paymentController
    ) {
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function dashboard(Request $request)
    {
        // Handle PayProGlobal POST callbacks - forward to payment success handler
        if ($request->isMethod('post') && ($request->has('ORDER_STATUS') || $request->has('ORDER_ID') || $request->has('ORDER_ITEMS'))) {
            // Merge gateway parameter into request and forward to payment success handler
            $request->merge(['gateway' => 'payproglobal']);
            return $this->paymentController->handleSuccess($request);
        }

        return view('dashboard.layout');
    }
}
