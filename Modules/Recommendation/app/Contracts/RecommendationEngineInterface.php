<?php

namespace Modules\Recommendation\Contracts;

use Illuminate\Support\Collection;
use Modules\Game\Models\Game;
use Modules\User\Models\User;
use Modules\Recommendation\Models\GameInteraction;

/**
 * Interface para o motor de recomendação
 */
interface RecommendationEngineInterface
{
    /**
     * Obtém os próximos jogos recomendados para o usuário
     */
    public function getRecommendations(User $user, int $limit = 10): Collection;

    /**
     * Registra uma interação do usuário com um jogo
     */
    public function recordInteraction(User $user, Game $game, string $type): GameInteraction;

    /**
     * Obtém jogos similares a um jogo específico
     */
    public function getSimilarGames(Game $game, int $limit = 5): Collection;

    /**
     * Obtém histórico de interações do usuário
     */
    public function getUserInteractionHistory(User $user, int $limit = 20): Collection;

    /**
     * Obtém jogos favoritos do usuário
     */
    public function getUserFavorites(User $user): Collection;

    /**
     * Obtém estatísticas de recomendação do usuário
     */
    public function getUserStats(User $user): array;
}

