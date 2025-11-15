<?php

namespace Modules\Recommendation\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Recommendation\Contracts\RecommendationEngineInterface;
use Modules\Recommendation\Http\Resources\GameInteractionResource;
use Modules\Game\Services\DailyGameCacheService;
use Modules\Recommendation\Models\GameInteraction;

class GameInteractionController extends Controller
{
    public function __construct(
        private RecommendationEngineInterface $recommendationEngine,
        private DailyGameCacheService $dailyGameCache
    ) {}

    /**
     * Registra uma interação de "like" com um jogo
     *
     * @param Request $request
     * @param int $gameId ID do jogo
     * @return JsonResponse
     */
    public function like(Request $request, int $gameId): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return $this->errorResponse('User not authenticated', 401);
            }
            
            $game = \Modules\Game\Models\Game::findOrFail($gameId);

            Log::debug('Like interaction requested', [
                'user_id' => $user->id,
                'game_id' => $gameId,
            ]);

            $interaction = $this->recommendationEngine->recordInteraction($user, $game, 'like');

            Log::info('Like interaction recorded successfully', [
                'user_id' => $user->id,
                'game_id' => $gameId,
                'interaction_id' => $interaction->id,
            ]);

            return $this->createdResponse(new GameInteractionResource($interaction), 'Game liked successfully');
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::warning('Game not found for like interaction', [
                'user_id' => $request->user()?->id,
                'game_id' => $gameId,
            ]);

            return $this->errorResponse('Game not found', 404);
        } catch (\Exception $e) {
            Log::error('Error recording like interaction', [
                'user_id' => $request->user()?->id,
                'game_id' => $gameId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse(
                'Failed to record interaction. Please try again later.',
                500
            );
        }
    }

    /**
     * Registra uma interação de "dislike" com um jogo
     */
    public function dislike(Request $request, int $gameId): JsonResponse
    {
        return $this->recordInteraction($request, $gameId, 'dislike', 'Game disliked successfully');
    }

    /**
     * Adiciona um jogo aos favoritos
     */
    public function favorite(Request $request, int $gameId): JsonResponse
    {
        return $this->recordInteraction($request, $gameId, 'favorite', 'Game added to favorites');
    }

    /**
     * Registra uma visualização de jogo
     */
    public function view(Request $request, int $gameId): JsonResponse
    {
        return $this->recordInteraction($request, $gameId, 'view', 'Game view recorded');
    }

    /**
     * Registra que o usuário pulou um jogo
     */
    public function skip(Request $request, int $gameId): JsonResponse
    {
        return $this->recordInteraction($request, $gameId, 'skip', 'Game skipped');
    }

    /**
     * Limpa todas as interações do usuário autenticado (apenas ambientes de desenvolvimento)
     */
    public function clearAll(Request $request): JsonResponse
    {
        if (!app()->environment(['local', 'development', 'testing']) && !config('app.debug')) {
            return $this->errorResponse('This endpoint is only available in non-production environments.', 403);
        }

        $user = $request->user();

        if (!$user) {
            return $this->errorResponse('User not authenticated', 401);
        }

        $interactionsCount = GameInteraction::where('user_id', $user->id)->count();

        GameInteraction::where('user_id', $user->id)->delete();
        $this->dailyGameCache->clearUserData($user->id);

        Log::warning('All user interactions cleared', [
            'user_id' => $user->id,
            'interactions_deleted' => $interactionsCount,
            'environment' => app()->environment(),
        ]);

        return $this->successResponse([], 'All interactions cleared successfully');
    }
    
    /**
     * Método auxiliar para registrar interações
     */
    private function recordInteraction(Request $request, int $gameId, string $type, string $message): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return $this->errorResponse('User not authenticated', 401);
            }
            
            $game = \Modules\Game\Models\Game::findOrFail($gameId);

            Log::debug('Game interaction requested', [
                'user_id' => $user->id,
                'game_id' => $gameId,
                'type' => $type,
            ]);

            $interaction = $this->recommendationEngine->recordInteraction($user, $game, $type);

            Log::info('Game interaction recorded successfully', [
                'user_id' => $user->id,
                'game_id' => $gameId,
                'type' => $type,
                'interaction_id' => $interaction->id,
            ]);

            return $this->createdResponse(new GameInteractionResource($interaction), $message);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::warning('Game not found for interaction', [
                'user_id' => $request->user()?->id,
                'game_id' => $gameId,
                'type' => $type,
            ]);

            return $this->errorResponse('Game not found', 404);
        } catch (\Exception $e) {
            Log::error('Error recording interaction', [
                'user_id' => $request->user()?->id,
                'game_id' => $gameId,
                'type' => $type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse(
                'Failed to record interaction. Please try again later.',
                500
            );
        }
    }
}
