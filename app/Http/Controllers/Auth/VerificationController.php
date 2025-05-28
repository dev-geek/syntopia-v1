<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\VerifyEmail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class VerificationController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Email Verification Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling email verification for any
    | user that recently registered with the application. Emails may also
    | be re-sent if the user didn't receive the original email message.
    |
    */

    /**
     * Where to redirect users after verification.
     *
     * @var string
     */
    protected $redirectTo = '/home';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('web');
        $this->middleware('signed')->only('verify');
        $this->middleware('throttle:6,1')->only('verify', 'resend');
    }

    public function show()
    {
        // Get email from session
        $email = session('email');

        if (!$email) {
            return redirect()->route('login');
        }

        return view('auth.verify-code', ['email' => $email]);
    }

    public function verify(Request $request)
    {
        \Log::info('Verification attempt', [
            'headers' => $request->headers->all(),
            'token_from_form' => $request->_token,
            'token_from_session' => session('_token'),
        ]);

        return response()->json(['message' => 'Check logs'], 403);
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
