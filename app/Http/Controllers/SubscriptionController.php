<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Order;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Route;


class SubscriptionController extends Controller
{
    //Handle Subscription
    public function createPaddleSession()
    {
        // Your Paddle session creation logic here
        // For example, you might want to redirect to a Paddle checkout page
        return view('subscription.paddle');
    }
    public function pricing(){
        $user = Auth::user();
        $userId = $user->id;
    
        // Check if the user already has any orders
        $alreadyHasAnyOrder = Order::where('user_id', $userId)->exists();
        
        // Fetch the user's orders
        $user_order = Order::where('user_id', $userId)->get();
        
        // Get the latest order for the user
        $latest_order = Order::where('user_id', $userId)
            ->orderBy('created_at', 'desc') // Order by creation date (latest first)
            ->first(); 
    
        // Get the latest order package (null if no orders exist)
        $latest_order_package = $latest_order->package ?? null;
    
        // Check the current route name to distinguish the logic
        $routeName = Route::currentRouteName();
     
        // Return the pricing view with the order and package data
        return view('subscription.index', compact('user_order', 'latest_order_package'));
    }
 
    public function handleSubscription(Request $request)
    {
        $user = Auth::user();
        $userId = $user->id;
        $package = $request->query('package_name');
        
        // Get the latest order for the user
        $latest_order = Order::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->first(); 
        
        $latest_order_package = $latest_order ? $latest_order->package : null;
        $user_order = Order::where('user_id', $userId)->get();

        // If user already has the same package as the latest order, redirect to package view
        if ($latest_order_package && strtolower($latest_order_package) === $package) {
            return view('subscription.package', compact('user_order', 'latest_order_package'));
        }

        if ($package == 'free') {
            // Create new free order
            $order = new Order();
            $order->user_id = $userId;
            $order->package = "Free";
            $order->amount = 0;
            $order->payment = null;
            $order->save();
    
            // Refresh the latest order data after creating new order
            $latest_order = Order::where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->first();
            $latest_order_package = $latest_order->package;
            $user_order = Order::where('user_id', $userId)->get();
            
            return view('subscription.package', compact('user_order', 'latest_order_package'));
        }
        
        if ($latest_order) {
            return view('subscription.package', compact('user_order', 'latest_order_package'));
        }
    
        return view('subscription.index', compact('package', 'latest_order_package'));
    }
    


    public function index()
    {
        return view('subscription.index');
    }
    public function login()
    {
        return view('subscription.login');
    }
    public function selectSub( Request $request)
    {
        $plan = $request->query('plan');
        // dd($plan);
        return view('subscription.select',compact('plan'));
    }
    public function handleFreePlan()
    {
        return redirect()->route('package', ['package_name' => 'free']);

    }
    
    

    public function handleStarterPackage()
{
    // Check if a Starter package exists and the payment is still null
    $starterOrder = Order::where('user_id', Auth::id())
                         ->where('package', 'Starter')
                         ->first();

    $hasStarterPackage = false;

    // If an order exists
    if ($starterOrder) {
        // If payment is null, user still needs to pay → show FastSpring
        $hasStarterPackage = is_null($starterOrder->payment);
    } else {
        // No starter package exists, so create it and set flag to show FastSpring
        Order::create([
            'user_id' => Auth::id(),
            'package' => "Starter",
            'amount' => 390,
            'payment' => null,
        ]);

        $hasStarterPackage = true;
    }

    // Check Free package status (optional)
    $hasFreePackage = Order::where('user_id', Auth::id())
                           ->where('package', 'Free')
                           ->exists();

    return view('subscription.starter', compact('hasStarterPackage', 'hasFreePackage'));
}

    public function handleProPlan(){
        // Check if a Starter package exists and the payment is still null
    $starterOrder = Order::where('user_id', Auth::id())
    ->where('package', 'Starter')
    ->first();

        $hasStarterPackage = false;

        // If an order exists
        if ($starterOrder) {
        // If payment is null, user still needs to pay → show FastSpring
        $hasStarterPackage = is_null($starterOrder->payment);
        } else {
        // No starter package exists, so create it and set flag to show FastSpring
        Order::create([
        'user_id' => Auth::id(),
        'package' => "Pro",
        'amount' => 780,
        'payment' => null,
        ]);

        $hasStarterPackage = true;
        }

        // Check Free package status (optional)
        $hasFreePackage = Order::where('user_id', Auth::id())
            ->where('package', 'Free')
            ->exists();

        return view('subscription.pro', compact('hasStarterPackage', 'hasFreePackage'));
            }

            public function handleBusinessPlan(){
                // Check if a Starter package exists and the payment is still null
            $starterOrder = Order::where('user_id', Auth::id())
            ->where('package', 'Starter')
            ->first();
        
                $hasStarterPackage = false;
        
                // If an order exists
                if ($starterOrder) {
                // If payment is null, user still needs to pay → show FastSpring
                $hasStarterPackage = is_null($starterOrder->payment);
                } else {
                // No starter package exists, so create it and set flag to show FastSpring
                Order::create([
                'user_id' => Auth::id(),
                'package' => "Business",
                'amount' => 2800,
                'payment' => null,
                ]);
        
                $hasStarterPackage = true;
                }
        
                // Check Free package status (optional)
                $hasFreePackage = Order::where('user_id', Auth::id())
                    ->where('package', 'Free')
                    ->exists();
        
                return view('subscription.business', compact('hasStarterPackage', 'hasFreePackage'));
                    }


                    public function businessPackageConfirmed(){
                        // First, check if user has a Pro package
            
                $proOrder = Order::where('user_id', Auth::id())
                    ->where('package', 'business')
                    ->first();
            
                if ($proOrder) {
                    // If Pro package exists, and payment is not 'yes', update it
                    if ($proOrder->payment !== 'yes') {
                        $proOrder->update(['payment' => 'yes']);
                    }
                }
                $hasFreePackage = Order::where('user_id', Auth::id())
                            ->where('package', 'Free')
                            ->exists();
                // Check if user already has the Starter package
                $hasStarterPackage = Order::where('user_id', Auth::id())
                ->where('package', 'Starter')
                ->where('payment', '!=', null)
                ->exists();

                $hasBusinessPackage = Order::where('user_id', Auth::id())
                ->where('package', 'Business')
                ->where('payment', '!=', null)
                ->exists();
            
                $hasProPackage = Order::where('user_id', Auth::id())
                ->where('package', 'Pro')
                ->where('payment', '!=', null)
                ->exists();

              
                return view('subscription.index', compact('hasStarterPackage', 'hasFreePackage','hasProPackage','hasBusinessPackage'));
                    
                }

    public function proPackageConfirmed(){
            // First, check if user has a Pro package

    $proOrder = Order::where('user_id', Auth::id())
        ->where('package', 'Pro')
        ->first();

    if ($proOrder) {
        // If Pro package exists, and payment is not 'yes', update it
        if ($proOrder->payment !== 'yes') {
            $proOrder->update(['payment' => 'yes']);
        }
    }
    $hasFreePackage = Order::where('user_id', Auth::id())
                ->where('package', 'Free')
                ->exists();
    // Check if user already has the Starter package
    $hasStarterPackage = Order::where('user_id', Auth::id())
    ->where('package', 'Starter')
    ->exists();
    $hasBusinessPackage = Order::where('user_id', Auth::id())
    ->where('package', 'Business')
    ->where('payment', '!=', null)
    ->exists();
    $hasProPackage = Order::where('user_id', Auth::id())
    ->where('package', 'Pro')
    ->exists();
    return view('subscription.index', compact('hasStarterPackage', 'hasFreePackage','hasProPackage','hasBusinessPackage'));
        
    }
    public function starterPackageConfirmed()
    {
        $starterOrder = Order::where('user_id', Auth::id())
        ->where('package', 'Starter')
        ->first();

         // Check if user already has the Starter package
         $hasStarterPackage = Order::where('user_id', Auth::id())
         ->where('package', 'Starter')
         ->exists();

            // Only create a new order if the package isn't already activated
            if ($starterOrder) {
                // If the order exists and payment is still null or not 'yes', update it
                if ($starterOrder->payment !== 'yes') {
                    $starterOrder->update(['payment' => 'yes']);
                }
            } else {
                // If no Starter package yet, create one
                Order::create([
                    'user_id' => Auth::id(),
                    'package' => "Starter",
                    'amount' => 390,
                    'payment' => 'yes',
                ]);
            }

            // Check Free package status too if you're showing both buttons on the same view
            $hasFreePackage = Order::where('user_id', Auth::id())
                ->where('package', 'Free')
                ->exists();
                $hasProPackage = Order::where('user_id', Auth::id())
                ->where('package', 'Pro')
                ->exists();

                $hasProPackage = Order::where('user_id', Auth::id())
                ->where('package', 'Pro')
                ->exists();

                $hasBusinessPackage = Order::where('user_id', Auth::id())
                ->where('package', 'Business')
                ->where('payment', '!=', null)
                ->exists();
            return view('subscription.index', compact('hasStarterPackage', 'hasFreePackage', 'hasProPackage','hasBusinessPackage'));
    }
    


    public function confirmSubscription(Request $request)
        {
        
            
            $plan = $request->query('plan'); // Retrieve the plan from query parameters
            $amount=100;
            if ($plan == 0){
                $package="Starter";
                $amount=0;
             }else {
                $package="Personal";
             }
             
            Order::create([
                'user_id' => Auth::id(),
                'package' => $package,
                'amount' =>$amount,
                'payment' => null,
            ]);

            if ($plan == 0) {
                $message = 'You have successfully purchased the "Starter" subscription.';
            } elseif ($plan == 100) {
                $message = 'You have successfully subscribed to the "Personal" plan for $100.';
            } else {
                $message = 'Invalid subscription plan.';
            }

            return view('subscription.confirm', compact('message'));
        }

        public function createFastSpringSession()
{
    return view('fastspring');
 return response()->json(json_decode($response, true));
    }
}

 