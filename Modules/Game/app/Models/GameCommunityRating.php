<?php

namespace Modules\Game\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameCommunityRating extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_id',
        'toxicity_rate',
        'cheater_rate',
        'bug_rate',
        'microtransaction_rate',
        'bad_optimization_rate',
        'not_recommended_rate',
    ];

    protected $casts = [
        'toxicity_rate' => 'decimal:3',
        'cheater_rate' => 'decimal:3',
        'bug_rate' => 'decimal:3',
        'microtransaction_rate' => 'decimal:3',
        'bad_optimization_rate' => 'decimal:3',
        'not_recommended_rate' => 'decimal:3',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
