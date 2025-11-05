<?php

namespace Modules\Recommendation\Services;

use Illuminate\Database\Eloquent\Builder;
use Modules\Game\Models\Game;
use Modules\User\Models\User;
use Modules\Recommendation\Contracts\GameFilterServiceInterface;

class GameFilterService implements GameFilterServiceInterface
{
    public function filterGames(User $user, ?Builder $query = null): Builder
    {
        $query = $query ?? Game::query();

        $query->where('is_active', true);

        $this->applyPlatformFilter($user, $query);
        $this->applyPriceFilter($user, $query);
        $this->applyContentFilter($user, $query);
        $this->excludeInteractedGames($user, $query);

        return $query;
    }

    private function applyPlatformFilter(User $user, Builder $query): void
    {
        $preferences = $user->preferences;

        if (!$preferences) {
            return;
        }

        // Check if any platform preferences are set
        $hasPlatformPreference = $preferences->prefer_windows 
            || $preferences->prefer_mac 
            || $preferences->prefer_linux;

        // Skip platform filtering if no platforms are preferred
        if (!$hasPlatformPreference) {
            return;
        }

        $query->whereHas('platform', function ($q) use ($preferences) {
            $q->where(function ($platformQuery) use ($preferences) {
                if ($preferences->prefer_windows) {
                    $platformQuery->orWhere('windows', true);
                }
                if ($preferences->prefer_mac) {
                    $platformQuery->orWhere('mac', true);
                }
                if ($preferences->prefer_linux) {
                    $platformQuery->orWhere('linux', true);
                }
            });
        });
    }

    private function applyPriceFilter(User $user, Builder $query): void
    {
        $preferences = $user->preferences;

        if (!$preferences) {
            return;
        }

        if ($preferences->prefer_free_to_play) {
            $query->where('is_free', true);
        }
    }

    private function applyContentFilter(User $user, Builder $query): void
    {
        $preferences = $user->preferences;

        if (!$preferences) {
            return;
        }

        if ($preferences->min_age_rating > 0) {
            $query->where('required_age', '<=', $preferences->min_age_rating);
        }

        if ($preferences->avoid_violence || $preferences->avoid_nudity) {
            $query->where(function ($q) use ($preferences) {
                if ($preferences->avoid_violence) {
                    $q->whereJsonDoesntContain('content_descriptors->ids', 1)
                        ->whereJsonDoesntContain('content_descriptors->ids', 2);
                }
                if ($preferences->avoid_nudity) {
                    $q->whereJsonDoesntContain('content_descriptors->ids', 3)
                        ->whereJsonDoesntContain('content_descriptors->ids', 4);
                }
            });
        }
    }

    private function excludeInteractedGames(User $user, Builder $query): void
    {
        $interactedGameIds = $user->gameInteractions()
            ->whereIn('type', ['like', 'dislike', 'skip'])
            ->pluck('game_id')
            ->toArray();

        if (!empty($interactedGameIds)) {
            $query->whereNotIn('id', $interactedGameIds);
        }
    }

    public function applyGenreBoost(User $user, Builder $query): Builder
    {
        $preferredGenreIds = $user->preferredGenres()->pluck('genres.id')->toArray();

        if (empty($preferredGenreIds)) {
            return $query;
        }

        return $query->leftJoin('game_genre', 'games.id', '=', 'game_genre.game_id')
            ->whereIn('game_genre.genre_id', $preferredGenreIds)
            ->select('games.*')
            ->distinct();
    }

    public function applyCategoryBoost(User $user, Builder $query): Builder
    {
        $preferredCategoryIds = $user->preferredCategories()->pluck('categories.id')->toArray();

        if (empty($preferredCategoryIds)) {
            return $query;
        }

        return $query->leftJoin('game_category', 'games.id', '=', 'game_category.game_id')
            ->whereIn('game_category.category_id', $preferredCategoryIds)
            ->select('games.*')
            ->distinct();
    }
}
