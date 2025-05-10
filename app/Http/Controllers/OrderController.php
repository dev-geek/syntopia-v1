<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Order;

class OrderController extends Controller
{
    public function index()
    {
        $userId = Auth::id();
        
        // Get all orders for the user, ordered by latest first
        $orders = Order::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();
            
        return view('subscription.order', compact('orders'));
    }
} 