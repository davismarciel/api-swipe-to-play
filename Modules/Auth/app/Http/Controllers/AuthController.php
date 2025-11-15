<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Auth\Services\AuthService;
use Modules\Auth\Http\Resources\AuthResource;
use Exception;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function login(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id_token' => 'required|string',
            ]);

            Log::info('Login attempt', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            $result = $this->authService->login($request->all());

            Log::info('Login successful', [
                'user_id' => $result['user']['id'] ?? null,
                'email' => $result['user']['email'] ?? null,
                'ip' => $request->ip()
            ]);

            return $this->successResponse(new AuthResource($result));
        } catch (ValidationException $e) {
            Log::warning('Login validation failed', [
                'errors' => $e->errors(),
                'ip' => $request->ip()
            ]);

            return $this->validationErrorResponse($e->errors());
        } catch (Exception $e) {
            Log::warning('Login failed', [
                'error' => $e->getMessage(),
                'ip' => $request->ip()
            ]);

            return $this->unauthorizedResponse($e->getMessage());
        }
    }

    public function logout(): JsonResponse
    {
        try {
            $user = auth()->user();
            
            Log::info('Logout attempt', [
                'user_id' => $user->id ?? null,
                'ip' => request()->ip()
            ]);

            $result = $this->authService->logout();

            Log::info('Logout successful', [
                'user_id' => $user->id ?? null,
                'ip' => request()->ip()
            ]);

            return $this->successResponse($result, 'Successfully logged out');
        } catch (Exception $e) {
            Log::warning('Logout failed', [
                'error' => $e->getMessage(),
                'ip' => request()->ip()
            ]);

            return $this->unauthorizedResponse($e->getMessage());
        }
    }

    public function refresh(): JsonResponse
    {
        try {
            $user = auth()->user();

            Log::info('Token refresh requested', [
                'user_id' => $user->id ?? null,
                'ip' => request()->ip()
            ]);

            $result = $this->authService->refreshToken();

            Log::info('Token refresh successful', [
                'user_id' => $user->id ?? null,
                'ip' => request()->ip()
            ]);

            return $this->successResponse(new AuthResource($result));
        } catch (Exception $e) {
            Log::warning('Token refresh failed', [
                'error' => $e->getMessage(),
                'user_id' => auth()->user()?->id,
                'ip' => request()->ip()
            ]);

            return $this->unauthorizedResponse($e->getMessage());
        }
    }

    public function me(): JsonResponse
    {
        try {
            $user = auth()->user();

            Log::debug('User info requested', [
                'user_id' => $user->id ?? null,
                'ip' => request()->ip()
            ]);

            $result = $this->authService->me();

            return $this->successResponse($result);
        } catch (Exception $e) {
            Log::warning('User info request failed', [
                'error' => $e->getMessage(),
                'ip' => request()->ip()
            ]);

            return $this->unauthorizedResponse($e->getMessage());
        }
    }
}
