<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    public function createPaymentSession()
    {
        try {
            $order = Order::query()
                ->forUser(Auth::id())
                ->unpaid()
                ->latest()
                ->firstOrFail();

            return redirect()->away(
                $this->paymentService->createPaymentSession($order)
            );
        } catch (\Exception $e) {
            Log::error("Payment session error: {$e->getMessage()}");
            return back()->with('error', $e->getMessage());
        }
    }

    public function pricing()
    {
        $user = Auth::user();
        $latestOrder = Order::query()
            ->forUser($user->id)
            ->latest()
            ->first();

        return view('subscription.index', [
            'user_order' => $user->orders,
            'latest_order_package' => $latestOrder?->package
        ]);
    }

    public function handleSubscription(Request $request)
    {
        $user = Auth::user();
        $package = strtolower($request->query('package_name', ''));

        if ($this->hasExistingPackage($user, $package)) {
            return view('subscription.package', [
                'latest_order_package' => $package
            ]);
        }

        if ($package === 'free') {
            $this->createFreeOrder($user);
            return $this->packageView($user, $package);
        }

        return view('subscription.index', [
            'package' => $package,
            'latest_order_package' => $user->latestOrder?->package
        ]);
    }

    // Package handlers
    public function handleStarterPackage()
    {
        return $this->handlePackage('Starter', 390);
    }

    public function handleProPlan()
    {
        return $this->handlePackage('Pro', 780);
    }

    public function handleBusinessPlan()
    {
        return $this->handlePackage('Business', 2800);
    }

    // Confirmation handlers
    public function starterPackageConfirmed()
    {
        return $this->confirmPackage('Starter');
    }

    public function proPackageConfirmed()
    {
        return $this->confirmPackage('Pro');
    }

    public function businessPackageConfirmed()
    {
        return $this->confirmPackage('Business');
    }

    // Webhook handler
    public function handlePaymentWebhook(Request $request, string $gateway)
    {
        try {
            $this->paymentService->setGateway($gateway)
                ->handlePaymentCallback($request->all());

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error("Payment webhook error: {$e->getMessage()}");
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /*********************
     * Private Helpers *
     *********************/

    private function handlePackage(string $package, float $amount)
    {
        $user = Auth::user();

        $order = Order::firstOrCreate(
            ['user_id' => $user->id, 'package' => $package],
            ['amount' => $amount, 'payment' => null]
        );

        return view("subscription.{$package}", [
            'hasPackage' => is_null($order->payment),
            'hasFreePackage' => $user->orders()->where('package', 'Free')->exists()
        ]);
    }

    private function confirmPackage(string $package)
    {
        $user = Auth::user();

        $user->orders()
            ->where('package', $package)
            ->update(['payment' => 'yes']);

        return view('subscription.index', [
            'packages' => $user->orders()
                ->select('package')
                ->distinct()
                ->pluck('package')
        ]);
    }

    private function hasExistingPackage($user, string $package): bool
    {
        return $user->orders()
            ->whereRaw('LOWER(package) = ?', [strtolower($package)])
            ->exists();
    }

    private function createFreeOrder($user): void
    {
        $user->orders()->create([
            'package' => 'Free',
            'amount' => 0,
            'payment' => null
        ]);
    }

    private function packageView($user, string $package)
    {
        return view('subscription.package', [
            'user_order' => $user->orders,
            'latest_order_package' => $user->orders()
                ->latest()
                ->value('package')
        ]);
    }
}
