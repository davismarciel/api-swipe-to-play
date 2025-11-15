<?php

namespace Modules\Recommendation\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Modules\Game\Models\Game;
use Modules\User\Models\User;
use Modules\Recommendation\Contracts\GameFilterServiceInterface;

class GameFilterService implements GameFilterServiceInterface
{
    public function filterGames(User $user, ?Builder $query = null): Builder
    {
        $query = $query ?? Game::query();

        Log::debug('Applying game filters', [
            'user_id' => $user->id
        ]);

        $query->where('is_active', true);

        $this->applyPlatformFilter($user, $query);
        $this->applyContentFilter($user, $query);
        $this->excludeInteractedGames($user, $query);

        Log::debug('Game filters applied successfully', [
            'user_id' => $user->id
        ]);

        return $query;
    }

    private function applyPlatformFilter(User $user, Builder $query): void
    {
        $preferences = $user->preferences;

        if (!$preferences) {
            Log::debug('No platform filter applied: user has no preferences', [
                'user_id' => $user->id
            ]);
            return;
        }

        $hasPlatformPreference = $preferences->prefer_windows 
            || $preferences->prefer_mac 
            || $preferences->prefer_linux;

        if (!$hasPlatformPreference) {
            Log::debug('No platform filter applied: no platform preferences', [
                'user_id' => $user->id
            ]);
            return;
        }

        $platforms = [];
        if ($preferences->prefer_windows) $platforms[] = 'windows';
        if ($preferences->prefer_mac) $platforms[] = 'mac';
        if ($preferences->prefer_linux) $platforms[] = 'linux';

        Log::debug('Applying platform filter', [
            'user_id' => $user->id,
            'platforms' => $platforms
        ]);

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

    private function applyContentFilter(User $user, Builder $query): void
    {
        $preferences = $user->preferences;

        if (!$preferences) {
            Log::debug('No content filter applied: user has no preferences', [
                'user_id' => $user->id
            ]);
            return;
        }

        $filters = [];

        if ($preferences->min_age_rating > 0) {
            $query->where('required_age', '<=', $preferences->min_age_rating);
            $filters['min_age_rating'] = $preferences->min_age_rating;
        }

        if ($preferences->avoid_violence || $preferences->avoid_nudity) {
            $contentFilters = [];
            if ($preferences->avoid_violence) {
                $contentFilters[] = 'violence';
            }
            if ($preferences->avoid_nudity) {
                $contentFilters[] = 'nudity';
            }
            $filters['avoid_content'] = $contentFilters;

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

        if (!empty($filters)) {
            Log::debug('Applying content filter', [
                'user_id' => $user->id,
                'filters' => $filters
            ]);
        }
    }

    private function excludeInteractedGames(User $user, Builder $query): void
    {
        $interactedGameIds = $user->gameInteractions()
            ->whereIn('type', ['like', 'dislike', 'skip'])
            ->pluck('game_id')
            ->toArray();

        if (!empty($interactedGameIds)) {
            Log::debug('Excluding interacted games', [
                'user_id' => $user->id,
                'excluded_count' => count($interactedGameIds)
            ]);

            $query->whereNotIn('id', $interactedGameIds);
        } else {
            Log::debug('No interacted games to exclude', [
                'user_id' => $user->id
            ]);
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
