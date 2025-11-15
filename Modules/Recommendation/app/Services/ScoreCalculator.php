<?php

namespace Modules\Recommendation\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\Game\Models\Game;
use Modules\User\Models\User;
use Modules\Recommendation\Models\UserBehaviorProfile;
use Modules\Recommendation\Contracts\ScoreCalculatorInterface;
use Modules\Recommendation\Contracts\BehaviorAnalysisServiceInterface;

class ScoreCalculator implements ScoreCalculatorInterface
{
    public function __construct(
        private BehaviorAnalysisServiceInterface $behaviorAnalysis
    ) {}

    public function calculateScore(User $user, Game $game): float
    {
        $profile = $user->behaviorProfile;
        
        if ($profile) {
            return $this->calculateScoreWithProfile($user, $game, $profile);
        }

        return $this->calculateDefaultScore($user, $game);
    }

    /**
     * Calcula o score de recomendação para um jogo baseado no perfil comportamental do usuário.
     *
     * Este método utiliza múltiplos fatores para calcular um score final:
     * - Match de gêneros preferidos pelo usuário
     * - Match de categorias preferidas
     * - Compatibilidade de plataforma
     * - Histórico com desenvolvedores/publishers
     * - Saúde da comunidade (toxicity, cheaters, etc.)
     * - Preferência free-to-play vs pago
     * - Tolerância a conteúdo maduro
     *
     * @param User $user Usuário para quem calcular o score
     * @param Game $game Jogo a ser avaliado
     * @param UserBehaviorProfile $profile Perfil comportamental do usuário (deve estar atualizado)
     * @return float Score entre 0.0 e 100.0, onde valores mais altos indicam melhor match
     * @throws \InvalidArgumentException Se os parâmetros não forem válidos
     * @throws \RuntimeException Se houver erro ao calcular algum componente do score
     */
    public function calculateScoreWithProfile(User $user, Game $game, UserBehaviorProfile $profile): float
    {
        if (!$user || !$game || !$profile) {
            throw new \InvalidArgumentException('User, Game and Profile are required');
        }
        
        if ($profile->user_id !== $user->id) {
            throw new \InvalidArgumentException('Profile does not belong to the specified user');
        }
        
        if (!$profile->last_analyzed_at) {
            Log::warning('Using unanalyzed profile', [
                'user_id' => $user->id,
                'profile_id' => $profile->id
            ]);
        }
        
        $weights = $profile->adaptive_weights ?? $this->getDefaultWeights($profile->total_interactions);
        
        if (empty($weights)) {
            Log::warning('Empty weights, using defaults', ['user_id' => $user->id]);
            $weights = $this->getDefaultWeights($profile->total_interactions);
        }
        
        $scores = [];

        if (isset($weights['genre_match'])) {
            $scores['genre_match'] = $this->calculateGenreScore($user, $game, $profile);
        }
        
        if (isset($weights['category_match'])) {
            $scores['category_match'] = $this->calculateCategoryScore($user, $game, $profile);
        }
        
        if (isset($weights['platform_match'])) {
            $scores['platform_match'] = $this->calculatePlatformScore($user, $game);
        }
        
        if (isset($weights['developer_match'])) {
            $scores['developer_match'] = $this->calculateDeveloperPublisherScore($game, $profile, $user);
        }
        
        if (isset($weights['community_health'])) {
            $scores['community_health'] = $this->calculateCommunityHealthScore($game, $profile);
        }
        
        if (isset($weights['free_to_play'])) {
            $scores['free_to_play'] = $this->calculateFreeToPlayScore($game, $profile);
        }
        
        if (isset($weights['popularity'])) {
            $scores['popularity'] = $this->calculatePopularityScore($game);
        }
        
        if (isset($weights['rating'])) {
            $scores['rating'] = $this->calculateRatingScore($game);
        }
        
        if (isset($weights['maturity_match'])) {
            $scores['maturity_match'] = $this->calculateMaturityScore($game, $profile);
        }

        $finalScore = 0;
        foreach ($scores as $key => $score) {
            $weight = $weights[$key] ?? 0;
            $finalScore += $score * ($weight / 100);
        }

        $finalScore = $this->applyPenalizations($finalScore, $game, $profile, $user);

        return round($finalScore, 2);
    }

    /**
     * Calcula score genérico para entidades (gêneros, categorias)
     * 
     * @param array $gameEntityIds IDs das entidades do jogo
     * @param array $likedStats Estatísticas de entidades gostadas pelo usuário
     * @param array $dislikedStats Estatísticas de entidades rejeitadas pelo usuário
     * @param float $defaultScore Score padrão quando não há dados
     * @param float $unknownScore Score para entidades desconhecidas
     * @param float $dislikePenalty Penalização por entidades rejeitadas
     * @return float Score entre 0 e 100
     */
    private function calculateEntityScore(
        array $gameEntityIds,
        array $likedStats,
        array $dislikedStats,
        float $defaultScore = 50,
        float $unknownScore = 30,
        float $dislikePenalty = 30
    ): float {
        if (empty($gameEntityIds)) {
            return $defaultScore;
        }

        if (empty($likedStats) && empty($dislikedStats)) {
            return $defaultScore;
        }

        $score = 0;
        $matchCount = 0;

        foreach ($gameEntityIds as $entityId) {
            if (isset($likedStats[$entityId])) {
                $stats = $likedStats[$entityId];
                $score += $stats['weighted_score'] ?? $defaultScore;
                $matchCount++;
            } elseif (isset($dislikedStats[$entityId])) {
                $score -= $dislikePenalty;
                $matchCount++;
            }
        }

        if ($matchCount === 0) {
            return $unknownScore;
        }

        return max(0, min(100, $score / $matchCount));
    }

    /**
     * Score de gêneros baseado no perfil comportamental
     */
    private function calculateGenreScore(User $user, Game $game, UserBehaviorProfile $profile): float
    {
        $gameGenres = $game->genres()->pluck('genres.id')->toArray();
        $likedStats = $profile->liked_genres_stats ?? [];
        $dislikedStats = $profile->disliked_genres_stats ?? [];
        
        return $this->calculateEntityScore($gameGenres, $likedStats, $dislikedStats);
    }

    /**
     * Score de categorias baseado no perfil comportamental
     */
    private function calculateCategoryScore(User $user, Game $game, UserBehaviorProfile $profile): float
    {
        $gameCategories = $game->categories()->pluck('categories.id')->toArray();
        $likedStats = $profile->liked_categories_stats ?? [];
        $dislikedStats = $profile->disliked_categories_stats ?? [];
        
        return $this->calculateEntityScore($gameCategories, $likedStats, $dislikedStats);
    }

    /**
     * Score de plataforma (mantido simples)
     */
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

    /**
     * NOVO: Score de desenvolvedores e publishers
     */
    private function calculateDeveloperPublisherScore(Game $game, UserBehaviorProfile $profile, User $user): float
    {
        $score = 50; // Neutro
        
        $gameDevelopers = $game->developers->pluck('id')->toArray();
        $gamePublishers = $game->publishers->pluck('id')->toArray();
        
        $topDevelopers = $profile->top_developers ?? [];
        $topPublishers = $profile->top_publishers ?? [];
        
        foreach ($gameDevelopers as $devId) {
            if (isset($topDevelopers[$devId])) {
                $score += min(25, $topDevelopers[$devId] * 5);
            }
        }
        
        foreach ($gamePublishers as $pubId) {
            if (isset($topPublishers[$pubId])) {
                $score += min(15, $topPublishers[$pubId] * 3);
            }
        }
        
        $rejectedDevs = $this->behaviorAnalysis->getRejectedDevelopers($user);
        $rejectedPubs = $this->behaviorAnalysis->getRejectedPublishers($user);
        
        foreach ($gameDevelopers as $devId) {
            if (in_array($devId, $rejectedDevs)) {
                $score -= 30;
            }
        }
        
        foreach ($gamePublishers as $pubId) {
            if (in_array($pubId, $rejectedPubs)) {
                $score -= 20;
            }
        }
        
        return max(0, min(100, $score));
    }

    /**
     * NOVO: Score de saúde comunitária (CORRIGIDO)
     */
    private function calculateCommunityHealthScore(Game $game, UserBehaviorProfile $profile): float
    {
        $rating = $game->communityRating;
        if (!$rating) return 50;
        
        $penalties = [
            ['rate' => $rating->toxicity_rate, 'tolerance' => $profile->toxicity_tolerance, 'weight' => 20],
            ['rate' => $rating->cheater_rate, 'tolerance' => $profile->cheater_tolerance, 'weight' => 20],
            ['rate' => $rating->bug_rate, 'tolerance' => $profile->bug_tolerance, 'weight' => 15],
            ['rate' => $rating->microtransaction_rate, 'tolerance' => $profile->microtransaction_tolerance, 'weight' => 15],
            ['rate' => $rating->bad_optimization_rate, 'tolerance' => $profile->optimization_tolerance, 'weight' => 15],
            ['rate' => $rating->not_recommended_rate, 'tolerance' => $profile->not_recommended_tolerance, 'weight' => 15],
        ];
        
        $totalPenalty = 0;
        foreach ($penalties as $p) {
            if ($p['rate'] > $p['tolerance']) {
                $totalPenalty += ($p['rate'] - $p['tolerance']) * $p['weight'];
            }
        }
        
        return max(0, 100 - $totalPenalty);
    }

    /**
     * NOVO: Score de alinhamento free-to-play
     */
    private function calculateFreeToPlayScore(Game $game, UserBehaviorProfile $profile): float
    {
        $preference = $profile->free_to_play_preference;
        $isFree = $game->is_free;

        if (abs($preference) < 0.2) {
            return 50;
        }

        if ($preference > 0) {
            return $isFree ? 100 : max(0, 50 - ($preference * 50));
        }

        return $isFree ? max(0, 50 + ($preference * 50)) : 100;
    }

    /**
     * Score de popularidade
     */
    private function calculatePopularityScore(Game $game): float
    {
        $totalReviews = $game->total_reviews;

        if ($totalReviews === 0) {
            return 50;
        }

        return min(100, ($totalReviews / 1000) * 100);
    }

    /**
     * Score de avaliação
     */
    private function calculateRatingScore(Game $game): float
    {
        if ($game->positive_ratio === null) {
            return 50;
        }

        return $game->positive_ratio * 100;
    }

    /**
     * NOVO: Score de maturidade de conteúdo
     */
    private function calculateMaturityScore(Game $game, UserBehaviorProfile $profile): float
    {
        $tolerance = $profile->mature_content_tolerance;
        $gameAge = $game->required_age;

        if ($gameAge >= 17) {
            return $tolerance * 100;
        }

        if ($gameAge === 0) {
            return 100;
        }

        return 70 + ($tolerance * 30);
    }

    /**
     * Aplica penalizações gerais
     */
    private function applyPenalizations(float $score, Game $game, UserBehaviorProfile $profile, User $user): float
    {
        if ($game->total_reviews < 10 && $game->release_date && $game->release_date->diffInYears(now()) > 3) {
            $score *= 0.8;
        }

        if ($game->positive_ratio && $game->positive_ratio < 0.4) {
            $score *= 0.7;
        }

        return $score;
    }

    /**
     * NOVO: Gera explicação inline da recomendação
     */
    public function generateExplanation(float $score, Game $game, UserBehaviorProfile $profile): array
    {
        $reasons = [];

        $likedGenres = $profile->liked_genres_stats ?? [];
        $gameGenres = $game->genres;
        
        $matchedGenres = [];
        foreach ($gameGenres as $genre) {
            if (isset($likedGenres[$genre->id])) {
                $matchedGenres[] = $genre->name;
            }
        }

        if (count($matchedGenres) > 0) {
            $reasons[] = 'Gêneros que você adora: ' . implode(', ', array_slice($matchedGenres, 0, 3));
        }

        $topDevelopers = $profile->top_developers ?? [];
        $gameDevelopers = $game->developers;
        
        foreach ($gameDevelopers as $developer) {
            if (isset($topDevelopers[$developer->id]) && $topDevelopers[$developer->id] >= 2) {
                $reasons[] = 'Desenvolvedor favorito: ' . $developer->name;
                break;
            }
        }

        $communityScore = $this->calculateCommunityHealthScore($game, $profile);
        if ($communityScore >= 70) {
            $reasons[] = 'Comunidade saudável e bem avaliada';
        }

        if ($game->positive_ratio && $game->positive_ratio >= 0.8) {
            $reasons[] = round($game->positive_ratio * 100) . '% de avaliações positivas';
        }

        if (empty($reasons)) {
            $reasons[] = 'Jogo popular e recomendado';
        }

        return [
            'match_percentage' => round($score),
            'top_reasons' => array_slice($reasons, 0, 3),
            'score_breakdown' => $this->getScoreBreakdown($game, $profile),
        ];
    }

    /**
     * Retorna breakdown detalhado dos scores
     */
    private function getScoreBreakdown(Game $game, UserBehaviorProfile $profile): array
    {
        $user = $profile->user;
        
        return [
            'genre' => round($this->calculateGenreScore($user, $game, $profile), 1),
            'category' => round($this->calculateCategoryScore($user, $game, $profile), 1),
            'platform' => round($this->calculatePlatformScore($user, $game), 1),
            'developer' => round($this->calculateDeveloperPublisherScore($game, $profile, $user), 1),
            'community' => round($this->calculateCommunityHealthScore($game, $profile), 1),
            'rating' => round($this->calculateRatingScore($game), 1),
        ];
    }

    /**
     * Score default para usuários sem perfil
     */
    private function calculateDefaultScore(User $user, Game $game): float
    {
        $scores = [
            'platform' => $this->calculatePlatformScore($user, $game),
            'popularity' => $this->calculatePopularityScore($game),
            'rating' => $this->calculateRatingScore($game),
        ];

        $weights = [
            'platform' => 20,
            'popularity' => 40,
            'rating' => 40,
        ];

        $finalScore = 0;
        foreach ($scores as $key => $score) {
            $finalScore += $score * ($weights[$key] / 100);
        }

        return round($finalScore, 2);
    }

    /**
     * Pesos default baseado em total de interações
     * Usa configuração do arquivo config/recommendation.php
     */
    private function getDefaultWeights(int $totalInteractions): array
    {
        $level = $this->determineExperienceLevel($totalInteractions);
        $config = config('recommendation.scoring.default_weights.' . $level, []);
        
        // Fallback para valores hardcoded se configuração não existir
        if (empty($config)) {
            if ($totalInteractions < 20) {
                return [
                    'genre_match' => 40,
                    'category_match' => 20,
                    'platform_match' => 10,
                    'popularity' => 20,
                    'rating' => 10,
                ];
            } elseif ($totalInteractions < 100) {
                return [
                    'genre_match' => 30,
                    'category_match' => 20,
                    'platform_match' => 10,
                    'developer_match' => 15,
                    'community_health' => 15,
                    'popularity' => 10,
                ];
            }

            return [
                'genre_match' => 25,
                'category_match' => 20,
                'platform_match' => 5,
                'developer_match' => 20,
                'community_health' => 15,
                'maturity_match' => 10,
                'rating' => 5,
            ];
        }
        
        return $config;
    }

    /**
     * Determina o nível de experiência baseado em interações
     */
    private function determineExperienceLevel(int $totalInteractions): string
    {
        if ($totalInteractions < 20) {
            return 'novice';
        } elseif ($totalInteractions < 100) {
            return 'intermediate';
        }
        
        return 'advanced';
    }
}
