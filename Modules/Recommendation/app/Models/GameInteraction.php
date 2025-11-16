<?php

namespace Modules\Recommendation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\User\Models\User;
use Modules\Game\Models\Game;
use Modules\Recommendation\Database\Factories\GameInteractionFactory;

class GameInteraction extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return GameInteractionFactory::new();
    }

    protected $fillable = [
        'user_id',
        'game_id',
        'type',
        'interaction_score',
        'interacted_at',
    ];

    protected $casts = [
        'interaction_score' => 'integer',
        'interacted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
