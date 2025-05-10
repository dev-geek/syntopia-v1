<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Closure;
use Illuminate\Validation\ValidationException;
use App\Models\User;


class LoginController extends Controller
{
    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected function authenticated(Request $request, $user)
    {
        // Check if user is admin (role 1 or 2)
        if ($user->role == 1 || $user->role == 2) {
            return redirect()->route('admin.index');
        }

        // For regular users, redirect to intended URL or profile
        return redirect()->intended(route('profile'));
    }
    public function redirectTo()
    {
        $user = Auth::user();


        // Check if the user is authenticated
        if (Auth::check()) {
            if($user->role== '3' && $user->status== '0'){
                Auth::logout(); // Log the user out if their status is 0
                return route('login-sub');
            }

            // Redirect users to the admin panel if they have role '1' or '2'
            if (in_array($user->role, ['1', '2'])) {
                return route('admin.index'); // Redirect to the admin page
            }
        }

        // Default redirect to home or other route
        return '/';
    }


    /**
     * Handle the incoming request and ensure user has the 'admin' role.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();


        if (Auth::check()) {
            if (Auth::check() && in_array(Auth::user()->role, ['1', '2']))  {
                return redirect()->route('admin.index'); // Redirect to admin login if the user doesn't have the 'admin' role
            }

        } else {
            return redirect()->route('/home'); // Redirect if the user is not authenticated
        }

        return $next($request); // Continue with the request if the user is authenticated and has the 'admin' role
    }

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }

    /**
     * Logout the user and redirect to admin or home based on the role.
     */
    public function logout(Request $request)
    {
        $user = Auth::user(); // Get the logged-in user

        // Perform logout
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Redirect based on user role
        if ($user && $user->role === 'admin') {
            return redirect()->route('admin.index'); // Redirect to the admin panel if the user has the 'admin' role
        }
          // Redirect based on user role
    if ($user && ($user->role == 1 || $user->role == 2)) {
        return redirect()->route('admin-login'); // Redirect to the admin login page if the user has role 1 or 2
    }

        return
         redirect('/'); // Default redirect if no role
    }
    public function customLogin(Request $request)
    {
        // Validate the login input (email and password)
        $credentials = $request->only('email', 'password','status');
        if (Auth::attempt($credentials)) {
            return redirect()->intended(route('profile'));
        }

        // Check if the user exists
    $user = \App\Models\User::where('email', $request->email)->first();

    if (!$user) {
        throw ValidationException::withMessages([
            'email' => ['User does not exist.'],
        ]);
    }

    // Check if the user signed in with Google
    if ($user->google_id !== null) {
        throw ValidationException::withMessages([
            'email' => ['Password not set! You have signed in with Google.'],
        ]);
    }

    // Attempt to log in the user
    if (Auth::attempt($credentials)) {
        // Check if the user's status is 0 (inactive)
        if (Auth::user()->status == 0) {
            Auth::logout(); // Log the user out if their status is 0
            return redirect()->route('login')->withErrors('Your account is deactive.');
        }

        // Redirect to the appropriate page after successful login
        return redirect()->intended($this->redirectTo());
    }

    // If the password is incorrect
    throw ValidationException::withMessages([
        'password' => ['Password is incorrect.'],
    ]);
    }

    public function checkEmail(Request $request)
    {
        $email = $request->input('email');
        $exists = User::where('email', $email)->exists();
        
        return response()->json(['exists' => $exists]);
    }

}
