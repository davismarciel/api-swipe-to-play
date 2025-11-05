<?php

namespace Modules\Game\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class GameCollection extends ResourceCollection
{
    public $collects = GameResource::class;

    public function toArray(Request $request): array
    {
        return [
            'games' => $this->collection,
        ];
    }
}
