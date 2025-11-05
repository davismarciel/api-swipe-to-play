<?php

namespace Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'prefer_windows',
        'prefer_mac',
        'prefer_linux',
        'preferred_languages',
        'prefer_single_player',
        'prefer_multiplayer',
        'prefer_coop',
        'prefer_competitive',
        'min_age_rating',
        'avoid_violence',
        'avoid_nudity',
        'max_price',
        'prefer_free_to_play',
        'include_early_access',
    ];

    protected $casts = [
        'prefer_windows' => 'boolean',
        'prefer_mac' => 'boolean',
        'prefer_linux' => 'boolean',
        'preferred_languages' => 'array',
        'prefer_single_player' => 'boolean',
        'prefer_multiplayer' => 'boolean',
        'prefer_coop' => 'boolean',
        'prefer_competitive' => 'boolean',
        'min_age_rating' => 'integer',
        'avoid_violence' => 'boolean',
        'avoid_nudity' => 'boolean',
        'max_price' => 'decimal:2',
        'prefer_free_to_play' => 'boolean',
        'include_early_access' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
