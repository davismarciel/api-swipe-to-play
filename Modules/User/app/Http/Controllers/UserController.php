<?php

namespace Modules\User\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Modules\User\Models\User;
use Modules\User\Http\Resources\UserResource;

class UserController extends Controller
{
    public function test(): JsonResponse
    {
        Log::debug('Test endpoint requested', [
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);

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
        Log::info('User list requested', [
            'ip' => request()->ip()
        ]);

        $users = User::all();

        Log::info('User list retrieved successfully', [
            'count' => $users->count(),
            'ip' => request()->ip()
        ]);

        return $this->successResponse(UserResource::collection($users));
    }

    public function show($id): JsonResponse
    {
        try {
            Log::info('User detail requested', [
                'user_id' => $id,
                'ip' => request()->ip()
            ]);

            $user = User::findOrFail($id);

            Log::info('User detail retrieved successfully', [
                'user_id' => $id,
                'email' => $user->email,
                'ip' => request()->ip()
            ]);

            return $this->successResponse(new UserResource($user));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::warning('User not found', [
                'user_id' => $id,
                'ip' => request()->ip()
            ]);

            throw $e;
        } catch (\Exception $e) {
            Log::error('Error retrieving user detail', [
                'user_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            Log::warning('User deletion requested', [
                'user_id' => $id,
                'ip' => request()->ip()
            ]);

            $user = User::findOrFail($id);
            $email = $user->email;
            
            $user->delete();

            Log::warning('User deleted successfully', [
                'user_id' => $id,
                'email' => $email,
                'ip' => request()->ip()
            ]);

            return $this->successResponse(null, 'User deleted successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::warning('User not found for deletion', [
                'user_id' => $id,
                'ip' => request()->ip()
            ]);

            throw $e;
        } catch (\Exception $e) {
            Log::error('Error deleting user', [
                'user_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }
}
