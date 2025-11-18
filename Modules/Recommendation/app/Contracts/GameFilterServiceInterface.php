<?php

namespace Modules\Recommendation\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Modules\User\Models\User;

/**
 * Interface for the game filter service
 */
interface GameFilterServiceInterface
{
    /**
     * Filters games based on the user's preferences
     */
    public function filterGames(User $user, ?Builder $query = null): Builder;

    /**
     * Applies boost by preferred genres
     */
    public function applyGenreBoost(User $user, Builder $query): Builder;

    /**
     * Applies boost by preferred categories
     */
    public function applyCategoryBoost(User $user, Builder $query): Builder;
}

