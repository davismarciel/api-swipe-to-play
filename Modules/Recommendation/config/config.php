<?php

return [
    'name' => 'Recommendation',
    
    'behavior_analysis' => [
        'interaction_limit' => env('RECOMMENDATION_INTERACTION_LIMIT', 50),
        'update_threshold' => env('RECOMMENDATION_UPDATE_THRESHOLD', 5),
        'days_threshold' => env('RECOMMENDATION_DAYS_THRESHOLD', 7),
        'min_interactions_for_profile' => env('RECOMMENDATION_MIN_INTERACTIONS', 3),
    ],
    
    'scoring' => [
        'default_weights' => [
            'novice' => [
                'genre_match' => 40,
                'category_match' => 20,
                'platform_match' => 10,
                'popularity' => 20,
                'rating' => 10,
            ],
            'intermediate' => [
                'genre_match' => 30,
                'category_match' => 20,
                'platform_match' => 10,
                'developer_match' => 15,
                'community_health' => 15,
                'popularity' => 10,
            ],
            'advanced' => [
                'genre_match' => 25,
                'category_match' => 20,
                'platform_match' => 5,
                'developer_match' => 20,
                'community_health' => 15,
                'maturity_match' => 10,
                'rating' => 5,
            ],
        ],
    ],
    
    'diversification' => [
        'max_per_genre_percentage' => env('RECOMMENDATION_MAX_GENRE_PERCENTAGE', 0.4),
        'candidate_multiplier' => env('RECOMMENDATION_CANDIDATE_MULTIPLIER', 5),
    ],
    
    'cache' => [
        'enabled' => env('RECOMMENDATION_CACHE_ENABLED', true),
        'ttl' => env('RECOMMENDATION_CACHE_TTL', 86400),
    ],
    
    'rate_limits' => [
        'recommendations' => env('RECOMMENDATION_RATE_LIMIT', '60,1'), 
        'interactions' => env('RECOMMENDATION_INTERACTION_RATE_LIMIT', '100,1'), 
    ],
];
