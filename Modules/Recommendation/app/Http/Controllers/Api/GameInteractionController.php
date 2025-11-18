<?php

namespace Modules\Recommendation\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Recommendation\Contracts\RecommendationEngineInterface;
use Modules\Recommendation\Http\Resources\GameInteractionResource;
use Illuminate\Support\Facades\Log;

class GameInteractionController extends Controller
{
    public function __construct(
        private RecommendationEngineInterface $recommendationEngine
    ) {}

    /**
     * Registers a "like" interaction with a game
     *
     * @param Request $request
     * @param int $gameId Game ID
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
            Log::error('Error recording like interaction', [
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
     * Registers a "dislike" interaction with a game
     */
    public function dislike(Request $request, int $gameId): JsonResponse
    {
        return $this->recordInteraction($request, $gameId, 'dislike', 'Game disliked successfully');
    }

    /**
     * Adds a game to the user's favorites
     */
    public function favorite(Request $request, int $gameId): JsonResponse
    {
        return $this->recordInteraction($request, $gameId, 'favorite', 'Game added to favorites');
    }

    /**
     * Registers a game view
     */
    public function view(Request $request, int $gameId): JsonResponse
    {
        return $this->recordInteraction($request, $gameId, 'view', 'Game view recorded');
    }

    /**
     * Registers that the user skipped a game
     */
    public function skip(Request $request, int $gameId): JsonResponse
    {
        return $this->recordInteraction($request, $gameId, 'skip', 'Game skipped');
    }
    
    /**
     * Helper method to record interactions
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
            Log::error('Error recording interaction', [
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
