<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Order;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\UsersImport;
use Spatie\Permission\Models\Role;


class AdminController extends Controller
{
    public function index()
    {
        if (!auth()->user()->hasAnyRole(['Sub Admin', 'Super Admin'])) {
            abort(403, 'Unauthorized');
        }

        return view('admin.index');
    }
    public function login()
    {
        return view('admin.login');
    }
    public function register()
    {
        return view('admin.register');
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

        return redirect()->route('admin.users')->with('success', 'User deleted successfully.');
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

    public function manageProfileUpdate(Request $request, $id)
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

        // Update the password if it was provided
        if ($request->has('password') && $request->password) {
            $user->password = Hash::make($request->password); // Hash the password before saving
        }

        // Save the updated user
        $user->save();

        // Return a success message
        return redirect()->route('admin.users')->with('success', 'User updated successfully.');
    }
    public function manageAdminProfileUpdate(Request $request, $id)
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

        // Update the password if it was provided
        if ($request->has('password') && $request->password) {
            $user->password = Hash::make($request->password); // Hash the password before saving
        }

        // Save the updated user
        $user->save();

        // Return a success message
        return redirect()->route('subadmins')->with('success', 'Sub Admin   updated successfully.');
    }

    public function adminOrders()
    {
        $orders = Order::with('user')->get(); // Eager load the user relationship
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

    public function storeUser(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // Create the user
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'status' => $request->input('status'),
        ]);

        // Assign the role of 'User'
        $user->assignRole('User');

        return redirect()->route('admin.users')->with('success', 'User created successfully.');
    }
}
