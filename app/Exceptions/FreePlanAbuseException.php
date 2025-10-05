<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FreePlanAbuseException extends Exception
{
    protected $errorCode;
    protected $userMessage;
    protected $shouldLog;

    public function __construct(
        string $message = 'Free plan abuse detected',
        string $errorCode = 'FREE_PLAN_ABUSE',
        string $userMessage = 'Access denied due to abuse prevention policies.',
        bool $shouldLog = true,
        int $code = 403,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);

        $this->errorCode = $errorCode;
        $this->userMessage = $userMessage;
        $this->shouldLog = $shouldLog;
    }

    /**
     * Get the error code
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * Get the user-friendly message
     */
    public function getUserMessage(): string
    {
        return $this->userMessage;
    }

    /**
     * Whether this exception should be logged
     */
    public function shouldLog(): bool
    {
        return $this->shouldLog;
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'error' => $this->errorCode,
                'message' => $this->userMessage,
                'details' => config('app.debug') ? $this->getMessage() : null
            ], $this->getCode());
        }

        return redirect()->back()
            ->withErrors(['email' => $this->userMessage])
            ->withInput();
    }
}
