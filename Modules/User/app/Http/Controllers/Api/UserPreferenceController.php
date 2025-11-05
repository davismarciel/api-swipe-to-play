<?php

namespace Modules\User\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $user->load([
            'preferences',
            'monetizationPreferences',
            'preferredGenres',
            'preferredCategories',
            'profile'
        ]);

        return $this->successResponse([
            'preferences' => new UserPreferenceResource($user->preferences),
            'monetization_preferences' => new UserMonetizationPreferenceResource($user->monetizationPreferences),
            'preferred_genres' => GenreResource::collection($user->preferredGenres),
            'preferred_categories' => CategoryResource::collection($user->preferredCategories),
            'profile' => $user->profile,
        ]);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
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
            'max_price' => 'sometimes|numeric|min:0',
            'prefer_free_to_play' => 'sometimes|boolean',
            'include_early_access' => 'sometimes|boolean',
        ]);

        $user = $request->user();

        $validated = array_filter($validated, function ($value) {
            return $value !== null;
        });

        $preferences = UserPreference::updateOrCreate(
            ['user_id' => $user->id],
            $validated
        );

        return $this->successResponse(new UserPreferenceResource($preferences), 'Preferences updated successfully');
    }

    public function updateMonetizationPreferences(Request $request): JsonResponse
    {
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

        $validated = array_filter($validated, function ($value) {
            return $value !== null;
        });

        $monetizationPreferences = UserMonetizationPreference::updateOrCreate(
            ['user_id' => $user->id],
            $validated
        );

        return $this->successResponse(
            new UserMonetizationPreferenceResource($monetizationPreferences),
            'Monetization preferences updated successfully'
        );
    }

    public function updatePreferredGenres(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'genres' => 'required|array',
            'genres.*.genre_id' => 'required|exists:genres,id',
            'genres.*.preference_weight' => 'required|integer|min:1|max:10',
        ]);

        $user = $request->user();

        $syncData = [];
        foreach ($validated['genres'] as $genre) {
            $syncData[$genre['genre_id']] = ['preference_weight' => $genre['preference_weight']];
        }

        $user->preferredGenres()->sync($syncData);

        return $this->successResponse(
            GenreResource::collection($user->preferredGenres()->get()),
            'Preferred genres updated successfully'
        );
    }

    public function updatePreferredCategories(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'categories' => 'required|array',
            'categories.*.category_id' => 'required|exists:categories,id',
            'categories.*.preference_weight' => 'required|integer|min:1|max:10',
        ]);

        $user = $request->user();

        $syncData = [];
        foreach ($validated['categories'] as $category) {
            $syncData[$category['category_id']] = ['preference_weight' => $category['preference_weight']];
        }

        $user->preferredCategories()->sync($syncData);

        return $this->successResponse(
            CategoryResource::collection($user->preferredCategories()->get()),
            'Preferred categories updated successfully'
        );
    }
}
