<?php

namespace Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'avatar_url',
        'bio',
        'level',
        'experience_points',
        'total_likes',
        'total_dislikes',
        'total_favorites',
        'total_views',
    ];

    protected $casts = [
        'level' => 'integer',
        'experience_points' => 'integer',
        'total_likes' => 'integer',
        'total_dislikes' => 'integer',
        'total_favorites' => 'integer',
        'total_views' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
