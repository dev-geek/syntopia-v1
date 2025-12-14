<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\Auth\RegistrationService;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ShowRegistrationFormRequest;

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

    public function __construct(
        private RegistrationService $registrationService
    ) {
        $this->middleware('guest');
    }


    public function showRegistrationForm(ShowRegistrationFormRequest $request)
    {
        if (session()->has('url.intended')) {
            session(['registration_intended_url' => session('url.intended')]);
        }

        return view('auth.register');
    }

    public function register(RegisterRequest $request)
    {
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

        $validationResult = $this->registrationService->validateRegistration($request);

        if (!$validationResult['success']) {
            if (isset($validationResult['error']) && $validationResult['error'] === 'email') {
                return redirect()->back()
                    ->withErrors(['email' => $validationResult['message']])
                    ->withInput();
            }
            abort(403, $validationResult['error'] ?? 'Registration not allowed.');
        }

        try {
            $result = $this->registrationService->registerUser($request);

            if (!$result['success']) {
                return redirect()->back()
                    ->withErrors([$result['error'] => $result['message']])
                    ->withInput();
            }

            if (isset($result['action']) && $result['action'] === 'login_and_redirect') {
                auth()->login($result['user']);
                session(['email' => $result['user']->email]);
                return redirect($result['route']);
            }

            auth()->login($result['user']);
            session(['email' => $result['user']->email]);

            if (session()->has('registration_intended_url')) {
                session(['verification_intended_url' => session('registration_intended_url')]);
                session()->forget('registration_intended_url');
            } elseif (session()->has('url.intended')) {
                session(['verification_intended_url' => session('url.intended')]);
                session()->forget('url.intended');
            }

            return redirect('/email/verify');

        } catch (\Exception $e) {
            Log::error('User registration failed', [
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
