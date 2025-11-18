<?php

namespace Modules\Recommendation\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Modules\Game\Models\Game;
use Modules\User\Models\User;
use Modules\Recommendation\Models\GameInteraction;
use Modules\Recommendation\Contracts\RecommendationEngineInterface;
use Modules\Recommendation\Contracts\Neo4j\Neo4jRecommendationServiceInterface;
use Modules\Recommendation\Contracts\GameFilterServiceInterface;
use Modules\Recommendation\Services\Synchronization\Neo4jSynchronizationService;

class RecommendationEngine implements RecommendationEngineInterface
{
    public function __construct(
        private Neo4jRecommendationServiceInterface $neo4jService,
        private GameFilterServiceInterface $filterService,
        private Neo4jSynchronizationService $syncService
    ) {}

    /**
     * Gets personalized recommendations for the user
     *
     * @param User $user User to generate recommendations for
     * @param int $limit Maximum number of recommendations (default: 10)
     * @return Collection Collection of recommended games with scores and explanations
     */
    public function getRecommendations(User $user, int $limit = 10): Collection
    {
        $startTime = microtime(true);
        
        try {
            $recommendations = $this->neo4jService->getRecommendations($user, $limit);
            
            if ($recommendations->isEmpty()) {
                Log::info('No recommendations found from Neo4j, using default', [
                    'user_id' => $user->id,
                    'limit' => $limit
                ]);
                return $this->getDefaultRecommendations($user, $limit);
            }
            
            $executionTime = microtime(true) - $startTime;
            
            Log::info('Recommendations generated successfully', [
                'user_id' => $user->id,
                'count' => $recommendations->count(),
                'limit' => $limit,
                'execution_time_ms' => round($executionTime * 1000, 2)
            ]);
            
            return $recommendations;
            
        } catch (\Exception $e) {
            Log::error('Failed to generate recommendations', [
                'user_id' => $user->id,
                'limit' => $limit,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->getDefaultRecommendations($user, $limit);
        }
    }

    /**
     * Returns default recommendations for users without behavioral profile
     *
     * @param User $user User to generate recommendations for
     * @param int $limit Number of recommendations
     * @return Collection Collection of games with neutral score
     */
    private function getDefaultRecommendations(User $user, int $limit): Collection
    {
        $query = $this->filterService->filterGames($user);
        
        if ($user->preferredGenres()->count() > 0) {
            $query = $this->filterService->applyGenreBoost($user, $query);
        }
        
        return $query
            ->with(['genres', 'categories', 'platform', 'developers', 'publishers', 'communityRating'])
            ->orderByDesc('total_reviews')
            ->orderByDesc('positive_ratio')
            ->limit($limit)
            ->get()
            ->map(function($game) {
                $game->recommendation_score = 50;
                $game->explanation = [
                    'match_percentage' => 50,
                    'top_reasons' => ['Popular and well-rated game'],
                    'score_breakdown' => []
                ];
                return $game;
            });
    }


    /**
     * Records a user interaction with a game
     *
     * @param User $user User who interacted
     * @param Game $game Game that was interacted with
     * @param string $type Interaction type (like, dislike, favorite, view, skip)
     * @return GameInteraction Recorded interaction
     */
    public function recordInteraction(User $user, Game $game, string $type): GameInteraction
    {
        $interactionScore = $this->calculateInteractionScore($type);

        $interaction = GameInteraction::updateOrCreate(
            [
                'user_id' => $user->id,
                'game_id' => $game->id,
                'type' => $type,
            ],
            [
                'interaction_score' => $interactionScore,
                'interacted_at' => now(),
            ]
        );

        try {
            $this->syncService->syncUser($user);
            $this->syncService->syncGame($game);
            
            if (in_array($type, ['like', 'dislike'])) {
                $likeDislikeCount = GameInteraction::where('user_id', $user->id)
                    ->whereIn('type', ['like', 'dislike'])
                    ->count();
                
                if ($likeDislikeCount % 5 == 0) {
                    $this->syncService->syncGameInteraction($interaction);
                    Log::info('Game interaction synced to Neo4j', [
                        'user_id' => $user->id,
                        'game_id' => $game->id,
                        'interaction_id' => $interaction->id,
                        'type' => $type,
                        'like_dislike_count' => $likeDislikeCount
                    ]);
                } else {
                    Log::debug('Game interaction skipped sync (batch threshold not reached)', [
                        'user_id' => $user->id,
                        'game_id' => $game->id,
                        'interaction_id' => $interaction->id,
                        'type' => $type,
                        'like_dislike_count' => $likeDislikeCount,
                        'next_sync_at' => (int)($likeDislikeCount / 5 + 1) * 5
                    ]);
                }
            } else {
                $this->syncService->syncGameInteraction($interaction);
                Log::info('Game interaction synced to Neo4j (immediate sync)', [
                    'user_id' => $user->id,
                    'game_id' => $game->id,
                    'interaction_id' => $interaction->id,
                    'type' => $type
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to sync interaction to Neo4j', [
                'user_id' => $user->id,
                'game_id' => $game->id,
                'interaction_id' => $interaction->id,
                'error' => $e->getMessage()
            ]);
        }

        return $interaction;
    }

    private function calculateInteractionScore(string $type): int
    {
        return match ($type) {
            'like' => 10,
            'favorite' => 15,
            'view' => 1,
            'dislike' => -5,
            'skip' => -2,
            default => 0,
        };
    }


    /**
     * Gets similar games to a specific game
     *
     * @param Game $game Reference game
     * @param int $limit Maximum number of similar games
     * @return Collection Collection of similar games
     */
    public function getSimilarGames(Game $game, int $limit = 5): Collection
    {
        return $this->neo4jService->getSimilarGames($game->id, $limit);
    }

    /**
     * Gets user interaction history
     *
     * @param User $user User
     * @param int $limit Maximum number of interactions
     * @return Collection Interaction history ordered by date
     */
    public function getUserInteractionHistory(User $user, int $limit = 20): Collection
    {
        return $user->gameInteractions()
            ->with(['game.genres', 'game.categories', 'game.platform', 'game.developers', 'game.publishers', 'game.communityRating'])
            ->orderBy('interacted_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Gets user favorite games
     *
     * @param User $user User
     * @return Collection Collection of favorited games
     */
    public function getUserFavorites(User $user): Collection
    {
        return $user->gameInteractions()
            ->where('type', 'favorite')
            ->with(['game.genres', 'game.categories', 'game.platform', 'game.developers', 'game.publishers', 'game.communityRating'])
            ->orderBy('interacted_at', 'desc')
            ->get()
            ->pluck('game');
    }

    /**
     * Gets user recommendation statistics
     *
     * @param User $user User
     * @return array Array with detailed statistics
     */
    public function getUserStats(User $user): array
    {
        $profile = $user->profile;

        return [
            'level' => $profile?->level ?? 1,
            'experience_points' => $profile?->experience_points ?? 0,
            'total_likes' => $profile?->total_likes ?? 0,
            'total_dislikes' => $profile?->total_dislikes ?? 0,
            'total_favorites' => $profile?->total_favorites ?? 0,
            'total_views' => $profile?->total_views ?? 0,
            'interactions_count' => $user->gameInteractions()->count(),
            'favorite_genres' => $user->preferredGenres()->get(),
            'favorite_categories' => $user->preferredCategories()->get(),
        ];
    }
}
