<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Order;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\UsersImport;
use App\Services\PasswordBindingService;
use Spatie\Permission\Models\Role;
use Illuminate\Validation\ValidationException;


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
        if (!$user->hasAnyRole(['Sub Admin', 'Super Admin'])) {
            return back()->withErrors([
                'email' => 'Access denied. Admin privileges required.',
            ])->withInput($request->except('password'));
        }

        // Check if account is active
        if ($user->status == 0) {
            return back()->withErrors([
                'email' => 'Your account is deactivated. Please contact support.',
            ])->withInput($request->except('password'));
        }

        // Attempt login
        if (!Auth::attempt($validated)) {
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
        $role = $request->role == 1 ? 'Super Admin' : 'Sub Admin';
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

    public function subadmins()
    {
       $users = User::role('Sub Admin')
                    ->with('roles')
                    ->orderBy('created_at', 'desc')
                    ->get();

        return view('admin.subadmins', compact('users'));
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

    public function manageAdminProfile($id)
    {
        // Get the user by ID
        $user = User::findOrFail($id);
        return view('admin.subadmin', compact('user'));
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
                return back()->with('swal_error', $apiResponse['error_message'])->withInput();
            }

            // Only update password if API call was successful
            $user->password = $request->password;
            $user->subscriber_password = $request->password;
        }

        // Save the updated user
        $user->save();

        // Return a success message
        return redirect()->route('admin.users')->with('success', 'User updated successfully!');
    }
    public function manageAdminProfileUpdate(Request $request, $id, PasswordBindingService $passwordBindingService)
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
                return back()->with('swal_error', $apiResponse['error_message'])->withInput();
            }

            // Only update password if API call was successful
            $user->password = $request->password;
            $user->subscriber_password = $request->password;
        }

        // Save the updated user
        $user->save();

        // Return a success message
        return redirect()->route('admin.subadmins')->with('success', 'Sub Admin updated successfully!');
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

    public function createOrUpdateSubAdmin() {}

    public function storeUser(Request $request, PasswordBindingService $passwordBindingService)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // Call password binding API before creating the user
        $apiResponse = $passwordBindingService->bindPassword(
            (new User())->forceFill(['email' => $validated['email']]),
            $validated['password']
        );

        if (!$apiResponse['success']) {
            return back()->with('swal_error', $apiResponse['error_message'])->withInput();
        }

        // Create the user only if API call was successful
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'subscriber_password' => $validated['password'],
            'status' => $request->input('status'),
        ]);

        // Assign the role of 'User'
        $user->assignRole('User');

        return redirect()->route('admin.users')->with('success', 'User created successfully!');
    }
}
