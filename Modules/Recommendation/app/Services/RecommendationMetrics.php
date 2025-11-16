<?php

namespace Modules\Recommendation\Services;

use Illuminate\Support\Facades\Log;
use Modules\User\Models\User;
use Modules\Game\Models\Game;

class RecommendationMetrics
{
    /**
     * Records metrics when recommendations are generated
     *
     * @param User $user
     * @param int $count
     * @param float $executionTime Execution time in seconds
     * @return void
     */
    public static function recordRecommendationGenerated(
        User $user,
        int $count,
        float $executionTime
    ): void {
        Log::info('recommendation.generated', [
            'user_id' => $user->id,
            'count' => $count,
            'execution_time_ms' => round($executionTime * 1000, 2),
        ]);
    }
    
    /**
     * Records metrics when a score is calculated
     *
     * @param User $user
     * @param Game $game
     * @param float $score
     * @param array $breakdown
     * @return void
     */
    public static function recordScoreCalculation(
        User $user,
        Game $game,
        float $score,
        array $breakdown
    ): void {
        Log::debug('recommendation.score_calculated', [
            'user_id' => $user->id,
            'game_id' => $game->id,
            'score' => $score,
            'breakdown' => $breakdown,
        ]);
    }
    
    /**
     * Records user interaction metrics
     *
     * @param User $user
     * @param Game $game
     * @param string $type Interaction type (like, dislike, favorite, etc.)
     * @return void
     */
    public static function recordInteraction(
        User $user,
        Game $game,
        string $type
    ): void {
        Log::info('recommendation.interaction_recorded', [
            'user_id' => $user->id,
            'game_id' => $game->id,
            'type' => $type,
        ]);
    }
}

