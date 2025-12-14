<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Services\Auth\LoginService;
use App\Services\AuthRedirectService;
use App\Http\Requests\Auth\LoginRequest;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    public function __construct(
        private LoginService $loginService,
        private AuthRedirectService $redirectService
    ) {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }

    protected function authenticated(Request $request, $user)
    {
        $result = $this->loginService->handleAuthenticated($request, $user);

        if ($result['action'] === 'logout_and_redirect') {
            Auth::logout();
            if (isset($result['error'])) {
                session(['email' => $user->email]);
                return redirect()->route($result['route'])->withErrors($result['error']);
            }
            return redirect()->route($result['route'])->with('error', $result['error'] ?? '');
        }

        return $result['response'];
    }



    public function redirectTo()
    {
        $user = Auth::user();

        if ($user) {
            if ($user->hasAnyRole(['Super Admin', 'Sub Admin'])) {
                return route('admin.dashboard');
            }

            return $this->redirectService->getRedirectForUser($user)->getTargetUrl();
        }

        return '/';
    }


    public function showLoginForm(Request $request)
    {
        $email = $request->session()->get('email');

        if ($email && $this->loginService->shouldRedirectToAdminLogin($email)) {
            return redirect()->route('admin-login');
        }

        return view('auth.login');
    }

    public function logout(Request $request)
    {
        $user = Auth::user();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Redirect based on role
        if ($user && $user->hasAnyRole(['Super Admin', 'Sub Admin'])) {
            return redirect()->route('admin-login');
        }

        // Redirect regular users to login page
        return redirect()->route('login');
    }

    public function customLogin(LoginRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        if ($user && $user->hasAnyRole(['Super Admin', 'Sub Admin'])) {
            session(['email' => $request->email]);
            return redirect()->route('admin-login');
        }

        session(['email' => $request->email]);
        $credentials = $request->only('email', 'password');

        $result = $this->loginService->handleCustomLogin($request, $credentials);

        if (!$result['success']) {
            if (isset($result['action']) && $result['action'] === 'redirect') {
                session(['email' => $request->email]);
                return redirect()->route($result['route'])->withErrors($result['error']);
            }
            throw ValidationException::withMessages([
                $result['error'] => [$result['message']],
            ]);
        }

        if (!Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'password' => ['Password is incorrect.'],
            ]);
        }

        $user = Auth::user();
        if ($user->hasAnyRole(['Super Admin', 'Sub Admin'])) {
            return $this->redirectService->getRedirectForUser($user);
        }

        if ($user->hasRole('User')) {
            return $this->redirectService->getRedirectForUser($user);
        }

        return redirect()->route('user.profile');
    }

    public function checkEmail(Request $request)
    {
        $email = $request->input('email');
        $exists = $this->loginService->checkEmailExists($email);

        return response()->json(['exists' => $exists]);
    }
}
