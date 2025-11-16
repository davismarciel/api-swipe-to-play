<?php

use Modules\Recommendation\Infrastructure\Neo4j\Neo4jConnection;

test('can get Neo4j driver instance', function () {
    $connection = new Neo4jConnection();
    
    $driver = $connection->getDriver();
    
    expect($driver)->not->toBeNull();
});

test('can get Neo4j session', function () {
    $connection = new Neo4jConnection();
    
    $session = $connection->getSession();
    
    expect($session)->not->toBeNull();
});

test('can connect to Neo4j and execute read query', function () {
    $connection = new Neo4jConnection();
    
    $result = $connection->executeReadQuery('RETURN 1 as value');
    
    expect($result)->toBeArray();
});

test('can execute write query with parameters on Neo4j', function () {
    $connection = new Neo4jConnection();
    $testId = 'test-' . time();
    
    $result = $connection->executeWriteQuery(
        'CREATE (n:TestNode {id: $id, name: $name, value: $value}) RETURN n',
        ['id' => $testId, 'name' => 'Test Node', 'value' => 42]
    );
    
    expect($result)->toBeArray();
    
    $readResult = $connection->executeReadQuery(
        'MATCH (n:TestNode {id: $id}) RETURN n',
        ['id' => $testId]
    );
    
    expect($readResult)->toBeArray();
    expect(count($readResult))->toBeGreaterThan(0);
    
    $connection->executeWriteQuery('MATCH (n:TestNode {id: $id}) DELETE n', ['id' => $testId]);
});

test('can create and query relationships in Neo4j', function () {
    $connection = new Neo4jConnection();
    $node1Id = 'test-node1-' . time();
    $node2Id = 'test-node2-' . time();
    
    $connection->executeWriteQuery(
        'CREATE (a:TestNode {id: $id1, name: "Node 1"})-[:CONNECTED_TO]->(b:TestNode {id: $id2, name: "Node 2"})',
        ['id1' => $node1Id, 'id2' => $node2Id]
    );
    
    $result = $connection->executeReadQuery(
        'MATCH (a:TestNode {id: $id1})-[r:CONNECTED_TO]->(b:TestNode {id: $id2}) RETURN a, r, b',
        ['id1' => $node1Id, 'id2' => $node2Id]
    );
    
    expect($result)->toBeArray();
    expect(count($result))->toBeGreaterThan(0);
    
    $connection->executeWriteQuery(
        'MATCH (n:TestNode) WHERE n.id IN [$id1, $id2] DETACH DELETE n',
        ['id1' => $node1Id, 'id2' => $node2Id]
    );
});

test('can close Neo4j connection', function () {
    $connection = new Neo4jConnection();
    
    expect(fn() => $connection->close())->not->toThrow(\Exception::class);
});
