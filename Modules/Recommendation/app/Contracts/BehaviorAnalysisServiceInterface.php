<?php

namespace Modules\Recommendation\Contracts;

use Modules\User\Models\User;
use Modules\Recommendation\Models\UserBehaviorProfile;

interface BehaviorAnalysisServiceInterface
{
    /**
     * Analisa padrões de gêneros (likes e dislikes)
     * 
     * @param User $user
     * @return array ['liked' => array, 'disliked' => array]
     */
    public function analyzeGenrePatterns(User $user): array;

    /**
     * Analisa padrões de categorias (likes e dislikes)
     * 
     * @param User $user
     * @return array ['liked' => array, 'disliked' => array]
     */
    public function analyzeCategoryPatterns(User $user): array;

    /**
     * Analisa padrões de desenvolvedores
     * 
     * @param User $user
     * @return array {developer_id: interaction_count}
     */
    public function analyzeDeveloperPatterns(User $user): array;

    /**
     * Analisa padrões de publishers
     * 
     * @param User $user
     * @return array {publisher_id: interaction_count}
     */
    public function analyzePublisherPatterns(User $user): array;

    /**
     * Analisa preferência por jogos free-to-play
     * 
     * @param User $user
     * @return float -1.0 a 1.0
     */
    public function analyzeFreeToPlayPreference(User $user): float;

    /**
     * Analisa tolerância a conteúdo maduro
     * 
     * @param User $user
     * @return float 0.0 a 1.0
     */
    public function analyzeMatureContentTolerance(User $user): float;

    /**
     * Analisa tolerâncias comunitárias
     * 
     * @param User $user
     * @return array Todas as tolerâncias
     */
    public function analyzeCommunityTolerances(User $user): array;

    /**
     * Calcula pesos adaptativos baseado nos padrões do usuário
     * 
     * @param User $user
     * @param array $patterns
     * @return array
     */
    public function calculateAdaptiveWeights(User $user, array $patterns): array;

    /**
     * Determina nível de experiência baseado no total de interações
     * 
     * @param int $totalInteractions
     * @return string 'novice', 'intermediate', 'advanced'
     */
    public function determineExperienceLevel(int $totalInteractions): string;

    /**
     * Constrói ou atualiza o perfil comportamental do usuário
     * 
     * @param User $user
     * @param bool $force
     * @return UserBehaviorProfile|null
     */
    public function buildOrUpdateProfile(User $user, bool $force = false): ?UserBehaviorProfile;

    /**
     * Verifica se o perfil deve ser atualizado
     * 
     * @param User $user
     * @return bool
     */
    public function shouldUpdateProfile(User $user): bool;

    /**
     * Incrementa contador de interações
     * 
     * @param User $user
     * @return void
     */
    public function incrementInteractionCounter(User $user): void;

    /**
     * Obtém desenvolvedores rejeitados (calculado dinamicamente)
     * 
     * @param User $user
     * @return array
     */
    public function getRejectedDevelopers(User $user): array;

    /**
     * Obtém publishers rejeitados (calculado dinamicamente)
     * 
     * @param User $user
     * @return array
     */
    public function getRejectedPublishers(User $user): array;
}

