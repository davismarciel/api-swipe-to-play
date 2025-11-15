# Fluxograma da API de Recomendações

> **📌 Fluxograma Principal**: O fluxograma principal completo e didático está disponível em [FLOWCHART_PRINCIPAL.md](./FLOWCHART_PRINCIPAL.md)

Este documento contém os fluxogramas detalhados dos componentes específicos do sistema de recomendações.

## Fluxo Detalhado: Sistema Híbrido Neo4j

```mermaid
flowchart TD
    Start([getHybridGraphRecommendations]) --> CheckCache{Cache<br/>existe?}
    
    CheckCache -->|Sim| ReturnCache[Retorna cache<br/>chave: neo4j:hybrid:userId:limit]
    CheckCache -->|Não| StartTimer[Inicia timer<br/>microtime]
    
    StartTimer --> ParallelExec[Executa estratégias<br/>em paralelo]
    
    ParallelExec --> S1[Estratégia 1:<br/>Collaborative Filtering]
    ParallelExec --> S2[Estratégia 2:<br/>Path-Based]
    ParallelExec --> S3[Estratégia 3:<br/>Developer-Based]
    ParallelExec --> S4[Estratégia 4:<br/>Community-Based]
    ParallelExec --> S5[Estratégia 5:<br/>Deep Walk]
    
    S1 --> S1Query[Query Cypher:<br/>Jaccard Similarity]
    S2 --> S2Query[Query Cypher:<br/>Multi-hop Paths]
    S3 --> S3Query[Query Cypher:<br/>Developer Affinity]
    S4 --> S4Query[Query Cypher:<br/>Graph Clustering]
    S5 --> S5Query[Query Cypher:<br/>Random Walk]
    
    S1Query --> S1Results[Resultados<br/>Collection]
    S2Query --> S2Results[Resultados<br/>Collection]
    S3Query --> S3Results[Resultados<br/>Collection]
    S4Query --> S4Results[Resultados<br/>Collection]
    S5Query --> S5Results[Resultados<br/>Collection]
    
    S1Results --> Combine[combineStrategies]
    S2Results --> Combine
    S3Results --> Combine
    S4Results --> Combine
    S5Results --> Combine
    
    Combine --> GetWeights[Calcula pesos<br/>adaptativos]
    GetWeights --> CheckInteractions{Total<br/>interações?}
    
    CheckInteractions -->|< 10| WeightsNew[Pesos Novos:<br/>Dev:35%, Comm:30%,<br/>Path:20%, Collab:10%]
    CheckInteractions -->|10-50| WeightsMid[Pesos Intermediário:<br/>Collab:25%, Path:25%,<br/>Dev:20%, Comm:20%]
    CheckInteractions -->|50+| WeightsAdv[Pesos Avançado:<br/>Collab:35%, Path:20%,<br/>Dev:15%, Comm:15%]
    
    WeightsNew --> MergeScores[Merge scores<br/>por jogo]
    WeightsMid --> MergeScores
    WeightsAdv --> MergeScores
    
    MergeScores --> ApplyMultiBonus{Jogo recomendado<br/>por múltiplas<br/>estratégias?}
    
    ApplyMultiBonus -->|Sim| BonusScore[Score × 1.15<br/>por estratégia extra]
    ApplyMultiBonus -->|Não| NoBonus[Score original]
    
    BonusScore --> SortGames[Ordena por<br/>score total]
    NoBonus --> SortGames
    
    SortGames --> TopN[Top N jogos<br/>limit]
    TopN --> HydratePG[Hidrata do<br/>PostgreSQL]
    
    HydratePG --> AddMetadata[Adiciona metadados:<br/>- strategies_used<br/>- strategy_count<br/>- scores]
    
    AddMetadata --> StopTimer[Para timer<br/>calcula tempo]
    StopTimer --> LogInfo[Log: estratégias,<br/>tempo, candidatos]
    LogInfo --> CacheStore[Armazena no cache<br/>TTL: 3600s]
    CacheStore --> Return[Retorna Collection]
    
    ReturnCache --> Return
    
    style Start fill:#e1f5ff
    style Return fill:#c8e6c9
    style Combine fill:#fff9c4
    style ParallelExec fill:#f3e5f5
```

## Fluxo Detalhado: Collaborative Filtering

```mermaid
flowchart TD
    Start([getCollaborativeRecommendations]) --> Query1[MATCH usuário<br/>e seus jogos gostados]
    
    Query1 --> Query2[MATCH outros usuários<br/>com jogos em comum]
    
    Query2 --> CalcJaccard[Calcula Jaccard<br/>Similarity:<br/>common / union]
    
    CalcJaccard --> FilterSimilar{Similaridade<br/>>= 0.15<br/>e comum >= 2?}
    
    FilterSimilar -->|Não| ReturnEmpty[Retorna Collection vazia]
    FilterSimilar -->|Sim| Query3[MATCH jogos dos<br/>usuários similares]
    
    Query3 --> FilterNotInteracted[Filtra jogos<br/>não interagidos<br/>pelo usuário]
    
    FilterNotInteracted --> CalcScore[Calcula score:<br/>weightedScore × 0.4 +<br/>recommenders × 0.3 +<br/>avgSimilarity × 0.2 +<br/>gameRating × 0.1]
    
    CalcScore --> SortByScore[Ordena por<br/>finalScore DESC]
    
    SortByScore --> LimitResults[LIMIT limit × 2]
    LimitResults --> HydrateGames[Hidrata jogos<br/>do PostgreSQL]
    
    HydrateGames --> AddScore[Adiciona<br/>recommendation_score]
    AddScore --> AddMeta[Adiciona metadata:<br/>recommenders_count,<br/>avg_interaction_score]
    
    AddMeta --> Return[Retorna Collection]
    
    ReturnEmpty --> Return
    
    style Start fill:#e1f5ff
    style Return fill:#c8e6c9
    style CalcJaccard fill:#fff9c4
    style CalcScore fill:#f3e5f5
```

## Fluxo Detalhado: Path-Based Recommendations

```mermaid
flowchart TD
    Start([getPathBasedRecommendations]) --> Query1[MATCH usuário<br/>e jogos gostados]
    
    Query1 --> Path1[Caminho 1:<br/>User → Liked → Genre → Game]
    Query1 --> Path2[Caminho 2:<br/>User → Liked → Developer → Game]
    Query1 --> Path3[Caminho 3:<br/>User → Liked → Category → Game]
    
    Path1 --> Collect1[Collect g1]
    Path2 --> Collect2[Collect g2]
    Path3 --> Collect3[Collect g3]
    
    Collect1 --> Unify[Unifica candidatos:<br/>g1 + g2 + g3]
    Collect2 --> Unify
    Collect3 --> Unify
    
    Unify --> CalcConnections[Calcula:<br/>connectionCount<br/>totalLikedScore<br/>avgLikedScore]
    
    CalcConnections --> AnalyzeOverlap[Analisa overlap<br/>de gêneros]
    
    AnalyzeOverlap --> CalcPathScore[Calcula pathScore:<br/>connectionCount × 0.35 +<br/>totalLikedScore × 0.25 +<br/>genreOverlap × 0.25 +<br/>rating × 0.15]
    
    CalcPathScore --> SortByPath[Ordena por<br/>pathScore DESC]
    
    SortByPath --> Limit[LIMIT limit]
    Limit --> Hydrate[Hidrata jogos]
    
    Hydrate --> AddScore[Adiciona score<br/>e metadata]
    AddScore --> Return[Retorna Collection]
    
    style Start fill:#e1f5ff
    style Return fill:#c8e6c9
    style Unify fill:#fff9c4
    style CalcPathScore fill:#f3e5f5
```

## Fluxo Detalhado: Combinação Neo4j + Padrão

```mermaid
flowchart TD
    Start([combineNeo4jWithStandard]) --> GetProfile[Obtém perfil<br/>comportamental]
    
    GetProfile --> CalcWeight[Calcula peso Neo4j<br/>baseado em interações]
    
    CalcWeight --> CheckInteractions{Total<br/>interações?}
    
    CheckInteractions -->|< 10| Weight40[40% Neo4j<br/>60% Padrão]
    CheckInteractions -->|10-50| Weight60[60% Neo4j<br/>40% Padrão]
    CheckInteractions -->|50-100| Weight70[70% Neo4j<br/>30% Padrão]
    CheckInteractions -->|100+| Weight80[80% Neo4j<br/>20% Padrão]
    
    Weight40 --> LoopGames[Para cada jogo<br/>do Neo4j]
    Weight60 --> LoopGames
    Weight70 --> LoopGames
    Weight80 --> LoopGames
    
    LoopGames --> GetNeo4jScore[Obtém score Neo4j<br/>do jogo]
    
    GetNeo4jScore --> CalcStandard[Calcula score padrão<br/>ScoreCalculator]
    
    CalcStandard --> CombineScores[Combina scores:<br/>neo4jScore × neo4jWeight +<br/>standardScore × standardWeight]
    
    CombineScores --> CheckMulti{Recomendado por<br/>múltiplas estratégias?}
    
    CheckMulti -->|Sim| ApplyBonus[Score × 1.05<br/>por estratégia extra]
    CheckMulti -->|Não| NoBonus[Score original]
    
    ApplyBonus --> StoreBreakdown[Armazena breakdown:<br/>neo4j_score,<br/>standard_score,<br/>weights, bonus]
    
    NoBonus --> StoreBreakdown
    
    StoreBreakdown --> NextGame{Próximo<br/>jogo?}
    
    NextGame -->|Sim| LoopGames
    NextGame -->|Não| SortFinal[Ordena por<br/>score final DESC]
    
    SortFinal --> TopN[Top N jogos<br/>limit]
    TopN --> Return[Retorna Collection<br/>com score_breakdown]
    
    style Start fill:#e1f5ff
    style Return fill:#c8e6c9
    style CombineScores fill:#fff9c4
    style CheckMulti fill:#f3e5f5
```

## Fluxo: Registro de Interação

```mermaid
flowchart TD
    Start([recordInteraction]) --> ValidateInput{Valida<br/>tipo de<br/>interação?}
    
    ValidateInput -->|Não| ReturnError[Retorna erro]
    ValidateInput -->|Sim| CalcInteractionScore[Calcula score<br/>da interação]
    
    CalcInteractionScore --> CheckType{Tipo?}
    
    CheckType -->|like| Score10[Score: 10]
    CheckType -->|favorite| Score15[Score: 15]
    CheckType -->|view| Score1[Score: 1]
    CheckType -->|dislike| ScoreNeg5[Score: -5]
    CheckType -->|skip| ScoreNeg2[Score: -2]
    
    Score10 --> SaveInteraction[Salva/Atualiza<br/>GameInteraction]
    Score15 --> SaveInteraction
    Score1 --> SaveInteraction
    ScoreNeg5 --> SaveInteraction
    ScoreNeg2 --> SaveInteraction
    
    SaveInteraction --> CheckImportant{Interação<br/>importante?<br/>like/dislike/favorite}
    
    CheckImportant -->|Sim| MarkSeen[Marca como visto<br/>no DailyGameCache]
    CheckImportant -->|Não| UpdateStats
    
    MarkSeen --> UpdateStats[Atualiza estatísticas<br/>do perfil]
    
    UpdateStats --> IncrementCounter[Incrementa contador<br/>de interações]
    
    IncrementCounter --> CheckUpdate{Deve atualizar<br/>perfil?}
    
    CheckUpdate -->|Sim| UpdateProfile[Atualiza perfil<br/>comportamental]
    CheckUpdate -->|Não| SyncNeo4j
    
    UpdateProfile --> SyncNeo4j{Neo4j<br/>habilitado?}
    
    SyncNeo4j -->|Sim| SyncUser[Sincroniza usuário<br/>no Neo4j]
    SyncNeo4j -->|Não| LogSuccess
    
    SyncUser --> SyncGame[Sincroniza jogo<br/>no Neo4j]
    SyncGame --> SyncInteraction[Sincroniza interação<br/>no Neo4j]
    
    SyncInteraction --> LogSuccess[Log: interação<br/>registrada]
    LogSuccess --> Return[Retorna<br/>GameInteraction]
    
    ReturnError --> Return
    
    style Start fill:#e1f5ff
    style Return fill:#c8e6c9
    style SyncNeo4j fill:#fff9c4
    style UpdateProfile fill:#f3e5f5
```

## Fluxo: Sincronização Neo4j

```mermaid
flowchart TD
    Start([syncNeo4jGraph]) --> CheckEnabled{Neo4j<br/>habilitado?}
    
    CheckEnabled -->|Não| Return[Retorna sem<br/>sincronizar]
    CheckEnabled -->|Sim| CheckConnected{Neo4j<br/>conectado?}
    
    CheckConnected -->|Não| LogError[Log erro<br/>e retorna]
    CheckConnected -->|Sim| CheckOptions{Opções<br/>especificadas?}
    
    CheckOptions -->|--full| SyncAll[Sincroniza tudo]
    CheckOptions -->|--games| SyncGames[Sincroniza jogos]
    CheckOptions -->|--users| SyncUsers[Sincroniza usuários]
    CheckOptions -->|--interactions| SyncInteractions[Sincroniza interações]
    
    SyncAll --> SyncGames
    SyncAll --> SyncUsers
    SyncAll --> SyncInteractions
    
    SyncGames --> QueryGames[Query PostgreSQL:<br/>Game::where active]
    QueryGames --> LoopGames[Para cada jogo]
    
    LoopGames --> CreateGameNode[CREATE/MERGE<br/>Game node]
    CreateGameNode --> CreateGenres[CREATE/MERGE<br/>Genre nodes<br/>e HAS_GENRE]
    CreateGenres --> CreateCategories[CREATE/MERGE<br/>Category nodes<br/>e HAS_CATEGORY]
    CreateCategories --> CreateDevs[CREATE/MERGE<br/>Developer nodes<br/>e DEVELOPED_BY]
    CreateDevs --> CreatePubs[CREATE/MERGE<br/>Publisher nodes<br/>e PUBLISHED_BY]
    
    CreatePubs --> NextGame{Próximo<br/>jogo?}
    
    NextGame -->|Sim| LoopGames
    NextGame -->|Não| SyncUsers
    
    SyncUsers --> QueryUsers[Query PostgreSQL:<br/>User::all]
    QueryUsers --> LoopUsers[Para cada usuário]
    
    LoopUsers --> CreateUserNode[CREATE/MERGE<br/>User node]
    CreateUserNode --> SyncPreferences[Sincroniza preferências:<br/>PREFERS_GENRE,<br/>PREFERS_CATEGORY]
    
    SyncPreferences --> NextUser{Próximo<br/>usuário?}
    
    NextUser -->|Sim| LoopUsers
    NextUser -->|Não| SyncInteractions
    
    SyncInteractions --> QueryInteractions[Query PostgreSQL:<br/>GameInteraction::all]
    QueryInteractions --> LoopInteractions[Para cada interação]
    
    LoopInteractions --> CreateInteractionRel[CREATE/MERGE<br/>INTERACTED_WITH<br/>relationship]
    CreateInteractionRel --> SetProps[Define propriedades:<br/>score, type,<br/>interacted_at]
    
    SetProps --> NextInteraction{Próxima<br/>interação?}
    
    NextInteraction -->|Sim| LoopInteractions
    NextInteraction -->|Não| LogSuccess[Log: sincronização<br/>concluída]
    
    LogSuccess --> Return
    
    LogError --> Return
    
    style Start fill:#e1f5ff
    style Return fill:#c8e6c9
    style SyncNeo4j fill:#fff9c4
    style CreateGameNode fill:#f3e5f5
```

## Estrutura de Dados no Grafo Neo4j

```mermaid
erDiagram
    User ||--o{ INTERACTED_WITH : "tem"
    User ||--o{ PREFERS_GENRE : "prefere"
    User ||--o{ PREFERS_CATEGORY : "prefere"
    
    Game ||--o{ HAS_GENRE : "tem"
    Game ||--o{ HAS_CATEGORY : "tem"
    Game ||--o{ DEVELOPED_BY : "desenvolvido por"
    Game ||--o{ PUBLISHED_BY : "publicado por"
    Game ||--o{ INTERACTED_WITH : "recebe"
    
    Genre ||--o{ HAS_GENRE : "categoriza"
    Genre ||--o{ PREFERS_GENRE : "preferido por"
    
    Category ||--o{ HAS_CATEGORY : "categoriza"
    Category ||--o{ PREFERS_CATEGORY : "preferido por"
    
    Developer ||--o{ DEVELOPED_BY : "desenvolve"
    
    Publisher ||--o{ PUBLISHED_BY : "publica"
    
    User {
        string id PK
        string name
        string email
    }
    
    Game {
        string id PK
        string name
        float positive_ratio
        int total_reviews
        boolean is_active
        boolean is_free
        int required_age
    }
    
    Genre {
        string id PK
        string name
    }
    
    Category {
        string id PK
        string name
    }
    
    Developer {
        string id PK
        string name
    }
    
    Publisher {
        string id PK
        string name
    }
    
    INTERACTED_WITH {
        int score
        string type
        datetime interacted_at
    }
```

## Decisões do Algoritmo

```mermaid
flowchart TD
    Start([Recomendação Solicitada]) --> CheckInteractions{Total de<br/>interações<br/>do usuário?}
    
    CheckInteractions -->|< 5| UseDefault[Usa recomendações<br/>default baseadas em<br/>preferências onboarding]
    
    CheckInteractions -->|>= 5| CheckNeo4j{Neo4j<br/>habilitado<br/>e conectado?}
    
    CheckNeo4j -->|Não| UseStandard[Usa apenas<br/>algoritmo padrão<br/>ScoreCalculator]
    
    CheckNeo4j -->|Sim| CheckInteractions2{Interações<br/>>= 5?}
    
    CheckInteractions2 -->|Não| UseStandard
    CheckInteractions2 -->|Sim| UseHybrid[Usa sistema híbrido<br/>Neo4j + Padrão]
    
    UseHybrid --> DetermineWeights{Total<br/>interações?}
    
    DetermineWeights -->|< 10| NewUser[Usuário Novo:<br/>40% Neo4j, 60% Padrão<br/>Estratégias: Dev 35%, Comm 30%]
    
    DetermineWeights -->|10-50| MidUser[Usuário Intermediário:<br/>60% Neo4j, 40% Padrão<br/>Estratégias: Collab 25%, Path 25%]
    
    DetermineWeights -->|50-100| AdvUser[Usuário Avançado:<br/>70% Neo4j, 30% Padrão<br/>Estratégias: Collab 35%, Deep 15%]
    
    DetermineWeights -->|100+| ExpertUser[Usuário Expert:<br/>80% Neo4j, 20% Padrão<br/>Estratégias: Collab 35%, Deep 15%]
    
    NewUser --> ExecuteStrategies[Executa estratégias<br/>com pesos adaptativos]
    MidUser --> ExecuteStrategies
    AdvUser --> ExecuteStrategies
    ExpertUser --> ExecuteStrategies
    
    ExecuteStrategies --> CombineResults[Combina resultados<br/>com bonus multi-estratégia]
    
    CombineResults --> RefineWithStandard[Refina com<br/>ScoreCalculator padrão]
    
    RefineWithStandard --> ApplyDiversification[Aplica diversificação<br/>máx 40% por gênero]
    
    ApplyDiversification --> SortAndLimit[Ordena e limita<br/>top N resultados]
    
    UseDefault --> SortAndLimit
    UseStandard --> SortAndLimit
    
    SortAndLimit --> ReturnResults[Retorna recomendações<br/>com scores e metadados]
    
    style Start fill:#e1f5ff
    style ReturnResults fill:#c8e6c9
    style UseHybrid fill:#fff9c4
    style ExecuteStrategies fill:#f3e5f5
```

## Cache Strategy

```mermaid
flowchart TD
    Start([Requisição de Recomendação]) --> CheckCache{Cache<br/>existe?<br/>chave: neo4j:hybrid:userId:limit}
    
    CheckCache -->|Sim| GetCache[Obtém do cache<br/>Laravel Cache]
    CheckCache -->|Não| ExecuteQuery[Executa queries<br/>Neo4j + PostgreSQL]
    
    GetCache --> ReturnCached[Retorna resultados<br/>em < 10ms]
    
    ExecuteQuery --> ProcessResults[Processa resultados<br/>combina estratégias]
    
    ProcessResults --> StoreCache[Armazena no cache<br/>TTL: 3600s<br/>1 hora]
    
    StoreCache --> ReturnFresh[Retorna resultados<br/>frescos]
    
    ReturnCached --> End([Fim])
    ReturnFresh --> End
    
    style Start fill:#e1f5ff
    style End fill:#c8e6c9
    style CheckCache fill:#fff9c4
    style ReturnCached fill:#c8e6c9
```

## Performance Metrics

```mermaid
flowchart LR
    Start([Recomendação Gerada]) --> LogMetrics[Registra Métricas]
    
    LogMetrics --> M1[Tempo de Execução<br/>ms]
    LogMetrics --> M2[Estratégias Usadas<br/>array]
    LogMetrics --> M3[Número de Candidatos<br/>int]
    LogMetrics --> M4[Scores Finais<br/>array]
    LogMetrics --> M5[Taxa de Cache Hit<br/>boolean]
    LogMetrics --> M6[Perfil do Usuário<br/>object]
    
    M1 --> Store[Armazena em Log]
    M2 --> Store
    M3 --> Store
    M4 --> Store
    M5 --> Store
    M6 --> Store
    
    Store --> Analyze[Análise de Performance]
    
    Analyze --> A1[Tempo médio<br/>p50, p95, p99]
    Analyze --> A2[Estratégias mais efetivas]
    Analyze --> A3[Diversidade de resultados]
    Analyze --> A4[Taxa de sucesso]
    
    style Start fill:#e1f5ff
    style Analyze fill:#fff9c4
```

---

## Legenda

- 🔵 **Azul claro**: Início/Fim de processos
- 🟢 **Verde**: Retorno de resultados bem-sucedidos
- 🟡 **Amarelo**: Processos críticos/importantes
- 🟣 **Roxo**: Processos auxiliares
- 🔴 **Vermelho**: Erros/Falhas

## Notas Importantes

1. **Cache**: Todas as recomendações são cacheadas por 1 hora (3600s)
2. **Fallback**: Se Neo4j falhar, o sistema usa automaticamente o algoritmo padrão
3. **Pesos Adaptativos**: Ajustados automaticamente baseado no número de interações
4. **Diversificação**: Máximo de 40% dos resultados podem ser do mesmo gênero
5. **Sincronização**: Interações são sincronizadas automaticamente para o Neo4j
6. **Logs**: Todas as operações geram logs detalhados para monitoramento

