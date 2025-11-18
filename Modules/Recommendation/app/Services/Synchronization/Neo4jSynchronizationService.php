<?php

namespace Modules\Recommendation\Services\Synchronization;

use Modules\Recommendation\Contracts\Neo4j\Neo4jConnectionInterface;
use Modules\Game\Models\Game;
use Modules\User\Models\User;
use Modules\Recommendation\Models\GameInteraction;
use Illuminate\Support\Facades\Log;

class Neo4jSynchronizationService
{
    public function __construct(
        private Neo4jConnectionInterface $connection
    ) {}
    
    public function syncUser(User $user): void
    {
        $cypher = "
            MERGE (u:User {id: \$userId})
            SET u.name = \$name,
                u.email = \$email,
                u.created_at = \$createdAt
        ";
        
        try {
            $this->connection->executeWriteQuery($cypher, [
                'userId' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'createdAt' => $user->created_at?->toIso8601String()
            ]);
            
            $this->syncUserPreferences($user);
        } catch (\Exception $e) {
            Log::error('Failed to sync user to Neo4j', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Sincroniza apenas as preferências coletadas no onboarding:
     * - Plataformas (prefer_windows, prefer_mac, prefer_linux)
     * - Gêneros preferidos (HAS_PREFERRED_GENRE com weight)
     * - 5 tolerâncias de monetização (tolerance_microtransactions, tolerance_dlc, tolerance_loot_boxes, tolerance_pay_to_win, tolerance_battle_pass)
     */
    public function syncUserPreferences(User $user): void
    {
        try {
            $user->load([
                'preferences',
                'monetizationPreferences',
                'preferredGenres' => function ($query) {
                    $query->withPivot('preference_weight');
                }
            ]);
            
            $this->syncUserPlatformAndMonetizationPreferences($user);
            $this->syncUserPreferredGenres($user);
        } catch (\Exception $e) {
            Log::error('Failed to sync user preferences to Neo4j', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    private function syncUserPlatformAndMonetizationPreferences(User $user): void
    {
        $preferences = $user->preferences;
        $monetization = $user->monetizationPreferences;
        
        $cypher = "
            MATCH (u:User {id: \$userId})
            SET u.prefer_windows = \$preferWindows,
                u.prefer_mac = \$preferMac,
                u.prefer_linux = \$preferLinux,
                u.tolerance_microtransactions = \$toleranceMicrotransactions,
                u.tolerance_dlc = \$toleranceDlc,
                u.tolerance_loot_boxes = \$toleranceLootBoxes,
                u.tolerance_pay_to_win = \$tolerancePayToWin,
                u.tolerance_battle_pass = \$toleranceBattlePass
        ";
        
        try {
            $this->connection->executeWriteQuery($cypher, [
                'userId' => $user->id,
                'preferWindows' => $preferences?->prefer_windows ?? false,
                'preferMac' => $preferences?->prefer_mac ?? false,
                'preferLinux' => $preferences?->prefer_linux ?? false,
                'toleranceMicrotransactions' => $monetization?->tolerance_microtransactions ?? 5,
                'toleranceDlc' => $monetization?->tolerance_dlc ?? 5,
                'toleranceLootBoxes' => $monetization?->tolerance_loot_boxes ?? 5,
                'tolerancePayToWin' => $monetization?->tolerance_pay_to_win ?? 5,
                'toleranceBattlePass' => $monetization?->tolerance_battle_pass ?? 5,
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to sync platform and monetization preferences to Neo4j', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    private function syncUserPreferredGenres(User $user): void
    {
        $cypher = "
            MATCH (u:User {id: \$userId})-[r:HAS_PREFERRED_GENRE]->(g:Genre)
            DELETE r
        ";
        
        try {
            $this->connection->executeWriteQuery($cypher, [
                'userId' => $user->id
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to remove old genre preferences from Neo4j', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
        
        foreach ($user->preferredGenres as $genre) {
            $weight = $genre->pivot->preference_weight ?? 5;
            
            $cypher = "
                MATCH (u:User {id: \$userId})
                MERGE (genre:Genre {id: \$genreId})
                SET genre.name = \$genreName
                MERGE (u)-[r:HAS_PREFERRED_GENRE]->(genre)
                SET r.weight = \$weight
            ";
            
            try {
                $this->connection->executeWriteQuery($cypher, [
                    'userId' => $user->id,
                    'genreId' => $genre->id,
                    'genreName' => $genre->name,
                    'weight' => $weight
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to sync genre preference to Neo4j', [
                    'user_id' => $user->id,
                    'genre_id' => $genre->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
    
    public function syncGame(Game $game): void
    {
        $cypher = "
            MERGE (g:Game {id: \$gameId})
            SET g.name = \$name,
                g.slug = \$slug,
                g.is_free = \$isFree,
                g.required_age = \$requiredAge
        ";
        
        try {
            $this->connection->executeWriteQuery($cypher, [
                'gameId' => $game->id,
                'name' => $game->name,
                'slug' => $game->slug,
                'isFree' => $game->is_free,
                'requiredAge' => $game->required_age
            ]);
            
            $this->syncGameGenres($game);
            $this->syncGameCategories($game);
            $this->syncGameSimilarities($game);
        } catch (\Exception $e) {
            Log::error('Failed to sync game to Neo4j', [
                'game_id' => $game->id,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    public function syncGameInteraction(GameInteraction $interaction): void
    {
        $cypher = "
            MATCH (u:User {id: \$userId})
            MATCH (g:Game {id: \$gameId})
            MERGE (u)-[r:INTERACTED {type: \$type}]->(g)
            SET r.interacted_at = \$interactedAt,
                r.score = \$score
        ";
        
        try {
            $this->connection->executeWriteQuery($cypher, [
                'userId' => $interaction->user_id,
                'gameId' => $interaction->game_id,
                'type' => strtoupper($interaction->type),
                'interactedAt' => $interaction->interacted_at?->toIso8601String(),
                'score' => $interaction->interaction_score
            ]);
            
            if (in_array($interaction->type, ['like', 'favorite'])) {
                $this->createLikedRelationship($interaction);
            } elseif ($interaction->type === 'dislike') {
                $this->createDislikedRelationship($interaction);
            }
            
            Log::info('Game interaction synced to Neo4j', [
                'interaction_id' => $interaction->id,
                'user_id' => $interaction->user_id,
                'game_id' => $interaction->game_id,
                'type' => $interaction->type,
                'score' => $interaction->interaction_score
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to sync game interaction to Neo4j', [
                'interaction_id' => $interaction->id,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    private function createLikedRelationship(GameInteraction $interaction): void
    {
        $relationshipType = $interaction->type === 'favorite' ? 'FAVORITED' : 'LIKED';
        
        $cypher = "
            MATCH (u:User {id: \$userId})
            MATCH (g:Game {id: \$gameId})
            MERGE (u)-[r:$relationshipType]->(g)
            SET r.created_at = \$createdAt
        ";
        
        $this->connection->executeWriteQuery($cypher, [
            'userId' => $interaction->user_id,
            'gameId' => $interaction->game_id,
            'createdAt' => $interaction->interacted_at?->toIso8601String()
        ]);
    }
    
    private function createDislikedRelationship(GameInteraction $interaction): void
    {
        $cypher = "
            MATCH (u:User {id: \$userId})
            MATCH (g:Game {id: \$gameId})
            MERGE (u)-[r:DISLIKED]->(g)
            SET r.created_at = \$createdAt
        ";
        
        $this->connection->executeWriteQuery($cypher, [
            'userId' => $interaction->user_id,
            'gameId' => $interaction->game_id,
            'createdAt' => $interaction->interacted_at?->toIso8601String()
        ]);
    }
    
    private function syncGameGenres(Game $game): void
    {
        $genres = $game->genres;
        
        foreach ($genres as $genre) {
            $cypher = "
                MATCH (g:Game {id: \$gameId})
                MERGE (genre:Genre {id: \$genreId})
                SET genre.name = \$genreName
                MERGE (g)-[:HAS_GENRE]->(genre)
            ";
            
            $this->connection->executeWriteQuery($cypher, [
                'gameId' => $game->id,
                'genreId' => $genre->id,
                'genreName' => $genre->name
            ]);
        }
    }
    
    private function syncGameCategories(Game $game): void
    {
        $categories = $game->categories;
        
        foreach ($categories as $category) {
            $cypher = "
                MATCH (g:Game {id: \$gameId})
                MERGE (cat:Category {id: \$categoryId})
                SET cat.name = \$categoryName
                MERGE (g)-[:HAS_CATEGORY]->(cat)
            ";
            
            $this->connection->executeWriteQuery($cypher, [
                'gameId' => $game->id,
                'categoryId' => $category->id,
                'categoryName' => $category->name
            ]);
        }
    }
    
    private function syncGameSimilarities(Game $game): void
    {
        $similarGames = $game->genres()
            ->with('games')
            ->get()
            ->flatMap->games
            ->where('id', '!=', $game->id)
            ->unique('id')
            ->take(10);
        
        foreach ($similarGames as $similarGame) {
            $cypher = "
                MATCH (g1:Game {id: \$gameId})
                MATCH (g2:Game {id: \$similarGameId})
                MERGE (g1)-[:SIMILAR_TO]->(g2)
            ";
            
            $this->connection->executeWriteQuery($cypher, [
                'gameId' => $game->id,
                'similarGameId' => $similarGame->id
            ]);
        }
    }
    
    public function createConstraints(): void
    {
        if (!config('neo4j.constraints.enabled', true)) {
            return;
        }
        
        $constraints = [
            'CREATE CONSTRAINT user_id_unique IF NOT EXISTS FOR (u:User) REQUIRE u.id IS UNIQUE',
            'CREATE CONSTRAINT game_id_unique IF NOT EXISTS FOR (g:Game) REQUIRE g.id IS UNIQUE',
            'CREATE CONSTRAINT genre_id_unique IF NOT EXISTS FOR (gen:Genre) REQUIRE gen.id IS UNIQUE',
            'CREATE CONSTRAINT category_id_unique IF NOT EXISTS FOR (cat:Category) REQUIRE cat.id IS UNIQUE',
        ];
        
        foreach ($constraints as $constraint) {
            try {
                $this->connection->executeWriteQuery($constraint);
            } catch (\Exception $e) {
                Log::warning('Failed to create Neo4j constraint', [
                    'constraint' => $constraint,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
    
    public function createIndexes(): void
    {
        if (!config('neo4j.indexes.enabled', true)) {
            return;
        }
        
        $indexes = [
            'CREATE INDEX user_id_index IF NOT EXISTS FOR (u:User) ON (u.id)',
            'CREATE INDEX game_id_index IF NOT EXISTS FOR (g:Game) ON (g.id)',
            'CREATE INDEX game_name_index IF NOT EXISTS FOR (g:Game) ON (g.name)',
        ];
        
        foreach ($indexes as $index) {
            try {
                $this->connection->executeWriteQuery($index);
            } catch (\Exception $e) {
                Log::warning('Failed to create Neo4j index', [
                    'index' => $index,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
}

