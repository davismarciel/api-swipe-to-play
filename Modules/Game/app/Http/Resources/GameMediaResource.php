<?php

namespace Modules\Game\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GameMediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Only return images/screenshots - videos are filtered out in GameResource
        // For screenshots/images, use thumbnail
        return [
            'id' => $this->id,
            'type' => 'screenshot',
            'url' => $this->thumbnail,
            'thumbnail' => $this->thumbnail,
            'name' => $this->name,
            'highlight' => $this->highlight,
        ];
    }
}

