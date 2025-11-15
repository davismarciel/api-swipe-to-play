<?php

namespace Modules\Recommendation\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Recommendation\Contracts\RecommendationEngineInterface;
use Modules\Game\Http\Resources\GameResource;
use Modules\Game\Services\DailyGameCacheService;

class RecommendationController extends Controller
{
    public function __construct(
        private RecommendationEngineInterface $recommendationEngine,
        private DailyGameCacheService $dailyGameCache
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

            Log::debug('Recommendations requested', [
                'user_id' => $user->id,
                'limit' => $limit,
            ]);

            $dailyLimitInfo = $this->buildDailyLimitInfo($user);

            if ($dailyLimitInfo['limit_reached']) {
                Log::warning('Daily recommendation limit reached', [
                    'user_id' => $user->id,
                    'current_count' => $dailyLimitInfo['current_count'],
                    'daily_limit' => $dailyLimitInfo['daily_limit'],
                ]);

                return $this->successResponse([
                    'recommendations' => GameResource::collection(collect()),
                    'count' => 0,
                    'limit' => $limit,
                    'daily_limit_info' => $dailyLimitInfo,
                    'message' => 'Daily recommendation limit reached. Come back tomorrow!',
                ]);
            }
            
            $recommendations = $this->recommendationEngine->getRecommendations($user, $limit);
            
            Log::info('Recommendations retrieved successfully', [
                'user_id' => $user->id,
                'limit' => $limit,
                'count' => $recommendations->count(),
                'remaining_today' => $dailyLimitInfo['remaining_today'],
            ]);
            
            return $this->successResponse([
                'recommendations' => GameResource::collection($recommendations),
                'count' => $recommendations->count(),
                'limit' => $limit,
                'daily_limit_info' => $dailyLimitInfo,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error retrieving recommendations', [
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

            Log::debug('Similar games requested', [
                'game_id' => $gameId,
                'game_name' => $game->name,
                'limit' => $limit,
            ]);

            $similarGames = $this->recommendationEngine->getSimilarGames($game, $limit);

            Log::info('Similar games retrieved successfully', [
                'game_id' => $gameId,
                'game_name' => $game->name,
                'limit' => $limit,
                'count' => $similarGames->count(),
            ]);

            return $this->successResponse([
                'similar_games' => GameResource::collection($similarGames),
                'game_id' => $gameId,
                'game_name' => $game->name,
                'count' => $similarGames->count(),
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::warning('Game not found for similar games request', [
                'game_id' => $gameId,
            ]);

            return $this->errorResponse('Game not found', 404);
        } catch (\Exception $e) {
            Log::error('Error retrieving similar games', [
                'game_id' => $gameId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
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
            
            Log::debug('User stats requested', [
                'user_id' => $user->id,
            ]);

            $stats = $this->recommendationEngine->getUserStats($user);

            Log::info('User stats retrieved successfully', [
                'user_id' => $user->id,
            ]);

            return $this->successResponse($stats);
            
        } catch (\Exception $e) {
            Log::error('Error retrieving user stats', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse(
                'Failed to retrieve statistics. Please try again later.',
                500
            );
        }
    }

    private function buildDailyLimitInfo($user): array
    {
        $dailyLimit = $this->dailyGameCache->getDailyLimit();
        $currentCount = $this->dailyGameCache->countToday($user->id);
        $remaining = max(0, $dailyLimit - $currentCount);

        return [
            'current_count' => $currentCount,
            'daily_limit' => $dailyLimit,
            'remaining_today' => $remaining,
            'limit_reached' => $remaining <= 0,
        ];
    }
}
