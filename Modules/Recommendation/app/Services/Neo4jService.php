<?php

namespace Modules\Recommendation\Services;

use Illuminate\Support\Facades\Log;
use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Authentication\Authenticate;

class Neo4jService
{
    private ?ClientInterface $client = null;

    public function __construct()
    {
        // Não inicializa se estiver desabilitado
        $neo4jConfig = config('recommendation.neo4j', []);
        $neo4Enabled = $neo4jConfig['enabled'] ?? env('NEO4J_ENABLED', false);
        
        if ($neo4Enabled) {
            $this->initializeClient();
        }
    }

    private function initializeClient(): void
    {
        $neo4jConfig = config('recommendation.neo4j', []);
        
        $neo4Enabled = $neo4jConfig['enabled'] ?? env('NEO4J_ENABLED', false);

        if (!$neo4Enabled) {
            return;
        }
        
        $uri = $neo4jConfig['uri'] ?? env('NEO4J_URI', 'bolt://neo4j:7687');
        $username = $neo4jConfig['username'] ?? env('NEO4J_USERNAME', 'neo4j');
        $password = $neo4jConfig['password'] ?? env('NEO4J_PASSWORD', 'password');

        try {
            $auth = Authenticate::basic($username, $password);
            
            // Detecta o driver baseado no esquema da URI
            $driverAlias = $this->detectDriverFromUri($uri);
            
            $this->client = ClientBuilder::create()
                ->withDriver($driverAlias, $uri, $auth)
                ->build();
        } catch (\Exception $e) {
            Log::error('Failed to initialize Neo4j client', [
                'error' => $e->getMessage(),
                'uri' => $uri,
            ]);
            throw $e;
        }
    }

    private function detectDriverFromUri(string $uri): string
    {
        // Neo4j Aura usa neo4j+s:// ou neo4j+ssc://
        if (str_starts_with($uri, 'neo4j+s://') || str_starts_with($uri, 'neo4j+ssc://')) {
            return 'neo4j';
        }
        
        // Neo4j padrão
        if (str_starts_with($uri, 'neo4j://')) {
            return 'neo4j';
        }
        
        // Bolt seguro
        if (str_starts_with($uri, 'bolt+s://') || str_starts_with($uri, 'bolt+ssc://')) {
            return 'bolt';
        }
        
        // Bolt padrão
        return 'bolt';
    }

    public function getClient(): ClientInterface
    {
        if ($this->client === null) {
            $this->initializeClient();
        }

        if ($this->client === null) {
            throw new \RuntimeException('Neo4j client not initialized. Check if NEO4J_ENABLED is set to true.');
        }

        return $this->client;
    }

    public function executeQuery(string $cypher, array $parameters = []): array
    {
        try {
            $result = $this->getClient()->run($cypher, $parameters);
            
            $results = [];
            foreach ($result as $record) {
                $row = [];
                foreach ($record as $key => $value) {
                    $row[$key] = $this->extractValue($value);
                }
                $results[] = $row;
            }
            
            return $results;
        } catch (\Exception $e) {
            Log::error('Neo4j query execution failed', [
                'cypher' => $cypher,
                'parameters' => $parameters,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function extractValue($value): mixed
    {
        if (is_object($value)) {
            if (method_exists($value, 'getValue')) {
                return $value->getValue();
            }
            if (method_exists($value, '__toString')) {
                return (string) $value;
            }
            return $value;
        }
        
        return $value;
    }

    public function executeTransaction(callable $callback): mixed
    {
        try {
            return $this->getClient()->writeTransaction(static function ($tsx) use ($callback) {
                return $callback($tsx);
            });
        } catch (\Exception $e) {
            Log::error('Neo4j transaction failed', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function isConnected(): bool
    {
        try {
            $this->getClient()->verifyConnectivity();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}

