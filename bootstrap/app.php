<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\TokenBlacklistedException;
use Tymon\JWTAuth\Exceptions\JWTException;
use Throwable;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'onboarding.completed' => \Modules\User\Http\Middleware\EnsureOnboardingCompleted::class,
            'force.json' => \App\Http\Middleware\ForceJsonResponse::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $e, Request $request) {
            if (!$request->is('api/*') && !$request->expectsJson()) {
                return null;
            }

            $statusCode = match (true) {
                $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface => $e->getStatusCode(),
                $e instanceof AuthenticationException,
                $e instanceof TokenExpiredException,
                $e instanceof TokenBlacklistedException,
                $e instanceof TokenInvalidException,
                $e instanceof JWTException => 401,
                $e instanceof ValidationException => 422,
                $e instanceof RouteNotFoundException,
                $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException => 404,
                default => (method_exists($e, 'getCode') && $e->getCode() >= 400 && $e->getCode() < 600) 
                    ? $e->getCode() 
                    : 500,
            };
            
            $response = match (true) {
                $e instanceof AuthenticationException => [
                    'success' => false,
                    'message' => 'Unauthenticated',
                    'error' => 'Invalid or expired token. Please login again.',
                ],
                $e instanceof ValidationException => [
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $e->errors(),
                ],
                $e instanceof RouteNotFoundException => [
                    'success' => false,
                    'message' => 'Not Found',
                    'error' => 'The requested resource was not found.',
                ],
                $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException => [
                    'success' => false,
                    'message' => 'Not Found',
                    'error' => 'The requested resource was not found.',
                ],
                $e instanceof TokenExpiredException => [
                    'success' => false,
                    'message' => 'Token Expired',
                    'error' => 'The token has expired. Use the /refresh endpoint to renew the token.',
                ],
                $e instanceof TokenBlacklistedException => [
                    'success' => false,
                    'message' => 'Token Blacklisted',
                    'error' => 'The token has been invalidated. Please login again.',
                ],
                $e instanceof TokenInvalidException => [
                    'success' => false,
                    'message' => 'Invalid Token',
                    'error' => 'The provided token is invalid. Please login again.',
                ],
                $e instanceof JWTException => [
                    'success' => false,
                    'message' => 'Authentication Error',
                    'error' => $e->getMessage() ?: 'Unable to process token. Please login again.',
                ],
                default => [
                    'success' => false,
                    'message' => $statusCode === 500 ? 'Internal server error' : ($e->getMessage() ?: 'An error occurred'),
                ],
            };
            
            if (config('app.debug') && $statusCode === 500 && !isset($response['error'])) {
                $response['error'] = $e->getMessage();
                $response['file'] = $e->getFile();
                $response['line'] = $e->getLine();
            }
            
            return response()->json($response, $statusCode);
        });
    })->create();
