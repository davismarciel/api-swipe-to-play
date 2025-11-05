<?php

namespace Modules\Recommendation\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Recommendation\Contracts\RecommendationEngineInterface;
use Modules\Game\Http\Resources\GameResource;

class RecommendationController extends Controller
{
    public function __construct(
        private RecommendationEngineInterface $recommendationEngine
    ) {}

    /**
     * Obtém recomendações personalizadas para o usuário autenticado
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => 'sometimes|integer|min:1|max:50',
        ]);

        try {
            $user = $request->user();
            
            if (!$user) {
                return $this->errorResponse('User not authenticated', 401);
            }
            
            $limit = $validated['limit'] ?? 10;
            
            $recommendations = $this->recommendationEngine->getRecommendations($user, $limit);
            
            return $this->successResponse([
                'recommendations' => GameResource::collection($recommendations),
                'count' => $recommendations->count(),
                'limit' => $limit,
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error retrieving recommendations', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse(
                'Failed to retrieve recommendations. Please try again later.',
                500
            );
        }
    }

    /**
     * Obtém jogos similares a um jogo específico
     *
     * @param Request $request
     * @param int $gameId ID do jogo
     * @return JsonResponse
     */
    public function similar(Request $request, int $gameId): JsonResponse
    {
        $validated = $request->validate([
            'limit' => 'sometimes|integer|min:1|max:20',
        ]);

        try {
            $game = \Modules\Game\Models\Game::findOrFail($gameId);
            $limit = $validated['limit'] ?? 5;

            $similarGames = $this->recommendationEngine->getSimilarGames($game, $limit);

            return $this->successResponse([
                'similar_games' => GameResource::collection($similarGames),
                'game_id' => $gameId,
                'game_name' => $game->name,
                'count' => $similarGames->count(),
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Game not found', 404);
        } catch (\Exception $e) {
            \Log::error('Error retrieving similar games', [
                'game_id' => $gameId,
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse(
                'Failed to retrieve similar games. Please try again later.',
                500
            );
        }
    }

    /**
     * Obtém estatísticas de recomendação do usuário
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return $this->errorResponse('User not authenticated', 401);
            }
            
            $stats = $this->recommendationEngine->getUserStats($user);

            return $this->successResponse($stats);
            
        } catch (\Exception $e) {
            \Log::error('Error retrieving user stats', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse(
                'Failed to retrieve statistics. Please try again later.',
                500
            );
        }
    }
}
