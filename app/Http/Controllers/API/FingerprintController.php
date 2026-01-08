<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\FreePlanAttempt;

class FingerprintController extends Controller
{
    /**
     * Store fingerprint data from the client
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $fingerprintId = $request->input('fingerprint_id');
            
            if (!$fingerprintId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No fingerprint ID provided'
                ], 400);
            }

            // Log the fingerprint data (without sensitive information)
            $loggableData = $request->except(['webgl_vendor', 'webgl_renderer', 'webgl_fp', 'canvas_fp', 'audio_fp']);
            // Store the fingerprint data in the database
            $attempt = new FreePlanAttempt([
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'device_fingerprint' => $request->input('canvas_fp', ''),
                'fingerprint_id' => $fingerprintId,
                'data' => json_encode($request->all()),
                'is_blocked' => false,
            ]);
            
            $attempt->save();

            return response()->json([
                'success' => true,
                'message' => 'Fingerprint data stored successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error storing fingerprint data: ' . $e->getMessage(), [
                'exception' => $e,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to store fingerprint data'
            ], 500);
        }
    }
}
