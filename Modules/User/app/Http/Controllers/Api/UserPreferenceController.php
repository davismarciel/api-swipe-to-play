<?php

namespace Modules\User\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\User\Models\UserPreference;
use Modules\User\Models\UserMonetizationPreference;
use Modules\User\Http\Resources\UserPreferenceResource;
use Modules\User\Http\Resources\UserMonetizationPreferenceResource;
use Modules\User\Http\Resources\GenreResource;
use Modules\User\Http\Resources\CategoryResource;
use Modules\User\Http\Requests\UpdatePreferencesRequest;
use Modules\User\Http\Requests\UpdateMonetizationPreferencesRequest;
use Modules\User\Http\Requests\UpdatePreferredGenresRequest;
use Modules\User\Http\Requests\UpdatePreferredCategoriesRequest;
use Modules\Recommendation\Services\Synchronization\Neo4jSynchronizationService;

class UserPreferenceController extends Controller
{
    public function __construct(
        private Neo4jSynchronizationService $syncService
    ) {}
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->load([
            'preferences',
            'monetizationPreferences',
            'preferredGenres',
            'preferredCategories',
            'profile'
        ]);

        Log::info('Fetching user preferences', [
            'user_id' => $user->id,
            'genres_count' => $user->preferredGenres->count(),
            'categories_count' => $user->preferredCategories->count(),
            'genre_ids' => $user->preferredGenres->pluck('id')->toArray(),
            'category_ids' => $user->preferredCategories->pluck('id')->toArray(),
        ]);

        return $this->successResponse([
            'preferences' => new UserPreferenceResource($user->preferences),
            'monetization_preferences' => new UserMonetizationPreferenceResource($user->monetizationPreferences),
            'preferred_genres' => GenreResource::collection($user->preferredGenres),
            'preferred_categories' => CategoryResource::collection($user->preferredCategories),
            'profile' => $user->profile,
        ]);
    }

    public function updatePreferences(UpdatePreferencesRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = $request->user();

        $validated = array_filter($validated, function ($value) {
            return $value !== null;
        });

        Log::info('Updating preferences', [
            'user_id' => $user->id,
            'validated_data' => $validated,
        ]);

        $preferences = UserPreference::updateOrCreate(
            ['user_id' => $user->id],
            $validated
        );

        Log::info('Preferences saved', [
            'user_id' => $user->id,
            'preferences_id' => $preferences->id,
            'saved_data' => $preferences->toArray(),
        ]);

        try {
            $user->load(['preferences', 'monetizationPreferences']);
            $this->syncService->syncUserPreferences($user);
        } catch (\Exception $e) {
            Log::warning('Failed to sync user preferences to Neo4j after update', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }

        return $this->successResponse(new UserPreferenceResource($preferences), 'Preferences updated successfully');
    }

    public function updateMonetizationPreferences(UpdateMonetizationPreferencesRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = $request->user();

        $validated = array_filter($validated, function ($value) {
            return $value !== null;
        });

        Log::info('Updating monetization preferences', [
            'user_id' => $user->id,
            'validated_data' => $validated,
        ]);

        $monetizationPreferences = UserMonetizationPreference::updateOrCreate(
            ['user_id' => $user->id],
            $validated
        );

        Log::info('Monetization preferences saved', [
            'user_id' => $user->id,
            'preferences_id' => $monetizationPreferences->id,
            'saved_data' => $monetizationPreferences->toArray(),
        ]);

        try {
            $user->load(['preferences', 'monetizationPreferences']);
            $this->syncService->syncUserPreferences($user);
        } catch (\Exception $e) {
            Log::warning('Failed to sync user preferences to Neo4j after monetization update', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }

        return $this->successResponse(
            new UserMonetizationPreferenceResource($monetizationPreferences),
            'Monetization preferences updated successfully'
        );
    }

    public function updatePreferredGenres(UpdatePreferredGenresRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $genres = $validated['genres'] ?? [];

        $user = $request->user();

        $syncData = [];
        foreach ($genres as $genre) {
            if (isset($genre['genre_id']) && isset($genre['preference_weight'])) {
                $syncData[$genre['genre_id']] = ['preference_weight' => $genre['preference_weight']];
            }
        }

        Log::info('Updating preferred genres', [
            'user_id' => $user->id,
            'genres_count' => count($genres),
            'sync_data' => $syncData,
        ]);

        $user->preferredGenres()->sync($syncData);

        $savedGenres = $user->preferredGenres()->withPivot('preference_weight')->get();
        Log::info('Preferred genres saved', [
            'user_id' => $user->id,
            'saved_genres_count' => $savedGenres->count(),
            'saved_genre_ids' => $savedGenres->pluck('id')->toArray(),
        ]);

        try {
            $user->load([
                'preferences',
                'monetizationPreferences',
                'preferredGenres' => function ($query) {
                    $query->withPivot('preference_weight');
                }
            ]);
            $this->syncService->syncUserPreferences($user);
        } catch (\Exception $e) {
            Log::warning('Failed to sync user preferences to Neo4j after genres update', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }

        return $this->successResponse(
            GenreResource::collection($savedGenres),
            'Preferred genres updated successfully'
        );
    }

    public function updatePreferredCategories(UpdatePreferredCategoriesRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $categories = $validated['categories'] ?? [];

        $user = $request->user();

        $syncData = [];
        foreach ($categories as $category) {
            if (isset($category['category_id']) && isset($category['preference_weight'])) {
                $syncData[$category['category_id']] = ['preference_weight' => $category['preference_weight']];
            }
        }

        Log::info('Updating preferred categories', [
            'user_id' => $user->id,
            'categories_count' => count($categories),
            'sync_data' => $syncData,
        ]);

        $user->preferredCategories()->sync($syncData);

        $savedCategories = $user->preferredCategories()->get();
        Log::info('Preferred categories saved', [
            'user_id' => $user->id,
            'saved_categories_count' => $savedCategories->count(),
            'saved_category_ids' => $savedCategories->pluck('id')->toArray(),
        ]);

        return $this->successResponse(
            CategoryResource::collection($savedCategories),
            'Preferred categories updated successfully'
        );
    }
}
