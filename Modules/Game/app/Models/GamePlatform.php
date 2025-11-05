<?php

namespace Modules\Game\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GamePlatform extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_id',
        'windows',
        'mac',
        'linux',
    ];

    protected $casts = [
        'windows' => 'boolean',
        'mac' => 'boolean',
        'linux' => 'boolean',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
