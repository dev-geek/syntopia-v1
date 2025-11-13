<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Auth\Access\AuthorizationException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $e): Response
    {
        if ($e instanceof TokenMismatchException) {
            if ($request->expectsJson()) {
                return new JsonResponse([
                    'message' => 'Your session has expired. Please refresh and try again.',
                ], 419);
            }

            return redirect()->back()
                ->withInput($request->except('_token'))
                ->with('error', 'Your session has expired. Please try again.');
        }

        // Handle 403 errors for admin routes - redirect to non-admin URL
        if (($e instanceof AuthorizationException || ($e instanceof HttpException && $e->getStatusCode() === 403))
            && $request->is('admin/*')) {

            if ($request->expectsJson()) {
                return new JsonResponse([
                    'message' => $e->getMessage() ?: 'You don\'t have permission to access this resource.',
                ], 403);
            }

            // Redirect to a non-admin route and store exception message in session
            return redirect()->route('access-denied')
                ->with('exception_message', $e->getMessage() ?: 'This action is unauthorized.');
        }

        return parent::render($request, $e);
    }
}
