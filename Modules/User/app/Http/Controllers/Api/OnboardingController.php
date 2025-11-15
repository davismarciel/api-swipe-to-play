<?php

namespace Modules\User\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\User\Models\UserPreference;
use Modules\User\Models\UserMonetizationPreference;
use Modules\Recommendation\Contracts\RecommendationEngineInterface;
use Modules\Game\Http\Resources\GameResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OnboardingController extends Controller
{
    public function __construct(
        private RecommendationEngineInterface $recommendationEngine
    ) {}

    /**
     * Salva todas as preferências do onboarding de uma vez
     * 
     * Endpoint completo para onboarding que salva:
     * - Preferências gerais
     * - Preferências de monetização
     * - Gêneros preferidos
     * - Categorias preferidas
     */
    public function complete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'preferences' => 'sometimes|array',
            'preferences.prefer_windows' => 'sometimes|boolean',
            'preferences.prefer_mac' => 'sometimes|boolean',
            'preferences.prefer_linux' => 'sometimes|boolean',
            'preferences.preferred_languages' => 'sometimes|array',
            'preferences.prefer_single_player' => 'sometimes|boolean',
            'preferences.prefer_multiplayer' => 'sometimes|boolean',
            'preferences.prefer_coop' => 'sometimes|boolean',
            'preferences.prefer_competitive' => 'sometimes|boolean',
            'preferences.min_age_rating' => 'sometimes|integer|min:0|max:18',
            'preferences.avoid_violence' => 'sometimes|boolean',
            'preferences.avoid_nudity' => 'sometimes|boolean',
            'preferences.include_early_access' => 'sometimes|boolean',

            'monetization' => 'sometimes|array',
            'monetization.tolerance_microtransactions' => 'sometimes|integer|min:0|max:10',
            'monetization.tolerance_dlc' => 'sometimes|integer|min:0|max:10',
            'monetization.tolerance_season_pass' => 'sometimes|integer|min:0|max:10',
            'monetization.tolerance_loot_boxes' => 'sometimes|integer|min:0|max:10',
            'monetization.tolerance_battle_pass' => 'sometimes|integer|min:0|max:10',
            'monetization.tolerance_ads' => 'sometimes|integer|min:0|max:10',
            'monetization.tolerance_pay_to_win' => 'sometimes|integer|min:0|max:10',
            'monetization.prefer_cosmetic_only' => 'sometimes|boolean',
            'monetization.avoid_subscription' => 'sometimes|boolean',
            'monetization.prefer_one_time_purchase' => 'sometimes|boolean',

            'genres' => 'sometimes|array',
            'genres.*.genre_id' => 'required_with:genres|exists:genres,id',
            'genres.*.preference_weight' => 'required_with:genres|integer|min:1|max:10',

            'categories' => 'sometimes|array',
            'categories.*.category_id' => 'required_with:categories|exists:categories,id',
            'categories.*.preference_weight' => 'required_with:categories|integer|min:1|max:10',
        ]);

        $user = $request->user();

        try {
            DB::beginTransaction();

            if (isset($validated['preferences'])) {
                $prefData = array_filter($validated['preferences'], fn($v) => $v !== null);
                if (!empty($prefData)) {
                    UserPreference::updateOrCreate(
                        ['user_id' => $user->id],
                        $prefData
                    );
                }
            }

            if (isset($validated['monetization'])) {
                $monetData = array_filter($validated['monetization'], fn($v) => $v !== null);
                if (!empty($monetData)) {
                    UserMonetizationPreference::updateOrCreate(
                        ['user_id' => $user->id],
                        $monetData
                    );
                }
            }

            if (isset($validated['genres'])) {
                $genreSyncData = [];
                foreach ($validated['genres'] as $genre) {
                    $genreSyncData[$genre['genre_id']] = ['preference_weight' => $genre['preference_weight']];
                }
                $user->preferredGenres()->sync($genreSyncData);
            }

            if (isset($validated['categories'])) {
                $categorySyncData = [];
                foreach ($validated['categories'] as $category) {
                    $categorySyncData[$category['category_id']] = ['preference_weight' => $category['preference_weight']];
                }
                $user->preferredCategories()->sync($categorySyncData);
            }

            DB::commit();

            $user->load([
                'preferences',
                'monetizationPreferences',
                'preferredGenres',
                'preferredCategories',
            ]);

            Log::info('Onboarding completed', [
                'user_id' => $user->id,
                'has_preferences' => $user->preferences !== null,
                'has_monetization' => $user->monetizationPreferences !== null,
                'genres_count' => $user->preferredGenres->count(),
                'categories_count' => $user->preferredCategories->count(),
            ]);

            return $this->successResponse([
                'message' => 'Onboarding completed successfully',
                'preferences' => $user->preferences,
                'monetization_preferences' => $user->monetizationPreferences,
                'preferred_genres' => $user->preferredGenres,
                'preferred_categories' => $user->preferredCategories,
            ], 'Onboarding completed successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error completing onboarding', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse(
                'Failed to complete onboarding. Please try again.',
                500
            );
        }
    }

    /**
     * Gera recomendações iniciais baseadas nas preferências do onboarding
     * 
     * Usa as preferências salvas para gerar recomendações personalizadas
     * mesmo antes do usuário ter interações suficientes para um perfil comportamental
     */
    public function getInitialRecommendations(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => 'sometimes|integer|min:1|max:50',
        ]);

        $user = $request->user();
        $limit = $validated['limit'] ?? 20;

        $user->load([
            'preferences',
            'monetizationPreferences',
            'preferredGenres',
            'preferredCategories',
        ]);

        $hasPreferences = $user->preferences !== null;
        $hasGenres = $user->preferredGenres->count() > 0;

        if (!$hasPreferences && !$hasGenres) {
            Log::warning('Initial recommendations requested without onboarding completion', [
                'user_id' => $user->id,
            ]);

            return $this->errorResponse(
                'Please complete onboarding first to get personalized recommendations.',
                400
            );
        }

        $recommendations = $this->recommendationEngine->getRecommendations($user, $limit);

        Log::info('Initial recommendations generated', [
            'user_id' => $user->id,
            'limit' => $limit,
            'count' => $recommendations->count(),
            'has_preferences' => $hasPreferences,
            'has_genres' => $hasGenres,
        ]);

        return $this->successResponse([
            'recommendations' => GameResource::collection($recommendations),
            'count' => $recommendations->count(),
            'limit' => $limit,
            'is_initial' => true,
            'message' => 'These recommendations are based on your onboarding preferences. They will improve as you interact with games.',
        ]);
    }

    /**
     * Verifica se o usuário completou o onboarding
     */
    public function checkStatus(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->load([
            'preferences',
            'monetizationPreferences',
            'preferredGenres',
            'preferredCategories',
        ]);

        $completed = (
            $user->preferences !== null ||
            $user->preferredGenres->count() > 0 ||
            $user->preferredCategories->count() > 0
        );

        Log::debug('Onboarding status checked', [
            'user_id' => $user->id,
            'completed' => $completed,
            'has_preferences' => $user->preferences !== null,
            'has_monetization' => $user->monetizationPreferences !== null,
            'genres_count' => $user->preferredGenres->count(),
            'categories_count' => $user->preferredCategories->count(),
        ]);

        return $this->successResponse([
            'completed' => $completed,
            'has_preferences' => $user->preferences !== null,
            'has_monetization' => $user->monetizationPreferences !== null,
            'genres_count' => $user->preferredGenres->count(),
            'categories_count' => $user->preferredCategories->count(),
        ]);
    }
}

