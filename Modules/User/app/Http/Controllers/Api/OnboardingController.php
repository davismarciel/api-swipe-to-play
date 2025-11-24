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
use Modules\Recommendation\Services\Synchronization\Neo4jSynchronizationService;
use Illuminate\Support\Facades\Log;

class OnboardingController extends Controller
{
    public function __construct(
        private Neo4jSynchronizationService $syncService
    ) {}

    /**
     * Complete onboarding by saving all preferences at once
     */
    public function complete(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($request->has('preferences')) {
            $preferencesData = $request->input('preferences', []);
            
            if (!empty($preferencesData)) {
                $preferencesData = array_filter($preferencesData, function ($value) {
                    return $value !== null;
                });

                if (!empty($preferencesData)) {
                    UserPreference::updateOrCreate(
                        ['user_id' => $user->id],
                        $preferencesData
                    );
                }
            }
        }

        if ($request->has('monetization')) {
            $monetizationData = $request->input('monetization', []);
            
            if (!empty($monetizationData)) {
                $monetizationData = array_filter($monetizationData, function ($value) {
                    return $value !== null;
                });

                if (!empty($monetizationData)) {
                    UserMonetizationPreference::updateOrCreate(
                        ['user_id' => $user->id],
                        $monetizationData
                    );
                }
            }
        }

        if ($request->has('genres')) {
            $genres = $request->input('genres', []);
            
            if (is_array($genres) && !empty($genres)) {
                $syncData = [];
                foreach ($genres as $genre) {
                    if (isset($genre['genre_id']) && isset($genre['preference_weight'])) {
                        $syncData[$genre['genre_id']] = ['preference_weight' => $genre['preference_weight']];
                    }
                }
                $user->preferredGenres()->sync($syncData);
            } else {
                $user->preferredGenres()->sync([]);
            }
        }

        if ($request->has('categories')) {
            $categories = $request->input('categories', []);
            
            if (is_array($categories) && !empty($categories)) {
                $syncData = [];
                foreach ($categories as $category) {
                    if (isset($category['category_id']) && isset($category['preference_weight'])) {
                        $syncData[$category['category_id']] = ['preference_weight' => $category['preference_weight']];
                    }
                }
                $user->preferredCategories()->sync($syncData);
            } else {
                $user->preferredCategories()->sync([]);
            }
        }

        $user->onboarding_completed_at = now();
        $user->save();

        $user->load([
            'preferences',
            'monetizationPreferences',
            'preferredGenres' => function ($query) {
                $query->withPivot('preference_weight');
            },
            'preferredCategories',
        ]);

        try {
            $this->syncService->syncUserPreferences($user);
        } catch (\Exception $e) {
            Log::warning('Failed to sync user preferences to Neo4j during onboarding', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }

        return $this->successResponse([
            'preferences' => new UserPreferenceResource($user->preferences),
            'monetization_preferences' => new UserMonetizationPreferenceResource($user->monetizationPreferences),
            'preferred_genres' => GenreResource::collection($user->preferredGenres),
            'preferred_categories' => CategoryResource::collection($user->preferredCategories),
        ], 'Onboarding completed successfully');
    }

    /**
     * Check onboarding status for the authenticated user
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return $this->errorResponse('Unauthenticated', 401);
        }

        $completed = !is_null($user->onboarding_completed_at);
        
        $hasPreferences = $user->preferences()->exists();
        $hasMonetization = $user->monetizationPreferences()->exists();
        $genresCount = $user->preferredGenres()->count();
        $categoriesCount = $user->preferredCategories()->count();

        if (!$completed) {
            $hasAnyPreferences = $hasPreferences || 
                                 $hasMonetization ||
                                 $genresCount > 0 ||
                                 $categoriesCount > 0;
            
            if ($hasAnyPreferences) {
                $user->onboarding_completed_at = now();
                $user->save();
                $completed = true;
            }
        }

        return $this->successResponse([
            'completed' => $completed,
            'has_preferences' => $hasPreferences,
            'has_monetization' => $hasMonetization,
            'genres_count' => $genresCount,
            'categories_count' => $categoriesCount,
        ]);
    }
}

