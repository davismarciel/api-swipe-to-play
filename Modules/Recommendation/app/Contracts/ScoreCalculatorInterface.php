<?php

namespace Modules\Recommendation\Contracts;

use Modules\Game\Models\Game;
use Modules\User\Models\User;

/**
 * Interface para o calculador de scores de recomendação
 */
interface ScoreCalculatorInterface
{
    /**
     * Calcula o score total de recomendação de um jogo para um usuário
     */
    public function calculateScore(User $user, Game $game): float;
}

