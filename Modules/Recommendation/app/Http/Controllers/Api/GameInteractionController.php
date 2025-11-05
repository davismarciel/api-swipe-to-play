<?php

namespace Modules\Recommendation\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Recommendation\Contracts\RecommendationEngineInterface;
use Modules\Recommendation\Http\Resources\GameInteractionResource;

class GameInteractionController extends Controller
{
    public function __construct(
        private RecommendationEngineInterface $recommendationEngine
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

            $interaction = $this->recommendationEngine->recordInteraction($user, $game, 'like');

            return $this->createdResponse(new GameInteractionResource($interaction), 'Game liked successfully');
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Game not found', 404);
        } catch (\Exception $e) {
            \Log::error('Error recording like interaction', [
                'user_id' => $request->user()?->id,
                'game_id' => $gameId,
                'error' => $e->getMessage()
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

            $interaction = $this->recommendationEngine->recordInteraction($user, $game, $type);

            return $this->createdResponse(new GameInteractionResource($interaction), $message);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Game not found', 404);
        } catch (\Exception $e) {
            \Log::error('Error recording interaction', [
                'user_id' => $request->user()?->id,
                'game_id' => $gameId,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse(
                'Failed to record interaction. Please try again later.',
                500
            );
        }
    }
}
