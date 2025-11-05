<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\TokenBlacklistedException;
use Tymon\JWTAuth\Exceptions\JWTException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Retorna 401 JSON para erros de autenticação em rotas de API
        // Isso previne que o middleware tente redirecionar para 'login' que não existe em APIs
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated',
                    'error' => 'Token inválido ou expirado. Por favor, faça login novamente.',
                ], 401);
            }
        });
        
        // Captura RouteNotFoundException que pode ocorrer quando middleware tenta redirecionar
        $exceptions->render(function (RouteNotFoundException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated',
                    'error' => 'Token inválido ou expirado. Por favor, faça login novamente.',
                ], 401);
            }
        });
        
        // Tratamento de exceções JWT específicas
        $exceptions->render(function (TokenExpiredException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token Expirado',
                    'error' => 'O token expirou. Use o endpoint /refresh para renovar o token.',
                ], 401);
            }
        });
        
        $exceptions->render(function (TokenBlacklistedException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token na Blacklist',
                    'error' => 'O token foi invalidado. Por favor, faça login novamente.',
                ], 401);
            }
        });
        
        $exceptions->render(function (TokenInvalidException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token Inválido',
                    'error' => 'O token fornecido é inválido. Por favor, faça login novamente.',
                ], 401);
            }
        });
        
        $exceptions->render(function (JWTException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro de Autenticação',
                    'error' => $e->getMessage() ?: 'Erro ao processar o token. Por favor, faça login novamente.',
                ], 401);
            }
        });
    })->create();
