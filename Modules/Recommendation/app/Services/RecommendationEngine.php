<?php

namespace Modules\Recommendation\Services;

use Illuminate\Support\Collection;
use Modules\Game\Models\Game;
use Modules\User\Models\User;
use Modules\Recommendation\Models\GameInteraction;
use Modules\Recommendation\Contracts\RecommendationEngineInterface;
use Modules\Recommendation\Contracts\ScoreCalculatorInterface;
use Modules\Recommendation\Contracts\GameFilterServiceInterface;

class RecommendationEngine implements RecommendationEngineInterface
{
    public function __construct(
        private ScoreCalculatorInterface $scoreCalculator,
        private GameFilterServiceInterface $filterService
    ) {}

    public function getRecommendations(User $user, int $limit = 10): Collection
    {
        $filteredGames = $this->filterService->filterGames($user)
            ->with(['genres', 'categories', 'platform', 'developers', 'publishers'])
            ->limit($limit * 3)
            ->get();

        $gamesWithScores = $filteredGames->map(function ($game) use ($user) {
            $score = $this->scoreCalculator->calculateScore($user, $game);
            $game->recommendation_score = $score;
            return $game;
        });

        return $gamesWithScores
            ->sortByDesc('recommendation_score')
            ->take($limit)
            ->values();
    }

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
            ->with(['genres', 'categories', 'platform'])
            ->limit($limit)
            ->get();
    }

    public function getUserInteractionHistory(User $user, int $limit = 20): Collection
    {
        return $user->gameInteractions()
            ->with('game')
            ->orderBy('interacted_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getUserFavorites(User $user): Collection
    {
        return $user->gameInteractions()
            ->where('type', 'favorite')
            ->with('game')
            ->orderBy('interacted_at', 'desc')
            ->get()
            ->pluck('game');
    }

    public function getUserStats(User $user): array
    {
        $profile = $user->profile;

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
        ];
    }
}
