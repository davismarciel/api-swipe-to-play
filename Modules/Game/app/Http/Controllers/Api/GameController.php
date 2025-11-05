<?php

namespace Modules\Game\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Game\Models\Game;
use Modules\Game\Http\Resources\GameResource;
use Modules\User\Http\Resources\GenreResource;
use Modules\User\Http\Resources\CategoryResource;

class GameController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'sometimes|string|max:255',
            'genre_id' => 'sometimes|exists:genres,id',
            'category_id' => 'sometimes|exists:categories,id',
            'is_free' => 'sometimes|boolean',
            'platform' => 'sometimes|in:windows,mac,linux',
            'per_page' => 'sometimes|integer|min:1|max:50',
        ]);

        $query = Game::query()->where('is_active', true);

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

        $perPage = $request->input('per_page', 15);
        $games = $query->with(['genres', 'categories', 'platform', 'developers', 'publishers', 'communityRating'])
            ->orderBy('positive_reviews', 'desc')
            ->paginate($perPage);

        return $this->paginatedResponse(GameResource::collection($games));
    }

    public function show(int $id): JsonResponse
    {
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

        return $this->successResponse(new GameResource($game));
    }

    public function genres(): JsonResponse
    {
        $genres = \Modules\User\Models\Genre::all();

        return $this->successResponse(GenreResource::collection($genres));
    }

    public function categories(): JsonResponse
    {
        $categories = \Modules\User\Models\Category::all();

        return $this->successResponse(CategoryResource::collection($categories));
    }
}
