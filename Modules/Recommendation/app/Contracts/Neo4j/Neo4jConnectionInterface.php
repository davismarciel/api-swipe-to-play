<?php

namespace Modules\Recommendation\Contracts\Neo4j;

interface Neo4jConnectionInterface
{
    public function getDriver();
    
    public function getSession();
    
    public function executeQuery(string $cypher, array $parameters = []): array;
    
    public function executeWriteQuery(string $cypher, array $parameters = []): array;
    
    public function executeReadQuery(string $cypher, array $parameters = []): array;
    
    public function close(): void;
}

