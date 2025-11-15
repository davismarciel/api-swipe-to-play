# Queries Úteis - Neo4j

## Visualização e Exploração

### Visualizar Estrutura do Grafo
```cypher
// Visualizar amostra do grafo completo
MATCH (u:User)-[r:INTERACTED_WITH]->(g:Game)-[:HAS_GENRE]->(genre:Genre)
RETURN u, r, g, genre
LIMIT 50
```

### Visualizar Conexões de um Jogo Específico
```cypher
MATCH (g:Game {name: "Counter-Strike 2"})-[r]-(connected)
RETURN g, r, connected
LIMIT 30
```

### Visualizar Rede de um Usuário
```cypher
MATCH (u:User {id: "1"})-[:INTERACTED_WITH]->(g:Game)-[:HAS_GENRE]->(genre:Genre)
RETURN u, g, genre
LIMIT 20
```

## Estatísticas do Grafo

### Contagem de Nós por Tipo
```cypher
MATCH (n)
RETURN labels(n)[0] as tipo, count(n) as total
ORDER BY total DESC
```

### Contagem de Relacionamentos por Tipo
```cypher
MATCH ()-[r]->()
RETURN type(r) as tipo, count(r) as total
ORDER BY total DESC
```

### Densidade do Grafo
```cypher
MATCH (n)
WITH count(n) as nodes
MATCH ()-[r]->()
WITH nodes, count(r) as rels
RETURN nodes as total_nodes, 
       rels as total_relationships,
       (rels * 1.0 / (nodes * nodes)) as density,
       (rels * 1.0 / nodes) as avg_degree
```

### Distribuição de Graus
```cypher
MATCH (n)
OPTIONAL MATCH (n)-[r]-()
WITH n, count(r) as degree
RETURN degree, count(n) as nodes_with_degree
ORDER BY degree DESC
LIMIT 20
```

## Análise de Jogos

### Top 10 Jogos Mais Conectados
```cypher
MATCH (g:Game)-[r]-()
RETURN g.name, count(r) as connections
ORDER BY connections DESC
LIMIT 10
```

### Jogos por Gênero
```cypher
MATCH (g:Game)-[:HAS_GENRE]->(genre:Genre)
RETURN genre.name, count(g) as total_games
ORDER BY total_games DESC
```

### Jogos por Desenvolvedor
```cypher
MATCH (g:Game)-[:DEVELOPED_BY]->(dev:Developer)
RETURN dev.name, count(g) as total_games, collect(g.name)[0..5] as sample_games
ORDER BY total_games DESC
LIMIT 15
```

### Jogos Mais Bem Avaliados
```cypher
MATCH (g:Game)
WHERE g.positive_ratio IS NOT NULL
RETURN g.name, g.positive_ratio, g.total_reviews
ORDER BY g.positive_ratio DESC, g.total_reviews DESC
LIMIT 20
```

### Jogos com Múltiplos Gêneros
```cypher
MATCH (g:Game)-[:HAS_GENRE]->(genre:Genre)
WITH g, collect(genre.name) as genres
WHERE size(genres) >= 3
RETURN g.name, genres, size(genres) as genre_count
ORDER BY genre_count DESC
LIMIT 15
```

## Análise de Usuários

### Usuários Mais Ativos
```cypher
MATCH (u:User)-[r:INTERACTED_WITH]->()
RETURN u.id, u.name, count(r) as interactions
ORDER BY interactions DESC
LIMIT 10
```

### Distribuição de Interações por Tipo
```cypher
MATCH ()-[r:INTERACTED_WITH]->()
RETURN r.type as tipo, count(*) as total
ORDER BY total DESC
```

### Distribuição de Scores de Interação
```cypher
MATCH ()-[r:INTERACTED_WITH]->()
RETURN r.score as score, count(*) as count
ORDER BY score DESC
```

### Usuários com Gostos Similares
```cypher
// Encontra usuários que gostam dos mesmos jogos
MATCH (u1:User {id: "1"})-[:INTERACTED_WITH]->(g:Game)<-[:INTERACTED_WITH]-(u2:User)
WHERE u1.id <> u2.id
WITH u1, u2, count(g) as common_games
WHERE common_games >= 2
RETURN u2.id, u2.name, common_games
ORDER BY common_games DESC
LIMIT 10
```

## Análise de Similaridade

### Jaccard Similarity entre Usuários
```cypher
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
RETURN u2.id, u2.name, 
       jaccard as similarity,
       common as common_games,
       u1_total as user1_total,
       u2_total as user2_total
ORDER BY jaccard DESC
LIMIT 10
```

### Jogos Similares (por Gênero e Desenvolvedor)
```cypher
MATCH (g1:Game {name: "Counter-Strike 2"})
MATCH (g1)-[:HAS_GENRE]->(genre:Genre)<-[:HAS_GENRE]-(g2:Game)
WHERE g1.id <> g2.id
WITH g1, g2, count(genre) as shared_genres
OPTIONAL MATCH (g1)-[:DEVELOPED_BY]->(dev:Developer)<-[:DEVELOPED_BY]-(g2)
WITH g2, shared_genres, count(dev) as shared_devs
RETURN g2.name, 
       shared_genres,
       shared_devs,
       (shared_genres + shared_devs * 2) as similarity_score
ORDER BY similarity_score DESC
LIMIT 10
```

## Análise de Caminhos

### Caminhos Curtos entre Usuário e Jogo
```cypher
MATCH path = shortestPath(
  (u:User {id: "1"})-[*..4]-(g:Game {name: "Dota 2"})
)
RETURN path
```

### Todos os Caminhos de Tamanho 2
```cypher
MATCH path = (u:User {id: "1"})-[*2]-(g:Game)
WHERE NOT EXISTS((u)-[:INTERACTED_WITH]->(g))
AND g.is_active = true
RETURN g.name, count(path) as path_count
ORDER BY path_count DESC
LIMIT 10
```

### Caminhos através de Gêneros
```cypher
MATCH (u:User {id: "1"})-[:INTERACTED_WITH]->(liked:Game)
      -[:HAS_GENRE]->(genre:Genre)<-[:HAS_GENRE]-(recommended:Game)
WHERE NOT EXISTS((u)-[:INTERACTED_WITH]->(recommended))
AND recommended.is_active = true
WITH recommended, collect(DISTINCT genre.name) as shared_genres, count(*) as path_count
RETURN recommended.name, shared_genres, path_count
ORDER BY path_count DESC
LIMIT 10
```

### Caminhos através de Desenvolvedores
```cypher
MATCH (u:User {id: "1"})-[:INTERACTED_WITH]->(liked:Game)
      -[:DEVELOPED_BY]->(dev:Developer)<-[:DEVELOPED_BY]-(recommended:Game)
WHERE NOT EXISTS((u)-[:INTERACTED_WITH]->(recommended))
AND recommended.is_active = true
WITH recommended, collect(DISTINCT dev.name) as shared_devs, count(*) as path_count
RETURN recommended.name, shared_devs, path_count
ORDER BY path_count DESC
LIMIT 10
```

## Análise de Comunidades

### Detectar Comunidades de Gêneros
```cypher
MATCH (g1:Game)-[:HAS_GENRE]->(genre:Genre)<-[:HAS_GENRE]-(g2:Game)
WHERE g1.id < g2.id
WITH genre, count(*) as games_in_genre
WHERE games_in_genre >= 5
MATCH (g:Game)-[:HAS_GENRE]->(genre)
RETURN genre.name, 
       games_in_genre,
       collect(g.name)[0..10] as sample_games
ORDER BY games_in_genre DESC
```

### Desenvolvedores Mais Influentes
```cypher
MATCH (dev:Developer)<-[:DEVELOPED_BY]-(g:Game)
WITH dev, count(g) as game_count
WHERE game_count >= 2
MATCH (dev)<-[:DEVELOPED_BY]-(g)-[:HAS_GENRE]->(genre:Genre)
WITH dev, game_count, collect(DISTINCT genre.name) as genres
RETURN dev.name, 
       game_count,
       size(genres) as genre_diversity,
       genres
ORDER BY game_count DESC, genre_diversity DESC
LIMIT 15
```

## Queries de Performance

### Verificar Índices
```cypher
SHOW INDEXES
```

### Verificar Constraints
```cypher
SHOW CONSTRAINTS
```

### Analisar Query Plan
```cypher
EXPLAIN MATCH (u:User {id: "1"})-[:INTERACTED_WITH]->(g:Game)
RETURN g.name
```

### Profile de Query (com estatísticas)
```cypher
PROFILE MATCH (u:User {id: "1"})-[:INTERACTED_WITH]->(g:Game)
RETURN g.name
```

## Queries de Manutenção

### Contar Nós Órfãos (sem relacionamentos)
```cypher
MATCH (n)
WHERE NOT (n)--()
RETURN labels(n)[0] as tipo, count(n) as orphans
```

### Verificar Integridade de Dados
```cypher
// Jogos sem gênero
MATCH (g:Game)
WHERE NOT (g)-[:HAS_GENRE]->()
RETURN count(g) as games_without_genre

// Jogos sem desenvolvedor
MATCH (g:Game)
WHERE NOT (g)-[:DEVELOPED_BY]->()
RETURN count(g) as games_without_developer
```

### Limpar Cache do Neo4j
```cypher
CALL db.clearQueryCaches()
```

## Queries de Teste

### Simular Recomendação Collaborative
```cypher
// Versão simplificada do algoritmo
MATCH (u:User {id: "1"})-[r1:INTERACTED_WITH]->(g1:Game)
WHERE r1.score > 0
WITH u, collect(g1.id) as liked_games

MATCH (other:User)-[r2:INTERACTED_WITH]->(g2:Game)
WHERE other.id <> u.id 
AND g2.id IN liked_games
AND r2.score > 0

WITH u, other, count(g2) as common
WHERE common >= 2

MATCH (other)-[r3:INTERACTED_WITH]->(recommended:Game)
WHERE r3.score > 0
AND NOT recommended.id IN liked_games
AND recommended.is_active = true

RETURN recommended.name, 
       count(DISTINCT other) as recommenders,
       avg(r3.score) as avg_score
ORDER BY recommenders DESC, avg_score DESC
LIMIT 10
```

### Simular Recomendação Path-Based
```cypher
MATCH (u:User {id: "1"})-[r:INTERACTED_WITH]->(liked:Game)
WHERE r.score > 0

OPTIONAL MATCH (liked)-[:HAS_GENRE]->(g:Genre)<-[:HAS_GENRE]-(rec1:Game)
WHERE NOT EXISTS((u)-[:INTERACTED_WITH]->(rec1))
AND rec1.is_active = true

OPTIONAL MATCH (liked)-[:DEVELOPED_BY]->(d:Developer)<-[:DEVELOPED_BY]-(rec2:Game)
WHERE NOT EXISTS((u)-[:INTERACTED_WITH]->(rec2))
AND rec2.is_active = true

WITH collect(DISTINCT rec1) + collect(DISTINCT rec2) as candidates
UNWIND candidates as recommended

RETURN recommended.name, count(*) as path_count
ORDER BY path_count DESC
LIMIT 10
```

## Queries Avançadas

### PageRank dos Jogos
```cypher
// Requer Graph Data Science Library
CALL gds.pageRank.stream({
  nodeProjection: 'Game',
  relationshipProjection: {
    SIMILAR: {
      type: 'HAS_GENRE',
      orientation: 'UNDIRECTED'
    }
  }
})
YIELD nodeId, score
RETURN gds.util.asNode(nodeId).name as game, score
ORDER BY score DESC
LIMIT 10
```

### Louvain Community Detection
```cypher
// Requer Graph Data Science Library
CALL gds.louvain.stream({
  nodeProjection: 'Game',
  relationshipProjection: 'HAS_GENRE'
})
YIELD nodeId, communityId
WITH communityId, collect(gds.util.asNode(nodeId).name) as games
RETURN communityId, size(games) as size, games[0..5] as sample
ORDER BY size DESC
LIMIT 10
```

## Dicas de Performance

1. **Use LIMIT**: Sempre limite resultados em queries exploratórias
2. **Use EXPLAIN/PROFILE**: Analise o plano de execução
3. **Crie Índices**: Para propriedades frequentemente filtradas
4. **Use Parâmetros**: `{userId}` ao invés de valores hardcoded
5. **Evite Cartesian Products**: Use WITH para quebrar queries complexas
6. **Use OPTIONAL MATCH**: Quando relacionamentos podem não existir

