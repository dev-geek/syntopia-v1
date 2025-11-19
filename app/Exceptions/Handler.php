<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
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

        if ($e instanceof AuthorizationException) {
            if ($request->expectsJson()) {
                return new JsonResponse([
                    'message' => 'You don\'t have permission to access this resource.',
                ], 403);
            }

            // Redirect regular users (non-admin) trying to access admin routes to their dashboard
            if (Auth::check()) {
                $user = Auth::user();

                // Check if user doesn't have admin roles and is trying to access admin routes
                $isAdminRoute = $request->is('admin/*') || str_starts_with($request->path(), 'admin/');

                if (!$user->hasAnyRole(['Super Admin', 'Sub Admin']) && $isAdminRoute) {
                    return redirect()->route('user.dashboard')
                        ->with('info', 'You have been redirected to your dashboard.');
                }
            }
        }

        return parent::render($request, $e);
    }
}
