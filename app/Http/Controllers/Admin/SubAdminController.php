<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Http\Requests\Admin\SubAdminRequest;
use App\Services\SubAdminService;

class SubAdminController extends Controller
{
    public function __construct(protected SubAdminService $subAdminService)
    {
    }

    public function create()
    {
        return view('admin.subadmins.create');
    }

    public function store(SubAdminRequest $request)
    {
        try {
            $this->subAdminService->createSubAdmin($request->validated());

            return redirect()->route('admin.subadmins')->with('success', 'Sub Admin created successfully.');
        } catch (\Exception $e) {
            Log::error('Sub Admin creation failed: ' . $e->getMessage());

            return back()->withInput()->with('error', 'Failed to create Sub Admin. Try again.');
        }
    }


    public function edit($id)
    {
        try {
            $subadmin = User::findOrFail($id);

            // Check if the user has the correct role
            if (!$subadmin->hasRole('Sub Admin')) {
                return redirect()
                    ->route('admin.subadmins')
                    ->with('error', 'Invalid subadmin access attempt.');
            }

            return view('admin.subadmins.edit', compact('subadmin'));
        } catch (ModelNotFoundException $e) {
            return redirect()
                ->route('admin.subadmins')
                ->with('error', 'Subadmin not found.');
        } catch (\Exception $e) {
            Log::error('Error editing subadmin: ' . $e->getMessage());

            return redirect()
                ->route('admin.subadmins')
                ->with('error', 'Something went wrong. Please try again later.');
        }
    }

    public function update(SubAdminRequest $request, $id)
    {
        try {
            $subadmin = User::role('Sub Admin')->findOrFail($id);
            $this->subAdminService->updateSubAdmin($subadmin, $request->validated());

            return redirect()->route('admin.subadmins')->with('success', 'Sub Admin updated successfully.');
        } catch (ModelNotFoundException $e) {
            return redirect()->route('admin.subadmins')->with('error', 'Sub Admin not found.');
        } catch (\Exception $e) {
            Log::error('Sub Admin update failed: ' . $e->getMessage());

            return back()->withInput()->with('error', 'Failed to update Sub Admin.');
        }
    }

    public function destroy($id)
    {
        try {
            $subadmin = User::role('Sub Admin')->findOrFail($id);
            $subadmin->delete();

            return redirect()->route('admin.subadmins')->with('success', 'Sub Admin deleted successfully.');
        } catch (ModelNotFoundException $e) {
            return redirect()->route('admin.subadmins')->with('error', 'Sub Admin not found.');
        } catch (\Exception $e) {
            Log::error('Deletion failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to delete Sub Admin.');
        }
    }
}
