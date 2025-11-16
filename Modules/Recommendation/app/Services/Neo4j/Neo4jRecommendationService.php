<?php

namespace Modules\Recommendation\Services\Neo4j;

use Modules\Recommendation\Contracts\Neo4j\Neo4jConnectionInterface;
use Modules\Recommendation\Contracts\Neo4j\Neo4jRecommendationServiceInterface;
use Modules\User\Models\User;
use Modules\Game\Models\Game;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class Neo4jRecommendationService implements Neo4jRecommendationServiceInterface
{
    public function __construct(
        private Neo4jConnectionInterface $connection
    ) {}
    
    public function getRecommendations(User $user, int $limit = 10): Collection
    {
        $cypher = "
            MATCH (u:User {id: \$userId})
            MATCH (u)-[:LIKED|FAVORITED]->(g1:Game)<-[:SIMILAR_TO]-(g2:Game)
            WHERE NOT (u)-[:LIKED|DISLIKED|SKIPPED]->(g2)
            WITH g2, count(DISTINCT g1) as similarityScore
            ORDER BY similarityScore DESC
            LIMIT \$limit
            RETURN g2.id as game_id, similarityScore as score
        ";
        
        try {
            $results = $this->connection->executeReadQuery($cypher, [
                'userId' => $user->id,
                'limit' => $limit
            ]);
            
            $gameIds = collect($results)->pluck('game_id')->toArray();
            
            if (empty($gameIds)) {
                return collect();
            }
            
            return Game::whereIn('id', $gameIds)
                ->with(['genres', 'categories', 'platform', 'developers', 'publishers', 'communityRating'])
                ->get()
                ->map(function ($game) use ($results) {
                    $result = collect($results)->firstWhere('game_id', $game->id);
                    $game->recommendation_score = $result['score'] ?? 0;
                    return $game;
                })
                ->sortByDesc('recommendation_score')
                ->values();
                
        } catch (\Exception $e) {
            Log::error('Failed to get Neo4j recommendations', [
                'user_id' => $user->id,
                'limit' => $limit,
                'error' => $e->getMessage()
            ]);
            
            return collect();
        }
    }
    
    public function getSimilarGames(int $gameId, int $limit = 5): Collection
    {
        $cypher = "
            MATCH (g1:Game {id: \$gameId})-[:SIMILAR_TO]->(g2:Game)
            RETURN g2.id as game_id
            LIMIT \$limit
        ";
        
        try {
            $results = $this->connection->executeReadQuery($cypher, [
                'gameId' => $gameId,
                'limit' => $limit
            ]);
            
            $gameIds = collect($results)->pluck('game_id')->toArray();
            
            if (empty($gameIds)) {
                return collect();
            }
            
            return Game::whereIn('id', $gameIds)
                ->with(['genres', 'categories', 'platform', 'communityRating'])
                ->get();
                
        } catch (\Exception $e) {
            Log::error('Failed to get similar games from Neo4j', [
                'game_id' => $gameId,
                'limit' => $limit,
                'error' => $e->getMessage()
            ]);
            
            return collect();
        }
    }
    
    public function getUserRecommendationScore(int $userId, int $gameId): float
    {
        $cypher = "
            MATCH (u:User {id: \$userId})
            MATCH (u)-[:LIKED|FAVORITED]->(g1:Game)<-[:SIMILAR_TO]-(g2:Game {id: \$gameId})
            WITH count(DISTINCT g1) as similarityScore
            RETURN similarityScore as score
        ";
        
        try {
            $results = $this->connection->executeReadQuery($cypher, [
                'userId' => $userId,
                'gameId' => $gameId
            ]);
            
            if (empty($results)) {
                return 0.0;
            }
            
            $score = $results[0]['score'] ?? 0;
            return min(100.0, max(0.0, (float) $score * 10));
            
        } catch (\Exception $e) {
            Log::error('Failed to get recommendation score from Neo4j', [
                'user_id' => $userId,
                'game_id' => $gameId,
                'error' => $e->getMessage()
            ]);
            
            return 0.0;
        }
    }
}

