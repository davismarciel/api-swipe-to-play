<?php

namespace Modules\Recommendation\Services;

use Illuminate\Support\Facades\Log;
use Modules\Game\Models\Game;
use Modules\User\Models\User;
use Modules\Recommendation\Models\GameInteraction;

class Neo4jGraphSyncService
{
    public function __construct(
        private Neo4jService $neo4j
    ) {}

    public function syncUser(User $user): void
    {
        $cypher = '
            MERGE (u:User {id: $userId})
            SET u.name = $name,
                u.email = $email,
                u.updated_at = datetime()
            RETURN u
        ';

        $this->neo4j->executeQuery($cypher, [
            'userId' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->email ?? '',
        ]);
    }

    public function syncGame(Game $game): void
    {
        $cypher = '
            MERGE (g:Game {id: $gameId})
            SET g.name = $name,
                g.steam_id = $steamId,
                g.is_free = $isFree,
                g.positive_ratio = $positiveRatio,
                g.total_reviews = $totalReviews,
                g.updated_at = datetime()
            RETURN g
        ';

        $this->neo4j->executeQuery($cypher, [
            'gameId' => (string) $game->id,
            'name' => $game->name,
            'steamId' => $game->steam_id ?? '',
            'isFree' => $game->is_free ?? false,
            'positiveRatio' => $game->positive_ratio ?? 0.5,
            'totalReviews' => $game->total_reviews ?? 0,
        ]);

        $this->syncGameRelationships($game);
    }

    private function syncGameRelationships(Game $game): void
    {
        $game->load(['genres', 'categories', 'developers', 'publishers']);

        $gameId = (string) $game->id;

        foreach ($game->genres as $genre) {
            $this->syncGenre($genre);
            $this->createRelationship('Game', $gameId, 'HAS_GENRE', 'Genre', (string) $genre->id);
        }

        foreach ($game->categories as $category) {
            $this->syncCategory($category);
            $this->createRelationship('Game', $gameId, 'HAS_CATEGORY', 'Category', (string) $category->id);
        }

        foreach ($game->developers as $developer) {
            $this->syncDeveloper($developer);
            $this->createRelationship('Game', $gameId, 'DEVELOPED_BY', 'Developer', (string) $developer->id);
        }

        foreach ($game->publishers as $publisher) {
            $this->syncPublisher($publisher);
            $this->createRelationship('Game', $gameId, 'PUBLISHED_BY', 'Publisher', (string) $publisher->id);
        }
    }

    public function syncGenre($genre): void
    {
        $cypher = '
            MERGE (g:Genre {id: $genreId})
            SET g.name = $name,
                g.slug = $slug
            RETURN g
        ';

        $this->neo4j->executeQuery($cypher, [
            'genreId' => (string) $genre->id,
            'name' => $genre->name,
            'slug' => $genre->slug ?? '',
        ]);
    }

    public function syncCategory($category): void
    {
        $cypher = '
            MERGE (c:Category {id: $categoryId})
            SET c.name = $name,
                c.slug = $slug
            RETURN c
        ';

        $this->neo4j->executeQuery($cypher, [
            'categoryId' => (string) $category->id,
            'name' => $category->name,
            'slug' => $category->slug ?? '',
        ]);
    }

    public function syncDeveloper($developer): void
    {
        $cypher = '
            MERGE (d:Developer {id: $developerId})
            SET d.name = $name
            RETURN d
        ';

        $this->neo4j->executeQuery($cypher, [
            'developerId' => (string) $developer->id,
            'name' => $developer->name,
        ]);
    }

    public function syncPublisher($publisher): void
    {
        $cypher = '
            MERGE (p:Publisher {id: $publisherId})
            SET p.name = $name
            RETURN p
        ';

        $this->neo4j->executeQuery($cypher, [
            'publisherId' => (string) $publisher->id,
            'name' => $publisher->name,
        ]);
    }

    public function syncInteraction(GameInteraction $interaction): void
    {
        $cypher = '
            MATCH (u:User {id: $userId})
            MATCH (g:Game {id: $gameId})
            MERGE (u)-[r:INTERACTED_WITH]->(g)
            SET r.type = $type,
                r.score = $score,
                r.interacted_at = $interactedAt
            RETURN r
        ';

        $this->neo4j->executeQuery($cypher, [
            'userId' => (string) $interaction->user_id,
            'gameId' => (string) $interaction->game_id,
            'type' => $interaction->type,
            'score' => $interaction->interaction_score,
            'interactedAt' => $interaction->interacted_at->toIso8601String(),
        ]);
    }

    public function syncUserPreferences(User $user): void
    {
        $user->load(['preferredGenres', 'preferredCategories']);

        $userId = (string) $user->id;

        foreach ($user->preferredGenres as $genre) {
            $this->syncGenre($genre);
            $this->createRelationship('User', $userId, 'PREFERS_GENRE', 'Genre', (string) $genre->id, [
                'weight' => $genre->pivot->preference_weight ?? 1.0,
            ]);
        }

        foreach ($user->preferredCategories as $category) {
            $this->syncCategory($category);
            $this->createRelationship('User', $userId, 'PREFERS_CATEGORY', 'Category', (string) $category->id, [
                'weight' => $category->pivot->preference_weight ?? 1.0,
            ]);
        }
    }

    private function createRelationship(
        string $fromLabel,
        string $fromId,
        string $relationshipType,
        string $toLabel,
        string $toId,
        array $properties = []
    ): void {
        $propsString = '';
        if (!empty($properties)) {
            $props = [];
            foreach ($properties as $key => $value) {
                $props[] = "$key: \${$key}";
            }
            $propsString = '{' . implode(', ', $props) . '}';
        }

        $cypher = "
            MATCH (from:{$fromLabel} {id: \$fromId})
            MATCH (to:{$toLabel} {id: \$toId})
            MERGE (from)-[r:{$relationshipType}]->(to)
            " . (!empty($propsString) ? "SET r = {$propsString}" : '') . "
            RETURN r
        ";

        $params = array_merge([
            'fromId' => $fromId,
            'toId' => $toId,
        ], $properties);

        $this->neo4j->executeQuery($cypher, $params);
    }

    public function bulkSyncInteractions(int $limit = 1000): void
    {
        $interactions = GameInteraction::with(['user', 'game'])
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($interactions as $interaction) {
            $this->syncUser($interaction->user);
            $this->syncGame($interaction->game);
            $this->syncInteraction($interaction);
        }

        Log::info('Bulk sync completed', [
            'count' => $interactions->count(),
        ]);
    }
}

