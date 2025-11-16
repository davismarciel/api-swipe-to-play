<?php

namespace Modules\Recommendation\Infrastructure\Neo4j;

use Modules\Recommendation\Contracts\Neo4j\Neo4jConnectionInterface;
use Illuminate\Support\Facades\Log;
use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\Contracts\ClientInterface;

class Neo4jConnection implements Neo4jConnectionInterface
{
    private ?ClientInterface $client = null;
    private bool $initialized = false;
    
    public function __construct()
    {
        
    }
    
    private function initializeClient(): void
    {
        if ($this->initialized) {
            return;
        }
        
        try {
            if (function_exists('app') && app()->bound('config')) {
                $config = app('config');
                $uri = $config->get('neo4j.uri');
                $username = $config->get('neo4j.username');
                $password = $config->get('neo4j.password');
                $database = $config->get('neo4j.database');
            } else {
                $uri = env('NEO4J_URI', 'bolt://localhost:7687');
                $username = env('NEO4J_USERNAME', 'neo4j');
                $password = env('NEO4J_PASSWORD', 'password');
                $database = env('NEO4J_DATABASE', 'neo4j');
            }
            
            if (!class_exists(ClientBuilder::class)) {
                if (function_exists('app') && app()->bound('log')) {
                    Log::warning('Neo4j driver not found. Please install a Neo4j PHP client library.');
                }
                $this->initialized = true;
                return;
            }
            
            $this->client = ClientBuilder::create()
                ->withDriver('default', $uri, Authenticate::basic($username, $password))
                ->withDefaultDriver('default')
                ->build();
                
            $this->initialized = true;
        } catch (\Exception $e) {
            $this->initialized = true;
            if (function_exists('app') && app()->bound('log')) {
                $config = app('config');
                Log::error('Failed to initialize Neo4j client', [
                    'uri' => $config ? $config->get('neo4j.uri') : 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
            throw $e;
        }
    }
    
    public function getDriver()
    {
        if ($this->client === null && !$this->initialized) {
            $this->initializeClient();
        }
        
        return $this->client;
    }
    
    public function getSession()
    {
        $client = $this->getDriver();
        
        if ($client === null) {
            throw new \RuntimeException('Neo4j client not initialized');
        }
        
        return $client;
    }
    
    public function executeQuery(string $cypher, array $parameters = []): array
    {
        try {
            $client = $this->getDriver();
            
            if ($client === null) {
                throw new \RuntimeException('Neo4j client not initialized');
            }
            
            $result = $client->run($cypher, $parameters);
            
            $records = [];
            foreach ($result as $record) {
                $records[] = $record->toArray();
            }
            
            return $records;
        } catch (\Exception $e) {
            if (app()->bound('log')) {
                Log::error('Neo4j query execution failed', [
                    'cypher' => $cypher,
                    'parameters' => $parameters,
                    'error' => $e->getMessage()
                ]);
            }
            throw $e;
        }
    }
    
    public function executeWriteQuery(string $cypher, array $parameters = []): array
    {
        return $this->executeQuery($cypher, $parameters);
    }
    
    public function executeReadQuery(string $cypher, array $parameters = []): array
    {
        return $this->executeQuery($cypher, $parameters);
    }
    
    public function close(): void
    {
        $this->client = null;
    }
}

