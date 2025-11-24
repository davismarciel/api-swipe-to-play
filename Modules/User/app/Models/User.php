<?php

namespace Modules\User\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Modules\User\Database\Factories\UserFactory;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, SoftDeletes;

    protected static function newFactory()
    {
        return UserFactory::new();
    }

    protected $fillable = [
        'google_id',
        'name',
        'email',
        'avatar',
        'provider',
        'onboarding_completed_at',
    ];

    protected $hidden = [
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'onboarding_completed_at' => 'datetime',
        ];
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function preferences(): HasOne
    {
        return $this->hasOne(UserPreference::class);
    }

    public function monetizationPreferences(): HasOne
    {
        return $this->hasOne(UserMonetizationPreference::class);
    }

    public function preferredGenres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class, 'user_preferred_genres')
            ->withPivot('preference_weight')
            ->withTimestamps();
    }

    public function preferredCategories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'user_preferred_categories')
            ->withPivot('preference_weight')
            ->withTimestamps();
    }

    public function gameInteractions(): HasMany
    {
        return $this->hasMany(\Modules\Recommendation\Models\GameInteraction::class);
    }
}
