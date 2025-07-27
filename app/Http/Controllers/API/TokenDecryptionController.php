<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class TokenDecryptionController extends Controller
{
    public function __construct()
    {
        // Allow CORS for cross-domain requests from the software
        header('Access-Control-Allow-Origin: https://live.syntopia.ai');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-CSRF-TOKEN');

        // Handle preflight OPTIONS request
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }

    /**
     * Decrypt token and return credentials
     */
    public function decryptToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Invalid token format',
                'details' => $validator->errors()
            ], 400);
        }

        try {
            // Decrypt the token
            $decryptedData = Crypt::decryptString($request->token);
            $tokenData = json_decode($decryptedData, true);

            if (!$tokenData) {
                return response()->json([
                    'error' => 'Invalid token data'
                ], 400);
            }

            // Validate token structure
            if (!isset($tokenData['user_id'], $tokenData['email'], $tokenData['password'], $tokenData['expires_at'])) {
                return response()->json([
                    'error' => 'Token missing required fields'
                ], 400);
            }

            // Check if token has expired
            if (Carbon::now()->timestamp > $tokenData['expires_at']) {
                return response()->json([
                    'error' => 'Token has expired'
                ], 401);
            }

            // Return credentials (email and password)
            return response()->json([
                'success' => true,
                'credentials' => [
                    'email' => $tokenData['email'],
                    'password' => $tokenData['password']
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to decrypt token',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate token without returning credentials
     */
    public function validateToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'valid' => false,
                'error' => 'Invalid token format'
            ]);
        }

        try {
            $decryptedData = Crypt::decryptString($request->token);
            $tokenData = json_decode($decryptedData, true);

            if (!$tokenData || !isset($tokenData['expires_at'])) {
                return response()->json([
                    'valid' => false,
                    'error' => 'Invalid token structure'
                ]);
            }

            $isExpired = Carbon::now()->timestamp > $tokenData['expires_at'];

            return response()->json([
                'valid' => !$isExpired,
                'expired' => $isExpired,
                'user_id' => $tokenData['user_id'] ?? null
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'valid' => false,
                'error' => 'Token decryption failed'
            ]);
        }
    }
}
