<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SubAdminRequest;
use App\Models\User;
use App\Services\SubAdminService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class SubAdminController extends Controller
{
    public function __construct(
        private SubAdminService $subAdminService
    ) {}

    /**
     * Display a listing of Sub Admins
     */
    public function index()
    {
        $subAdmins = User::subAdmins()
            ->with('roles')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.subadmins.index', compact('subAdmins'));
    }

    /**
     * Show the form for creating a new Sub Admin
     */
    public function create()
    {
        return view('admin.subadmins.create');
    }

    /**
     * Store a newly created Sub Admin
     */
    public function store(SubAdminRequest $request)
    {
        $result = $this->subAdminService->createSubAdmin($request->validated());

        if (!$result['success']) {
            return back()->with('swal_error', $result['message'])->withInput();
        }

        return redirect()->route('admin.subadmins.index')
            ->with('success', 'Sub Admin created successfully!');
    }

    /**
     * Display the specified Sub Admin
     */
    public function show(User $subadmin)
    {
        $this->authorize('view', $subadmin);

        return view('admin.subadmins.show', ['subAdmin' => $subadmin]);
    }

    /**
     * Show the form for editing the specified Sub Admin
     */
    public function edit(User $subadmin)
    {
        $this->authorize('update', $subadmin);

        return view('admin.subadmins.edit', ['subAdmin' => $subadmin]);
    }

    /**
     * Update the specified Sub Admin
     */
    public function update(SubAdminRequest $request, User $subadmin)
    {
        $this->authorize('update', $subadmin);

        $result = $this->subAdminService->updateSubAdmin($subadmin, $request->validated());

        if (!$result['success']) {
            return back()->with('swal_error', $result['message'])->withInput();
        }

        return redirect()->route('admin.subadmins.index')
            ->with('success', 'Sub Admin updated successfully!');
    }

    /**
     * Toggle Sub Admin active status
     */
    public function toggleStatus(User $subadmin)
    {
        $this->authorize('update', $subadmin);

        $result = $this->subAdminService->toggleStatus($subadmin);

        if (!$result['success']) {
            return back()->with('swal_error', $result['message']);
        }

        $status = $subadmin->fresh()->is_active ? 'activated' : 'deactivated';
        return back()->with('success', "Sub Admin {$status} successfully!");
    }

    /**
     * Remove the specified Sub Admin
     */
    public function destroy(User $subadmin)
    {
        $this->authorize('delete', $subadmin);

        $result = $this->subAdminService->deleteSubAdmin($subadmin);

        if (!$result['success']) {
            return back()->with('swal_error', $result['message']);
        }

        return redirect()->route('admin.subadmins.index')
            ->with('success', 'Sub Admin deleted successfully!');
    }
}
