<?php

namespace Modules\Recommendation\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Modules\User\Models\User;

/**
 * Interface para o serviço de filtros de jogos
 */
interface GameFilterServiceInterface
{
    /**
     * Filtra jogos baseado nas preferências do usuário
     */
    public function filterGames(User $user, ?Builder $query = null): Builder;

    /**
     * Aplica boost por gêneros preferidos
     */
    public function applyGenreBoost(User $user, Builder $query): Builder;

    /**
     * Aplica boost por categorias preferidas
     */
    public function applyCategoryBoost(User $user, Builder $query): Builder;
}

