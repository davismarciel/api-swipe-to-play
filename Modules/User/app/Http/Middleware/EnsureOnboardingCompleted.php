<?php

namespace Modules\User\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboardingCompleted
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        if ($request->routeIs('onboarding.complete') || 
            $request->is('api/onboarding/complete') ||
            $request->is('api/v1/auth/login') ||
            $request->is('api/v1/auth/health')) {
            return $next($request);
        }

        if (!$user->onboarding_completed_at) {
            return response()->json([
                'success' => false,
                'message' => 'Onboarding not completed. Please complete onboarding to access this resource.',
                'requires_onboarding' => true
            ], 403);
        }

        return $next($request);
    }
}

