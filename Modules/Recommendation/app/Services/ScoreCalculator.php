<?php

namespace Modules\Recommendation\Services;

use Modules\Game\Models\Game;
use Modules\User\Models\User;
use Modules\Recommendation\Contracts\ScoreCalculatorInterface;

class ScoreCalculator implements ScoreCalculatorInterface
{
    private const WEIGHTS = [
        'genre_match' => 35,
        'category_match' => 25,
        'platform_match' => 15,
        'popularity' => 15,
        'rating' => 10,
    ];

    public function calculateScore(User $user, Game $game): float
    {
        $scores = [
            'genre_match' => $this->calculateGenreScore($user, $game),
            'category_match' => $this->calculateCategoryScore($user, $game),
            'platform_match' => $this->calculatePlatformScore($user, $game),
            'popularity' => $this->calculatePopularityScore($game),
            'rating' => $this->calculateRatingScore($game),
        ];

        $finalScore = 0;
        foreach ($scores as $key => $score) {
            $finalScore += $score * (self::WEIGHTS[$key] / 100);
        }

        return round($finalScore, 2);
    }

    private function calculateGenreScore(User $user, Game $game): float
    {
        $userGenres = $user->preferredGenres()->pluck('genres.id', 'preference_weight')->toArray();
        $gameGenres = $game->genres()->pluck('genres.id')->toArray();

        if (empty($userGenres) || empty($gameGenres)) {
            return 50;
        }

        $matches = array_intersect($gameGenres, array_keys($userGenres));

        if (empty($matches)) {
            return 0;
        }

        $totalWeight = 0;
        $matchCount = count($matches);

        foreach ($matches as $genreId) {
            $totalWeight += $userGenres[$genreId] ?? 5;
        }

        return ($totalWeight / $matchCount) * 10;
    }

    private function calculateCategoryScore(User $user, Game $game): float
    {
        $userCategories = $user->preferredCategories()->pluck('categories.id', 'preference_weight')->toArray();
        $gameCategories = $game->categories()->pluck('categories.id')->toArray();

        if (empty($userCategories) || empty($gameCategories)) {
            return 50;
        }

        $matches = array_intersect($gameCategories, array_keys($userCategories));

        if (empty($matches)) {
            return 0;
        }

        $totalWeight = 0;
        $matchCount = count($matches);

        foreach ($matches as $categoryId) {
            $totalWeight += $userCategories[$categoryId] ?? 5;
        }

        return ($totalWeight / $matchCount) * 10;
    }

    private function calculatePlatformScore(User $user, Game $game): float
    {
        $preferences = $user->preferences;
        $platform = $game->platform;

        if (!$preferences || !$platform) {
            return 50;
        }

        $score = 0;
        $count = 0;

        if ($preferences->prefer_windows && $platform->windows) {
            $score += 100;
            $count++;
        }
        if ($preferences->prefer_mac && $platform->mac) {
            $score += 100;
            $count++;
        }
        if ($preferences->prefer_linux && $platform->linux) {
            $score += 100;
            $count++;
        }

        return $count > 0 ? $score / $count : 0;
    }

    private function calculatePopularityScore(Game $game): float
    {
        $totalReviews = $game->total_reviews;

        if ($totalReviews === 0) {
            return 50;
        }

        $popularityScore = min(100, ($totalReviews / 1000) * 100);

        return $popularityScore;
    }

    private function calculateRatingScore(Game $game): float
    {
        if ($game->positive_ratio === null) {
            return 50;
        }

        return $game->positive_ratio * 100;
    }
}
