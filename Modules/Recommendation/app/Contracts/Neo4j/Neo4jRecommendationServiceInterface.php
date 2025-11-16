<?php

namespace Modules\Recommendation\Contracts\Neo4j;

use Modules\User\Models\User;
use Illuminate\Support\Collection;

interface Neo4jRecommendationServiceInterface
{
    public function getRecommendations(User $user, int $limit = 10): Collection;
    
    public function getSimilarGames(int $gameId, int $limit = 5): Collection;
    
    public function getUserRecommendationScore(int $userId, int $gameId): float;
}

