<?php

use App\Exceptions\ApiErrorResponse;
use App\Exceptions\ApiException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \App\Http\Middleware\ForceJsonResponse::class,
            \App\Http\Middleware\SetLocaleFromHeader::class,
            \App\Http\Middleware\EnsureValidJsonBody::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ApiException $exception, $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::make(
                $exception->errorCode,
                $exception->getMessage(),
                $exception->status,
                $exception->details,
            );
        });

        $exceptions->render(function (ValidationException $exception, $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::make(
                'VALIDATION_ERROR',
                'The given data was invalid.',
                422,
                collect($exception->errors())
                    ->map(fn (array $messages, string $field) => ['field' => $field, 'issue' => $messages[0]])
                    ->values()
                    ->all(),
            );
        });

        $exceptions->render(function (AuthenticationException $exception, $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $bearer = $request->bearerToken();
            $token = $bearer ? PersonalAccessToken::findToken($bearer) : null;

            if ($token?->expires_at?->isPast()) {
                return ApiErrorResponse::make('TOKEN_EXPIRED', 'The access token has expired.', 401);
            }

            return ApiErrorResponse::make('UNAUTHORIZED', 'The token is missing or invalid.', 401);
        });

        $exceptions->render(function (AccessDeniedHttpException $exception, $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::make('FORBIDDEN', 'You are not allowed to access this resource.', 403);
        });

        $exceptions->render(function (NotFoundHttpException $exception, $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::make('NOT_FOUND', 'The requested resource was not found.', 404);
        });

        $exceptions->render(function (ThrottleRequestsException $exception, $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::make('RATE_LIMIT_EXCEEDED', 'Too many requests. Please try again later.', 429);
        });

        $exceptions->render(function (Throwable $exception, $request) {
            if (! $request->is('api/*') || app()->hasDebugModeEnabled()) {
                return null;
            }

            return ApiErrorResponse::make('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
        });
    })->create();
