<?php

return [
    'name' => 'Recommendation',
    
    'cache' => [
        'enabled' => env('RECOMMENDATION_CACHE_ENABLED', true),
        'ttl' => env('RECOMMENDATION_CACHE_TTL', 86400),
    ],
    
    'rate_limits' => [
        'recommendations' => env('RECOMMENDATION_RATE_LIMIT', '60,1'), 
        'interactions' => env('RECOMMENDATION_INTERACTION_RATE_LIMIT', '100,1'), 
    ],
];
