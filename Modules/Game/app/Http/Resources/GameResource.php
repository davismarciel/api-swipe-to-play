<?php

namespace Modules\Game\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GameResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'steam_id' => $this->steam_id,
            'name' => $this->name,
            'type' => $this->type,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'required_age' => $this->required_age,
            'is_free' => $this->is_free,
            'have_dlc' => $this->have_dlc,
            'icon' => $this->icon,
            'supported_languages' => $this->supported_languages,
            'release_date' => $this->release_date,
            'coming_soon' => $this->coming_soon,
            'recommendations' => $this->recommendations,
            'achievements_count' => $this->achievements_count,
            'positive_reviews' => $this->positive_reviews,
            'negative_reviews' => $this->negative_reviews,
            'total_reviews' => $this->total_reviews,
            'positive_ratio' => $this->positive_ratio,
            'content_descriptors' => $this->content_descriptors,
            'genres' => $this->whenLoaded('genres'),
            'categories' => $this->whenLoaded('categories'),
            'platform' => $this->whenLoaded('platform'),
            'developers' => $this->whenLoaded('developers'),
            'publishers' => $this->whenLoaded('publishers'),
            'requirements' => $this->whenLoaded('requirements'),
            'community_rating' => $this->whenLoaded('communityRating')
                ? ($this->communityRating ? new CommunityRatingResource($this->communityRating) : null)
                : null,
            'media' => $this->whenLoaded('media')
                ? GameMediaResource::collection(
                    $this->media->filter(function ($media) {
                        // Filter out videos - only return images/screenshots
                        return empty($media->mp4) && empty($media->webm) && empty($media->hls_h264);
                    })
                )
                : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
