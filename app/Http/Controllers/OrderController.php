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
                ->orderBy('created_at', 'desc')
                ->paginate(10);
        }

        return view('subscription.order', compact('orders'));
    }
}
