<?php

namespace Modules\Game\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\User\Models\User;

class UserDailyGame extends Model
{
    protected $fillable = [
        'user_id',
        'game_id',
        'date',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * Verifica se um usuário já viu um jogo hoje
     */
    public static function hasSeenToday(int $userId, int $gameId): bool
    {
        return self::where('user_id', $userId)
            ->where('game_id', $gameId)
            ->where('date', today())
            ->exists();
    }

    /**
     * Obtém os IDs dos jogos que o usuário já viu hoje
     */
    public static function getTodayGameIds(int $userId): array
    {
        return self::where('user_id', $userId)
            ->where('date', today())
            ->pluck('game_id')
            ->toArray();
    }

    /**
     * Registra que um usuário viu um jogo hoje
     */
    public static function markAsSeen(int $userId, int $gameId): void
    {
        self::firstOrCreate([
            'user_id' => $userId,
            'game_id' => $gameId,
            'date' => today(),
        ]);
    }

    /**
     * Conta quantos jogos o usuário já viu hoje
     */
    public static function countToday(int $userId): int
    {
        return self::where('user_id', $userId)
            ->where('date', today())
            ->count();
    }
}

