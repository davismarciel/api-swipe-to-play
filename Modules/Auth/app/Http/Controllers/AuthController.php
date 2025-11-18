<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Auth\Services\AuthService;
use Modules\Auth\Http\Resources\AuthResource;
use Modules\Auth\Http\Requests\LoginRequest;
use Exception;

class AuthController extends Controller
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->login($request->validated());

            return $this->successResponse(new AuthResource($result));
        } catch (Exception $e) {
            return $this->unauthorizedResponse($e->getMessage());
        }
    }

    public function logout(): JsonResponse
    {
        try {
            $result = $this->authService->logout();
            return $this->successResponse($result, 'Successfully logged out');
        } catch (Exception $e) {
            return $this->unauthorizedResponse($e->getMessage());
        }
    }

    public function refresh(): JsonResponse
    {
        try {
            $result = $this->authService->refreshToken();
            return $this->successResponse(new AuthResource($result));
        } catch (Exception $e) {
            return $this->unauthorizedResponse($e->getMessage());
        }
    }

    public function me(): JsonResponse
    {
        try {
            $result = $this->authService->me();
            return $this->successResponse($result);
        } catch (Exception $e) {
            return $this->unauthorizedResponse($e->getMessage());
        }
    }

    /**
     * Health check endpoint - verifies if API is online
     * No authentication required
     */
    public function health(): JsonResponse
    {
        return $this->successResponse([
            'status' => 'online',
            'timestamp' => now()->toIso8601String(),
        ], 'API is online');
    }
}
