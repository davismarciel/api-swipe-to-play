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

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'limit' => 'sometimes|integer|min:1|max:50',
        ]);

        $user = $request->user();
        $limit = $request->input('limit', 10);

        $recommendations = $this->recommendationEngine->getRecommendations($user, $limit);

        return $this->successResponse([
            'recommendations' => GameResource::collection($recommendations),
            'count' => $recommendations->count(),
            'limit' => $limit,
        ]);
    }

    public function similar(Request $request, int $gameId): JsonResponse
    {
        $request->validate([
            'limit' => 'sometimes|integer|min:1|max:20',
        ]);

        $game = \Modules\Game\Models\Game::findOrFail($gameId);
        $limit = $request->input('limit', 5);

        $similarGames = $this->recommendationEngine->getSimilarGames($game, $limit);

        return $this->successResponse([
            'similar_games' => GameResource::collection($similarGames),
            'game_id' => $gameId,
            'game_name' => $game->name,
            'count' => $similarGames->count(),
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $stats = $this->recommendationEngine->getUserStats($user);

        return $this->successResponse($stats);
    }
}
