<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FreePlanAttempt;
use App\Services\FreePlanAbuseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FreePlanAttemptController extends Controller
{
    private FreePlanAbuseService $freePlanAbuseService;

    public function __construct(FreePlanAbuseService $freePlanAbuseService)
    {
        $this->middleware('auth');
        $this->middleware('role:Super Admin|Sub Admin');
        $this->freePlanAbuseService = $freePlanAbuseService;
    }

    public function index(Request $request)
    {
        $query = FreePlanAttempt::query();

        // Filter by IP
        if ($request->filled('ip')) {
            $query->byIp($request->ip);
        }

        // Filter by email
        if ($request->filled('email')) {
            $query->byEmail($request->email);
        }

        // Filter by blocked status
        if ($request->filled('blocked')) {
            if ($request->blocked === '1') {
                $query->blocked();
            } else {
                $query->notBlocked();
            }
        }

        // Filter by date range
        if ($request->filled('days')) {
            $query->recent($request->days);
        }

        $attempts = $query->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        // Get statistics using the service
        $stats = $this->freePlanAbuseService->getAbuseStatistics(30);
        $stats['recent_attempts'] = FreePlanAttempt::recent(7)->count();

        return view('admin.free-plan-attempts.index', compact('attempts', 'stats'));
    }

    public function unblock(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:free_plan_attempts,id',
        ]);

        $count = FreePlanAttempt::whereIn('id', $request->ids)
            ->update([
                'is_blocked' => false,
                'blocked_at' => null,
                'block_reason' => null,
            ]);

        return redirect()->back()
            ->with('success', "Successfully unblocked {$count} attempts.");
    }

    public function block(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:free_plan_attempts,id',
            'reason' => 'nullable|string|max:255',
        ]);

        $count = FreePlanAttempt::whereIn('id', $request->ids)
            ->update([
                'is_blocked' => true,
                'blocked_at' => now(),
                'block_reason' => $request->reason,
            ]);

        return redirect()->back()
            ->with('success', "Successfully blocked {$count} attempts.");
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:free_plan_attempts,id',
        ]);

        $count = FreePlanAttempt::whereIn('id', $request->ids)->delete();

        return redirect()->back()
            ->with('success', "Successfully deleted {$count} attempts.");
    }

    /**
     * Show details of a specific attempt
     */
    public function show(FreePlanAttempt $attempt)
    {
        $attempt->load('user');

        // Get related attempts from same IP, email, or device
        $relatedAttempts = FreePlanAttempt::where(function ($query) use ($attempt) {
            $query->where('ip_address', $attempt->ip_address)
                  ->orWhere('email', $attempt->email)
                  ->orWhere('device_fingerprint', $attempt->device_fingerprint);
        })
        ->where('id', '!=', $attempt->id)
        ->orderBy('created_at', 'desc')
        ->limit(10)
        ->get();

        return view('admin.free-plan-attempts.show', compact('attempt', 'relatedAttempts'));
    }

    /**
     * Block an identifier (IP, email, device)
     */
    public function blockIdentifier(Request $request)
    {
        $request->validate([
            'type' => 'required|in:ip,email,device_fingerprint,fingerprint_id',
            'value' => 'required|string',
            'reason' => 'nullable|string|max:255'
        ]);

        $success = $this->freePlanAbuseService->blockIdentifier(
            $request->type,
            $request->value,
            $request->reason ?? 'Manual block by admin'
        );

        if ($success) {
            Log::info('Identifier blocked by admin', [
                'admin_id' => auth()->id(),
                'type' => $request->type,
                'value' => $request->value,
                'reason' => $request->reason
            ]);

            return redirect()->back()
                ->with('success', 'Identifier blocked successfully.');
        }

        return redirect()->back()
            ->with('error', 'Failed to block identifier.');
    }

    /**
     * Unblock an identifier
     */
    public function unblockIdentifier(Request $request)
    {
        $request->validate([
            'type' => 'required|in:ip,email,device_fingerprint,fingerprint_id',
            'value' => 'required|string'
        ]);

        $success = $this->freePlanAbuseService->unblockIdentifier(
            $request->type,
            $request->value
        );

        if ($success) {
            Log::info('Identifier unblocked by admin', [
                'admin_id' => auth()->id(),
                'type' => $request->type,
                'value' => $request->value
            ]);

            return redirect()->back()
                ->with('success', 'Identifier unblocked successfully.');
        }

        return redirect()->back()
            ->with('error', 'Failed to unblock identifier.');
    }

    /**
     * Export abuse data
     */
    public function export(Request $request)
    {
        $query = FreePlanAttempt::query();

        // Apply same filters as index
        if ($request->filled('ip')) {
            $query->byIp($request->ip);
        }
        if ($request->filled('email')) {
            $query->byEmail($request->email);
        }
        if ($request->filled('blocked')) {
            if ($request->blocked === '1') {
                $query->blocked();
            } else {
                $query->notBlocked();
            }
        }
        if ($request->filled('days')) {
            $query->recent($request->days);
        }

        $attempts = $query->orderBy('created_at', 'desc')->get();

        // Generate CSV
        $filename = 'free_plan_attempts_' . now()->format('Y-m-d_H-i-s') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($attempts) {
            $file = fopen('php://output', 'w');

            // CSV headers
            fputcsv($file, [
                'ID',
                'IP Address',
                'Email',
                'User Agent',
                'Device Fingerprint',
                'Fingerprint ID',
                'Is Blocked',
                'Blocked At',
                'Block Reason',
                'Created At',
                'Updated At'
            ]);

            // CSV data
            foreach ($attempts as $attempt) {
                fputcsv($file, [
                    $attempt->id,
                    $attempt->ip_address,
                    $attempt->email,
                    $attempt->user_agent,
                    $attempt->device_fingerprint,
                    $attempt->fingerprint_id,
                    $attempt->is_blocked ? 'Yes' : 'No',
                    $attempt->blocked_at,
                    $attempt->block_reason,
                    $attempt->created_at,
                    $attempt->updated_at
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
