<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerifyEmail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Services\MailService;
use App\Services\DeviceFingerprintService;
use App\Services\FreePlanAbuseService;
use App\Models\FreePlanAttempt;

class RegisterController extends Controller
{

    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    use RegistersUsers;

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected function redirectTo()
    {
        // Check the authenticated user's role
        if (Auth::check()) {
            $user = Auth::user();

            // Redirect based on the user's role using Spatie
            if ($user->hasAnyRole(['Super Admin'])) {
                return route('admin.dashboard');
            }

            // For regular users, redirect to verification page
            // After verification, they'll be redirected to subscriptions.index
            return '/email/verify';
        }

        return '/email/verify';
    }

    protected $redirectTo = '/email/verify';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    protected $deviceFingerprintService;
    protected $freePlanAbuseService;

    public function __construct(DeviceFingerprintService $deviceFingerprintService, FreePlanAbuseService $freePlanAbuseService)
    {
        $this->middleware('guest');
        $this->deviceFingerprintService = $deviceFingerprintService;
        $this->freePlanAbuseService = $freePlanAbuseService;
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data, Request $request = null)
    {
        Log::info('Registration attempt', $data);

        // Check for device fingerprint abuse
        if ($request) {
            $isBlocked = $this->deviceFingerprintService->isBlocked($request);
            $hasRecentAttempts = $this->deviceFingerprintService->hasRecentAttempts(
                $request,
                config('free_plan_abuse.max_attempts', 3),
                config('free_plan_abuse.tracking_period_days', 30)
            );

            if ($isBlocked || $hasRecentAttempts) {
                Log::warning('Registration blocked due to fingerprint abuse', [
                    'ip' => $request->ip(),
                    'email' => $data['email'] ?? null,
                    'fingerprint_id' => $data['fingerprint_id'] ?? null,
                    'is_blocked' => $isBlocked,
                    'has_recent_attempts' => $hasRecentAttempts
                ]);

                abort(403, 'Registration is not allowed from this device. Please contact support if you believe this is an error.');
            }
        }

        $validator = Validator::make($data, [
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:users',
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'max:30',
                'regex:/^(?=.*[0-9])(?=.*[A-Z])(?=.*[a-z])(?=.*[,.<>{}~!@#$%^&_])[A-Za-z0-9,.<>{}~!@#$%^&_]{8,30}$/'
            ],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'integer'],
            'subscriber_password' => ['nullable', 'string'],
        ], [
            'password.required' => 'Password is required.',
            'password.string' => 'Password must be a valid string.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.max' => 'Password must not exceed 30 characters.',
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.',
        ]);

        return $validator;
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return \App\Models\User
     */
    protected function create(array $data)
    {
        $user = User::create([
            'name' => trim($data['first_name'] . ' ' . $data['last_name']),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'password' => $data['password'], // Let the mutator handle hashing
            'status' => 0,
            'subscriber_password' => $data['password'], // Store plain text for API
        ]);

        $user->assignRole('User');

        return $user;
    }

    public function showRegistrationForm(Request $request)
    {
        // Ensure email parameter is present in URL
        if (!$request->has('email') || empty($request->get('email'))) {
            Log::warning('Registration page accessed without email parameter', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            return redirect()->route('login')
                ->withErrors(['email' => 'Please enter your email address on the login page first.']);
        }

        // Validate email format
        if (!filter_var($request->get('email'), FILTER_VALIDATE_EMAIL)) {
            Log::warning('Invalid email format in registration URL', [
                'email' => $request->get('email'),
                'ip' => $request->ip()
            ]);

            return redirect()->route('login')
                ->withErrors(['email' => 'Invalid email format. Please enter a valid email address.']);
        }

        // Preserve the intended URL if it exists
        if (session()->has('url.intended')) {
            // Keep the intended URL in session for after registration
            session(['registration_intended_url' => session('url.intended')]);
        }

        return view('auth.register');
    }

    public function register(Request $request)
    {
        // Validate that the email from URL parameter matches the submitted email
        $urlEmail = $request->get('email');
        $submittedEmail = $request->input('email');

        if ($urlEmail !== $submittedEmail) {
            Log::warning('Email mismatch during registration', [
                'url_email' => $urlEmail,
                'submitted_email' => $submittedEmail,
                'ip' => $request->ip()
            ]);

            return redirect()->back()
                ->withErrors(['email' => 'Email address cannot be modified. Please use the email from the login page.'])
                ->withInput();
        }

        // Validate the request including fingerprint data
        $validator = $this->validator($request->all(), $request);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Check for free plan abuse before proceeding
        $abuseCheck = $this->freePlanAbuseService->checkAbusePatterns($request);
        if (!$abuseCheck['allowed']) {
            Log::warning('Registration blocked due to abuse patterns', [
                'reason' => $abuseCheck['reason'],
                'ip' => $request->ip(),
                'email' => $request->input('email'),
                'user_agent' => $request->userAgent()
            ]);

            return redirect()->back()
                ->withErrors(['email' => $abuseCheck['message']])
                ->withInput();
        }

        // Record the fingerprint attempt
        try {
            $this->deviceFingerprintService->recordAttempt($request);
        } catch (\Exception $e) {
            Log::error('Failed to record fingerprint attempt: ' . $e->getMessage(), [
                'exception' => $e,
                'ip' => $request->ip(),
                'email' => $request->input('email')
            ]);
        }

        $validator = Validator::make($request->all(), [
            'email' => [
                'required',
                'email',
                'unique:users',
            ],
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'password' => [
                'required',
                'string',
                'min:8',
                'max:30',
                'regex:/^(?=.*[0-9])(?=.*[A-Z])(?=.*[a-z])(?=.*[,.<>{}~!@#$%^&_])[0-9A-Za-z,.<>{}~!@#$%^&_]{8,30}$/'
            ],
        ], [
            'password.required' => 'Password is required.',
            'password.string' => 'Password must be a valid string.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.max' => 'Password must not exceed 30 characters.',
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $verification_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $full_name = $request->first_name . ' ' . $request->last_name;

        try {
            DB::beginTransaction();

            // Create user with both hashed password and plain text subscriber_password
            $user = User::create([
                'email' => $request->email,
                'name' => $full_name,
                'password' => $request->password, // This will be hashed by the mutator
                'subscriber_password' => $request->password, // Store plain text for API
                'verification_code' => $verification_code,
                'email_verified_at' => null,
                'status' => 0
            ]);

            $user->assignRole('User');

            Log::info('User created successfully with subscriber_password', [
                'user_id' => $user->id,
                'email' => $user->email,
                'has_subscriber_password' => !empty($user->subscriber_password)
            ]);

            DB::commit();

            // Send verification email with proper error handling
            $mailResult = MailService::send($user->email, new VerifyEmail($user));

            if ($mailResult['success']) {
                Log::info('Verification email sent successfully', [
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);
            } else {
                Log::warning('Failed to send verification email during registration', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $mailResult['error'] ?? 'Unknown error'
                ]);

                // Store the mail error and verification code in session
                session(['mail_error' => $mailResult['message']]);
                session(['verification_code' => $verification_code]);
            }

            auth()->login($user);
            session(['email' => $user->email]);

            // Preserve the intended URL for after verification
            if (session()->has('registration_intended_url')) {
                session(['verification_intended_url' => session('registration_intended_url')]);
                session()->forget('registration_intended_url');
            } elseif (session()->has('url.intended')) {
                session(['verification_intended_url' => session('url.intended')]);
                session()->forget('url.intended');
            }

            return redirect('/email/verify');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('User registration failed and rolled back', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->withErrors(['registration_error' => 'Registration failed. Please try again.'])
                ->withInput();
        }
    }

    protected function registered(Request $request, $user)
    {
        // Don't auto-login after registration
        Auth::logout();

        // Store email in session
        session(['email' => $user->email]);

        return redirect('/email/verify');
    }
}
