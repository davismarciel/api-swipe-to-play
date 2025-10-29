<?php

namespace Modules\Game\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameRequirement extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_id',
        'pc_requirements',
        'mac_requirements',
        'linux_requirements',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
