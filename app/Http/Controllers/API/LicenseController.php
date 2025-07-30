<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LicenseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LicenseController extends Controller
{
    private $licenseService;

    public function __construct(LicenseService $licenseService)
    {
        $this->licenseService = $licenseService;
    }

    /**
     * Get the current user's license information
     */
    public function getCurrentLicense(Request $request)
    {
        $user = Auth::user();
        $activeLicense = $this->licenseService->getActiveLicense($user);

        if (!$activeLicense) {
            return response()->json([
                'success' => false,
                'message' => 'No active license found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'license' => [
                'id' => $activeLicense->id,
                'license_key' => $activeLicense->license_key,
                'package' => [
                    'id' => $activeLicense->package->id,
                    'name' => $activeLicense->package->name,
                    'price' => $activeLicense->package->price
                ],
                'is_active' => $activeLicense->is_active,
                'activated_at' => $activeLicense->activated_at,
                'payment_gateway' => $activeLicense->payment_gateway,
                'subscription_id' => $activeLicense->subscription_id
            ]
        ]);
    }

    /**
     * Get all licenses for the current user
     */
    public function getLicenseHistory(Request $request)
    {
        $user = Auth::user();
        $licenses = $this->licenseService->getUserLicenses($user);

        return response()->json([
            'success' => true,
            'licenses' => $licenses->map(function ($license) {
                return [
                    'id' => $license->id,
                    'license_key' => $license->license_key,
                    'package' => [
                        'id' => $license->package->id,
                        'name' => $license->package->name,
                        'price' => $license->package->price
                    ],
                    'is_active' => $license->is_active,
                    'activated_at' => $license->activated_at,
                    'created_at' => $license->created_at,
                    'payment_gateway' => $license->payment_gateway,
                    'subscription_id' => $license->subscription_id,
                    'metadata' => $license->metadata
                ];
            })
        ]);
    }

    /**
     * Activate a specific license for the current user
     */
    public function activateLicense(Request $request, $licenseId)
    {
        $user = Auth::user();
        $license = $user->licenses()->find($licenseId);

        if (!$license) {
            return response()->json([
                'success' => false,
                'message' => 'License not found'
            ], 404);
        }

        $success = $this->licenseService->activateLicense($license);

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to activate license'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'License activated successfully',
            'license' => [
                'id' => $license->id,
                'license_key' => $license->license_key,
                'package' => [
                    'id' => $license->package->id,
                    'name' => $license->package->name
                ],
                'is_active' => $license->is_active
            ]
        ]);
    }

    /**
     * Check if user has an active license for a specific package
     */
    public function checkPackageAccess(Request $request, $packageName)
    {
        $user = Auth::user();
        $hasAccess = $this->licenseService->hasActiveLicenseForPackage($user, $packageName);

        return response()->json([
            'success' => true,
            'has_access' => $hasAccess,
            'package' => $packageName
        ]);
    }
}
