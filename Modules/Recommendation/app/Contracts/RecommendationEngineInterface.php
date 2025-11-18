<?php

namespace Modules\Recommendation\Contracts;

use Illuminate\Support\Collection;
use Modules\Game\Models\Game;
use Modules\User\Models\User;
use Modules\Recommendation\Models\GameInteraction;

/**
 * Interface for the recommendation engine
 */
interface RecommendationEngineInterface
{
    /**
     * Gets the next recommended games for the user
     */
    public function getRecommendations(User $user, int $limit = 10): Collection;

    /**
     * Registers a user interaction with a game
     */
    public function recordInteraction(User $user, Game $game, string $type): GameInteraction;

    /**
     * Gets similar games to a specific game
     */
    public function getSimilarGames(Game $game, int $limit = 5): Collection;

    /**
     * Gets the user interaction history
     */
    public function getUserInteractionHistory(User $user, int $limit = 20): Collection;

    /**
     * Gets the user's favorite games
     */
    public function getUserFavorites(User $user): Collection;

    /**
     * Gets the user's recommendation statistics
     */
    public function getUserStats(User $user): array;
}

