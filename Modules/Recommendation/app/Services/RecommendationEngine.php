<?php

namespace Modules\Recommendation\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Modules\Game\Models\Game;
use Modules\User\Models\User;
use Modules\Recommendation\Models\GameInteraction;
use Modules\Game\Services\DailyGameCacheService;
use Modules\Recommendation\Contracts\RecommendationEngineInterface;
use Modules\Recommendation\Contracts\ScoreCalculatorInterface;
use Modules\Recommendation\Contracts\GameFilterServiceInterface;
use Modules\Recommendation\Contracts\BehaviorAnalysisServiceInterface;
use Modules\Recommendation\Services\Neo4jRecommendationService;
use Modules\Recommendation\Services\Neo4jGraphSyncService;
use Modules\Recommendation\Services\Neo4jService;

class RecommendationEngine implements RecommendationEngineInterface
{
    private ?Neo4jRecommendationService $neo4jRecommendation = null;
    private ?Neo4jGraphSyncService $neo4jSync = null;

    public function __construct(
        private ScoreCalculatorInterface $scoreCalculator,
        private GameFilterServiceInterface $filterService,
        private BehaviorAnalysisServiceInterface $behaviorAnalysis,
        private DailyGameCacheService $dailyGameCache
    ) {
        if (config('recommendation.neo4j.enabled')) {
            try {
                $neo4j = app(Neo4jService::class);
                if ($neo4j->isConnected()) {
                    $this->neo4jRecommendation = app(Neo4jRecommendationService::class);
                    $this->neo4jSync = app(Neo4jGraphSyncService::class);
                }
            } catch (\Exception $e) {
                Log::warning('Neo4j not available, using standard recommendations', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Obtém recomendações personalizadas para o usuário
     *
     * @param User $user Usuário para quem gerar recomendações
     * @param int $limit Número máximo de recomendações (padrão: 10)
     * @return Collection Coleção de jogos recomendados com scores
     */
    public function getRecommendations(User $user, int $limit = 10): Collection
    {
        $startTime = microtime(true);
        
        try {
            $profile = $this->behaviorAnalysis->buildOrUpdateProfile($user);
            
            if (!$profile) {
                Log::info('Using default recommendations for user without profile', [
                    'user_id' => $user->id,
                    'limit' => $limit
                ]);
                return $this->getDefaultRecommendations($user, $limit);
            }

            $useNeo4j = $this->neo4jRecommendation !== null && $profile->total_interactions >= 5;
            
            if ($useNeo4j) {
                try {
                    // Usa o sistema híbrido avançado do Neo4j
                    $neo4jRecommendations = $this->neo4jRecommendation->getHybridGraphRecommendations($user, $limit * 2);
                    
                    if ($neo4jRecommendations->isNotEmpty()) {
                        // Combina com algoritmo padrão para refinar scores
                        $hybridRecommendations = $this->combineNeo4jWithStandard($user, $neo4jRecommendations, $profile, $limit);
                        
                        $executionTime = microtime(true) - $startTime;
                        
                        Log::info('Advanced hybrid recommendations generated (Neo4j Multi-Strategy + Standard)', [
                            'user_id' => $user->id,
                            'count' => $hybridRecommendations->count(),
                            'limit' => $limit,
                            'execution_time_ms' => round($executionTime * 1000, 2),
                            'neo4j_candidates' => $neo4jRecommendations->count(),
                            'strategies_used' => $neo4jRecommendations->first()?->neo4j_metadata['strategies_used'] ?? [],
                        ]);
                        
                        return $hybridRecommendations;
                    }
                } catch (\Exception $e) {
                    Log::warning('Neo4j hybrid recommendations failed, falling back to standard', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            $candidateMultiplier = config('recommendation.diversification.candidate_multiplier', 5);
            $games = $this->filterService->filterGames($user)
                ->with(['genres', 'categories', 'platform', 'developers', 'publishers', 'communityRating', 'requirements'])
                ->limit($limit * $candidateMultiplier)
                ->get();
            
            if ($games->isEmpty()) {
                Log::warning('No games found after filtering', [
                    'user_id' => $user->id,
                    'limit' => $limit
                ]);
                return $this->getDefaultRecommendations($user, $limit);
            }
            
            $gamesWithScores = $games->map(function($game) use ($user, $profile) {
                try {
                    $score = $this->scoreCalculator->calculateScoreWithProfile($user, $game, $profile);
                    
                    $game->recommendation_score = $score;
                    
                    return $game;
                } catch (\Exception $e) {
                    Log::warning('Error calculating score for game', [
                        'user_id' => $user->id,
                        'game_id' => $game->id,
                        'error' => $e->getMessage()
                    ]);
                    $game->recommendation_score = 50;
                    return $game;
                }
            });
            
            $diversified = $this->applyDiversification($gamesWithScores, $limit);
            
            $result = $diversified->sortByDesc('recommendation_score')->take($limit)->values();
            
            $executionTime = microtime(true) - $startTime;
            
            Log::info('Recommendations generated', [
                'user_id' => $user->id,
                'count' => $result->count(),
                'limit' => $limit,
                'execution_time_ms' => round($executionTime * 1000, 2),
                'profile_age_days' => $profile->last_analyzed_at?->diffInDays(now()),
                'total_interactions' => $profile->total_interactions ?? 0,
                'neo4j_enabled' => $useNeo4j,
            ]);
            
            return $result;
            
        } catch (\Exception $e) {
            Log::error('Error generating recommendations', [
                'user_id' => $user->id,
                'limit' => $limit,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->getDefaultRecommendations($user, $limit);
        }
    }

    /**
     * Combina recomendações do Neo4j com o algoritmo padrão
     * Usa pesos adaptativos baseados no perfil do usuário
     */
    private function combineNeo4jWithStandard(
        User $user,
        Collection $neo4jGames,
        $profile,
        int $limit
    ): Collection {
        $combined = collect();
        $totalInteractions = $profile->total_interactions ?? 0;
        
        // Pesos adaptativos: quanto mais interações, mais confiamos no Neo4j
        $neo4jWeight = $this->calculateNeo4jWeight($totalInteractions);
        $standardWeight = 1 - $neo4jWeight;
        
        foreach ($neo4jGames as $game) {
            $neo4jScore = $game->recommendation_score ?? 50;
            
            try {
                $standardScore = $this->scoreCalculator->calculateScoreWithProfile($user, $game, $profile);
            } catch (\Exception $e) {
                Log::warning('Error calculating standard score, using Neo4j only', [
                    'user_id' => $user->id,
                    'game_id' => $game->id,
                    'error' => $e->getMessage(),
                ]);
                $standardScore = $neo4jScore;
            }
            
            // Score final combinado
            $finalScore = ($neo4jScore * $neo4jWeight) + ($standardScore * $standardWeight);
            
            // Bonus para jogos recomendados por múltiplas estratégias do Neo4j
            $strategyCount = $game->neo4j_metadata['strategy_count'] ?? 1;
            if ($strategyCount > 1) {
                $finalScore *= (1 + (($strategyCount - 1) * 0.05));
            }
            
            $game->recommendation_score = round($finalScore, 2);
            $game->score_breakdown = [
                'neo4j_score' => round($neo4jScore, 2),
                'standard_score' => round($standardScore, 2),
                'neo4j_weight' => $neo4jWeight,
                'standard_weight' => $standardWeight,
                'strategy_bonus' => $strategyCount > 1,
            ];
            
            $combined->push($game);
        }
        
        return $combined->sortByDesc('recommendation_score')->take($limit)->values();
    }
    
    /**
     * Calcula peso adaptativo para Neo4j baseado em interações
     */
    private function calculateNeo4jWeight(int $totalInteractions): float
    {
        // Usuários novos (< 10 interações): 40% Neo4j, 60% padrão
        if ($totalInteractions < 10) {
            return 0.40;
        }
        
        // Usuários intermediários (10-50): 60% Neo4j, 40% padrão
        if ($totalInteractions < 50) {
            return 0.60;
        }
        
        // Usuários avançados (50-100): 70% Neo4j, 30% padrão
        if ($totalInteractions < 100) {
            return 0.70;
        }
        
        // Usuários muito ativos (100+): 80% Neo4j, 20% padrão
        return 0.80;
    }

    /**
     * Retorna recomendações default para usuários sem perfil comportamental
     * 
     * Usa preferências do onboarding (gêneros, categorias, plataformas) quando disponíveis
     * Caso contrário, usa critérios básicos como popularidade e avaliações positivas
     *
     * @param User $user Usuário para quem gerar recomendações
     * @param int $limit Número de recomendações
     * @return Collection Coleção de jogos com score baseado em preferências do onboarding
     */
    private function getDefaultRecommendations(User $user, int $limit): Collection
    {
        $user->load(['preferredGenres', 'preferredCategories', 'preferences']);
        
        $query = $this->filterService->filterGames($user);
        
        if ($user->preferredGenres->isNotEmpty()) {
            $genreIds = $user->preferredGenres->pluck('id')->toArray();
            $query->whereHas('genres', function ($q) use ($genreIds) {
                $q->whereIn('genres.id', $genreIds);
            });
        }
        
        if ($user->preferredCategories->isNotEmpty()) {
            $categoryIds = $user->preferredCategories->pluck('id')->toArray();
            $query->orWhereHas('categories', function ($q) use ($categoryIds) {
                $q->whereIn('categories.id', $categoryIds);
            });
        }
        
        $games = $query
            ->with(['genres', 'categories', 'platform', 'developers', 'publishers', 'communityRating'])
            ->orderByDesc('total_reviews')
            ->orderByDesc('positive_ratio')
            ->limit($limit * 2)
            ->get();
        
        if ($games->isEmpty() && ($user->preferredGenres->isNotEmpty() || $user->preferredCategories->isNotEmpty())) {
            $games = $this->filterService->filterGames($user)
                ->with(['genres', 'categories', 'platform', 'developers', 'publishers', 'communityRating'])
                ->orderByDesc('total_reviews')
                ->orderByDesc('positive_ratio')
                ->limit($limit * 2)
                ->get();
        }
        
        return $games->map(function($game) use ($user) {
            $score = 50;
            
            $userGenreIds = $user->preferredGenres->pluck('id')->toArray();
            $gameGenreIds = $game->genres->pluck('id')->toArray();
            $matchingGenres = array_intersect($userGenreIds, $gameGenreIds);
            
            if (!empty($matchingGenres)) {
                $score += 20;
            }
            
            $userCategoryIds = $user->preferredCategories->pluck('id')->toArray();
            $gameCategoryIds = $game->categories->pluck('id')->toArray();
            $matchingCategories = array_intersect($userCategoryIds, $gameCategoryIds);
            
            if (!empty($matchingCategories)) {
                $score += 10;
            }
            
            if ($game->total_reviews > 10000) {
                $score += 10;
            }
            
            if ($game->positive_ratio > 0.8) {
                $score += 10;
            }
            
            $score = min(100, max(0, $score));
            
            $game->recommendation_score = $score;
            
            return $game;
        })
        ->sortByDesc('recommendation_score')
        ->take($limit)
        ->values();
    }

    /**
     * Aplica algoritmo de diversificação para garantir variedade de gêneros
     * 
     * Garante que não mais de X% dos jogos recomendados sejam do mesmo gênero,
     * evitando monotonia nas recomendações.
     *
     * @param Collection $games Coleção de jogos com scores calculados
     * @param int $limit Número máximo de jogos a selecionar
     * @return Collection Coleção diversificada de jogos
     */
    private function applyDiversification(Collection $games, int $limit): Collection
    {
        $selected = collect();
        $genreCount = [];
        $maxPerGenrePercentage = config('recommendation.diversification.max_per_genre_percentage', 0.4);
        $maxPerGenre = ceil($limit * $maxPerGenrePercentage);
        
        foreach ($games->sortByDesc('recommendation_score') as $game) {
            $gameGenres = $game->genres->pluck('id')->toArray();
            
            $canAdd = true;
            foreach ($gameGenres as $genreId) {
                if (($genreCount[$genreId] ?? 0) >= $maxPerGenre) {
                    $canAdd = false;
                    break;
                }
            }
            
            if ($canAdd) {
                $selected->push($game);
                
                foreach ($gameGenres as $genreId) {
                    $genreCount[$genreId] = ($genreCount[$genreId] ?? 0) + 1;
                }
            }
            
            if ($selected->count() >= $limit) break;
        }
        
        if ($selected->count() < $limit) {
            $remaining = $games->diff($selected)->take($limit - $selected->count());
            $selected = $selected->concat($remaining);
        }
        
        return $selected;
    }

    /**
     * Registra uma interação do usuário com um jogo
     *
     * @param User $user Usuário que interagiu
     * @param Game $game Jogo com o qual interagiu
     * @param string $type Tipo de interação (like, dislike, favorite, view, skip)
     * @return GameInteraction Interação registrada
     */
    public function recordInteraction(User $user, Game $game, string $type): GameInteraction
    {
        $interactionScore = $this->calculateInteractionScore($type);

        $interaction = GameInteraction::updateOrCreate(
            [
                'user_id' => $user->id,
                'game_id' => $game->id,
                'type' => $type,
            ],
            [
                'interaction_score' => $interactionScore,
                'interacted_at' => now(),
            ]
        );

        if (in_array($type, ['like', 'dislike', 'favorite'], true)) {
            $this->dailyGameCache->markAsSeen($user->id, $game->id);
        }

        $this->updateUserProfileStats($user, $type);

        $this->behaviorAnalysis->incrementInteractionCounter($user);

        $profileUpdated = false;
        if ($this->behaviorAnalysis->shouldUpdateProfile($user)) {
            $this->behaviorAnalysis->buildOrUpdateProfile($user, force: true);
            $profileUpdated = true;
        }

        if ($this->neo4jSync !== null) {
            try {
                $this->neo4jSync->syncUser($user);
                $this->neo4jSync->syncGame($game);
                $this->neo4jSync->syncInteraction($interaction);
            } catch (\Exception $e) {
                Log::warning('Failed to sync interaction to Neo4j', [
                    'user_id' => $user->id,
                    'game_id' => $game->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Game interaction recorded', [
            'user_id' => $user->id,
            'game_id' => $game->id,
            'type' => $type,
            'interaction_id' => $interaction->id,
            'interaction_score' => $interactionScore,
            'profile_updated' => $profileUpdated,
            'neo4j_synced' => $this->neo4jSync !== null,
        ]);

        return $interaction;
    }

    private function calculateInteractionScore(string $type): int
    {
        return match ($type) {
            'like' => 10,
            'favorite' => 15,
            'view' => 1,
            'dislike' => -5,
            'skip' => -2,
            default => 0,
        };
    }

    private function updateUserProfileStats(User $user, string $type): void
    {
        $profile = $user->profile;

        if (!$profile) {
            return;
        }

        match ($type) {
            'like' => $profile->increment('total_likes'),
            'dislike' => $profile->increment('total_dislikes'),
            'favorite' => $profile->increment('total_favorites'),
            'view' => $profile->increment('total_views'),
            default => null,
        };

        $this->addExperience($profile, $type);
    }

    private function addExperience($profile, string $type): void
    {
        $xpGain = match ($type) {
            'like' => 10,
            'dislike' => 5,
            'favorite' => 15,
            'view' => 1,
            default => 0,
        };

        $profile->experience_points += $xpGain;

        $newLevel = floor($profile->experience_points / 100) + 1;
        $profile->level = $newLevel;

        $profile->save();
    }

    /**
     * Obtém jogos similares a um jogo específico
     * 
     * Baseado em gêneros e categorias compartilhados
     *
     * @param Game $game Jogo de referência
     * @param int $limit Número máximo de jogos similares
     * @return Collection Coleção de jogos similares
     */
    public function getSimilarGames(Game $game, int $limit = 5): Collection
    {
        $genreIds = $game->genres()->pluck('genres.id')->toArray();
        $categoryIds = $game->categories()->pluck('categories.id')->toArray();

        Log::debug('Similar games requested', [
            'game_id' => $game->id,
            'game_name' => $game->name,
            'limit' => $limit,
            'genre_ids' => $genreIds,
            'category_ids' => $categoryIds,
        ]);

        $similarGames = Game::query()
            ->where('id', '!=', $game->id)
            ->where('is_active', true)
            ->where(function ($query) use ($genreIds, $categoryIds) {
                if (!empty($genreIds)) {
                    $query->whereHas('genres', function ($q) use ($genreIds) {
                        $q->whereIn('genres.id', $genreIds);
                    });
                }
                if (!empty($categoryIds)) {
                    $query->orWhereHas('categories', function ($q) use ($categoryIds) {
                        $q->whereIn('categories.id', $categoryIds);
                    });
                }
            })
            ->with(['genres', 'categories', 'platform', 'communityRating', 'requirements'])
            ->limit($limit)
            ->get();

        Log::info('Similar games retrieved', [
            'game_id' => $game->id,
            'limit' => $limit,
            'count' => $similarGames->count(),
        ]);

        return $similarGames;
    }

    /**
     * Obtém histórico de interações do usuário
     *
     * @param User $user Usuário
     * @param int $limit Número máximo de interações
     * @return Collection Histórico de interações ordenado por data
     */
    public function getUserInteractionHistory(User $user, int $limit = 20): Collection
    {
        return $user->gameInteractions()
            ->with(['game.genres', 'game.categories', 'game.platform', 'game.developers', 'game.publishers', 'game.communityRating'])
            ->orderBy('interacted_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Obtém jogos favoritos do usuário
     *
     * @param User $user Usuário
     * @return Collection Coleção de jogos favoritados
     */
    public function getUserFavorites(User $user): Collection
    {
        return $user->gameInteractions()
            ->where('type', 'favorite')
            ->with(['game.genres', 'game.categories', 'game.platform', 'game.developers', 'game.publishers', 'game.communityRating'])
            ->orderBy('interacted_at', 'desc')
            ->get()
            ->pluck('game');
    }

    /**
     * Obtém estatísticas de recomendação do usuário
     *
     * @param User $user Usuário
     * @return array Array com estatísticas detalhadas
     */
    public function getUserStats(User $user): array
    {
        $profile = $user->profile;
        $behaviorProfile = $user->behaviorProfile;

        $interactionsCount = $user->gameInteractions()->count();

        Log::debug('User stats retrieved', [
            'user_id' => $user->id,
            'has_profile' => $profile !== null,
            'has_behavior_profile' => $behaviorProfile !== null,
            'interactions_count' => $interactionsCount,
        ]);

        return [
            'level' => $profile?->level ?? 1,
            'experience_points' => $profile?->experience_points ?? 0,
            'total_likes' => $profile?->total_likes ?? 0,
            'total_dislikes' => $profile?->total_dislikes ?? 0,
            'total_favorites' => $profile?->total_favorites ?? 0,
            'total_views' => $profile?->total_views ?? 0,
            'interactions_count' => $interactionsCount,
            'favorite_genres' => $user->preferredGenres()->get(),
            'favorite_categories' => $user->preferredCategories()->get(),
            'experience_level' => $behaviorProfile?->experience_level ?? 'novice',
            'profile_last_analyzed' => $behaviorProfile?->last_analyzed_at?->diffForHumans(),
        ];
    }
}
