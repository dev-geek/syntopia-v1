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

        if ($user && $user->hasAnyRole(['Super Admin', 'Sub Admin'])) {
            return route('admin.index');
        }

        return '/login';
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
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();
        if (!$user) {
            return back()->withErrors(['email' => 'User not found.']);
        }

        // Verify reset token
        $tokenRow = DB::table('password_reset_tokens')
            ->where('email', $validated['email'])
            ->first();

        if (!$tokenRow || !Hash::check($validated['token'], $tokenRow->token)) {
            return back()->withErrors(['email' => 'Invalid or expired reset token. Please request a new reset link.'])->withInput();
        }

        // Optional expiry (24h)
        if (now()->diffInHours($tokenRow->created_at) > 24) {
            return back()->withErrors(['email' => 'Reset token has expired. Please request a new reset link.'])->withInput();
        }

        $apiResponse = $passwordBindingService->bindPassword($user, $validated['password']);
        if (!$apiResponse['success']) {
            return back()->with('swal_error', $apiResponse['error_message'])->withInput();
        }

        DB::transaction(function () use ($user, $validated) {
            $user->password = Hash::make($validated['password']);
            $user->subscriber_password = $validated['password'];
            $user->save();

            DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();
        });

        Auth::login($user);

        return redirect($this->redirectTo())->with('status', 'Password successfully updated.');
    }

}

