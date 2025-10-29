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

    public function like(Request $request, int $gameId): JsonResponse
    {
        $user = $request->user();
        $game = \Modules\Game\Models\Game::findOrFail($gameId);

        $interaction = $this->recommendationEngine->recordInteraction($user, $game, 'like');

        return $this->createdResponse(new GameInteractionResource($interaction), 'Game liked successfully');
    }

    public function dislike(Request $request, int $gameId): JsonResponse
    {
        $user = $request->user();
        $game = \Modules\Game\Models\Game::findOrFail($gameId);

        $interaction = $this->recommendationEngine->recordInteraction($user, $game, 'dislike');

        return $this->createdResponse(new GameInteractionResource($interaction), 'Game disliked successfully');
    }

    public function favorite(Request $request, int $gameId): JsonResponse
    {
        $user = $request->user();
        $game = \Modules\Game\Models\Game::findOrFail($gameId);

        $interaction = $this->recommendationEngine->recordInteraction($user, $game, 'favorite');

        return $this->createdResponse(new GameInteractionResource($interaction), 'Game added to favorites');
    }

    public function view(Request $request, int $gameId): JsonResponse
    {
        $user = $request->user();
        $game = \Modules\Game\Models\Game::findOrFail($gameId);

        $interaction = $this->recommendationEngine->recordInteraction($user, $game, 'view');

        return $this->createdResponse(new GameInteractionResource($interaction), 'Game view recorded');
    }

    public function skip(Request $request, int $gameId): JsonResponse
    {
        $user = $request->user();
        $game = \Modules\Game\Models\Game::findOrFail($gameId);

        $interaction = $this->recommendationEngine->recordInteraction($user, $game, 'skip');

        return $this->createdResponse(new GameInteractionResource($interaction), 'Game skipped');
    }

    public function history(Request $request): JsonResponse
    {
        $request->validate([
            'limit' => 'sometimes|integer|min:1|max:100',
        ]);

        $user = $request->user();
        $limit = $request->input('limit', 20);

        $history = $this->recommendationEngine->getUserInteractionHistory($user, $limit);

        return $this->successResponse([
            'history' => GameInteractionResource::collection($history),
            'count' => $history->count(),
            'limit' => $limit,
        ]);
    }

    public function favorites(Request $request): JsonResponse
    {
        $user = $request->user();
        $favorites = $this->recommendationEngine->getUserFavorites($user);

        return $this->successResponse([
            'favorites' => \Modules\Game\Http\Resources\GameResource::collection($favorites),
            'count' => $favorites->count(),
        ]);
    }
}
