<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FreePlanAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FreePlanAttemptController extends Controller
{
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

        // Get statistics
        $stats = [
            'total_attempts' => FreePlanAttempt::count(),
            'blocked_attempts' => FreePlanAttempt::blocked()->count(),
            'unique_ips' => FreePlanAttempt::distinct('ip_address')->count(),
            'unique_emails' => FreePlanAttempt::whereNotNull('email')->distinct('email')->count(),
            'recent_attempts' => FreePlanAttempt::recent(7)->count(),
        ];

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
} 