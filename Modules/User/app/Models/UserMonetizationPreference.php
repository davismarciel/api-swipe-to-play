<?php

namespace Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserMonetizationPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tolerance_microtransactions',
        'tolerance_dlc',
        'tolerance_season_pass',
        'tolerance_loot_boxes',
        'tolerance_battle_pass',
        'tolerance_ads',
        'tolerance_pay_to_win',
        'prefer_cosmetic_only',
        'avoid_subscription',
        'prefer_one_time_purchase',
    ];

    protected $casts = [
        'tolerance_microtransactions' => 'integer',
        'tolerance_dlc' => 'integer',
        'tolerance_season_pass' => 'integer',
        'tolerance_loot_boxes' => 'integer',
        'tolerance_battle_pass' => 'integer',
        'tolerance_ads' => 'integer',
        'tolerance_pay_to_win' => 'integer',
        'prefer_cosmetic_only' => 'boolean',
        'avoid_subscription' => 'boolean',
        'prefer_one_time_purchase' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
