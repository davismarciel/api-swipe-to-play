<?php

namespace Modules\Recommendation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\User\Models\User;

class UserBehaviorProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'total_interactions',
        'liked_genres_stats',
        'disliked_genres_stats',
        'liked_categories_stats',
        'disliked_categories_stats',
        'top_developers',
        'top_publishers',
        'free_to_play_preference',
        'mature_content_tolerance',
        'toxicity_tolerance',
        'cheater_tolerance',
        'bug_tolerance',
        'microtransaction_tolerance',
        'optimization_tolerance',
        'not_recommended_tolerance',
        'adaptive_weights',
        'interactions_since_update',
        'last_analyzed_at',
        'last_interaction_at',
    ];

    protected $casts = [
        'total_interactions' => 'integer',
        'liked_genres_stats' => 'array',
        'disliked_genres_stats' => 'array',
        'liked_categories_stats' => 'array',
        'disliked_categories_stats' => 'array',
        'top_developers' => 'array',
        'top_publishers' => 'array',
        'free_to_play_preference' => 'decimal:2',
        'mature_content_tolerance' => 'decimal:2',
        'toxicity_tolerance' => 'decimal:2',
        'cheater_tolerance' => 'decimal:2',
        'bug_tolerance' => 'decimal:2',
        'microtransaction_tolerance' => 'decimal:2',
        'optimization_tolerance' => 'decimal:2',
        'not_recommended_tolerance' => 'decimal:2',
        'adaptive_weights' => 'array',
        'interactions_since_update' => 'integer',
        'last_analyzed_at' => 'datetime',
        'last_interaction_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Calcula o nível de experiência do usuário em tempo real
     */
    public function getExperienceLevelAttribute(): string
    {
        if ($this->total_interactions < 20) {
            return 'novice';
        } elseif ($this->total_interactions < 100) {
            return 'intermediate';
        }
        
        return 'advanced';
    }

    /**
     * Verifica se o perfil precisa ser atualizado
     */
    public function needsUpdate(): bool
    {
        $updateThreshold = config('recommendation.behavior_analysis.update_threshold', 5);
        $daysThreshold = config('recommendation.behavior_analysis.days_threshold', 7);
        
        // Atualizar a cada N interações (configurável)
        if ($this->interactions_since_update >= $updateThreshold) {
            return true;
        }

        // Atualizar se passou N+ dias desde última análise (configurável)
        if ($this->last_analyzed_at && $this->last_analyzed_at->diffInDays(now()) >= $daysThreshold) {
            return true;
        }

        // Atualizar se nunca foi analisado
        if (!$this->last_analyzed_at) {
            return true;
        }

        return false;
    }

    /**
     * Reseta o contador de interações após análise
     */
    public function markAsAnalyzed(): void
    {
        $this->update([
            'interactions_since_update' => 0,
            'last_analyzed_at' => now(),
        ]);
    }
}

