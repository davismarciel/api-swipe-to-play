# Guia de Testes - Sistema de Recomendação com Neo4j

## Pré-requisitos

1. Neo4j rodando e configurado
2. Dados sincronizados (jogos, usuários, interações)
3. Índices criados

## Setup Inicial

```bash
# 1. Configurar índices
docker exec stp_api php artisan recommendation:setup-neo4j-indexes

# 2. Sincronizar jogos
docker exec stp_api php artisan recommendation:sync-neo4j --games

# 3. Verificar dados no Neo4j
docker exec stp_neo4j cypher-shell -u neo4j -p password "
MATCH (g:Game) RETURN count(g) as total_games;
MATCH (u:User) RETURN count(u) as total_users;
MATCH ()-[r:INTERACTED_WITH]->() RETURN count(r) as total_interactions;
"
```

## Testes Funcionais

### 1. Testar Conectividade

```bash
docker exec stp_api php artisan tinker
```

```php
// No tinker
$neo4j = app(\Modules\Recommendation\Services\Neo4jService::class);
$neo4j->isConnected(); // deve retornar true
```

### 2. Testar Sincronização de Jogos

```bash
# Sincronizar 10 jogos
docker exec stp_api php artisan recommendation:sync-neo4j --games --limit=10
```

Verificar no Neo4j:
```cypher
MATCH (g:Game)-[:HAS_GENRE]->(genre:Genre)
RETURN g.name, collect(genre.name) as genres
LIMIT 5
```

### 3. Testar Estratégias Individuais

#### Collaborative Filtering

```bash
docker exec stp_api php artisan tinker
```

```php
$user = \Modules\User\Models\User::first();
$neo4jRec = app(\Modules\Recommendation\Services\Neo4jRecommendationService::class);

// Testar collaborative filtering
$recommendations = $neo4jRec->getCollaborativeRecommendations($user, 5);
echo "Collaborative: " . $recommendations->count() . " recomendações\n";
$recommendations->pluck('name')->each(fn($name) => echo "  - $name\n");
```

#### Path-Based

```php
$recommendations = $neo4jRec->getPathBasedRecommendations($user, 5);
echo "Path-Based: " . $recommendations->count() . " recomendações\n";
$recommendations->pluck('name')->each(fn($name) => echo "  - $name\n");
```

#### Developer-Based

```php
$recommendations = $neo4jRec->getDeveloperBasedRecommendations($user, 5);
echo "Developer-Based: " . $recommendations->count() . " recomendações\n";
$recommendations->pluck('name')->each(fn($name) => echo "  - $name\n");
```

#### Community-Based

```php
$recommendations = $neo4jRec->getCommunityBasedRecommendations($user, 5);
echo "Community-Based: " . $recommendations->count() . " recomendações\n";
$recommendations->pluck('name')->each(fn($name) => echo "  - $name\n");
```

#### Deep Walk

```php
$recommendations = $neo4jRec->getDeepWalkRecommendations($user, 5);
echo "Deep Walk: " . $recommendations->count() . " recomendações\n";
$recommendations->pluck('name')->each(fn($name) => echo "  - $name\n");
```

### 4. Testar Sistema Híbrido Completo

```php
$user = \Modules\User\Models\User::first();
$neo4jRec = app(\Modules\Recommendation\Services\Neo4jRecommendationService::class);

// Sistema híbrido (combina todas as estratégias)
$recommendations = $neo4jRec->getHybridGraphRecommendations($user, 10);

echo "Híbrido: " . $recommendations->count() . " recomendações\n\n";

foreach ($recommendations as $game) {
    echo "Jogo: {$game->name}\n";
    echo "  Score: {$game->recommendation_score}\n";
    echo "  Estratégias: " . implode(', ', $game->neo4j_metadata['strategies_used'] ?? []) . "\n";
    echo "  Count: " . ($game->neo4j_metadata['strategy_count'] ?? 1) . "\n\n";
}
```

### 5. Testar RecommendationEngine (Híbrido Neo4j + Padrão)

```php
$user = \Modules\User\Models\User::first();
$engine = app(\Modules\Recommendation\Contracts\RecommendationEngineInterface::class);

// Obtém recomendações (usa Neo4j se disponível)
$recommendations = $engine->getRecommendations($user, 10);

echo "Recomendações Finais: " . $recommendations->count() . "\n\n";

foreach ($recommendations as $game) {
    echo "Jogo: {$game->name}\n";
    echo "  Score Final: {$game->recommendation_score}\n";
    
    if (isset($game->score_breakdown)) {
        echo "  Neo4j Score: {$game->score_breakdown['neo4j_score']}\n";
        echo "  Standard Score: {$game->score_breakdown['standard_score']}\n";
        echo "  Neo4j Weight: " . ($game->score_breakdown['neo4j_weight'] * 100) . "%\n";
        echo "  Standard Weight: " . ($game->score_breakdown['standard_weight'] * 100) . "%\n";
    }
    echo "\n";
}
```

## Testes de Performance

### Benchmark de Queries

```bash
docker exec stp_api php artisan tinker
```

```php
$user = \Modules\User\Models\User::first();
$engine = app(\Modules\Recommendation\Contracts\RecommendationEngineInterface::class);

// Benchmark
$iterations = 10;
$times = [];

for ($i = 0; $i < $iterations; $i++) {
    $start = microtime(true);
    $recommendations = $engine->getRecommendations($user, 10);
    $end = microtime(true);
    $times[] = ($end - $start) * 1000; // em ms
}

echo "Tempo médio: " . round(array_sum($times) / count($times), 2) . "ms\n";
echo "Tempo mínimo: " . round(min($times), 2) . "ms\n";
echo "Tempo máximo: " . round(max($times), 2) . "ms\n";
```

### Verificar Cache

```php
// Primeira chamada (sem cache)
$start = microtime(true);
$recommendations = $engine->getRecommendations($user, 10);
$time1 = (microtime(true) - $start) * 1000;

// Segunda chamada (com cache)
$start = microtime(true);
$recommendations = $engine->getRecommendations($user, 10);
$time2 = (microtime(true) - $start) * 1000;

echo "Sem cache: " . round($time1, 2) . "ms\n";
echo "Com cache: " . round($time2, 2) . "ms\n";
echo "Speedup: " . round($time1 / $time2, 2) . "x\n";
```

## Queries Úteis no Neo4j

### Estatísticas do Grafo

```cypher
// Total de nós por tipo
MATCH (n)
RETURN labels(n) as tipo, count(n) as total
ORDER BY total DESC

// Total de relacionamentos por tipo
MATCH ()-[r]->()
RETURN type(r) as tipo, count(r) as total
ORDER BY total DESC

// Densidade do grafo
MATCH (n)
WITH count(n) as nodes
MATCH ()-[r]->()
WITH nodes, count(r) as rels
RETURN nodes as total_nodes, 
       rels as total_relationships,
       (rels * 1.0 / (nodes * nodes)) as density
```

### Análise de Usuários

```cypher
// Usuários mais ativos
MATCH (u:User)-[r:INTERACTED_WITH]->()
RETURN u.name, count(r) as interactions
ORDER BY interactions DESC
LIMIT 10

// Distribuição de scores de interação
MATCH ()-[r:INTERACTED_WITH]->()
RETURN r.score as score, count(*) as count
ORDER BY score DESC
```

### Análise de Jogos

```cypher
// Jogos mais populares
MATCH ()-[r:INTERACTED_WITH]->(g:Game)
WHERE r.score > 0
RETURN g.name, count(r) as positive_interactions
ORDER BY positive_interactions DESC
LIMIT 10

// Jogos por gênero
MATCH (g:Game)-[:HAS_GENRE]->(genre:Genre)
RETURN genre.name, count(g) as total_games
ORDER BY total_games DESC

// Desenvolvedores mais populares
MATCH (g:Game)-[:DEVELOPED_BY]->(dev:Developer)
RETURN dev.name, count(g) as total_games
ORDER BY total_games DESC
LIMIT 10
```

### Análise de Similaridade

```cypher
// Usuários similares (Jaccard)
MATCH (u1:User {id: "1"})-[:INTERACTED_WITH]->(g:Game)<-[:INTERACTED_WITH]-(u2:User)
WHERE u1.id <> u2.id
WITH u1, u2, count(g) as common
MATCH (u1)-[:INTERACTED_WITH]->(g1:Game)
WITH u1, u2, common, count(g1) as u1_total
MATCH (u2)-[:INTERACTED_WITH]->(g2:Game)
WITH u1, u2, common, u1_total, count(g2) as u2_total
WITH u1, u2, common, u1_total, u2_total,
     (common * 1.0) / (u1_total + u2_total - common) as jaccard
WHERE jaccard > 0.1
RETURN u2.name, jaccard, common
ORDER BY jaccard DESC
LIMIT 10
```

### Análise de Caminhos

```cypher
// Caminhos entre usuário e jogos recomendados
MATCH path = (u:User {id: "1"})-[:INTERACTED_WITH]->(:Game)
             -[:HAS_GENRE]->(:Genre)<-[:HAS_GENRE]-(recommended:Game)
WHERE NOT EXISTS((u)-[:INTERACTED_WITH]->(recommended))
RETURN recommended.name, count(path) as path_count
ORDER BY path_count DESC
LIMIT 10
```

## Validação de Resultados

### Checklist de Qualidade

- [ ] Recomendações retornam jogos diferentes dos já interagidos
- [ ] Scores estão entre 0 e 100
- [ ] Múltiplas estratégias estão sendo usadas
- [ ] Cache está funcionando (2ª chamada mais rápida)
- [ ] Logs mostram métricas detalhadas
- [ ] Diversidade de gêneros nas recomendações
- [ ] Performance < 100ms na maioria dos casos

### Métricas Esperadas

| Métrica | Valor Esperado |
|---------|----------------|
| Tempo de resposta (cache hit) | < 10ms |
| Tempo de resposta (cache miss) | 30-80ms |
| Estratégias por recomendação | 2-3 |
| Diversidade de gêneros | > 3 gêneros diferentes |
| Taxa de cache hit | > 80% |

## Troubleshooting

### Problema: Nenhuma recomendação retornada

**Causas possíveis**:
1. Usuário tem menos de 5 interações
2. Não há dados sincronizados no Neo4j
3. Neo4j não está conectado

**Solução**:
```bash
# Verificar interações do usuário
docker exec stp_api php artisan tinker
$user = \Modules\User\Models\User::first();
$user->gameInteractions()->count();

# Verificar dados no Neo4j
docker exec stp_neo4j cypher-shell -u neo4j -p password "MATCH (g:Game) RETURN count(g)"

# Sincronizar se necessário
docker exec stp_api php artisan recommendation:sync-neo4j --full
```

### Problema: Performance ruim

**Causas possíveis**:
1. Índices não criados
2. Cache desabilitado
3. Muitos dados sem otimização

**Solução**:
```bash
# Recriar índices
docker exec stp_api php artisan recommendation:setup-neo4j-indexes --drop

# Limpar cache
docker exec stp_api php artisan cache:clear

# Verificar query plan
docker exec stp_neo4j cypher-shell -u neo4j -p password "EXPLAIN MATCH (u:User {id: '1'})-[:INTERACTED_WITH]->(g:Game) RETURN g"
```

### Problema: Erros de conexão

**Solução**:
```bash
# Verificar se Neo4j está rodando
docker ps | grep neo4j

# Reiniciar Neo4j
docker restart stp_neo4j

# Verificar logs
docker logs stp_neo4j --tail 50
```

## Próximos Passos

1. Criar testes automatizados (PHPUnit)
2. Implementar A/B testing
3. Adicionar métricas de negócio (CTR, conversão)
4. Monitoramento em produção (Prometheus/Grafana)

