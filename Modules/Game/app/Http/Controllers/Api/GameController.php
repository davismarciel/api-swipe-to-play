<?php

namespace Modules\Game\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Game\Models\Game;
use Modules\Game\Models\UserDailyGame;
use Modules\Game\Services\DailyGameCacheService;
use Modules\Game\Http\Resources\GameResource;
use Modules\User\Http\Resources\GenreResource;
use Modules\User\Http\Resources\CategoryResource;

class GameController extends Controller
{
    private const DAILY_GAME_LIMIT = 20;

    public function __construct(
        private DailyGameCacheService $dailyGameCache
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'sometimes|string|max:255',
            'genre_id' => 'sometimes|exists:genres,id',
            'category_id' => 'sometimes|exists:categories,id',
            'is_free' => 'sometimes|boolean',
            'platform' => 'sometimes|in:windows,mac,linux',
            'per_page' => 'sometimes|integer|min:1|max:50',
            'skip_daily_limit' => 'sometimes|boolean',
        ]);

        $user = $request->user();
        $skipDailyLimit = $request->input('skip_daily_limit', false);

        Log::info('Game list requested', [
            'user_id' => $user->id ?? null,
            'search' => $request->input('search'),
            'genre_id' => $request->input('genre_id'),
            'category_id' => $request->input('category_id'),
            'is_free' => $request->input('is_free'),
            'platform' => $request->input('platform'),
            'per_page' => $request->input('per_page', 20),
            'skip_daily_limit' => $skipDailyLimit,
            'ip' => $request->ip()
        ]);

        $gamesSeenToday = 0;
        $remainingToday = $request->input('per_page', 20);

        if (!$skipDailyLimit && $user) {
            $gamesSeenToday = $this->dailyGameCache->countToday($user->id);

            if ($this->dailyGameCache->hasReachedLimit($user->id)) {
                Log::warning('Daily game limit reached', [
                    'user_id' => $user->id,
                    'games_seen_today' => $gamesSeenToday,
                    'daily_limit' => self::DAILY_GAME_LIMIT
                ]);

                return $this->buildLimitReachedResponse($gamesSeenToday);
            }

            $remainingToday = min(
                $this->dailyGameCache->getRemainingToday($user->id),
                $request->input('per_page', 20)
            );
        }

        $query = Game::query()->where('is_active', true);

        if (!$skipDailyLimit && $user) {
            $seenGameIds = $this->dailyGameCache->getTodayGameIds($user->id);
            if (!empty($seenGameIds)) {
                $query->whereNotIn('id', $seenGameIds);
            }
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('short_description', 'ilike', "%{$search}%");
            });
        }

        if ($request->has('genre_id')) {
            $query->whereHas('genres', function ($q) use ($request) {
                $q->where('genres.id', $request->input('genre_id'));
            });
        }

        if ($request->has('category_id')) {
            $query->whereHas('categories', function ($q) use ($request) {
                $q->where('categories.id', $request->input('category_id'));
            });
        }

        if ($request->has('is_free')) {
            $query->where('is_free', $request->input('is_free'));
        }

        if ($request->has('platform')) {
            $platform = $request->input('platform');
            $query->whereHas('platform', function ($q) use ($platform) {
                $q->where($platform, true);
            });
        }

        $perPage = $skipDailyLimit
            ? $request->input('per_page', 20)
            : $remainingToday;

        $games = $query->with(['genres', 'categories', 'platform', 'developers', 'publishers', 'communityRating'])
            ->orderBy('positive_reviews', 'desc')
            ->limit($perPage)
            ->get();

        $data = GameResource::collection($games)->resolve();

        Log::info('Game list retrieved successfully', [
            'user_id' => $user->id ?? null,
            'count' => $games->count(),
            'per_page' => $perPage,
            'games_seen_today' => $gamesSeenToday,
            'remaining_today' => $remainingToday
        ]);

        return response()->json([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'current_page' => 1,
                'per_page' => $perPage,
                'total' => $games->count(),
                'from' => $games->count() > 0 ? 1 : null,
                'to' => $games->count(),
                'last_page' => 1,
            ],
            'daily_limit_info' => $this->buildDailyLimitInfo($gamesSeenToday),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        try {
            $user = request()->user();

            Log::info('Game detail requested', [
                'user_id' => $user->id ?? null,
                'game_id' => $id,
                'ip' => request()->ip()
            ]);

            $game = Game::with([
                'developers',
                'publishers',
                'genres',
                'categories',
                'platform',
                'requirements',
                'communityRating',
                'media',
            ])->findOrFail($id);

            Log::info('Game detail retrieved successfully', [
                'user_id' => $user->id ?? null,
                'game_id' => $id,
                'game_name' => $game->name
            ]);

            return $this->successResponse(new GameResource($game));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::warning('Game not found', [
                'game_id' => $id,
                'ip' => request()->ip()
            ]);

            throw $e;
        } catch (\Exception $e) {
            Log::error('Error retrieving game detail', [
                'game_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    public function genres(): JsonResponse
    {
        Log::debug('Genres list requested', [
            'ip' => request()->ip()
        ]);

        $genres = \Modules\User\Models\Genre::all();

        Log::debug('Genres list retrieved', [
            'count' => $genres->count()
        ]);

        return $this->successResponse(GenreResource::collection($genres));
    }

    public function categories(): JsonResponse
    {
        Log::debug('Categories list requested', [
            'ip' => request()->ip()
        ]);

        $categories = \Modules\User\Models\Category::all();

        Log::debug('Categories list retrieved', [
            'count' => $categories->count()
        ]);

        return $this->successResponse(CategoryResource::collection($categories));
    }

    private function buildLimitReachedResponse(int $gamesSeenToday): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [],
            'pagination' => [
                'current_page' => 1,
                'per_page' => self::DAILY_GAME_LIMIT,
                'total' => 0,
                'from' => null,
                'to' => null,
                'last_page' => 1,
            ],
            'daily_limit_info' => $this->buildDailyLimitInfo($gamesSeenToday),
            'message' => 'Você já visualizou todos os jogos disponíveis hoje. Volte amanhã para mais recomendações!',
        ]);
    }

    private function buildDailyLimitInfo(int $gamesSeenToday): array
    {
        return [
            'games_seen_today' => $gamesSeenToday,
            'daily_limit' => self::DAILY_GAME_LIMIT,
            'remaining_today' => max(0, self::DAILY_GAME_LIMIT - $gamesSeenToday),
            'limit_reached' => $gamesSeenToday >= self::DAILY_GAME_LIMIT,
        ];
    }
}
