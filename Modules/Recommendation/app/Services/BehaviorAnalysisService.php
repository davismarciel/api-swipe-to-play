<?php

namespace Modules\Recommendation\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\User\Models\User;
use Modules\Recommendation\Models\UserBehaviorProfile;
use Modules\Recommendation\Contracts\BehaviorAnalysisServiceInterface;

class BehaviorAnalysisService implements BehaviorAnalysisServiceInterface
{
    private const INTERACTION_LIMIT = null; // Será obtido da configuração
    private const UPDATE_THRESHOLD = null; // Será obtido da configuração
    private const DAYS_THRESHOLD = null; // Será obtido da configuração
    
    /**
     * Obtém o limite de interações da configuração
     */
    private function getInteractionLimit(): int
    {
        return config('recommendation.behavior_analysis.interaction_limit', 50);
    }
    
    /**
     * Obtém o threshold de atualização da configuração
     */
    private function getUpdateThreshold(): int
    {
        return config('recommendation.behavior_analysis.update_threshold', 5);
    }
    
    /**
     * Obtém o threshold de dias da configuração
     */
    private function getDaysThreshold(): int
    {
        return config('recommendation.behavior_analysis.days_threshold', 7);
    }

    public function analyzeGenrePatterns(User $user): array
    {
        $interactions = $this->getRecentInteractions($user);

        $likedGenres = [];
        $dislikedGenres = [];

        foreach ($interactions as $interaction) {
            $game = $interaction->game;
            if (!$game) continue;

            $genres = $game->genres;
            $temporalWeight = $this->calculateTemporalWeight($interaction->interacted_at);

            foreach ($genres as $genre) {
                $genreId = $genre->id;

                if (in_array($interaction->type, ['like', 'favorite'])) {
                    if (!isset($likedGenres[$genreId])) {
                        $likedGenres[$genreId] = [
                            'count' => 0,
                            'weighted_score' => 0,
                            'temporal_weights' => [],
                        ];
                    }
                    $likedGenres[$genreId]['count']++;
                    $likedGenres[$genreId]['weighted_score'] += $temporalWeight * 10;
                    $likedGenres[$genreId]['temporal_weights'][] = $temporalWeight;
                } elseif ($interaction->type === 'dislike') {
                    if (!isset($dislikedGenres[$genreId])) {
                        $dislikedGenres[$genreId] = [
                            'count' => 0,
                            'rejection_rate' => 0,
                        ];
                    }
                    $dislikedGenres[$genreId]['count']++;
                }
            }
        }

        foreach ($likedGenres as $genreId => &$stats) {
            $stats['avg_temporal_weight'] = count($stats['temporal_weights']) > 0
                ? array_sum($stats['temporal_weights']) / count($stats['temporal_weights'])
                : 0;
            unset($stats['temporal_weights']);
        }

        $totalDislikes = $interactions->where('type', 'dislike')->count();
        foreach ($dislikedGenres as $genreId => &$stats) {
            $stats['rejection_rate'] = $totalDislikes > 0
                ? $stats['count'] / $totalDislikes
                : 0;
        }

        return [
            'liked' => $likedGenres,
            'disliked' => $dislikedGenres,
        ];
    }

    public function analyzeCategoryPatterns(User $user): array
    {
        $interactions = $this->getRecentInteractions($user);

        $likedCategories = [];
        $dislikedCategories = [];

        foreach ($interactions as $interaction) {
            $game = $interaction->game;
            if (!$game) continue;

            $categories = $game->categories;
            $temporalWeight = $this->calculateTemporalWeight($interaction->interacted_at);

            foreach ($categories as $category) {
                $categoryId = $category->id;

                if (in_array($interaction->type, ['like', 'favorite'])) {
                    if (!isset($likedCategories[$categoryId])) {
                        $likedCategories[$categoryId] = [
                            'count' => 0,
                            'weighted_score' => 0,
                            'temporal_weights' => [],
                        ];
                    }
                    $likedCategories[$categoryId]['count']++;
                    $likedCategories[$categoryId]['weighted_score'] += $temporalWeight * 10;
                    $likedCategories[$categoryId]['temporal_weights'][] = $temporalWeight;
                } elseif ($interaction->type === 'dislike') {
                    if (!isset($dislikedCategories[$categoryId])) {
                        $dislikedCategories[$categoryId] = [
                            'count' => 0,
                            'rejection_rate' => 0,
                        ];
                    }
                    $dislikedCategories[$categoryId]['count']++;
                }
            }
        }

        foreach ($likedCategories as $categoryId => &$stats) {
            $stats['avg_temporal_weight'] = count($stats['temporal_weights']) > 0
                ? array_sum($stats['temporal_weights']) / count($stats['temporal_weights'])
                : 0;
            unset($stats['temporal_weights']);
        }

        $totalDislikes = $interactions->where('type', 'dislike')->count();
        foreach ($dislikedCategories as $categoryId => &$stats) {
            $stats['rejection_rate'] = $totalDislikes > 0
                ? $stats['count'] / $totalDislikes
                : 0;
        }

        return [
            'liked' => $likedCategories,
            'disliked' => $dislikedCategories,
        ];
    }

    public function analyzeDeveloperPatterns(User $user): array
    {
        $interactions = $this->getRecentInteractions($user)
            ->whereIn('type', ['like', 'favorite']);

        $developers = [];

        foreach ($interactions as $interaction) {
            $game = $interaction->game;
            if (!$game) continue;

            foreach ($game->developers as $developer) {
                $developers[$developer->id] = ($developers[$developer->id] ?? 0) + 1;
            }
        }

        arsort($developers);
        return array_slice($developers, 0, 10, true);
    }

    public function analyzePublisherPatterns(User $user): array
    {
        $interactions = $this->getRecentInteractions($user)
            ->whereIn('type', ['like', 'favorite']);

        $publishers = [];

        foreach ($interactions as $interaction) {
            $game = $interaction->game;
            if (!$game) continue;

            foreach ($game->publishers as $publisher) {
                $publishers[$publisher->id] = ($publishers[$publisher->id] ?? 0) + 1;
            }
        }

        arsort($publishers);
        return array_slice($publishers, 0, 10, true);
    }

    public function analyzeFreeToPlayPreference(User $user): float
    {
        $interactions = $this->getRecentInteractions($user);

        $freeGamesLiked = 0;
        $paidGamesLiked = 0;
        $freeGamesDisliked = 0;
        $paidGamesDisliked = 0;

        foreach ($interactions as $interaction) {
            $game = $interaction->game;
            if (!$game) continue;

            $isFree = $game->is_free;
            $isLike = in_array($interaction->type, ['like', 'favorite']);
            $isDislike = $interaction->type === 'dislike';

            if ($isFree && $isLike) $freeGamesLiked++;
            if (!$isFree && $isLike) $paidGamesLiked++;
            if ($isFree && $isDislike) $freeGamesDisliked++;
            if (!$isFree && $isDislike) $paidGamesDisliked++;
        }

        $totalLikes = $freeGamesLiked + $paidGamesLiked;
        $totalDislikes = $freeGamesDisliked + $paidGamesDisliked;

        if ($totalLikes === 0 && $totalDislikes === 0) {
            return 0.0; // Neutro
        }

        $freeScore = ($freeGamesLiked - $freeGamesDisliked) / max(1, $freeGamesLiked + $freeGamesDisliked);
        $paidScore = ($paidGamesLiked - $paidGamesDisliked) / max(1, $paidGamesLiked + $paidGamesDisliked);

        return round(($freeScore - $paidScore) / 2, 2);
    }

    public function analyzeMatureContentTolerance(User $user): float
    {
        $interactions = $this->getRecentInteractions($user);

        $matureGamesLiked = 0;
        $totalLiked = 0;

        foreach ($interactions as $interaction) {
            $game = $interaction->game;
            if (!$game) continue;

            if (in_array($interaction->type, ['like', 'favorite'])) {
                $totalLiked++;
                if ($game->required_age >= 17) {
                    $matureGamesLiked++;
                }
            }
        }

        if ($totalLiked === 0) {
            return 0.50; // Default neutro
        }

        return round($matureGamesLiked / $totalLiked, 2);
    }

    public function analyzeCommunityTolerances(User $user): array
    {
        $interactions = $this->getRecentInteractions($user)
            ->whereIn('type', ['like', 'favorite']);

        $tolerances = [
            'toxicity' => [],
            'cheater' => [],
            'bug' => [],
            'microtransaction' => [],
            'optimization' => [],
            'not_recommended' => [],
        ];

        foreach ($interactions as $interaction) {
            $game = $interaction->game;
            if (!$game || !$game->communityRating) continue;

            $rating = $game->communityRating;
            $tolerances['toxicity'][] = (float) $rating->toxicity_rate;
            $tolerances['cheater'][] = (float) $rating->cheater_rate;
            $tolerances['bug'][] = (float) $rating->bug_rate;
            $tolerances['microtransaction'][] = (float) $rating->microtransaction_rate;
            $tolerances['optimization'][] = (float) $rating->bad_optimization_rate;
            $tolerances['not_recommended'][] = (float) $rating->not_recommended_rate;
        }

        $result = [];
        foreach ($tolerances as $key => $values) {
            if (count($values) > 0) {
                $result[$key . '_tolerance'] = round(array_sum($values) / count($values), 2);
            } else {
                $result[$key . '_tolerance'] = 0.50; // Default neutro
            }
        }

        return $result;
    }

    public function calculateAdaptiveWeights(User $user, array $patterns): array
    {
        $totalInteractions = $user->gameInteractions()->count();
        $baseWeights = $this->getBaseWeightsByLevel($totalInteractions);

        $genreConsistency = $this->calculateConsistency($patterns['genres']['liked'] ?? []);
        $categoryConsistency = $this->calculateConsistency($patterns['categories']['liked'] ?? []);
        $developerConsistency = count($patterns['developers'] ?? []) > 0 ? 1.2 : 0.8;

        $baseWeights['genre_match'] *= (1 + $genreConsistency * 0.3);
        $baseWeights['category_match'] *= (1 + $categoryConsistency * 0.3);
        
        if (isset($baseWeights['developer_match'])) {
            $baseWeights['developer_match'] *= $developerConsistency;
        }

        $total = array_sum($baseWeights);
        foreach ($baseWeights as &$weight) {
            $weight = round(($weight / $total) * 100, 1);
        }

        return $baseWeights;
    }

    public function determineExperienceLevel(int $totalInteractions): string
    {
        if ($totalInteractions < 20) {
            return 'novice';
        } elseif ($totalInteractions < 100) {
            return 'intermediate';
        }
        
        return 'advanced';
    }

    public function buildOrUpdateProfile(User $user, bool $force = false): ?UserBehaviorProfile
    {
        $startTime = microtime(true);
        
        $cacheEnabled = config('recommendation.cache.enabled', true);
        $cacheKey = null;
        
        if ($cacheEnabled && !$force) {
            $lastInteraction = $user->gameInteractions()->latest('interacted_at')->first();
            $cacheKey = "user_profile:{$user->id}";
            
            if ($lastInteraction) {
                $cacheKey .= ":{$lastInteraction->interacted_at->timestamp}";
            }
            
            $cached = Cache::get($cacheKey);
            if ($cached && !$this->shouldUpdateProfile($user)) {
                Log::debug('Profile retrieved from cache', [
                    'user_id' => $user->id,
                    'cache_key' => $cacheKey
                ]);
                return $cached;
            }
            
            Log::debug('Profile not found in cache or needs update', [
                'user_id' => $user->id,
                'cache_key' => $cacheKey,
                'has_cached' => $cached !== null,
                'needs_update' => $this->shouldUpdateProfile($user)
            ]);
        }
        
        if (!$force && !$this->shouldUpdateProfile($user)) {
            Log::debug('Profile does not need update', [
                'user_id' => $user->id,
                'has_profile' => $user->behaviorProfile !== null
            ]);
            return $user->behaviorProfile;
        }

        $minInteractions = config('recommendation.behavior_analysis.min_interactions_for_profile', 3);
        $totalInteractions = $user->gameInteractions()->count();
        
        if ($totalInteractions < $minInteractions) {
            Log::debug('User does not have enough interactions for profile', [
                'user_id' => $user->id,
                'total_interactions' => $totalInteractions,
                'min_required' => $minInteractions
            ]);
            return null;
        }

        Log::debug('Starting profile analysis', [
            'user_id' => $user->id,
            'total_interactions' => $totalInteractions,
            'force' => $force
        ]);

        $profile = $this->performAnalysis($user, $totalInteractions);

        if ($profile && $cacheEnabled && $cacheKey) {
            $cacheTtl = config('recommendation.cache.ttl', 86400);
            Cache::put($cacheKey, $profile, now()->addSeconds($cacheTtl));
            
            Log::debug('Profile cached', [
                'user_id' => $user->id,
                'cache_key' => $cacheKey,
                'cache_ttl' => $cacheTtl
            ]);
        }
        
        $executionTime = microtime(true) - $startTime;
        
        Log::info('Profile updated', [
            'user_id' => $user->id,
            'interactions_analyzed' => $totalInteractions,
            'execution_time_ms' => round($executionTime * 1000, 2),
            'cached' => $cacheEnabled && $cacheKey !== null,
            'profile_id' => $profile->id ?? null
        ]);

        return $profile;
    }
    
    /**
     * Executa a análise completa do perfil comportamental
     */
    private function performAnalysis(User $user, int $totalInteractions): ?UserBehaviorProfile
    {
        Log::debug('Analyzing user patterns', [
            'user_id' => $user->id,
            'total_interactions' => $totalInteractions
        ]);

        $genrePatterns = $this->analyzeGenrePatterns($user);
        $categoryPatterns = $this->analyzeCategoryPatterns($user);
        $developerPatterns = $this->analyzeDeveloperPatterns($user);
        $publisherPatterns = $this->analyzePublisherPatterns($user);
        $freeToPlayPref = $this->analyzeFreeToPlayPreference($user);
        $matureContentTol = $this->analyzeMatureContentTolerance($user);
        $communityTolerances = $this->analyzeCommunityTolerances($user);

        $adaptiveWeights = $this->calculateAdaptiveWeights($user, [
            'genres' => $genrePatterns,
            'categories' => $categoryPatterns,
            'developers' => $developerPatterns,
        ]);

        Log::debug('Pattern analysis completed', [
            'user_id' => $user->id,
            'liked_genres_count' => count($genrePatterns['liked']),
            'disliked_genres_count' => count($genrePatterns['disliked']),
            'top_developers_count' => count($developerPatterns),
            'top_publishers_count' => count($publisherPatterns)
        ]);

        $profile = $user->behaviorProfile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'total_interactions' => $totalInteractions,
                'liked_genres_stats' => $genrePatterns['liked'],
                'disliked_genres_stats' => $genrePatterns['disliked'],
                'liked_categories_stats' => $categoryPatterns['liked'],
                'disliked_categories_stats' => $categoryPatterns['disliked'],
                'top_developers' => $developerPatterns,
                'top_publishers' => $publisherPatterns,
                'free_to_play_preference' => $freeToPlayPref,
                'mature_content_tolerance' => $matureContentTol,
                'toxicity_tolerance' => $communityTolerances['toxicity_tolerance'],
                'cheater_tolerance' => $communityTolerances['cheater_tolerance'],
                'bug_tolerance' => $communityTolerances['bug_tolerance'],
                'microtransaction_tolerance' => $communityTolerances['microtransaction_tolerance'],
                'optimization_tolerance' => $communityTolerances['optimization_tolerance'],
                'not_recommended_tolerance' => $communityTolerances['not_recommended_tolerance'],
                'adaptive_weights' => $adaptiveWeights,
            ]
        );

        $profile->markAsAnalyzed();

        return $profile->fresh();
    }

    public function shouldUpdateProfile(User $user): bool
    {
        $profile = $user->behaviorProfile;

        if (!$profile) {
            return true; // Nunca foi criado
        }

        return $profile->needsUpdate();
    }

    public function incrementInteractionCounter(User $user): void
    {
        $profile = $user->behaviorProfile;
        
        if ($profile) {
            $profile->increment('interactions_since_update');
            $profile->increment('total_interactions');
            $profile->update(['last_interaction_at' => now()]);
            
            Log::debug('Interaction counter incremented', [
                'user_id' => $user->id,
                'total_interactions' => $profile->total_interactions,
                'interactions_since_update' => $profile->interactions_since_update
            ]);
        } else {
            $user->behaviorProfile()->create([
                'total_interactions' => 1,
                'interactions_since_update' => 1,
                'last_interaction_at' => now(),
            ]);
            
            Log::debug('Initial behavior profile created', [
                'user_id' => $user->id
            ]);
        }
    }

    public function getRejectedDevelopers(User $user): array
    {
        $interactions = $user->gameInteractions()
            ->where('type', 'dislike')
            ->with('game.developers')
            ->limit(30)
            ->get();

        $developers = [];

        foreach ($interactions as $interaction) {
            $game = $interaction->game;
            if (!$game) continue;

            foreach ($game->developers as $developer) {
                $developers[$developer->id] = ($developers[$developer->id] ?? 0) + 1;
            }
        }

        return array_keys(array_filter($developers, fn($count) => $count >= 2));
    }

    public function getRejectedPublishers(User $user): array
    {
        $interactions = $user->gameInteractions()
            ->where('type', 'dislike')
            ->with('game.publishers')
            ->limit(30)
            ->get();

        $publishers = [];

        foreach ($interactions as $interaction) {
            $game = $interaction->game;
            if (!$game) continue;

            foreach ($game->publishers as $publisher) {
                $publishers[$publisher->id] = ($publishers[$publisher->id] ?? 0) + 1;
            }
        }

        return array_keys(array_filter($publishers, fn($count) => $count >= 2));
    }

    // ========== Private Helper Methods ==========

    private function getRecentInteractions(User $user)
    {
        return $user->gameInteractions()
            ->with(['game.genres', 'game.categories', 'game.developers', 'game.publishers', 'game.communityRating'])
            ->whereIn('type', ['like', 'dislike', 'favorite'])
            ->orderBy('interacted_at', 'desc')
            ->limit($this->getInteractionLimit())
            ->get();
    }

    private function calculateTemporalWeight(Carbon $interactionDate): float
    {
        $daysAgo = now()->diffInDays($interactionDate);
        return max(0.25, 1 - ($daysAgo / 365) * 0.75);
    }

    private function getBaseWeightsByLevel(int $totalInteractions): array
    {
        $level = $this->determineExperienceLevel($totalInteractions);

        return match ($level) {
            'novice' => [
                'genre_match' => 40,
                'category_match' => 20,
                'platform_match' => 10,
                'popularity' => 20,
                'rating' => 10,
            ],
            'intermediate' => [
                'genre_match' => 30,
                'category_match' => 20,
                'platform_match' => 10,
                'developer_match' => 15,
                'community_health' => 15,
                'popularity' => 10,
            ],
            'advanced' => [
                'genre_match' => 25,
                'category_match' => 20,
                'platform_match' => 5,
                'developer_match' => 20,
                'community_health' => 15,
                'maturity_match' => 10,
                'rating' => 5,
            ],
        };
    }

    private function calculateConsistency(array $likedItems): float
    {
        if (empty($likedItems)) {
            return 0;
        }

        $counts = array_column($likedItems, 'count');
        $total = array_sum($counts);
        $max = max($counts);

        return $total > 0 ? $max / $total : 0;
    }
}

