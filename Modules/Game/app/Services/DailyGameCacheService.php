<?php

namespace Modules\Game\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;

class DailyGameCacheService
{
    private const DAILY_LIMIT = 20;
    private const CACHE_PREFIX = 'user_daily_games:';
    private const COUNT_PREFIX = 'user_daily_count:';

    /**
     * Obtém a chave de cache para os jogos vistos hoje por um usuário
     */
    private function getTodayCacheKey(int $userId): string
    {
        $today = today()->format('Y-m-d');
        return self::CACHE_PREFIX . "{$userId}:{$today}";
    }

    /**
     * Obtém a chave de cache para a contagem de jogos vistos hoje
     */
    private function getCountCacheKey(int $userId): string
    {
        $today = today()->format('Y-m-d');
        return self::COUNT_PREFIX . "{$userId}:{$today}";
    }

    /**
     * Calcula quantos segundos faltam até o final do dia (para TTL do cache)
     */
    private function getSecondsUntilMidnight(): int
    {
        $now = Carbon::now();
        $midnight = Carbon::today()->addDay();
        return (int) $midnight->diffInSeconds($now);
    }

    /**
     * Conta quantos jogos o usuário já viu hoje (com cache)
     */
    public function countToday(int $userId): int
    {
        $cacheKey = $this->getCountCacheKey($userId);
        
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            Log::debug('Daily game count cache hit', [
                'user_id' => $userId,
                'cache_key' => $cacheKey,
                'count' => (int) $cached
            ]);
            return (int) $cached;
        }

        Log::debug('Daily game count cache miss', [
            'user_id' => $userId,
            'cache_key' => $cacheKey
        ]);

        $count = \Modules\Game\Models\UserDailyGame::where('user_id', $userId)
            ->where('date', today())
            ->count();

        Cache::put($cacheKey, $count, $this->getSecondsUntilMidnight());

        Log::debug('Daily game count cached', [
            'user_id' => $userId,
            'count' => $count,
            'cache_key' => $cacheKey
        ]);

        return $count;
    }

    /**
     * Obtém os IDs dos jogos que o usuário já viu hoje (com cache)
     */
    public function getTodayGameIds(int $userId): array
    {
        $cacheKey = $this->getTodayCacheKey($userId);
        
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            Log::debug('Daily game IDs cache hit', [
                'user_id' => $userId,
                'cache_key' => $cacheKey,
                'game_ids_count' => count($cached)
            ]);
            return $cached;
        }

        Log::debug('Daily game IDs cache miss', [
            'user_id' => $userId,
            'cache_key' => $cacheKey
        ]);

        $gameIds = \Modules\Game\Models\UserDailyGame::where('user_id', $userId)
            ->where('date', today())
            ->pluck('game_id')
            ->toArray();

        Cache::put($cacheKey, $gameIds, $this->getSecondsUntilMidnight());

        Log::debug('Daily game IDs cached', [
            'user_id' => $userId,
            'game_ids_count' => count($gameIds),
            'cache_key' => $cacheKey
        ]);

        return $gameIds;
    }

    /**
     * Verifica se um usuário já viu um jogo hoje (otimizado com Redis SET)
     */
    public function hasSeenToday(int $userId, int $gameId): bool
    {
        $cacheKey = $this->getTodayCacheKey($userId);
        
        if (config('cache.default') === 'redis') {
            return Redis::sismember($cacheKey, $gameId);
        }

        $gameIds = $this->getTodayGameIds($userId);
        return in_array($gameId, $gameIds);
    }

    /**
     * Marca um jogo como visto hoje (atualiza cache e banco)
     */
    public function markAsSeen(int $userId, int $gameId): void
    {
        try {
            Log::info('Marking game as seen', [
                'user_id' => $userId,
                'game_id' => $gameId
            ]);

            \Modules\Game\Models\UserDailyGame::firstOrCreate([
                'user_id' => $userId,
                'game_id' => $gameId,
                'date' => today(),
            ]);

            $this->invalidateCache($userId);
            
            $cacheKey = $this->getTodayCacheKey($userId);
            $countKey = $this->getCountCacheKey($userId);
            $ttl = $this->getSecondsUntilMidnight();

            if (config('cache.default') === 'redis') {
                Redis::sadd($cacheKey, $gameId);
                Redis::expire($cacheKey, $ttl);
                
                $newCount = Redis::incr($countKey);
                Redis::expire($countKey, $ttl);

                Log::debug('Game marked as seen in Redis', [
                    'user_id' => $userId,
                    'game_id' => $gameId,
                    'new_count' => $newCount
                ]);
            } else {
                $gameIds = $this->getTodayGameIds($userId);
                if (!in_array($gameId, $gameIds)) {
                    $gameIds[] = $gameId;
                    Cache::put($cacheKey, $gameIds, $ttl);
                    
                    $count = count($gameIds);
                    Cache::put($countKey, $count, $ttl);

                    Log::debug('Game marked as seen in cache', [
                        'user_id' => $userId,
                        'game_id' => $gameId,
                        'new_count' => $count
                    ]);
                }
            }

            Log::info('Game marked as seen successfully', [
                'user_id' => $userId,
                'game_id' => $gameId
            ]);
        } catch (\Exception $e) {
            Log::error('Error marking game as seen', [
                'user_id' => $userId,
                'game_id' => $gameId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Marca múltiplos jogos como vistos (otimizado para batch)
     */
    public function markMultipleAsSeen(int $userId, array $gameIds): void
    {
        if (empty($gameIds)) {
            return;
        }

        $records = [];
        $today = today();
        
        foreach ($gameIds as $gameId) {
            $records[] = [
                'user_id' => $userId,
                'game_id' => $gameId,
                'date' => $today,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        \Modules\Game\Models\UserDailyGame::insertOrIgnore($records);

        $this->invalidateCache($userId);
        
        $cacheKey = $this->getTodayCacheKey($userId);
        $countKey = $this->getCountCacheKey($userId);
        $ttl = $this->getSecondsUntilMidnight();

        if (config('cache.default') === 'redis') {
            if (!empty($gameIds)) {
                Redis::sadd($cacheKey, ...$gameIds);
                Redis::expire($cacheKey, $ttl);
                
                $newCount = Redis::incrby($countKey, count($gameIds));
                Redis::expire($countKey, $ttl);
            }
        } else {
            $this->getTodayGameIds($userId);
        }
    }

    /**
     * Invalida o cache de um usuário (útil quando há mudanças)
     */
    public function invalidateCache(int $userId): void
    {
        $cacheKey = $this->getTodayCacheKey($userId);
        $countKey = $this->getCountCacheKey($userId);
        
        Log::debug('Invalidating daily game cache', [
            'user_id' => $userId,
            'cache_key' => $cacheKey,
            'count_key' => $countKey
        ]);

        Cache::forget($cacheKey);
        Cache::forget($countKey);
        
        if (config('cache.default') === 'redis') {
            Redis::del($cacheKey);
            Redis::del($countKey);

            Log::debug('Cache invalidated in Redis', [
                'user_id' => $userId,
                'cache_key' => $cacheKey,
                'count_key' => $countKey
            ]);
        } else {
            Log::debug('Cache invalidated', [
                'user_id' => $userId,
                'cache_key' => $cacheKey,
                'count_key' => $countKey
            ]);
        }
    }

    /**
     * Verifica se o usuário atingiu o limite diário
     */
    public function hasReachedLimit(int $userId): bool
    {
        $count = $this->countToday($userId);
        $reached = $count >= self::DAILY_LIMIT;

        Log::debug('Daily limit check', [
            'user_id' => $userId,
            'count' => $count,
            'daily_limit' => self::DAILY_LIMIT,
            'reached' => $reached
        ]);

        return $reached;
    }

    public function getRemainingToday(int $userId): int
    {
        $seen = $this->countToday($userId);
        $remaining = max(0, self::DAILY_LIMIT - $seen);

        Log::debug('Remaining games today calculated', [
            'user_id' => $userId,
            'seen' => $seen,
            'daily_limit' => self::DAILY_LIMIT,
            'remaining' => $remaining
        ]);

        return $remaining;
    }

    /**
     * Retorna o limite diário configurado
     */
    public function getDailyLimit(): int
    {
        return self::DAILY_LIMIT;
    }

    /**
     * Limpa os registros diários do usuário (útil para ambiente de desenvolvimento)
     */
    public function clearUserData(int $userId): void
    {
        Log::warning('Clearing user daily game data', [
            'user_id' => $userId
        ]);

        $deleted = \Modules\Game\Models\UserDailyGame::where('user_id', $userId)->delete();
        $this->invalidateCache($userId);

        Log::warning('User daily game data cleared', [
            'user_id' => $userId,
            'deleted_count' => $deleted
        ]);
    }
}

