<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Exception;

class MailService
{
    /**
     * Send email with proper error handling
     *
     * @param string $to
     * @param mixed $mailable
     * @return array
     */
    public static function send($to, $mailable): array
    {
        try {
            Mail::to($to)->send($mailable);

            return [
                'success' => true,
                'message' => 'Email sent successfully'
            ];

        } catch (Exception $e) {
            $errorMessage = self::getUserFriendlyErrorMessage($e);

            Log::error('Failed to send email', [
                'to' => $to,
                'mailable_class' => get_class($mailable),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => $errorMessage,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get user-friendly error message based on the exception
     *
     * @param Exception $e
     * @return string
     */
    private static function getUserFriendlyErrorMessage(Exception $e): string
    {
        $message = $e->getMessage();

        // Check for common mail connection errors
        if (str_contains($message, 'Connection could not be established') ||
            str_contains($message, 'getaddrinfo') ||
            str_contains($message, 'No such host is known')) {
            return 'Email service is temporarily unavailable. Please try again later or contact support.';
        }

        if (str_contains($message, 'Authentication failed') ||
            str_contains($message, 'Invalid credentials')) {
            return 'Email service configuration error. Please contact support.';
        }

        if (str_contains($message, 'timeout') ||
            str_contains($message, 'Connection timed out')) {
            return 'Email service is taking longer than expected. Please try again in a few minutes.';
        }

        if (str_contains($message, 'SSL') ||
            str_contains($message, 'TLS')) {
            return 'Email service security configuration issue. Please contact support.';
        }

        // Default message for unknown errors
        return 'Unable to send email at this time. Please try again later or contact support.';
    }

    /**
     * Check if mail service is available
     *
     * @return bool
     */
    public static function isAvailable(): bool
    {
        try {
            // Try to get the mail configuration
            $config = config('mail.default');
            return !empty($config);
        } catch (Exception $e) {
            Log::error('Mail service availability check failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
