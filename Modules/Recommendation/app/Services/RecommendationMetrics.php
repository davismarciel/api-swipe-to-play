<?php

namespace Modules\Recommendation\Services;

use Illuminate\Support\Facades\Log;
use Modules\User\Models\User;
use Modules\Game\Models\Game;
use Modules\Recommendation\Models\UserBehaviorProfile;

/**
 * Classe para registro de métricas e monitoramento do sistema de recomendação
 */
class RecommendationMetrics
{
    /**
     * Registra métricas quando recomendações são geradas
     *
     * @param User $user
     * @param int $count
     * @param float $executionTime Tempo de execução em segundos
     * @param UserBehaviorProfile|null $profile
     * @return void
     */
    public static function recordRecommendationGenerated(
        User $user,
        int $count,
        float $executionTime,
        ?UserBehaviorProfile $profile = null
    ): void {
        Log::info('recommendation.generated', [
            'user_id' => $user->id,
            'count' => $count,
            'execution_time_ms' => round($executionTime * 1000, 2),
            'has_profile' => $profile !== null,
            'profile_age_days' => $profile?->last_analyzed_at?->diffInDays(now()),
            'total_interactions' => $profile?->total_interactions ?? 0,
        ]);
    }
    
    /**
     * Registra métricas quando um score é calculado
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
     * Registra métricas quando um perfil é atualizado
     *
     * @param User $user
     * @param int $interactionsAnalyzed
     * @param float $executionTime Tempo de execução em segundos
     * @return void
     */
    public static function recordProfileUpdate(
        User $user,
        int $interactionsAnalyzed,
        float $executionTime
    ): void {
        Log::info('recommendation.profile_updated', [
            'user_id' => $user->id,
            'interactions_analyzed' => $interactionsAnalyzed,
            'execution_time_ms' => round($executionTime * 1000, 2),
        ]);
    }
    
    /**
     * Registra métricas de interação do usuário
     *
     * @param User $user
     * @param Game $game
     * @param string $type Tipo de interação (like, dislike, favorite, etc.)
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

