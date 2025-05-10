<?php

namespace App\Http\Controllers;
use App\Models\UserLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\Order;
use App\Models\User;


class ProfileController extends Controller
{
    public function profile()
    {
        // Ensure the user is authenticated and return their details
        $user = Auth::user();

        

         // Check if user already has the Starter package
         $hasStarterPackage = Order::where('user_id', Auth::id())
         ->where('package', 'Starter')
         ->where('payment', '!=', null)

         ->exists();

         

            // Check Free package status too if you're showing both buttons on the same view
            $hasFreePackage = Order::where('user_id', Auth::id())
                ->where('package', 'Free')
                ->exists();

                $hasProPackage = Order::where('user_id', Auth::id())
                ->where('package', 'Pro')
                ->where('payment', '!=', null)
                ->exists();

                
                $hasBusinessPackage = Order::where('user_id', Auth::id())
                ->where('package', 'Business')
                ->where('payment', '!=', null)

                ->exists();

            
        return view('auth.profile', compact('user','hasStarterPackage','hasFreePackage','hasProPackage','hasBusinessPackage'));
    }
    public function updatePassword(Request $request)
    {
        return view('auth.update-password');
    }
    public function updateProfile(Request $request)
    {
        $request->validate([
            
            'password' => 'nullable|string|min:8',
            'subscriber_password' => 'nullable|string', // Allow subscriber_password to be nullable
        ]);
    
        $user = Auth::user();
        $originalName = $user->name; // Store original name
        $originalPassword = $user->password; // Store original hashed password
    
        
        $user->subscriber_password = $request->input('password');
    
        $passwordChanged = false;
    
        // Check if password is provided and different from the existing one
        if ($request->filled('password') && !Hash::check($request->password, $originalPassword)) {
            $user->password = Hash::make($request->input('password'));
            $passwordChanged = true;
        }
    
        $user->save();
    
        // Determine the activity log message
        if ($originalName !== $user->name && !$passwordChanged) {
            // Name updated but password remains the same
            $activity = "User Updated Name to {$user->name}";
        } elseif ($passwordChanged) {
            // Password was updated
            $activity = "{$user->name} Updated Password";
        } else {
            // No meaningful changes, do not log
            return back()->with('info', 'No changes detected.');
        }
    
        // Log the user activity
        UserLog::create([
            'user_id' => $user->id,
            'activity' => $activity,
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
        ]);
    
        return back()->with('success', 'User updated successfully.');
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
    
        $userId = Auth::id();
        
        // Get the latest order for the user
        $latest_order = Order::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->first();
            
        $latest_order_package = $latest_order ? $latest_order->package : null;
        $user_order = Order::where('user_id', $userId)->get();

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
     
        $user_order = Order::where('user_id', $userId)->get();
        $latest_order = Order::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->first(); 
        
        $latest_order_package = $latest_order->package ?? null;

        return view('subscription.package', compact('user_order', 'latest_order_package'));
    }
    
    


}
