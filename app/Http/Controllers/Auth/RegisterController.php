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
use Illuminate\Support\Str;

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

            // Redirect based on the user's role
            if ($user->role == 1 || $user->role == 2) {
                return route('admin.index'); // Use the named route
            }
        }

        // Default redirection
        return '/email/verify';
    }

    protected $redirectTo = '/email/verify';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'status' => ['nullable', 'integer'],
            'subscriber_password' => ['nullable', 'string'],
        ]);
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
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'status' => 0,
            'subscriber_password' => $data['password'] ?? null,
        ]);

        $user->assignRole('User');

        return $user;
    }

    public function showRegistrationForm(Request $request)
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'password' => 'required|string|min:8'
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Generate verification code
        $verification_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Combine first_name and last_name into full name
        $full_name = $request->first_name . ' ' . $request->last_name;

        $user = User::create([
            'email' => $request->email,
            'name' => $full_name,
            'password' => Hash::make($request->password),
            'verification_code' => $verification_code,
            'email_verified_at' => null,
            'status' => 0
        ]);

        $user->assignRole('User');

        // Send verification email
        try {
            Mail::to($user->email)->send(new VerifyEmail($user));
        } catch (\Exception $e) {
            \Log::error('Email sending failed: ' . $e->getMessage());
        }

        // Log in the user
        auth()->login($user);

        // Store email in session
        session(['email' => $user->email]);

        return redirect('/email/verify');
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
