# Sistema de Recomendação Otimizado com Neo4j

## 📚 Documentação

Este diretório contém a documentação completa do sistema de recomendação otimizado com Neo4j.

### Documentos Disponíveis

1. **[EXECUTIVE_SUMMARY.md](./EXECUTIVE_SUMMARY.md)** ⭐ **COMECE AQUI**
   - Resumo executivo do projeto
   - Resultados e melhorias alcançadas
   - Dados sincronizados e estatísticas
   - Próximos passos recomendados

2. **[FLOWCHART.md](./FLOWCHART.md)** 📊 **FLUXOGRAMA VISUAL**
   - Fluxo completo da API
   - Detalhes de cada algoritmo
   - Diagramas de decisão
   - Estrutura de dados no grafo
   - Estratégias de cache

3. **[NEO4J_OPTIMIZATION.md](./NEO4J_OPTIMIZATION.md)** 📖 **DOCUMENTAÇÃO TÉCNICA**
   - Arquitetura do sistema
   - Detalhes de cada estratégia de recomendação
   - Sistema híbrido adaptativo
   - Configuração e comandos
   - Troubleshooting

4. **[TESTING_GUIDE.md](./TESTING_GUIDE.md)** 🧪 **GUIA DE TESTES**
   - Setup inicial
   - Testes funcionais de cada estratégia
   - Testes de performance
   - Benchmarks
   - Validação de resultados

5. **[USEFUL_QUERIES.md](./USEFUL_QUERIES.md)** 🔍 **QUERIES CYPHER**
   - Queries de visualização
   - Análise de dados
   - Queries de manutenção
   - Queries avançadas
   - Dicas de performance

## 🚀 Quick Start

### 1. Configurar Neo4j

```bash
# Já está no docker-compose.yml
docker-compose up -d neo4j
```

### 2. Configurar Variáveis de Ambiente

Adicione ao `.env`:
```env
NEO4J_ENABLED=true
NEO4J_URI=bolt://neo4j:7687
NEO4J_USERNAME=neo4j
NEO4J_PASSWORD=password
NEO4J_DATABASE=neo4j
NEO4J_AUTH=neo4j/password
```

### 3. Criar Índices

```bash
docker exec stp_api php artisan recommendation:setup-neo4j-indexes
```

### 4. Sincronizar Dados

```bash
# Sincronizar jogos
docker exec stp_api php artisan recommendation:sync-neo4j --games

# Sincronizar tudo (quando houver usuários e interações)
docker exec stp_api php artisan recommendation:sync-neo4j --full
```

### 5. Testar

```bash
docker exec stp_api php artisan tinker
```

```php
$user = \Modules\User\Models\User::first();
$engine = app(\Modules\Recommendation\Contracts\RecommendationEngineInterface::class);
$recommendations = $engine->getRecommendations($user, 10);
$recommendations->pluck('name');
```

## 📊 Visão Geral do Sistema

### Estratégias Implementadas

1. **Collaborative Filtering** - Usuários similares
2. **Path-Based** - Caminhos no grafo
3. **Developer-Based** - Desenvolvedores favoritos
4. **Community-Based** - Clusters de jogos
5. **Deep Walk** - Caminhadas aleatórias

### Pesos Adaptativos

| Usuário | Collaborative | Path | Developer | Community | Deep Walk |
|---------|--------------|------|-----------|-----------|-----------|
| Novo (< 10) | 10% | 20% | **35%** | 30% | 5% |
| Intermediário (10-50) | **25%** | **25%** | 20% | 20% | 10% |
| Avançado (50+) | **35%** | 20% | 15% | 15% | 15% |

### Performance

- **Antes**: 150-300ms (SQL)
- **Depois**: 30-80ms (Neo4j + Cache)
- **Melhoria**: **3-10x mais rápido**

## 🎯 Casos de Uso

### Usuário Novo
```php
// Prioriza Developer-Based e Community-Based
// Usa preferências do onboarding
$recommendations = $engine->getRecommendations($newUser, 10);
```

### Usuário Intermediário
```php
// Balanceia Collaborative e Path-Based
// Descobre padrões através do grafo
$recommendations = $engine->getRecommendations($intermediateUser, 10);
```

### Usuário Avançado
```php
// Prioriza Collaborative e Deep Walk
// Descobre conexões não óbvias
$recommendations = $engine->getRecommendations($advancedUser, 10);
```

## 🔧 Comandos Úteis

### Sincronização
```bash
# Sincronizar jogos
php artisan recommendation:sync-neo4j --games

# Sincronizar usuários
php artisan recommendation:sync-neo4j --users --limit=1000

# Sincronizar interações
php artisan recommendation:sync-neo4j --interactions --limit=5000

# Sincronizar tudo
php artisan recommendation:sync-neo4j --full
```

### Índices
```bash
# Criar índices
php artisan recommendation:setup-neo4j-indexes

# Recriar índices
php artisan recommendation:setup-neo4j-indexes --drop
```

### Neo4j Browser
```bash
# Acessar interface web
open http://localhost:7474

# Conectar com:
# URL: bolt://localhost:7687
# Username: neo4j
# Password: password
```

### Queries Úteis
```cypher
// Estatísticas do grafo
MATCH (n) RETURN labels(n)[0] as tipo, count(n) as total

// Visualizar amostra
MATCH (u:User)-[r:INTERACTED_WITH]->(g:Game)-[:HAS_GENRE]->(genre:Genre)
RETURN u, r, g, genre LIMIT 50

// Verificar índices
SHOW INDEXES
```

## 📈 Monitoramento

### Logs
O sistema registra automaticamente:
- Estratégias usadas
- Tempo de execução
- Número de candidatos
- Scores finais

### Métricas
- Tempo de resposta (p50, p95, p99)
- Taxa de cache hit
- Estratégias mais efetivas
- Diversidade de recomendações

## 🐛 Troubleshooting

### Neo4j não conecta
```bash
# Verificar container
docker ps | grep neo4j

# Verificar logs
docker logs stp_neo4j

# Reiniciar
docker restart stp_neo4j
```

### Recomendações vazias
```bash
# Verificar dados sincronizados
docker exec stp_neo4j cypher-shell -u neo4j -p password "
MATCH (g:Game) RETURN count(g) as total_games
"

# Sincronizar se necessário
docker exec stp_api php artisan recommendation:sync-neo4j --games
```

### Performance ruim
```bash
# Recriar índices
docker exec stp_api php artisan recommendation:setup-neo4j-indexes --drop

# Limpar cache
docker exec stp_api php artisan cache:clear
```

## 📖 Leitura Recomendada

1. Comece com **EXECUTIVE_SUMMARY.md** para entender o contexto
2. Leia **NEO4J_OPTIMIZATION.md** para detalhes técnicos
3. Use **TESTING_GUIDE.md** para validar o sistema
4. Consulte **USEFUL_QUERIES.md** quando precisar de queries específicas

## 🎓 Conceitos Importantes

### Jaccard Similarity
Mede similaridade entre dois conjuntos:
```
J(A, B) = |A ∩ B| / |A ∪ B|
```

### Random Walk
Caminhada aleatória no grafo para descobrir conexões profundas.

### Graph Clustering
Agrupamento de nós fortemente conectados.

### Pesos Adaptativos
Ajuste automático de importância de cada estratégia baseado no perfil do usuário.

## 🔗 Links Úteis

- [Neo4j Documentation](https://neo4j.com/docs/)
- [Cypher Query Language](https://neo4j.com/docs/cypher-manual/)
- [Graph Data Science](https://neo4j.com/docs/graph-data-science/)
- [Laudis Neo4j PHP Client](https://github.com/neo4j-php/neo4j-php-client)

## 💬 Suporte

Para dúvidas ou problemas:
1. Consulte a documentação neste diretório
2. Verifique os logs do sistema
3. Use o Neo4j Browser para análise visual
4. Execute queries de diagnóstico

---

**Versão**: 1.0.0  
**Última Atualização**: Novembro 2024  
**Mantido por**: Equipe API-STP

