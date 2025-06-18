<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\VerifyEmail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Foundation\Auth\VerifiesEmails;

class VerificationController extends Controller
{
    use VerifiesEmails;

    protected $redirectTo = '/pricing';

    public function __construct()
    {
        $this->middleware('web');
        // Removed 'signed' middleware as we're using custom verification codes, not signed URLs
        $this->middleware('throttle:6,1')->only('verifyCode', 'resend');
    }

    public function show()
    {
        // Get email from session
        $email = session('email');

        if (!$email) {
            return redirect()->route('login')->withErrors('Session expired. Please login again.');
        }

        // Check if user exists
        $user = User::where('email', $email)->first();
        if (!$user) {
            return redirect()->route('login')->withErrors('User not found. Please register again.');
        }

        // Check if already verified
        if ($user->status == 1 && !is_null($user->email_verified_at)) {
            return redirect()->route('login')->with('success', 'Email already verified. Please login.');
        }

        return view('auth.verify-code', ['email' => $email]);
    }

    public function verifyCode(Request $request)
    {
        // Removed dd() - this was stopping execution
        $request->validate([
            'verification_code' => 'required|string|size:6'
        ]);

        $email = session('email');

        if (!$email) {
            return redirect()->route('login')->withErrors('Session expired. Please login again.');
        }

        $user = User::where('email', $email)->first();

        if (!$user) {
            return redirect()->route('login')->withErrors('User not found. Please register again.');
        }

        // Check if already verified
        if ($user->status == 1 && !is_null($user->email_verified_at)) {
            return redirect()->route('login')->with('success', 'Email already verified. Please login.');
        }

        // Verify the code
        if ($user->verification_code !== $request->verification_code) {
            return back()->withErrors(['verification_code' => 'Invalid verification code.']);
        }

        // Update user verification status
        $user->update([
            'email_verified_at' => now(),
            'status' => 1,
            'verification_code' => null // Clear the code after use
        ]);

        // Clear email from session
        session()->forget('email');
        
        // Log the user in
        Auth::login($user);
        
        // Redirect based on user role
        if ($user->hasRole('User')) {
            return redirect()->route('subscriptions.index')
                ->with('success', 'Email verified successfully!');
        }
        
        return redirect()->route('login')
            ->with('success', 'Email verified successfully! You can now login.');
    }

    public function resend()
    {
        $user = User::where('email', session('email'))->first();

        if ($user) {
            $user->verification_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $user->save();

            Mail::to($user->email)->send(new VerifyEmail($user));
        }

        return back()->with('message', 'Verification code has been resent');
    }
}
