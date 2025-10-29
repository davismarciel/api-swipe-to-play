<?php

namespace Modules\User\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\User\Models\User;
use Modules\User\Http\Resources\UserResource;

class UserController extends Controller
{
    public function test(): JsonResponse
    {
        return $this->successResponse([
            'status' => 'success',
            'message' => 'API is working!',
            'timestamp' => now(),
            'server_ip' => request()->server('SERVER_ADDR'),
            'client_ip' => request()->ip()
        ]);
    }

    public function index(): JsonResponse
    {
        $users = User::all();
        return $this->successResponse(UserResource::collection($users));
    }

    public function show($id): JsonResponse
    {
        $user = User::findOrFail($id);
        return $this->successResponse(new UserResource($user));
    }

    public function destroy($id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->delete();

        return $this->successResponse(null, 'User deleted successfully');
    }
}
