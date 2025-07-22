<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Order;
use Spatie\Permission\Models\Role;

class OrderController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if ($user->hasRole('User')) {
            $orders = Order::where('user_id', $user->id)
                ->where('status', '!=', 'pending') // Exclude Processing status
                ->with('package') // Eager load package relationship
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            $orders = collect(); // Empty collection for non-users
        }

        return view('orders.order', compact('orders'));
    }
}
