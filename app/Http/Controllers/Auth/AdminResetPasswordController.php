<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminResetPasswordRequest;
use App\Services\PasswordBindingService;
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
        public function reset(AdminResetPasswordRequest $request, PasswordBindingService $passwordBindingService)
{
    // Get validated data
    $validated = $request->validated();

    // Find the user by email
    $user = User::where('email', $validated['email'])->first();
    if (!$user) {
        return back()->withErrors(['email' => 'User not found.']);
    }

    // Call password binding API before updating the database
    $apiResponse = $passwordBindingService->bindPassword($user, $validated['password']);

    if (!$apiResponse['success']) {
        return back()->with('swal_error', $apiResponse['error_message'])->withInput();
    }

    // Only update password if API call was successful
    $user->password = Hash::make($validated['password']);
    $user->subscriber_password = $validated['password'];
    $user->save();

    // Auto-login after password reset
    Auth::login($user);

    return redirect($this->redirectTo())->with('status', 'Password successfully updated.');
}

}

