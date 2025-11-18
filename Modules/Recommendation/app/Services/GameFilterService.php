<?php

namespace Modules\Recommendation\Services;

use Illuminate\Database\Eloquent\Builder;
use Modules\Game\Models\Game;
use Modules\User\Models\User;
use Modules\Recommendation\Contracts\GameFilterServiceInterface;
use Illuminate\Support\Facades\DB;

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

        $hasPlatformPreference = $preferences->prefer_windows 
            || $preferences->prefer_mac 
            || $preferences->prefer_linux;

        if (!$hasPlatformPreference) {
            return;
        }

        $platformConditions = [];
        if ($preferences->prefer_windows) {
            $platformConditions[] = 'CAST(windows AS BOOLEAN) = true';
        }
        if ($preferences->prefer_mac) {
            $platformConditions[] = 'CAST(mac AS BOOLEAN) = true';
        }
        if ($preferences->prefer_linux) {
            $platformConditions[] = 'CAST(linux AS BOOLEAN) = true';
        }

        if (empty($platformConditions)) {
            return;
        }

        $query->whereExists(function ($subquery) use ($platformConditions) {
            $subquery->select(DB::raw(1))
                ->from('game_platforms')
                ->whereColumn('game_platforms.game_id', 'games.id')
                ->whereRaw('(' . implode(' OR ', $platformConditions) . ')');
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

        return $query->whereIn('games.id', function ($subquery) use ($preferredGenreIds) {
            $subquery->select('game_id')
                ->from('game_genre')
                ->whereIn('genre_id', $preferredGenreIds);
        });
    }

    public function applyCategoryBoost(User $user, Builder $query): Builder
    {
        $preferredCategoryIds = $user->preferredCategories()->pluck('categories.id')->toArray();

        if (empty($preferredCategoryIds)) {
            return $query;
        }

        return $query->whereIn('games.id', function ($subquery) use ($preferredCategoryIds) {
            $subquery->select('game_id')
                ->from('game_category')
                ->whereIn('category_id', $preferredCategoryIds);
        });
    }
}