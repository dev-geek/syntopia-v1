<?php

namespace App\Http\Controllers;

use App\Models\PaymentGateways;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentGatewaysController extends Controller
{
    public function index()
    {
        if (!auth()->user()->hasAnyRole(['Super Admin', 'Sub Admin'])) {
            abort(403, 'Unauthorized access');
        }

        $payment_gateways = PaymentGateways::all();
        return view('admin.payment-gateways.index', compact('payment_gateways'));
    }

    public function toggleStatus(Request $request)
    {
        try {
            $id = $request->id;

            // Validate ID
            if (!$id || !is_numeric($id)) {
                return redirect()->back()->with('error', 'Invalid Payment Gateway ID.');
            }

            // Check if gateway exists
            $gateway = PaymentGateways::find($id);
            if (!$gateway) {
                return redirect()->back()->with('error', 'Payment Gateway not found.');
            }

            // Deactivate all first
            PaymentGateways::query()->update(['is_active' => 0]);

            // Activate selected
            $gateway->is_active = 1;
            $gateway->save();

            return redirect()->back()->with('success', $gateway->name . ' payment gateway has been activated successfully.');
        } catch (\Exception $e) {
            Log::error('Payment gateway toggle error: '.$e->getMessage());

            return redirect()->back()->with('error', 'An unexpected error occurred. Please try again.');
        }
    }
}
