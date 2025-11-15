<?php

namespace Modules\Recommendation\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Game\Models\Game;
use Modules\User\Models\User;

class Neo4jRecommendationService
{
    private const CACHE_TTL = 3600; // 1 hora
    private const SIMILARITY_THRESHOLD = 0.15;
    private const MIN_COMMON_GAMES = 2;

    public function __construct(
        private Neo4jService $neo4j
    ) {}

    /**
     * Obtém recomendações usando múltiplas estratégias de grafo combinadas
     * Esta é a função principal que orquestra todas as estratégias
     */
    public function getHybridGraphRecommendations(User $user, int $limit = 10): Collection
    {
        $cacheKey = "neo4j:hybrid:{$user->id}:{$limit}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user, $limit) {
            $startTime = microtime(true);
            
            // Executa múltiplas estratégias em paralelo
            $strategies = [
                'collaborative' => $this->getCollaborativeRecommendations($user, $limit),
                'path_based' => $this->getPathBasedRecommendations($user, $limit),
                'developer_based' => $this->getDeveloperBasedRecommendations($user, $limit),
                'community_based' => $this->getCommunityBasedRecommendations($user, $limit),
                'deep_walk' => $this->getDeepWalkRecommendations($user, $limit),
            ];
            
            // Combina resultados com pesos adaptativos
            $combined = $this->combineStrategies($strategies, $user, $limit);
            
            $executionTime = microtime(true) - $startTime;
            
            Log::info('Neo4j hybrid recommendations generated', [
                'user_id' => $user->id,
                'strategies_used' => array_keys(array_filter($strategies, fn($s) => $s->isNotEmpty())),
                'total_candidates' => $combined->count(),
                'execution_time_ms' => round($executionTime * 1000, 2),
            ]);
            
            return $combined;
        });
    }

    /**
     * Filtragem Colaborativa Avançada com Jaccard Similarity
     * Encontra usuários similares e recomenda jogos que eles gostaram
     */
    public function getCollaborativeRecommendations(User $user, int $limit = 10): Collection
    {
        $cypher = '
            // Encontra usuários similares usando Jaccard Similarity
            MATCH (u:User {id: $userId})-[r1:INTERACTED_WITH]->(g1:Game)
            WHERE r1.score > 0
            WITH u, collect(DISTINCT g1) as userGames
            
            MATCH (other:User)-[r2:INTERACTED_WITH]->(g2:Game)
            WHERE other.id <> u.id 
            AND r2.score > 0 
            AND g2 IN userGames
            
            WITH u, other, userGames,
                 collect(DISTINCT g2) as commonGames,
                 count(DISTINCT g2) as commonCount
            
            WHERE commonCount >= $minCommonGames
            
            // Calcula Jaccard Similarity
            MATCH (other)-[:INTERACTED_WITH]->(allOtherGames:Game)
            WITH u, other, userGames, commonGames, commonCount,
                 collect(DISTINCT allOtherGames) as otherGames
            
            WITH u, other, commonCount,
                 size(userGames) as userSize,
                 size(otherGames) as otherSize,
                 (commonCount * 1.0) / (size(userGames) + size(otherGames) - commonCount) as jaccardSim
            
            WHERE jaccardSim >= $similarityThreshold
            
            // Busca recomendações dos usuários similares
            MATCH (other)-[r3:INTERACTED_WITH]->(recommended:Game)
            WHERE r3.score > 0
            AND NOT EXISTS((u)-[:INTERACTED_WITH]->(recommended))
            AND recommended.is_active = true
            
            WITH recommended, 
                 collect(DISTINCT other) as recommenders,
                 sum(jaccardSim * r3.score) as weightedScore,
                 avg(r3.score) as avgScore,
                 count(DISTINCT other) as recommenderCount,
                 avg(jaccardSim) as avgSimilarity
            
            // Pontuação final considerando múltiplos fatores
            WITH recommended,
                 weightedScore,
                 recommenderCount,
                 avgSimilarity,
                 recommended.positive_ratio as gameRating,
                 recommended.total_reviews as reviewCount,
                 (weightedScore * 0.4 + 
                  recommenderCount * 0.3 + 
                  avgSimilarity * 0.2 + 
                  gameRating * 0.1) as finalScore
            
            ORDER BY finalScore DESC, recommenderCount DESC, gameRating DESC
            LIMIT $limit
            
            RETURN recommended.id as game_id, 
                   weightedScore,
                   recommenderCount,
                   avgSimilarity,
                   gameRating,
                   reviewCount,
                   finalScore
        ';

        try {
            $results = $this->neo4j->executeQuery($cypher, [
                'userId' => (string) $user->id,
                'limit' => $limit * 2,
                'minCommonGames' => self::MIN_COMMON_GAMES,
                'similarityThreshold' => self::SIMILARITY_THRESHOLD,
            ]);

            return $this->hydrateGames($results, 'collaborative');
        } catch (\Exception $e) {
            Log::error('Neo4j collaborative recommendation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return collect();
        }
    }

    public function getSimilarUsers(User $user, int $limit = 10): array
    {
        $cypher = '
            MATCH (u:User {id: $userId})-[:INTERACTED_WITH]->(g:Game)
            WITH u, collect(g.id) as userGames
            MATCH (other:User)-[:INTERACTED_WITH]->(g2:Game)
            WHERE other.id <> u.id AND g2.id IN userGames
            WITH other, count(DISTINCT g2) as commonGames,
                 size([(u)-[:INTERACTED_WITH]->(g3:Game) | g3.id]) as userGameCount,
                 size([(other)-[:INTERACTED_WITH]->(g4:Game) | g4.id]) as otherGameCount
            WITH other, commonGames, userGameCount, otherGameCount,
                 (commonGames * 1.0 / (userGameCount + otherGameCount - commonGames)) as jaccardSimilarity
            WHERE jaccardSimilarity > 0.1
            ORDER BY jaccardSimilarity DESC, commonGames DESC
            LIMIT $limit
            RETURN other.id as user_id, 
                   other.name as name,
                   jaccardSimilarity,
                   commonGames
        ';

        try {
            $results = $this->neo4j->executeQuery($cypher, [
                'userId' => (string) $user->id,
                'limit' => $limit,
            ]);

            return array_map(function ($result) {
                return [
                    'user_id' => (int) $result['user_id'],
                    'name' => $result['name'],
                    'similarity' => round($result['jaccardSimilarity'], 3),
                    'common_games' => $result['commonGames'],
                ];
            }, $results);
        } catch (\Exception $e) {
            Log::error('Neo4j similar users query failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Recomendações baseadas em caminhos no grafo
     * Descobre conexões através de múltiplos saltos (gêneros, categorias, desenvolvedores)
     */
    public function getPathBasedRecommendations(User $user, int $limit = 10): Collection
    {
        $cypher = '
            // Encontra jogos através de caminhos de 2-3 saltos
            MATCH (u:User {id: $userId})-[r:INTERACTED_WITH]->(liked:Game)
            WHERE r.score > 0
            
            // Caminho 1: Usuário -> Jogo Gostado -> Gênero -> Novo Jogo
            OPTIONAL MATCH (liked)-[:HAS_GENRE]->(genre:Genre)<-[:HAS_GENRE]-(g1:Game)
            WHERE NOT EXISTS((u)-[:INTERACTED_WITH]->(g1))
            AND g1.is_active = true
            
            // Caminho 2: Usuário -> Jogo Gostado -> Desenvolvedor -> Novo Jogo
            OPTIONAL MATCH (liked)-[:DEVELOPED_BY]->(dev:Developer)<-[:DEVELOPED_BY]-(g2:Game)
            WHERE NOT EXISTS((u)-[:INTERACTED_WITH]->(g2))
            AND g2.is_active = true
            
            // Caminho 3: Usuário -> Jogo Gostado -> Categoria -> Novo Jogo
            OPTIONAL MATCH (liked)-[:HAS_CATEGORY]->(cat:Category)<-[:HAS_CATEGORY]-(g3:Game)
            WHERE NOT EXISTS((u)-[:INTERACTED_WITH]->(g3))
            AND g3.is_active = true
            
            // Unifica todos os caminhos
            WITH u, liked, r.score as likedScore,
                 collect(DISTINCT g1) + collect(DISTINCT g2) + collect(DISTINCT g3) as candidates
            
            UNWIND candidates as recommended
            
            // Calcula força do caminho
            WITH recommended, 
                 count(DISTINCT liked) as connectionCount,
                 sum(likedScore) as totalLikedScore,
                 avg(likedScore) as avgLikedScore
            
            // Analisa atributos compartilhados
            MATCH (recommended)-[:HAS_GENRE]->(g:Genre)
            WITH recommended, connectionCount, totalLikedScore, avgLikedScore,
                 collect(DISTINCT g.id) as gameGenres
            
            MATCH (u:User {id: $userId})-[r2:INTERACTED_WITH]->(liked2:Game)-[:HAS_GENRE]->(g2:Genre)
            WHERE r2.score > 0 AND g2.id IN gameGenres
            
            WITH recommended, connectionCount, totalLikedScore, avgLikedScore,
                 count(DISTINCT g2) as genreOverlap,
                 recommended.positive_ratio as rating,
                 recommended.total_reviews as reviews
            
            // Score final baseado em múltiplos fatores
            WITH recommended,
                 (connectionCount * 0.35 + 
                  totalLikedScore * 0.25 + 
                  genreOverlap * 0.25 + 
                  rating * 0.15) as pathScore
            
            ORDER BY pathScore DESC, connectionCount DESC
            LIMIT $limit
            
            RETURN recommended.id as game_id,
                   connectionCount,
                   genreOverlap,
                   pathScore
        ';

        try {
            $results = $this->neo4j->executeQuery($cypher, [
                'userId' => (string) $user->id,
                'limit' => $limit,
            ]);

            return $this->hydrateGames($results, 'path_based');
        } catch (\Exception $e) {
            Log::error('Neo4j path-based recommendation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return collect();
        }
    }

    public function getDeveloperBasedRecommendations(User $user, int $limit = 10): Collection
    {
        $cypher = '
            MATCH (u:User {id: $userId})-[r:INTERACTED_WITH]->(likedGame:Game)-[:DEVELOPED_BY]->(dev:Developer)
            WHERE r.score > 0
            WITH dev, count(DISTINCT likedGame) as likedGamesCount
            WHERE likedGamesCount >= 2
            MATCH (dev)<-[:DEVELOPED_BY]-(g:Game)
            WHERE NOT EXISTS((u)-[:INTERACTED_WITH]->(g))
            AND g.positive_ratio > 0.6
            WITH g, likedGamesCount, g.positive_ratio as rating
            ORDER BY likedGamesCount DESC, rating DESC
            LIMIT $limit
            RETURN g.id as game_id, likedGamesCount, rating
        ';

        try {
            $results = $this->neo4j->executeQuery($cypher, [
                'userId' => (string) $user->id,
                'limit' => $limit,
            ]);

            $gameIds = array_map(fn($r) => (int) $r['game_id'], $results);

            if (empty($gameIds)) {
                return collect();
            }

            $games = Game::whereIn('id', $gameIds)
                ->with(['genres', 'categories', 'platform', 'developers', 'publishers', 'communityRating'])
                ->get()
                ->keyBy('id');

            $recommendations = collect();
            foreach ($results as $result) {
                $gameId = (int) $result['game_id'];
                if ($games->has($gameId)) {
                    $game = $games->get($gameId);
                    $game->recommendation_score = min(100, 70 + ($result['likedGamesCount'] * 5) + ($result['rating'] * 30));
                    $recommendations->push($game);
                }
            }

            return $recommendations;
        } catch (\Exception $e) {
            Log::error('Neo4j developer-based recommendation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return collect();
        }
    }

    /**
     * Recomendações baseadas em comunidade/clusters
     * Identifica clusters de jogos e recomenda dentro do mesmo cluster
     */
    public function getCommunityBasedRecommendations(User $user, int $limit = 10): Collection
    {
        $cypher = '
            // Identifica jogos que o usuário gostou
            MATCH (u:User {id: $userId})-[r:INTERACTED_WITH]->(liked:Game)
            WHERE r.score > 0
            WITH u, collect(DISTINCT liked) as likedGames
            
            // Encontra jogos fortemente conectados aos jogos gostados
            UNWIND likedGames as lg
            MATCH (lg)-[:HAS_GENRE]->(g:Genre)<-[:HAS_GENRE]-(candidate:Game)
            WHERE NOT candidate IN likedGames
            AND NOT EXISTS((u)-[:INTERACTED_WITH]->(candidate))
            AND candidate.is_active = true
            
            WITH u, candidate, count(DISTINCT g) as genreConnections
            
            // Adiciona conexões por desenvolvedor
            MATCH (candidate)-[:DEVELOPED_BY]->(dev:Developer)
            OPTIONAL MATCH (u)-[:INTERACTED_WITH {score: $positiveScore}]->(lg2:Game)-[:DEVELOPED_BY]->(dev)
            
            WITH candidate, genreConnections,
                 count(DISTINCT dev) as devConnections,
                 candidate.positive_ratio as rating,
                 candidate.total_reviews as popularity
            
            // Calcula densidade de conexões (community strength)
            WITH candidate,
                 (genreConnections * 0.5 + devConnections * 0.3 + rating * 0.2) as communityScore
            
            WHERE communityScore > 0.5
            ORDER BY communityScore DESC, popularity DESC
            LIMIT $limit
            
            RETURN candidate.id as game_id,
                   genreConnections,
                   devConnections,
                   communityScore
        ';

        try {
            $results = $this->neo4j->executeQuery($cypher, [
                'userId' => (string) $user->id,
                'limit' => $limit,
                'positiveScore' => 10,
            ]);

            return $this->hydrateGames($results, 'community_based');
        } catch (\Exception $e) {
            Log::error('Neo4j community-based recommendation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return collect();
        }
    }

    /**
     * Deep Walk - Explora caminhos profundos no grafo
     * Simula random walks para descobrir conexões não óbvias
     */
    public function getDeepWalkRecommendations(User $user, int $limit = 10): Collection
    {
        $cypher = '
            // Inicia random walk a partir dos jogos do usuário
            MATCH (u:User {id: $userId})-[r:INTERACTED_WITH]->(start:Game)
            WHERE r.score > 0
            
            // Caminho de 3 saltos: Game -> Genre -> Game -> Developer -> Game
            MATCH path = (start)-[:HAS_GENRE]->(g1:Genre)<-[:HAS_GENRE]-(mid:Game)
                        -[:DEVELOPED_BY]->(d:Developer)<-[:DEVELOPED_BY]-(end:Game)
            
            WHERE NOT EXISTS((u)-[:INTERACTED_WITH]->(end))
            AND end.is_active = true
            AND end.id <> start.id
            AND mid.positive_ratio > 0.6
            
            WITH end, count(DISTINCT path) as pathCount,
                 avg(mid.positive_ratio) as avgMidRating,
                 end.positive_ratio as endRating,
                 end.total_reviews as popularity
            
            // Score baseado na força dos caminhos encontrados
            WITH end,
                 (pathCount * 0.4 + avgMidRating * 0.3 + endRating * 0.3) as deepWalkScore
            
            ORDER BY deepWalkScore DESC, pathCount DESC
            LIMIT $limit
            
            RETURN end.id as game_id,
                   pathCount,
                   avgMidRating,
                   deepWalkScore
        ';

        try {
            $results = $this->neo4j->executeQuery($cypher, [
                'userId' => (string) $user->id,
                'limit' => $limit,
            ]);

            return $this->hydrateGames($results, 'deep_walk');
        } catch (\Exception $e) {
            Log::error('Neo4j deep walk recommendation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return collect();
        }
    }

    /**
     * Combina múltiplas estratégias com pesos adaptativos
     */
    private function combineStrategies(array $strategies, User $user, int $limit): Collection
    {
        $profile = $user->behaviorProfile;
        $totalInteractions = $profile?->total_interactions ?? 0;
        
        // Pesos adaptativos baseados no perfil do usuário
        $weights = $this->calculateStrategyWeights($totalInteractions);
        
        $combined = collect();
        $gameScores = [];
        
        foreach ($strategies as $strategyName => $games) {
            $weight = $weights[$strategyName] ?? 0;
            
            foreach ($games as $game) {
                $gameId = $game->id;
                $score = ($game->recommendation_score ?? 50) * $weight;
                
                if (!isset($gameScores[$gameId])) {
                    $gameScores[$gameId] = [
                        'game' => $game,
                        'total_score' => 0,
                        'strategy_count' => 0,
                        'strategies' => [],
                    ];
                }
                
                $gameScores[$gameId]['total_score'] += $score;
                $gameScores[$gameId]['strategy_count']++;
                $gameScores[$gameId]['strategies'][] = $strategyName;
            }
        }
        
        // Bonus para jogos recomendados por múltiplas estratégias
        foreach ($gameScores as $gameId => &$data) {
            if ($data['strategy_count'] > 1) {
                $data['total_score'] *= (1 + ($data['strategy_count'] - 1) * 0.15);
            }
        }
        
        // Ordena e retorna top N
        usort($gameScores, fn($a, $b) => $b['total_score'] <=> $a['total_score']);
        
        foreach (array_slice($gameScores, 0, $limit) as $data) {
            $game = $data['game'];
            $game->recommendation_score = round($data['total_score'], 2);
            $game->neo4j_metadata = [
                'strategies_used' => $data['strategies'],
                'strategy_count' => $data['strategy_count'],
            ];
            $combined->push($game);
        }
        
        return $combined;
    }

    /**
     * Calcula pesos adaptativos para cada estratégia
     */
    private function calculateStrategyWeights(int $totalInteractions): array
    {
        // Usuários novos: prioriza community e developer
        if ($totalInteractions < 10) {
            return [
                'collaborative' => 0.10,
                'path_based' => 0.20,
                'developer_based' => 0.35,
                'community_based' => 0.30,
                'deep_walk' => 0.05,
            ];
        }
        
        // Usuários intermediários: balanceado
        if ($totalInteractions < 50) {
            return [
                'collaborative' => 0.25,
                'path_based' => 0.25,
                'developer_based' => 0.20,
                'community_based' => 0.20,
                'deep_walk' => 0.10,
            ];
        }
        
        // Usuários avançados: prioriza collaborative e deep walk
        return [
            'collaborative' => 0.35,
            'path_based' => 0.20,
            'developer_based' => 0.15,
            'community_based' => 0.15,
            'deep_walk' => 0.15,
        ];
    }

    /**
     * Hidrata jogos do Neo4j com dados do PostgreSQL
     */
    private function hydrateGames(array $results, string $strategy): Collection
    {
        $gameIds = array_map(fn($r) => (int) $r['game_id'], $results);

        if (empty($gameIds)) {
            return collect();
        }

        $games = Game::whereIn('id', $gameIds)
            ->with(['genres', 'categories', 'platform', 'developers', 'publishers', 'communityRating'])
            ->get()
            ->keyBy('id');

        $recommendations = collect();
        
        foreach ($results as $result) {
            $gameId = (int) $result['game_id'];
            if ($games->has($gameId)) {
                $game = $games->get($gameId);
                
                // Calcula score baseado na estratégia
                $game->recommendation_score = $this->calculateScoreForStrategy($result, $strategy);
                
                // Adiciona metadados da estratégia
                $game->strategy_metadata = array_merge(
                    ['strategy' => $strategy],
                    $result
                );
                
                $recommendations->push($game);
            }
        }

        return $recommendations;
    }

    /**
     * Calcula score baseado na estratégia específica
     */
    private function calculateScoreForStrategy(array $result, string $strategy): float
    {
        return match($strategy) {
            'collaborative' => $this->calculateCollaborativeScore($result),
            'path_based' => $this->calculatePathScore($result),
            'developer_based' => $this->calculateDeveloperScore($result),
            'community_based' => $this->calculateCommunityScore($result),
            'deep_walk' => $this->calculateDeepWalkScore($result),
            default => 50.0,
        };
    }

    private function calculateCollaborativeScore(array $result): float
    {
        return min(100, ($result['finalScore'] ?? 50) * 10);
    }

    private function calculatePathScore(array $result): float
    {
        return min(100, ($result['pathScore'] ?? 5) * 10);
    }

    private function calculateDeveloperScore(array $result): float
    {
        $likedCount = $result['likedGamesCount'] ?? 1;
        $rating = $result['rating'] ?? 0.5;
        return min(100, 60 + ($likedCount * 8) + ($rating * 20));
    }

    private function calculateCommunityScore(array $result): float
    {
        return min(100, ($result['communityScore'] ?? 0.5) * 100);
    }

    private function calculateDeepWalkScore(array $result): float
    {
        return min(100, ($result['deepWalkScore'] ?? 0.5) * 100);
    }
}

