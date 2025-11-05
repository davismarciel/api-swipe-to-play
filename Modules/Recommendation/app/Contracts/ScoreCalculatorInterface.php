<?php

namespace Modules\Recommendation\Contracts;

use Modules\Game\Models\Game;
use Modules\User\Models\User;
use Modules\Recommendation\Models\UserBehaviorProfile;

/**
 * Interface para o calculador de scores de recomendação
 */
interface ScoreCalculatorInterface
{
    /**
     * Calcula o score total de recomendação de um jogo para um usuário
     */
    public function calculateScore(User $user, Game $game): float;

    /**
     * Calcula o score usando perfil comportamental do usuário
     *
     * @param User $user Usuário para quem calcular o score
     * @param Game $game Jogo a ser avaliado
     * @param UserBehaviorProfile $profile Perfil comportamental do usuário
     * @return float Score entre 0.0 e 100.0
     */
    public function calculateScoreWithProfile(User $user, Game $game, UserBehaviorProfile $profile): float;

    /**
     * Gera explicação da recomendação baseada no score
     *
     * @param float $score Score calculado
     * @param Game $game Jogo avaliado
     * @param UserBehaviorProfile $profile Perfil comportamental do usuário
     * @return array Array com explicação detalhada
     */
    public function generateExplanation(float $score, Game $game, UserBehaviorProfile $profile): array;
}

