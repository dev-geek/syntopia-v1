<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Order;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\UsersImport;
use App\Services\PasswordBindingService;
use Spatie\Permission\Models\Role;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;


class AdminController extends Controller
{
    public function login()
    {
        return view('admin.login');
    }

    public function adminLogin(Request $request)
    {
        // Validate the request
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ], [
            'email.required' => 'Email address is required',
            'email.email' => 'Please enter a valid email address',
            'password.required' => 'Password is required',
        ]);

        // Check if user exists
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return back()->withErrors([
                'email' => 'The provided credentials do not match our records.',
            ])->withInput($request->except('password'));
        }

        // Check if user has admin role
        if (!$user->hasAnyRole(['Super Admin', 'Sub Admin'])) {
            return back()->withErrors([
                'email' => 'Access denied. Admin privileges required.',
            ])->withInput($request->except('password'));
        }

        // Check if Sub Admin is active
        if ($user->hasRole('Sub Admin') && !$user->canSubAdminLogin()) {
            return back()->withErrors([
                'email' => 'Your account is not active. Please contact support to activate your account.',
            ])->withInput($request->except('password'));
        }

        // Check if account is active
        if ($user->status == 0) {
            return back()->withErrors([
                'email' => 'Your account is deactivated. Please contact support.',
            ])->withInput($request->except('password'));
        }

        // Attempt login
        $credentials = $request->only('email', 'password');
        if (!Auth::attempt($credentials)) {
            return back()->withErrors([
                'password' => 'The provided password is incorrect.',
            ])->withInput($request->except('password'));
        }

        // Regenerate the session
        $request->session()->regenerate();

        // Redirect to admin dashboard
        return redirect()->intended(route('admin.dashboard'));
    }
    public function register()
    {
        return view('admin.register');
    }

    public function adminRegister(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'in:1,2'],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'status' => 1,
            'email_verified_at' => now(),
        ]);

        // Assign role based on selection
        $role = 'Super Admin';
        $user->assignRole($role);

        return redirect()->route('admin-login')->with('success', 'Admin account created successfully! Please login.');
    }
    public function users()
    {
        // $users = User::whereNotNull('role')->where('role', '!=', 1 )->get();
        $users = User::role('User')
                    ->with('roles')
                    ->orderBy('created_at', 'desc')
                    ->get();
        return view('admin.users', compact('users'));
    }
   public function destroy(User $user)
    {
        $user->delete();

        return redirect()->route('admin.users')->with('success', 'User deleted successfully!');
    }


    public function profile()
    {
        $user = Auth::user();
        return view('admin.profile', compact('user'));
    }
    public function manageProfile($id)
    {
        // Get the user by ID
        $user = User::findOrFail($id);
        return view('admin.edit', compact('user'));
    }


    public function manageProfileUpdate(Request $request, $id, PasswordBindingService $passwordBindingService)
    {
        // Validate the incoming request data
        $validated = $request->validate([
            'name' => 'nullable',
            'role' => 'nullable',
            'status' => 'nullable',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        // Get the user based on the ID passed
        $user = User::findOrFail($id); // Find user by ID, or 404 if not found

        // Update the name if it was provided
        if ($request->has('name') && $request->name != $user->name) {
            $user->name = $request->name;
        }

        // Update the role if it was provided and it differs from the current one
        if ($request->has('role') && $request->role != $user->role) {
            $user->role = $request->role;
        }

        // Update the status if it was provided and it differs from the current one
        if ($request->has('status') && $request->status != $user->status) {
            $user->status = $request->status;
        }

        // Handle password update with API binding
        if ($request->has('password') && $request->password) {
            // Call password binding API before updating the database
            $apiResponse = $passwordBindingService->bindPassword($user, $request->password);

            if (!$apiResponse['success']) {
                Log::warning('Password binding failed during admin user update - will retry later', [
                    'user_id' => $user->id,
                    'error' => $apiResponse['error_message']
                ]);
                // Continue with password update even if binding failed - will retry later
            }

            // Update password even if binding failed - will retry later
            $user->password = $request->password;
            $user->subscriber_password = $request->password;
        }

        // Save the updated user
        $user->save();

        // Return a success message
        return redirect()->route('admin.users')->with('success', 'User updated successfully!');
    }

    public function adminOrders()
    {
        $orders = Order::where('status', '!=', 'Processing')
            ->with('user') // Eager load the user relationship
            ->where('status', '!=', 'pending') // Exclude Processing status
            ->orderBy('created_at', 'desc')
            ->get();
        return view('admin.orders', compact('orders'));
    }
    public function addusers()
    {
        return view('admin.users.create');
    }
    public function addExcelUsers(Request $request)
    {
        // Validate the file
        $request->validate([
            'excel_file' => 'required|mimes:xlsx,xls',
        ]);

        // Handle the file upload and import process
        $file = $request->file('excel_file');

        // Import the data
        Excel::import(new UsersImport, $file);

        // Return success response (you can redirect or show a success message)
        return redirect()->back()->with('success', 'Users imported successfully!');
    }

    public function AdminForgotPassword()
    {
        return view('admin.forgotpassword'); // Ensure you have a Blade file named `forgotpassword.blade.php`
    }


    public function storeUser(Request $request, PasswordBindingService $passwordBindingService)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // Create the user first
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'subscriber_password' => $validated['password'],
            'status' => $request->input('status'),
        ]);

        // Try to bind password (requires tenant_id, so may fail if tenant not assigned yet)
        $apiResponse = $passwordBindingService->bindPassword($user, $validated['password']);

        if (!$apiResponse['success']) {
            Log::warning('Password binding failed during admin user creation - will retry later', [
                'user_id' => $user->id,
                'error' => $apiResponse['error_message']
            ]);
            // Continue even if password binding failed - will retry later
        }

        // Assign the role of 'User'
        $user->assignRole('User');

        return redirect()->route('admin.users')->with('success', 'User created successfully!');
    }

    /**
     * Update admin profile (name and password only)
     */
    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        $user->name = $validated['name'];

        if (!empty($validated['password'])) {
            $user->password = $validated['password'];
        }

        $user->save();

        return redirect()->route('admin.profile')->with('success', 'Profile updated successfully!');
    }

    public function cronStatus(Request $request)
    {
        $secretKey = env('CRON_STATUS_KEY', 'change-this-secret-key-in-env');

        if ($request->get('key') !== $secretKey) {
            return response()->json([
                'error' => 'Unauthorized. Invalid key.',
                'status' => 'error'
            ], 401);
        }

        $logPath = storage_path('logs/laravel.log');
        $logContent = File::exists($logPath) ? File::get($logPath) : '';

        $now = Carbon::now();
        $tenMinutesAgo = $now->copy()->subMinutes(10);
        $oneHourAgo = $now->copy()->subHour();

        $tenantRetryPattern = '/Tenant assignment retry command completed/';
        $passwordRetryPattern = '/Password binding retry command completed/';

        preg_match_all($tenantRetryPattern, $logContent, $tenantMatches);
        preg_match_all($passwordRetryPattern, $logContent, $passwordMatches);

        $tenantLastRun = null;
        $passwordLastRun = null;

        $lines = explode("\n", $logContent);
        $lines = array_reverse($lines);

        foreach ($lines as $line) {
            if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\].*Tenant assignment retry command completed/', $line, $matches)) {
                if (!$tenantLastRun) {
                    try {
                        $tenantLastRun = Carbon::createFromFormat('Y-m-d H:i:s', $matches[1]);
                    } catch (\Exception $e) {
                        // Skip invalid dates
                    }
                }
            }

            if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\].*Password binding retry command completed/', $line, $matches)) {
                if (!$passwordLastRun) {
                    try {
                        $passwordLastRun = Carbon::createFromFormat('Y-m-d H:i:s', $matches[1]);
                    } catch (\Exception $e) {
                        // Skip invalid dates
                    }
                }
            }

            if ($tenantLastRun && $passwordLastRun) {
                break;
            }
        }

        $usersNeedingTenant = User::whereNull('tenant_id')
            ->whereNotNull('subscriber_password')
            ->where('status', '>=', 0)
            ->count();

        $usersNeedingPasswordBinding = User::whereNotNull('tenant_id')
            ->whereNotNull('subscriber_password')
            ->where('status', '>=', 0)
            ->count();

        $tenantStatus = 'unknown';
        $passwordStatus = 'unknown';

        if ($tenantLastRun) {
            $minutesSince = $now->diffInMinutes($tenantLastRun);
            $tenantStatus = $minutesSince <= 10 ? 'running' : ($minutesSince <= 60 ? 'warning' : 'stopped');
        }

        if ($passwordLastRun) {
            $minutesSince = $now->diffInMinutes($passwordLastRun);
            $passwordStatus = $minutesSince <= 10 ? 'running' : ($minutesSince <= 60 ? 'warning' : 'stopped');
        }

        $overallStatus = ($tenantStatus === 'running' || $tenantStatus === 'warning') &&
                        ($passwordStatus === 'running' || $passwordStatus === 'warning')
                        ? 'ok' : 'error';

        if ($tenantStatus === 'stopped' || $passwordStatus === 'stopped') {
            $overallStatus = 'error';
        }

        return response()->json([
            'status' => $overallStatus,
            'timestamp' => $now->toDateTimeString(),
            'tenant_retry' => [
                'status' => $tenantStatus,
                'last_run' => $tenantLastRun ? $tenantLastRun->toDateTimeString() : 'Never',
                'minutes_ago' => $tenantLastRun ? $now->diffInMinutes($tenantLastRun) : null,
                'users_pending' => $usersNeedingTenant,
            ],
            'password_retry' => [
                'status' => $passwordStatus,
                'last_run' => $passwordLastRun ? $passwordLastRun->toDateTimeString() : 'Never',
                'minutes_ago' => $passwordLastRun ? $now->diffInMinutes($passwordLastRun) : null,
                'users_pending' => $usersNeedingPasswordBinding,
            ],
            'summary' => [
                'total_users_needing_tenant' => $usersNeedingTenant,
                'total_users_needing_password_binding' => $usersNeedingPasswordBinding,
            ],
            'message' => $overallStatus === 'ok'
                ? 'Cron jobs are running successfully'
                : 'Cron jobs may not be running. Check your server cron configuration.',
        ], $overallStatus === 'ok' ? 200 : 503);
    }

    public function runScheduler()
    {
        $results = [
            'timestamp' => now()->toDateTimeString(),
            'scheduler_command' => 'schedule:run',
            'exit_code' => null,
            'execution_time_ms' => 0,
            'output' => '',
            'error' => null,
        ];

        try {
            $startTime = microtime(true);
            $exitCode = Artisan::call('schedule:run');
            $endTime = microtime(true);
            $executionTime = round(($endTime - $startTime) * 1000, 2);

            $output = Artisan::output();
            $success = $exitCode === 0;

            $results['status'] = $success ? 'success' : 'failed';
            $results['exit_code'] = $exitCode;
            $results['execution_time_ms'] = $executionTime;
            $results['output'] = trim($output);
            $results['message'] = $success
                ? 'Scheduler ran successfully. All due scheduled tasks have been executed.'
                : 'Scheduler completed with errors. Check the output for details.';

        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['exit_code'] = -1;
            $results['error'] = $e->getMessage();
            $results['message'] = 'An error occurred while running the scheduler: ' . $e->getMessage();
        }

        dd($results);
    }
}
