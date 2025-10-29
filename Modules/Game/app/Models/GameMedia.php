<?php

namespace Modules\Game\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameMedia extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_id',
        'media_id',
        'name',
        'thumbnail',
        'webm',
        'mp4',
        'dash_av1',
        'dash_h264',
        'hls_h264',
        'highlight',
    ];

    protected $casts = [
        'webm' => 'array',
        'mp4' => 'array',
        'highlight' => 'boolean',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
