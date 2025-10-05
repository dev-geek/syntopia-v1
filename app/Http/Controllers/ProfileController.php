<?php

namespace App\Http\Controllers;
use App\Models\UserLog;
use App\Services\PasswordBindingService;
use App\Services\FreePlanAbuseService;
use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\Order;
use App\Models\User;


class ProfileController extends Controller
{
    private FreePlanAbuseService $freePlanAbuseService;

    public function __construct(FreePlanAbuseService $freePlanAbuseService)
    {
        $this->freePlanAbuseService = $freePlanAbuseService;
    }

    public function profile()
    {
        $user = auth()->user()->load('package');
        return view('auth.profile', compact('user'));
    }
    public function updatePassword(Request $request)
    {
        return view('auth.update-password');
    }
    public function updateProfile(UpdateProfileRequest $request, PasswordBindingService $passwordBindingService)
    {
        $validated = $request->validated();

        $user = Auth::user();

        $user->name = $validated['name'];

        // Handle password update
        if (!empty($validated['password'])) {
            // Skip password binding API for Super Admin role
            if ($user->hasRole('Super Admin')) {
                // Direct password update for admin users
                $user->password = $validated['password'];
                $user->subscriber_password = $validated['password'];
            } else {
                // Call password binding API for regular users
                $apiResponse = $passwordBindingService->bindPassword($user, $validated['password']);

                if (!$apiResponse['success']) {
                    return back()->with('swal_error', $apiResponse['error_message'])->withInput();
                }

                // Only update password if API call was successful
                $user->password = $validated['password'];
                $user->subscriber_password = $validated['password'];
            }
        }

        // Check if any attributes were changed
        if (!$user->isDirty()) {
            return back()->with('info', 'No changes were made.')->withInput();
        }

        // Determine what was changed for the log message
        $changes = [];
        if ($user->isDirty('name')) {
            $changes[] = 'name';
        }
        if ($user->isDirty('password')) {
            $changes[] = 'password';
        }

        $user->save();

        // Log the activity if something was saved
        if (!empty($changes)) {
            $activityMessage = "{$user->name} updated " . implode(' and ', $changes) . ".";
            UserLog::create([
                'user_id' => $user->id,
                'activity' => $activityMessage,
                'ip_address' => request()->ip(),
                'user_agent' => request()->header('User-Agent'),
            ]);
        }

        return back()->with('success', 'Profile updated successfully!');
    }



    public function package($package_name)
    {
        $packages = [
            'starter' => ['name' => 'Starter', 'amount' => 390],
            'pro' => ['name' => 'Pro', 'amount' => 780],
            'business' => ['name' => 'Business', 'amount' => 2800],
            'free' => ['name' => 'Free', 'amount' => 0],
        ];

        if (!array_key_exists($package_name, $packages)) {
            abort(404, 'Package not found.');
        }

        $user = Auth::user();
        $userId = $user->id;

        // Special handling for free plan
        if ($package_name === 'free') {
            // Check if user can use free plan
            $canUseFreePlan = $this->freePlanAbuseService->canUseFreePlan($user, request());

            if (!$canUseFreePlan['allowed']) {
                return redirect()->back()
                    ->with('swal_error', $canUseFreePlan['message']);
            }

            // Assign free plan
            $result = $this->freePlanAbuseService->assignFreePlan($user, request());

            if (!$result['success']) {
                return redirect()->back()
                    ->with('swal_error', $result['message']);
            }

            return redirect()->route('subscription')
                ->with('success', 'Free plan has been assigned successfully!');
        }

        // Get the latest order for the user (excluding Processing status)
        $latest_order = Order::where('user_id', $userId)
            ->where('status', '!=', 'pending')
            ->orderBy('created_at', 'desc')
            ->first();

        $latest_order_package = $latest_order ? $latest_order->package : null;
        $user_order = Order::where('user_id', $userId)
            ->where('status', '!=', 'pending')
            ->get();

        // Only prevent duplicate if the latest order has the same package
        if ($latest_order_package === $packages[$package_name]['name']) {
            return view('subscription.package', compact('user_order', 'latest_order_package'));
        }

        // Create new order if latest order is different or no orders exist
        $order = new Order();
        $order->user_id = $userId;
        $order->package = $packages[$package_name]['name'];
        $order->amount = $packages[$package_name]['amount'];
        $order->payment = 'Yes';
        $order->save();

        $user_order = Order::where('user_id', $userId)
            ->where('status', '!=', 'pending')
            ->get();
        $latest_order = Order::where('user_id', $userId)
            ->where('status', '!=', 'pending')
            ->orderBy('created_at', 'desc')
            ->first();

        $latest_order_package = $latest_order->package ?? null;

        return view('subscription.package', compact('user_order', 'latest_order_package'));
    }




}
