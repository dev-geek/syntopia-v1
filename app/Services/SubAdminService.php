<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class SubAdminService
{
    /**
     * Create a new Sub Admin
     *
     * @param array $data
     * @return array
     */
    public function createSubAdmin(array $data): array
    {
        try {
            DB::beginTransaction();

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'is_active' => $data['is_active'] ?? true,
                'status' => 1,
                'email_verified_at' => now(),
            ]);

            $user->assignRole('Sub Admin');

            DB::commit();

            return [
                'success' => true,
                'message' => 'Sub Admin created successfully',
                'user' => $user
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'success' => false,
                'message' => 'Failed to create Sub Admin: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update an existing Sub Admin
     *
     * @param User $subAdmin
     * @param array $data
     * @return array
     */
    public function updateSubAdmin(User $subAdmin, array $data): array
    {
        try {
            DB::beginTransaction();

            $subAdmin->name = $data['name'];

            // Only update password if provided
            if (!empty($data['password'])) {
                $subAdmin->password = $data['password'];
            }

            // Only update is_active if provided
            if (isset($data['is_active'])) {
                $subAdmin->is_active = $data['is_active'];
            }

            $subAdmin->save();

            DB::commit();

            return [
                'success' => true,
                'message' => 'Sub Admin updated successfully',
                'user' => $subAdmin
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'success' => false,
                'message' => 'Failed to update Sub Admin: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Toggle Sub Admin active status
     *
     * @param User $subAdmin
     * @return array
     */
    public function toggleStatus(User $subAdmin): array
    {
        try {
            $subAdmin->is_active = !$subAdmin->is_active;
            $subAdmin->save();

            return [
                'success' => true,
                'message' => 'Sub Admin status updated successfully',
                'user' => $subAdmin
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to update Sub Admin status: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Delete a Sub Admin
     *
     * @param User $subAdmin
     * @return array
     */
    public function deleteSubAdmin(User $subAdmin): array
    {
        try {
            // Remove the Sub Admin role
            $subAdmin->removeRole('Sub Admin');

            // Soft delete the user
            $subAdmin->delete();

            return [
                'success' => true,
                'message' => 'Sub Admin deleted successfully'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to delete Sub Admin: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get Sub Admin statistics
     *
     * @return array
     */
    public function getSubAdminStats(): array
    {
        $totalSubAdmins = User::subAdmins()->count();
        $activeSubAdmins = User::activeSubAdmins()->count();
        $inactiveSubAdmins = $totalSubAdmins - $activeSubAdmins;

        return [
            'total' => $totalSubAdmins,
            'active' => $activeSubAdmins,
            'inactive' => $inactiveSubAdmins
        ];
    }
}
