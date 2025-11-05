<?php

namespace Modules\Game\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\User\Models\Genre;
use Modules\User\Models\Category;

class Game extends Model
{
    use HasFactory;

    protected $fillable = [
        'steam_id',
        'name',
        'type',
        'slug',
        'short_description',
        'required_age',
        'is_free',
        'have_dlc',
        'icon',
        'cover',
        'supported_languages',
        'release_date',
        'coming_soon',
        'recommendations',
        'achievements_count',
        'achievements_highlighted',
        'positive_reviews',
        'negative_reviews',
        'total_reviews',
        'positive_ratio',
        'content_descriptors',
        'is_active',
    ];

    protected $casts = [
        'is_free' => 'boolean',
        'have_dlc' => 'boolean',
        'release_date' => 'date',
        'coming_soon' => 'boolean',
        'recommendations' => 'integer',
        'achievements_count' => 'integer',
        'positive_reviews' => 'integer',
        'negative_reviews' => 'integer',
        'total_reviews' => 'integer',
        'positive_ratio' => 'decimal:3',
        'supported_languages' => 'array',
        'content_descriptors' => 'array',
        'achievements_highlighted' => 'array',
        'is_active' => 'boolean',
    ];

    public function platform(): HasOne
    {
        return $this->hasOne(GamePlatform::class);
    }

    public function requirements(): HasOne
    {
        return $this->hasOne(GameRequirement::class);
    }

    public function communityRating(): HasOne
    {
        return $this->hasOne(GameCommunityRating::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(GameMedia::class);
    }

    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class, 'game_genre')->withTimestamps();
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'game_category')->withTimestamps();
    }

    public function developers(): BelongsToMany
    {
        return $this->belongsToMany(Developer::class, 'game_developer')->withTimestamps();
    }

    public function publishers(): BelongsToMany
    {
        return $this->belongsToMany(Publisher::class, 'game_publisher')->withTimestamps();
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(\Modules\Recommendation\Models\GameInteraction::class);
    }
}
