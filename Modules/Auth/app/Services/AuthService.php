<?php

namespace Modules\Auth\Services;

use Modules\User\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;
use InvalidArgumentException;
use Exception;
use Google\AccessToken\Verify;

class AuthService
{
    public function login(array $credentials): array
    {
        $idToken = $credentials['id_token'] ?? null;

        if (!$idToken) {
            throw new InvalidArgumentException('ID Token is required');
        }

        $decoded = $this->validateGoogleToken($idToken);

        $googleId = $decoded->sub ?? $decoded->user_id;
        $email = $decoded->email ?? null;
        $name = $decoded->name ?? null;
        $avatar = $decoded->picture ?? null;
        
        if (!$googleId || !$email) {
            throw new Exception('Invalid token data: missing user information');
        }

        $user = User::firstOrCreate(
            ['google_id' => $googleId],
            [
                'email' => $email,
                'name' => $name,
                'avatar' => $avatar,
                'provider' => 'google',
                'email_verified_at' => now(),
            ]
        );

        $token = JWTAuth::fromUser($user);
        
        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
            ],
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl', 60),
        ];
    }

    private function validateGoogleToken(string $idToken): object
    {
        $clientId = env('GOOGLE_CLIENT_ID');
        
        if (!$clientId) {
            throw new Exception('Google Client ID not configured');
        }
        
        $verifier = new Verify();
        $payload = $verifier->verifyIdToken($idToken, $clientId);

        if (!$payload) {
            throw new Exception('Invalid Google ID token');
        }

        if (empty($payload['email_verified']) || !$payload['email_verified']) {
            throw new Exception('Email not verified');
        }

        return (object)[
            'sub' => $payload['sub'],
            'email' => $payload['email'],
            'name' => $payload['name'] ?? null,
            'picture' => $payload['picture'] ?? null,
        ];
    }

    public function logout(): array
    {
        $token = JWTAuth::getToken();
        
        if (!$token) {
            throw new Exception('Token not provided');
        }
        
        JWTAuth::invalidate($token);
        
        return [];
    }

    public function refreshToken(): array
    {
        $token = JWTAuth::getToken();
        
        if (!$token) {
            throw new Exception('Token not provided');
        }
        
        $newToken = JWTAuth::refresh($token);
        
        return [
            'access_token' => $newToken,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl', 60),
        ];
    }
    
    public function me(): array
    {
        $user = JWTAuth::parseToken()->authenticate();
        
        if (!$user) {
            throw new Exception('User not found');
        }
        
        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'provider' => $user->provider,
            ],
        ];
    }
}
