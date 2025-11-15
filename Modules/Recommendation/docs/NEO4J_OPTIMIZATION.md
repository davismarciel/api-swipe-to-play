# Otimização de Recomendações com Neo4j

## Visão Geral

O sistema de recomendação foi otimizado usando **Neo4j** (banco de dados de grafos) para aproveitar as relações naturais entre usuários, jogos, gêneros, desenvolvedores e categorias. Esta implementação combina múltiplas estratégias de grafos em um sistema híbrido inteligente.

## Arquitetura

### Fluxo de Dados

```
PostgreSQL (Source of Truth)
    ↓ (Sincronização)
Neo4j (Graph Database)
    ↓ (Queries Otimizadas)
Sistema de Recomendação Híbrido
    ↓
API Response
```

### Componentes Principais

1. **Neo4jService**: Gerencia conexão e queries ao Neo4j
2. **Neo4jGraphSyncService**: Sincroniza dados do PostgreSQL para Neo4j
3. **Neo4jRecommendationService**: Implementa algoritmos de grafos
4. **RecommendationEngine**: Orquestra recomendações híbridas

## Estratégias de Recomendação

### 1. Collaborative Filtering (Filtragem Colaborativa)
**Algoritmo**: Jaccard Similarity

Encontra usuários similares baseado em gostos compartilhados e recomenda jogos que eles gostaram.

```cypher
// Encontra usuários com gostos similares
MATCH (u:User)-[:INTERACTED_WITH]->(g:Game)<-[:INTERACTED_WITH]-(other:User)
WHERE u.id = $userId AND other.id <> u.id
WITH u, other, count(g) as commonGames
WHERE commonGames >= 2
// Calcula Jaccard Similarity
WITH u, other, 
     (commonGames * 1.0) / (userGames + otherGames - commonGames) as similarity
WHERE similarity >= 0.15
// Recomenda jogos dos usuários similares
MATCH (other)-[:INTERACTED_WITH]->(recommended:Game)
WHERE NOT EXISTS((u)-[:INTERACTED_WITH]->(recommended))
RETURN recommended
```

**Vantagens**:
- Descobre padrões não óbvios
- Aprende com comportamento coletivo
- Funciona bem com usuários ativos (50+ interações)

### 2. Path-Based Recommendations (Baseado em Caminhos)
**Algoritmo**: Multi-hop Path Analysis

Explora caminhos de 2-3 saltos no grafo para descobrir conexões indiretas.

```cypher
// Caminho: Usuário → Jogo Gostado → Gênero → Novo Jogo
MATCH (u:User)-[:INTERACTED_WITH]->(liked:Game)
      -[:HAS_GENRE]->(g:Genre)<-[:HAS_GENRE]-(recommended:Game)
WHERE NOT EXISTS((u)-[:INTERACTED_WITH]->(recommended))
RETURN recommended, count(*) as pathStrength
```

**Vantagens**:
- Encontra jogos relacionados indiretamente
- Balanceado para todos os níveis de usuários
- Explora múltiplas dimensões (gênero, dev, categoria)

### 3. Developer-Based Recommendations
**Algoritmo**: Developer Affinity

Identifica desenvolvedores favoritos e recomenda outros jogos deles.

```cypher
MATCH (u:User)-[:INTERACTED_WITH]->(liked:Game)
      -[:DEVELOPED_BY]->(dev:Developer)
WHERE liked.score > 0
WITH dev, count(liked) as likedCount
WHERE likedCount >= 2
MATCH (dev)<-[:DEVELOPED_BY]-(recommended:Game)
WHERE NOT EXISTS((u)-[:INTERACTED_WITH]->(recommended))
RETURN recommended
```

**Vantagens**:
- Forte para usuários novos (< 10 interações)
- Aproveita lealdade a desenvolvedores
- Alta precisão

### 4. Community-Based Recommendations
**Algoritmo**: Graph Clustering

Identifica clusters/comunidades de jogos fortemente conectados.

```cypher
MATCH (u:User)-[:INTERACTED_WITH]->(liked:Game)
      -[:HAS_GENRE]->(g:Genre)<-[:HAS_GENRE]-(candidate:Game)
WITH candidate, count(g) as genreConnections
WHERE genreConnections >= 2
RETURN candidate
ORDER BY genreConnections DESC
```

**Vantagens**:
- Mantém coerência temática
- Reduz recomendações aleatórias
- Bom para exploração dentro de nichos

### 5. Deep Walk Recommendations
**Algoritmo**: Random Walk Simulation

Simula caminhadas aleatórias no grafo para descobrir conexões profundas.

```cypher
// Caminho de 3 saltos
MATCH path = (start:Game)-[:HAS_GENRE]->(:Genre)<-[:HAS_GENRE]-(mid:Game)
             -[:DEVELOPED_BY]->(:Developer)<-[:DEVELOPED_BY]-(end:Game)
WHERE start IN userLikedGames
  AND NOT EXISTS((u)-[:INTERACTED_WITH]->(end))
RETURN end, count(path) as pathCount
```

**Vantagens**:
- Descobre conexões não óbvias
- Serendipidade (surpresa positiva)
- Ideal para usuários avançados (100+ interações)

## Sistema Híbrido Adaptativo

### Pesos por Nível de Experiência

#### Usuários Novos (< 10 interações)
```
Developer-Based:  35%
Community-Based:  30%
Path-Based:       20%
Collaborative:    10%
Deep Walk:         5%
```

#### Usuários Intermediários (10-50 interações)
```
Collaborative:    25%
Path-Based:       25%
Developer-Based:  20%
Community-Based:  20%
Deep Walk:        10%
```

#### Usuários Avançados (50+ interações)
```
Collaborative:    35%
Path-Based:       20%
Developer-Based:  15%
Community-Based:  15%
Deep Walk:        15%
```

### Combinação Neo4j + Algoritmo Padrão

O sistema combina scores do Neo4j com o algoritmo tradicional usando pesos adaptativos:

```php
// Peso do Neo4j aumenta com experiência
< 10 interações:  40% Neo4j + 60% Padrão
10-50 interações: 60% Neo4j + 40% Padrão
50-100 interações: 70% Neo4j + 30% Padrão
100+ interações:  80% Neo4j + 20% Padrão
```

**Bonus**: Jogos recomendados por múltiplas estratégias recebem +5% por estratégia adicional.

## Performance

### Índices Criados

**Constraints (Unicidade)**:
- `user_id_unique`, `game_id_unique`, `genre_id_unique`
- `category_id_unique`, `developer_id_unique`, `publisher_id_unique`

**Índices Simples**:
- Propriedades de Game: `name`, `is_active`, `positive_ratio`, `total_reviews`
- Relacionamentos: `INTERACTED_WITH.score`, `INTERACTED_WITH.type`
- Entidades: `Genre.name`, `Category.name`, `Developer.name`

**Índices Compostos**:
- `(Game.is_active, Game.positive_ratio)`
- `(Game.is_active, Game.total_reviews)`
- `(INTERACTED_WITH.score, INTERACTED_WITH.type)`

### Cache em Camadas

1. **Cache de Recomendações**: 1 hora (3600s)
   - Chave: `neo4j:hybrid:{user_id}:{limit}`
   
2. **Cache de Perfil**: Configurável via `recommendation.cache.ttl`
   - Invalidado após novas interações

### Benchmarks Esperados

| Métrica | Antes (SQL) | Depois (Neo4j) | Melhoria |
|---------|-------------|----------------|----------|
| Tempo médio | 150-300ms | 30-80ms | **3-5x** |
| Queries complexas | 500-1000ms | 80-150ms | **5-10x** |
| Usuários similares | N/A | 20-40ms | **Novo** |
| Caminhos profundos | N/A | 50-100ms | **Novo** |

## Comandos Artisan

### Sincronização de Dados

```bash
# Sincronizar tudo
php artisan recommendation:sync-neo4j --full

# Sincronizar apenas jogos
php artisan recommendation:sync-neo4j --games

# Sincronizar usuários (limite de 1000)
php artisan recommendation:sync-neo4j --users --limit=1000

# Sincronizar interações
php artisan recommendation:sync-neo4j --interactions --limit=5000
```

### Configuração de Índices

```bash
# Criar índices e constraints
php artisan recommendation:setup-neo4j-indexes

# Recriar índices (remove e cria novamente)
php artisan recommendation:setup-neo4j-indexes --drop
```

## Configuração

### Variáveis de Ambiente

```env
# Habilitar Neo4j
NEO4J_ENABLED=true

# Container local
NEO4J_URI=bolt://neo4j:7687
NEO4J_USERNAME=neo4j
NEO4J_PASSWORD=password
NEO4J_DATABASE=neo4j

# Autenticação do container
NEO4J_AUTH=neo4j/password
```

### Arquivo de Configuração

`Modules/Recommendation/config/config.php`:

```php
'neo4j' => [
    'enabled' => env('NEO4J_ENABLED', false),
    'uri' => env('NEO4J_URI', 'bolt://neo4j:7687'),
    'username' => env('NEO4J_USERNAME', 'neo4j'),
    'password' => env('NEO4J_PASSWORD', 'password'),
    'database' => env('NEO4J_DATABASE', 'neo4j'),
],
```

## Monitoramento

### Logs

O sistema registra métricas detalhadas:

```php
Log::info('Neo4j hybrid recommendations generated', [
    'user_id' => $user->id,
    'strategies_used' => ['collaborative', 'path_based', 'developer_based'],
    'total_candidates' => 20,
    'execution_time_ms' => 45.23,
]);
```

### Neo4j Browser

Acesse `http://localhost:7474` para visualizar o grafo:

```cypher
// Visualizar estrutura do grafo
MATCH (u:User)-[r:INTERACTED_WITH]->(g:Game)-[:HAS_GENRE]->(genre:Genre)
RETURN u, r, g, genre
LIMIT 50

// Estatísticas
MATCH (g:Game)
OPTIONAL MATCH (g)-[:HAS_GENRE]->(genre:Genre)
OPTIONAL MATCH (g)-[:DEVELOPED_BY]->(dev:Developer)
RETURN 
  count(DISTINCT g) as total_jogos,
  count(DISTINCT genre) as total_generos,
  count(DISTINCT dev) as total_desenvolvedores
```

## Manutenção

### Sincronização Automática

As interações são sincronizadas automaticamente quando registradas via `recordInteraction()`.

### Sincronização Manual

Para grandes volumes de dados históricos:

```bash
# Sincronizar em lotes
php artisan recommendation:sync-neo4j --games --limit=100
php artisan recommendation:sync-neo4j --users --limit=100
php artisan recommendation:sync-neo4j --interactions --limit=1000
```

### Limpeza de Cache

```bash
# Limpar cache do Laravel (inclui cache de recomendações)
php artisan cache:clear

# Limpar cache de configuração
php artisan config:clear
```

## Troubleshooting

### Neo4j não conecta

1. Verificar se o container está rodando:
   ```bash
   docker ps | grep neo4j
   ```

2. Verificar logs:
   ```bash
   docker logs stp_neo4j
   ```

3. Testar conectividade:
   ```bash
   docker exec stp_neo4j cypher-shell -u neo4j -p password "RETURN 1"
   ```

### Recomendações vazias

1. Verificar se há dados sincronizados:
   ```cypher
   MATCH (g:Game) RETURN count(g) as total_games
   MATCH (u:User) RETURN count(u) as total_users
   MATCH ()-[r:INTERACTED_WITH]->() RETURN count(r) as total_interactions
   ```

2. Verificar threshold mínimo de interações (5):
   ```php
   $profile->total_interactions >= 5
   ```

### Performance degradada

1. Verificar índices:
   ```bash
   php artisan recommendation:setup-neo4j-indexes
   ```

2. Analisar query plans:
   ```cypher
   EXPLAIN MATCH (u:User {id: "1"})-[:INTERACTED_WITH]->(g:Game)
   RETURN g
   ```

3. Verificar cache:
   ```bash
   php artisan cache:clear
   ```

## Próximos Passos

### Melhorias Futuras

1. **Graph Neural Networks (GNN)**: Embeddings de grafos para recomendações ainda mais precisas
2. **Real-time Streaming**: Sincronização em tempo real com Kafka/RabbitMQ
3. **A/B Testing**: Framework para testar diferentes estratégias
4. **Explainability**: Explicações detalhadas de por que cada jogo foi recomendado
5. **Multi-objective Optimization**: Balancear precisão, diversidade e serendipidade

### Métricas para Avaliar

- **Precision@K**: Quantos dos top K são relevantes?
- **Recall@K**: Quantos relevantes estão no top K?
- **NDCG**: Normalized Discounted Cumulative Gain
- **Diversity**: Variedade de gêneros/desenvolvedores
- **Serendipity**: Surpresas positivas (jogos inesperados mas amados)

## Referências

- [Neo4j Graph Data Science](https://neo4j.com/docs/graph-data-science/)
- [Collaborative Filtering with Graphs](https://neo4j.com/developer/graph-data-science/collaborative-filtering/)
- [Jaccard Similarity](https://en.wikipedia.org/wiki/Jaccard_index)
- [Random Walk Algorithms](https://en.wikipedia.org/wiki/Random_walk)

