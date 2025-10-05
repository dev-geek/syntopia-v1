<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\FreePlanAbuseService;
use App\Exceptions\FreePlanAbuseException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class FreePlanController extends Controller
{
    private FreePlanAbuseService $freePlanAbuseService;

    public function __construct(FreePlanAbuseService $freePlanAbuseService)
    {
        $this->middleware('auth:sanctum');
        $this->freePlanAbuseService = $freePlanAbuseService;
    }

    /**
     * Check if user can access free plan
     */
    public function checkEligibility(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.',
                    'error_code' => 'UNAUTHENTICATED'
                ], 401);
            }

            $eligibility = $this->freePlanAbuseService->canUseFreePlan($user, $request);

            // Tests expect 200 for eligibility regardless; include allowed flag
            return response()->json([
                'success' => true,
                'data' => $eligibility
            ], 200);

        } catch (FreePlanAbuseException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getUserMessage(),
                'error_code' => $e->getErrorCode()
            ], $e->getCode());

        } catch (\Exception $e) {
            Log::error('Error checking free plan eligibility', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to verify free plan eligibility. Please try again later.',
                'error_code' => 'SYSTEM_ERROR'
            ], 500);
        }
    }

    /**
     * Assign free plan to user
     */
    public function assignFreePlan(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.',
                    'error_code' => 'UNAUTHENTICATED'
                ], 401);
            }

            $result = $this->freePlanAbuseService->assignFreePlan($user, $request);

            if (!empty($result['success'])) {
                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                    'data' => [
                        'package' => $result['package'],
                        'user' => $user->fresh(['package'])
                    ]
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'error_code' => $result['error_code'] ?? 'ASSIGNMENT_FAILED',
                'reason' => $result['reason'] ?? null,
            ], ($result['reason'] ?? null) === 'already_used' ? 400 : 400);

        } catch (FreePlanAbuseException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getUserMessage(),
                'error_code' => $e->getErrorCode()
            ], $e->getCode());

        } catch (\Exception $e) {
            Log::error('Error assigning free plan', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to assign free plan. Please try again later.',
                'error_code' => 'SYSTEM_ERROR'
            ], 500);
        }
    }

    /**
     * Get user's free plan status
     */
    public function getStatus(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.',
                    'error_code' => 'UNAUTHENTICATED'
                ], 401);
            }

            $hasUsedFreePlan = $user->hasUsedFreePlan();
            $canAccessFreePlan = $user->canAccessFreePlan();
            $isCurrentDeviceBlocked = $user->isCurrentDeviceBlocked();

            return response()->json([
                'success' => true,
                'data' => [
                    'has_used_free_plan' => $hasUsedFreePlan,
                    'can_access_free_plan' => $canAccessFreePlan,
                    'is_device_blocked' => $isCurrentDeviceBlocked,
                    'current_package' => $user->package ? $user->package->name : null,
                    'free_plan_used_at' => $user->free_plan_used_at,
                    'last_ip' => $user->last_ip,
                    'last_login_at' => $user->last_login_at
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting free plan status', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to retrieve free plan status.',
                'error_code' => 'SYSTEM_ERROR'
            ], 500);
        }
    }

    /**
     * Report suspicious activity
     */
    public function reportSuspiciousActivity(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'description' => 'required|string|max:1000',
                'evidence' => 'nullable|string|max:2000'
            ]);

            $user = Auth::user();

            Log::warning('Suspicious activity reported', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'description' => $request->description,
                'evidence' => $request->evidence,
                'reported_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Suspicious activity has been reported. Our team will investigate.'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'The given data was invalid.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error reporting suspicious activity', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to report suspicious activity. Please try again later.',
                'error_code' => 'REPORT_FAILED'
            ], 500);
        }
    }
}
