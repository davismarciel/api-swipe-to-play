<?php

namespace Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Game\Models\Game;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_preferred_categories')
            ->withPivot('preference_weight')
            ->withTimestamps();
    }

    public function games(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'game_category')->withTimestamps();
    }
}
