<?php

namespace Modules\Auth\Services;

use Illuminate\Support\Facades\Log;
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
            Log::warning('Login attempt without ID token');
            throw new InvalidArgumentException('ID Token is required');
        }

        Log::debug('Validating Google token');

        $decoded = $this->validateGoogleToken($idToken);

        $googleId = $decoded->sub ?? $decoded->user_id;
        $email = $decoded->email ?? null;
        $name = $decoded->name ?? null;
        $avatar = $decoded->picture ?? null;
        
        if (!$googleId || !$email) {
            Log::warning('Invalid token data: missing user information', [
                'has_google_id' => !empty($googleId),
                'has_email' => !empty($email)
            ]);
            throw new Exception('Invalid token data: missing user information');
        }

        Log::debug('Google token validated successfully', [
            'google_id' => $googleId,
            'email' => $email
        ]);

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

        if ($user->wasRecentlyCreated) {
            Log::info('New user created', [
                'user_id' => $user->id,
                'email' => $user->email,
                'google_id' => $googleId
            ]);
        } else {
            Log::info('Existing user retrieved', [
                'user_id' => $user->id,
                'email' => $user->email,
                'google_id' => $googleId
            ]);
        }

        Log::debug('Generating JWT token', [
            'user_id' => $user->id
        ]);

        $token = JWTAuth::fromUser($user);

        Log::debug('JWT token generated successfully', [
            'user_id' => $user->id,
            'token_expires_in' => config('jwt.ttl', 60)
        ]);
        
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
            Log::error('Google Client ID not configured');
            throw new Exception('Google Client ID not configured');
        }
        
        Log::debug('Verifying Google ID token');

        $verifier = new Verify();
        $payload = $verifier->verifyIdToken($idToken, $clientId);

        if (!$payload) {
            Log::warning('Invalid Google ID token');
            throw new Exception('Invalid Google ID token');
        }

        if (empty($payload['email_verified']) || !$payload['email_verified']) {
            Log::warning('Email not verified', [
                'email' => $payload['email'] ?? null
            ]);
            throw new Exception('Email not verified');
        }

        Log::debug('Google ID token verified successfully', [
            'email' => $payload['email'] ?? null,
            'sub' => $payload['sub'] ?? null
        ]);

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
            Log::warning('Logout attempt without token');
            throw new Exception('Token not provided');
        }
        
        Log::debug('Invalidating JWT token');

        JWTAuth::invalidate($token);

        Log::debug('JWT token invalidated successfully');
        
        return [];
    }

    public function refreshToken(): array
    {
        try {
            $token = JWTAuth::getToken();
            
            if (!$token) {
                Log::warning('Token refresh attempted without token');
                throw new Exception('Token not provided');
            }

            Log::debug('Refreshing JWT token');

            $newToken = JWTAuth::refresh($token);

            Log::info('JWT token refreshed successfully', [
                'token_expires_in' => config('jwt.ttl', 60)
            ]);
            
            return [
                'access_token' => $newToken,
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl', 60),
            ];
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            Log::warning('Token refresh failed: token expired', [
                'error' => $e->getMessage()
            ]);
            throw new Exception('Token cannot be refreshed. Please login again.');
        } catch (\Tymon\JWTAuth\Exceptions\TokenBlacklistedException $e) {
            Log::warning('Token refresh failed: token blacklisted', [
                'error' => $e->getMessage()
            ]);
            throw new Exception('Token has been invalidated. Please login again.');
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            Log::warning('Token refresh failed: JWT exception', [
                'error' => $e->getMessage()
            ]);
            throw new Exception('Unable to refresh token: ' . $e->getMessage());
        }
    }
    
    public function me(): array
    {
        try {
            Log::debug('Authenticating user from token');

            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                Log::warning('User not found during authentication');
                throw new Exception('User not found');
            }

            Log::debug('User authenticated successfully', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);
            
            return [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'provider' => $user->provider,
                ],
            ];
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            Log::warning('Authentication failed: JWT exception', [
                'error' => $e->getMessage()
            ]);
            throw new Exception('Authentication failed: ' . $e->getMessage());
        }
    }
}
