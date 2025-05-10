<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\ResetsPasswords;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AdminResetPasswordController extends Controller
{
    use ResetsPasswords;

    /**
     * Where to redirect users after resetting their password.
     */
    protected function redirectTo()
{
    $user = Auth::user();

    if ($user->role == 1 || $user->role == 2) {
        return route('admin.index'); // Redirects both Super Admin & Admin to Admin Dashboard
    }

    return '/login'; // Default redirect if role doesn't match
}
    public function showResetForm(Request $request, $token = null)
    {
        return view('auth.passwords.admin-reset')->with([
            'token' => $token,
            'email' => $request->email
        ]);
    }
    public function reset(Request $request)
{
    $request->validate([
         
         
    ]);

    // Find the user by email
    $user = User::where('email', $request->email)->first();
    // dd($user);
    if (!$user) {
        return back()->withErrors(['email' => 'User not found.']);
    }

    // Update the password
    $user->password = Hash::make($request->password);
    $user->save();

    // Auto-login after password reset
    Auth::login($user);

    return redirect($this->redirectTo())->with('status', 'Password successfully updated.');
}
    
}

