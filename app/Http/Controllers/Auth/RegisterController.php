<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\BusinessEmailValidation;
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

class RegisterController extends Controller
{
    use BusinessEmailValidation;

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
            if ($user->hasAnyRole(['Super Admin', 'Sub Admin'])) {
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
        Log::info('Registration attempt', $data);

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:users',
                function ($attribute, $value, $fail) {
                    $isBusiness = $this->isBusinessEmail($value);
                    Log::info('Email validation check', [
                        'email' => $value,
                        'is_business' => $isBusiness
                    ]);
                    if (!$isBusiness) {
                        $fail('Please use your business email to register.');
                    }
                }
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'max:30',
                'confirmed',
                'regex:/^(?=.*[0-9])(?=.*[A-Z])(?=.*[a-z])(?=.*[,.<>{}~!@#$%^&_])[0-9A-Za-z,.<>{}~!@#$%^&_]{8,30}$/'
            ],
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'status' => ['nullable', 'integer'],
            'subscriber_password' => ['nullable', 'string'],
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
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'status' => 0,
            'subscriber_password' => $data['password'] ?? null,
        ]);

        $user->assignRole('User');

        $this->callXiaoiceApiWithCreds($user, $data['password'] ?? null);

        return $user;
    }

    public function showRegistrationForm(Request $request)
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => [
                'required',
                'email',
                'unique:users',
                function ($attribute, $value, $fail) {
                    if (!$this->isBusinessEmail($value)) {
                        $fail('Please use your business email to register.');
                    }
                }
            ],
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'password' => 'required|string|min:8|max:30|regex:/^(?=.*[0-9])(?=.*[A-Z])(?=.*[a-z])(?=.*[,.<>{}~!@#$%^&_])[0-9A-Za-z,.<>{}~!@#$%^&_]{8,30}$/'
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

            $user = User::create([
                'email' => $request->email,
                'name' => $full_name,
                'password' => Hash::make($request->password),
                'verification_code' => $verification_code,
                'email_verified_at' => null,
                'status' => 0
            ]);

            $user->assignRole('User');

            // Call API
            $apiResponse = $this->callXiaoiceApiWithCreds($user, $request->password);

            if (!$apiResponse || empty($apiResponse['data']['tenantId'])) {
                $apiError = $apiResponse['message'] ?? 'Xiaoice API failed or tenantId missing';
                throw new \Exception($apiError);
            }            

            DB::commit();

            try {
                Mail::to($user->email)->send(new VerifyEmail($user));
            } catch (\Exception $e) {
                Log::error('Email sending failed: ' . $e->getMessage());
            }

            auth()->login($user);
            session(['email' => $user->email]);

            return redirect('/email/verify');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('User registration failed and rolled back: ' . $e->getMessage());
        
            return redirect()->back()
                ->withErrors($e->getMessage())
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

    private function callXiaoiceApiWithCreds($user, $plainPassword)
    {
        try {
            $response = Http::withHeaders([
                'subscription-key' => '5c745ccd024140ffad8af2ed7a30ccad',
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->post('https://openapi.xiaoice.com/vh-cp/api/partner/tenant/create', [
                'name' => $user->name,
                'regionCode' => 'OTHER',
                'adminName' => "Super Admin",
                'adminEmail' => "admin@syntopia.io",
                'adminPassword' => "Passw0rd@2025",
                'appIds' => [2],
            ]);

            if ($response->successful()) {
                $tenantId = $response->json('data.tenantId');

                if ($tenantId) {
                    $user->update([
                        'tenant_id' => $tenantId
                    ]);
                }

                Log::info('Xiaoice API call successful', [
                    'user_id' => $user->id,
                    'response' => $response->json()
                ]);

                return $response->json(); // Now we return after saving
            }

            // Handle known error codes
            $status = $response->status();
            $errorMessage = match ($status) {
                400 => 'Bad Request - Missing required parameters.',
                401 => 'Unauthorized - Invalid or expired subscription key.',
                404 => 'Not Found - The requested resource does not exist.',
                429 => 'Too Many Requests - Rate limit exceeded.',
                500 => 'Internal Server Error - API server issue.',
                default => 'Unexpected error occurred.'
            };

            Log::error('Xiaoice API call failed', [
                'user_id' => $user->id,
                'status' => $status,
                'error_message' => $errorMessage,
                'response_body' => $response->body()
            ]);
        } catch (\Exception $e) {
            Log::error('Error calling Xiaoice API', [
                'user_id' => $user->id,
                'exception_message' => $e->getMessage()
            ]);
        }

        return null;
    }

}
