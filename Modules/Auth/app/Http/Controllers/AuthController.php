<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

            $result = $this->authService->login($request->all());

            return $this->successResponse(new AuthResource($result));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
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
}
