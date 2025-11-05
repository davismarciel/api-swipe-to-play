<?php

namespace Modules\Recommendation\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Modules\Game\Models\Game;
use Modules\User\Models\User;
use Modules\Recommendation\Models\GameInteraction;
use Modules\Recommendation\Contracts\RecommendationEngineInterface;
use Modules\Recommendation\Contracts\ScoreCalculatorInterface;
use Modules\Recommendation\Contracts\GameFilterServiceInterface;
use Modules\Recommendation\Contracts\BehaviorAnalysisServiceInterface;

class RecommendationEngine implements RecommendationEngineInterface
{
    public function __construct(
        private ScoreCalculatorInterface $scoreCalculator,
        private GameFilterServiceInterface $filterService,
        private BehaviorAnalysisServiceInterface $behaviorAnalysis
    ) {}

    /**
     * Obtém recomendações personalizadas para o usuário
     *
     * @param User $user Usuário para quem gerar recomendações
     * @param int $limit Número máximo de recomendações (padrão: 10)
     * @return Collection Coleção de jogos recomendados com scores e explicações
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
                    $game->explanation = $this->scoreCalculator->generateExplanation($score, $game, $profile);
                    
                    return $game;
                } catch (\Exception $e) {
                    Log::warning('Error calculating score for game', [
                        'user_id' => $user->id,
                        'game_id' => $game->id,
                        'error' => $e->getMessage()
                    ]);
                    $game->recommendation_score = 50;
                    $game->explanation = [
                        'match_percentage' => 50,
                        'top_reasons' => ['Score calculation error'],
                        'score_breakdown' => []
                    ];
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
     * Retorna recomendações default para usuários sem perfil comportamental
     * 
     * Usa critérios básicos como popularidade e avaliações positivas
     *
     * @param User $user Usuário para quem gerar recomendações
     * @param int $limit Número de recomendações
     * @return Collection Coleção de jogos com score neutro
     */
    private function getDefaultRecommendations(User $user, int $limit): Collection
    {
        return $this->filterService->filterGames($user)
            ->with(['genres', 'categories', 'platform', 'developers', 'publishers', 'communityRating'])
            ->orderByDesc('total_reviews')
            ->orderByDesc('positive_ratio')
            ->limit($limit)
            ->get()
            ->map(function($game) {
                $game->recommendation_score = 50; // Score neutro
                $game->explanation = [
                    'match_percentage' => 50,
                    'top_reasons' => ['Jogo popular e bem avaliado'],
                    'score_breakdown' => []
                ];
                return $game;
            });
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

        $this->updateUserProfileStats($user, $type);

        $this->behaviorAnalysis->incrementInteractionCounter($user);

        if ($this->behaviorAnalysis->shouldUpdateProfile($user)) {
            $this->behaviorAnalysis->buildOrUpdateProfile($user, force: true);
        }

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

        return Game::query()
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

        return [
            'level' => $profile?->level ?? 1,
            'experience_points' => $profile?->experience_points ?? 0,
            'total_likes' => $profile?->total_likes ?? 0,
            'total_dislikes' => $profile?->total_dislikes ?? 0,
            'total_favorites' => $profile?->total_favorites ?? 0,
            'total_views' => $profile?->total_views ?? 0,
            'interactions_count' => $user->gameInteractions()->count(),
            'favorite_genres' => $user->preferredGenres()->get(),
            'favorite_categories' => $user->preferredCategories()->get(),
            'experience_level' => $behaviorProfile?->experience_level ?? 'novice',
            'profile_last_analyzed' => $behaviorProfile?->last_analyzed_at?->diffForHumans(),
        ];
    }
}
