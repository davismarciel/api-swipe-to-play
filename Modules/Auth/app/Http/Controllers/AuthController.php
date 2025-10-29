<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Auth\Services\AuthService;
use Exception;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function login(Request $request)
    {
        try {
            $request->validate([
                'id_token' => 'required|string',
            ]);

            $result = $this->authService->login($request->all());

            return $this->successResponse($result);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Exception $e) {
            return $this->unauthorizedResponse($e->getMessage());
        }
    }

    public function logout()
    {
        try {
            $result = $this->authService->logout();
            return $this->successResponse($result, 'Successfully logged out');
        } catch (Exception $e) {
            return $this->unauthorizedResponse($e->getMessage());
        }
    }

    public function refresh()
    {
        try {
            $result = $this->authService->refreshToken();
            return $this->successResponse($result);
        } catch (Exception $e) {
            return $this->unauthorizedResponse($e->getMessage());
        }
    }

    public function me()
    {
        try {
            $result = $this->authService->me();
            return $this->successResponse($result);
        } catch (Exception $e) {
            return $this->unauthorizedResponse($e->getMessage());
        }
    }
}
