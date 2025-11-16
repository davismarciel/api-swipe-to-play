<?php

return [
    'uri' => env('NEO4J_URI', 'bolt://localhost:7687'),
    'username' => env('NEO4J_USERNAME', 'neo4j'),
    'password' => env('NEO4J_PASSWORD', 'password'),
    'database' => env('NEO4J_DATABASE', 'neo4j'),
    
    'connection' => [
        'timeout' => env('NEO4J_TIMEOUT', 30),
        'max_transaction_retry_time' => env('NEO4J_MAX_TRANSACTION_RETRY_TIME', 30),
    ],
    
    'constraints' => [
        'enabled' => env('NEO4J_CONSTRAINTS_ENABLED', true),
    ],
    
    'indexes' => [
        'enabled' => env('NEO4J_INDEXES_ENABLED', true),
    ],
];

