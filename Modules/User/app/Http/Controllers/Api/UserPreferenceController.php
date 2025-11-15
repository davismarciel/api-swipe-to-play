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

class UserPreferenceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        Log::debug('User preferences requested', [
            'user_id' => $user->id,
            'ip' => $request->ip()
        ]);

        $user->load([
            'preferences',
            'monetizationPreferences',
            'preferredGenres',
            'preferredCategories',
            'profile'
        ]);

        Log::debug('User preferences retrieved', [
            'user_id' => $user->id,
            'has_preferences' => $user->preferences !== null,
            'has_monetization' => $user->monetizationPreferences !== null,
            'genres_count' => $user->preferredGenres->count(),
            'categories_count' => $user->preferredCategories->count()
        ]);

        return $this->successResponse([
            'preferences' => $user->preferences ? new UserPreferenceResource($user->preferences) : null,
            'monetization_preferences' => $user->monetizationPreferences ? new UserMonetizationPreferenceResource($user->monetizationPreferences) : null,
            'preferred_genres' => GenreResource::collection($user->preferredGenres),
            'preferred_categories' => CategoryResource::collection($user->preferredCategories),
            'profile' => $user->profile,
        ]);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'prefer_windows' => 'sometimes|boolean',
                'prefer_mac' => 'sometimes|boolean',
                'prefer_linux' => 'sometimes|boolean',
                'preferred_languages' => 'sometimes|array',
                'prefer_single_player' => 'sometimes|boolean',
                'prefer_multiplayer' => 'sometimes|boolean',
                'prefer_coop' => 'sometimes|boolean',
                'prefer_competitive' => 'sometimes|boolean',
                'min_age_rating' => 'sometimes|integer|min:0|max:18',
                'avoid_violence' => 'sometimes|boolean',
                'avoid_nudity' => 'sometimes|boolean',
                'include_early_access' => 'sometimes|boolean',
            ]);

            $user = $request->user();

            Log::info('User preferences update requested', [
                'user_id' => $user->id,
                'fields' => array_keys($validated),
                'ip' => $request->ip()
            ]);

            $validated = array_filter($validated, function ($value) {
                return $value !== null;
            });

            $preferences = UserPreference::updateOrCreate(
                ['user_id' => $user->id],
                $validated
            );

            Log::info('User preferences updated successfully', [
                'user_id' => $user->id,
                'preference_id' => $preferences->id,
                'updated_fields' => array_keys($validated)
            ]);

            return $this->successResponse(new UserPreferenceResource($preferences), 'Preferences updated successfully');
        } catch (\Exception $e) {
            Log::error('Error updating user preferences', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    public function updateMonetizationPreferences(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'tolerance_microtransactions' => 'sometimes|integer|min:0|max:10',
                'tolerance_dlc' => 'sometimes|integer|min:0|max:10',
                'tolerance_season_pass' => 'sometimes|integer|min:0|max:10',
                'tolerance_loot_boxes' => 'sometimes|integer|min:0|max:10',
                'tolerance_battle_pass' => 'sometimes|integer|min:0|max:10',
                'tolerance_ads' => 'sometimes|integer|min:0|max:10',
                'tolerance_pay_to_win' => 'sometimes|integer|min:0|max:10',
                'prefer_cosmetic_only' => 'sometimes|boolean',
                'avoid_subscription' => 'sometimes|boolean',
                'prefer_one_time_purchase' => 'sometimes|boolean',
            ]);

            $user = $request->user();

            Log::info('Monetization preferences update requested', [
                'user_id' => $user->id,
                'fields' => array_keys($validated),
                'ip' => $request->ip()
            ]);

            $validated = array_filter($validated, function ($value) {
                return $value !== null;
            });

            $monetizationPreferences = UserMonetizationPreference::updateOrCreate(
                ['user_id' => $user->id],
                $validated
            );

            Log::info('Monetization preferences updated successfully', [
                'user_id' => $user->id,
                'preference_id' => $monetizationPreferences->id,
                'updated_fields' => array_keys($validated)
            ]);

            return $this->successResponse(
                new UserMonetizationPreferenceResource($monetizationPreferences),
                'Monetization preferences updated successfully'
            );
        } catch (\Exception $e) {
            Log::error('Error updating monetization preferences', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    public function updatePreferredGenres(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'genres' => 'required|array',
                'genres.*.genre_id' => 'required|exists:genres,id',
                'genres.*.preference_weight' => 'required|integer|min:1|max:10',
            ]);

            $user = $request->user();

            Log::info('Preferred genres update requested', [
                'user_id' => $user->id,
                'genres_count' => count($validated['genres']),
                'ip' => $request->ip()
            ]);

            $syncData = [];
            foreach ($validated['genres'] as $genre) {
                $syncData[$genre['genre_id']] = ['preference_weight' => $genre['preference_weight']];
            }

            $user->preferredGenres()->sync($syncData);

            Log::info('Preferred genres updated successfully', [
                'user_id' => $user->id,
                'genres_count' => count($syncData),
                'genre_ids' => array_keys($syncData)
            ]);

            return $this->successResponse(
                GenreResource::collection($user->preferredGenres()->get()),
                'Preferred genres updated successfully'
            );
        } catch (\Exception $e) {
            Log::error('Error updating preferred genres', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    public function updatePreferredCategories(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'categories' => 'sometimes|array',
                'categories.*.category_id' => 'required_with:categories|exists:categories,id',
                'categories.*.preference_weight' => 'required_with:categories|integer|min:1|max:10',
            ]);

            $user = $request->user();

            $categories = $validated['categories'] ?? [];

            Log::info('Preferred categories update requested', [
                'user_id' => $user->id,
                'categories_count' => count($categories),
                'ip' => $request->ip()
            ]);

            $syncData = [];
            foreach ($categories as $category) {
                $syncData[$category['category_id']] = ['preference_weight' => $category['preference_weight']];
            }

            $user->preferredCategories()->sync($syncData);

            Log::info('Preferred categories updated successfully', [
                'user_id' => $user->id,
                'categories_count' => count($syncData),
                'category_ids' => array_keys($syncData)
            ]);

            return $this->successResponse(
                CategoryResource::collection($user->preferredCategories()->get()),
                'Preferred categories updated successfully'
            );
        } catch (\Exception $e) {
            Log::error('Error updating preferred categories', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }
}
