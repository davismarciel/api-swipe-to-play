# Swipe to Play (STP) - API de Recomendação de Jogos

Uma API RESTful desenvolvida em Laravel 12 para o aplicativo **Swipe to Play**, uma plataforma de recomendações personalizadas de jogos da Steam com elementos de gamificação.

## 🎯 Objetivo do Projeto

O **STP** (Swipe to Play) é um aplicativo voltado para recomendações personalizadas de jogos da Steam, oferecendo:

- **Sistema de Recomendação Baseado em Grafos** utilizando Neo4j para análises de similaridade entre jogos
- **Recomendações Personalizadas** baseadas em interações do usuário e preferências configuráveis
- **Integração com API da Steam** para dados oficiais dos jogos
- **Sistema de Interações** com like, dislike, favorite, view e skip que alimentam o algoritmo
- **Preferências Avançadas** incluindo plataformas, monetização, gêneros e categorias
- **Análise de Qualidade** com ratings de toxicidade, bugs e microtransações
- **Autenticação JWT** com Google OAuth para login social
- **Arquitetura Modular** usando Laravel Modules
- **Sincronização Bidirecional** entre PostgreSQL e Neo4j
- **Documentação Automática** com Scramble
- **Cache Redis** para performance otimizada

## 🚀 Tecnologias Utilizadas

- **Laravel 12** - Framework PHP
- **PostgreSQL** - Banco de dados relacional principal
- **Neo4j** - Banco de dados de grafos para sistema de recomendação
- **Redis** - Cache e sessões
- **JWT Auth** - Autenticação segura
- **Google API Client** - Integração Google OAuth
- **Steam API** - Dados oficiais dos jogos
- **Scramble** - Documentação automática da API
- **Docker** - Containerização completa
- **Nginx** - Servidor web otimizado

## 📋 Pré-requisitos

- Docker e Docker Compose
- PHP 8.2+ (se rodando localmente)
- Composer (se rodando localmente)
- Node.js (para assets frontend)

## 🛠️ Instalação e Configuração

### 1. Clone o repositório

```bash
git clone <url-do-repositorio>
cd api-stp
```

### 2. Configure as variáveis de ambiente

```bash
cp .env.example .env
```

Edite o arquivo `.env` com suas configurações:

```env
# Database
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=laravel
DB_PASSWORD=secret

# Redis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

# Neo4j
NEO4J_URI=bolt://neo4j:7687
NEO4J_USERNAME=neo4j
NEO4J_PASSWORD=password
NEO4J_DATABASE=neo4j

# JWT
JWT_SECRET=sua-chave-jwt-secreta

# Google OAuth
GOOGLE_CLIENT_ID=seu-google-client-id
GOOGLE_CLIENT_SECRET=seu-google-client-secret
GOOGLE_REDIRECT_URL=http://localhost:8000/api/auth/google/callback

```

### 3. Execute com Docker

```bash
# Subir os containers
docker-compose up -d

# Instalar dependências
docker-compose exec app composer install

# Gerar chave da aplicação
docker-compose exec app php artisan key:generate

# Gerar chave JWT
docker-compose exec app php artisan jwt:secret

# Executar migrações
docker-compose exec app php artisan migrate

# Executar seeders (opcional)
docker-compose exec app php artisan db:seed
```

### 4. Acessar a aplicação

- **API**: http://localhost:8000
- **Neo4j Browser**: http://localhost:7474
- **PgAdmin**: http://localhost:8080
- **Documentação da API**: http://localhost:8000/docs

## 📚 Endpoints Disponíveis

### Autenticação
- `GET /api/v1/auth/health` - Health check do serviço de autenticação (sem autenticação)
- `POST /api/v1/auth/login` - Login de usuário (requer id_token do Google)
- `POST /api/v1/auth/refresh` - Renovar token JWT (requer refresh token)
- `POST /api/v1/auth/logout` - Logout do usuário (requer autenticação JWT)
- `GET /api/v1/auth/me` - Obter dados do usuário logado (requer autenticação JWT)

### Usuários
- `GET /api/v1/test` - Teste de conectividade da API (sem autenticação)
- `GET /api/v1/users` - Listar usuários (sem autenticação)
- `POST /api/v1/users` - Criar novo usuário (sem autenticação)
- `GET /api/v1/users/{id}` - Buscar usuário específico (sem autenticação)
- `PUT /api/v1/users/{id}` - Atualizar usuário (sem autenticação)
- `PATCH /api/v1/users/{id}` - Atualizar usuário parcialmente (sem autenticação)
- `DELETE /api/v1/users/{id}` - Deletar usuário (sem autenticação)

### Onboarding
- `POST /api/onboarding/complete` - Completar processo de onboarding (requer autenticação JWT)

### Preferências do Usuário
- `GET /api/user/preferences` - Obter preferências do usuário autenticado (requer autenticação JWT e onboarding completo)
- `PUT /api/user/preferences` - Atualizar preferências gerais (requer autenticação JWT e onboarding completo)
- `PUT /api/user/preferences/monetization` - Atualizar preferências de monetização (requer autenticação JWT e onboarding completo)
- `PUT /api/user/preferences/genres` - Atualizar gêneros preferidos (requer autenticação JWT e onboarding completo)
- `PUT /api/user/preferences/categories` - Atualizar categorias preferidas (requer autenticação JWT e onboarding completo)

### Jogos
- `GET /api/games` - Listar jogos (requer autenticação JWT)
- `GET /api/games/{id}` - Buscar jogo específico (requer autenticação JWT)
- `GET /api/genres` - Listar gêneros disponíveis (requer autenticação JWT)
- `GET /api/categories` - Listar categorias disponíveis (requer autenticação JWT)

### Recomendações
- `GET /api/recommendations` - Obter recomendações personalizadas (requer autenticação JWT e onboarding completo, rate limit: 60/min)
- `GET /api/recommendations/stats` - Obter estatísticas de recomendação do usuário (requer autenticação JWT e onboarding completo, rate limit: 60/min)
- `GET /api/recommendations/similar/{gameId}` - Obter jogos similares a um jogo específico (requer autenticação JWT e onboarding completo, rate limit: 60/min)

### Interações com Jogos
- `POST /api/games/{gameId}/like` - Curtir um jogo (requer autenticação JWT e onboarding completo, rate limit: 100/min)
- `POST /api/games/{gameId}/dislike` - Descurtir um jogo (requer autenticação JWT e onboarding completo, rate limit: 100/min)
- `POST /api/games/{gameId}/favorite` - Adicionar jogo aos favoritos (requer autenticação JWT e onboarding completo, rate limit: 100/min)
- `POST /api/games/{gameId}/view` - Registrar visualização de jogo (requer autenticação JWT e onboarding completo, rate limit: 100/min)
- `POST /api/games/{gameId}/skip` - Pular um jogo (requer autenticação JWT e onboarding completo, rate limit: 100/min)

### Sistema
- `GET /docs/api` - Documentação interativa da API (Scramble)
- `GET /docs/api.json` - Documentação da API em formato JSON

## 🎮 Funcionalidades Principais

### Sistema de Recomendação Baseado em Grafos (Neo4j)

O sistema utiliza **Neo4j** como motor de recomendação, aproveitando a natureza de grafos para análises complexas de relacionamentos entre usuários e jogos:

- **Recomendações Colaborativas por Similaridade**: Utiliza queries Cypher para encontrar jogos similares aos que o usuário curtiu ou favoritou
- **Grafo de Relacionamentos**: Modela usuários, jogos e interações como nós e relacionamentos no grafo
- **Sincronização Bidirecional**: Mantém PostgreSQL e Neo4j sincronizados automaticamente quando interações são registradas
- **Fallback Inteligente**: Se o Neo4j não retornar recomendações, utiliza filtros baseados em preferências do usuário
- **Score de Similaridade**: Calcula scores baseados no número de jogos similares que o usuário interagiu positivamente
- **Exclusão de Jogos Interagidos**: Automaticamente exclui jogos que o usuário já curtiu, descurtiu ou pulou
- **Filtros Personalizados**: Aplica preferências de plataforma, preço e conteúdo antes de retornar recomendações

#### Tipos de Interações Suportadas
- **Like** (score: +10) - Indica interesse positivo no jogo
- **Favorite** (score: +15) - Marca jogo como favorito (maior peso)
- **View** (score: +1) - Registra visualização do jogo
- **Dislike** (score: -5) - Indica desinteresse no jogo
- **Skip** (score: -2) - Indica que o usuário pulou o jogo

### Sistema de Preferências do Usuário

#### Preferências Gerais
- **Plataformas**: Windows, Mac, Linux
- **Idiomas**: Lista de idiomas preferidos
- **Gameplay**: Single-player, Multiplayer, Co-op, Competitivo
- **Conteúdo**: Classificação etária mínima, evitar violência/nudez
- **Preço**: Preço máximo, preferência por jogos gratuitos, incluir early access

#### Preferências de Monetização
- **Tolerâncias** (escala 0-10): Microtransações, DLCs, Season Pass, Loot Boxes, Battle Pass, Anúncios, Pay-to-Win
- **Preferências Específicas**: Apenas cosméticos, evitar assinaturas, preferir compra única

#### Preferências de Gêneros e Categorias
- Seleção de gêneros preferidos (ex: RPG, FPS, Strategy)
- Seleção de categorias preferidas (ex: Single-player, Multiplayer, Co-op)

### Gamificação
- **Sistema de Interações**: Like, dislike, favorite, view e skip que alimentam o algoritmo
- **Histórico de Interações**: Acompanhamento completo das interações do usuário
- **Favoritos**: Lista de jogos favoritados para acesso rápido
- **Estatísticas do Usuário**: Nível, pontos de experiência, contadores de interações
- **Perfil Comportamental**: Análise de padrões de gostos e aversões baseada em interações
- **Jogos Similares**: Encontra jogos relacionados usando o grafo de similaridade

### Análise de Qualidade
- Ratings automáticos baseados em reviews da Steam
- Indicadores de toxicidade, bugs e microtransações
- Análise de otimização e performance
- Taxa de recomendação da comunidade

## 🔧 Comandos Úteis

### Docker
```bash
# Parar containers
docker-compose down

# Ver logs
docker-compose logs -f app

# Acessar container
docker-compose exec app bash

# Rebuild containers
docker-compose up -d --build
```

### Laravel
```bash
# Limpar cache
docker-compose exec app php artisan cache:clear

# Limpar configurações
docker-compose exec app php artisan config:clear

# Executar testes
docker-compose exec app php artisan test

# Gerar documentação
docker-compose exec app php artisan scramble:export
```

### Neo4j
```bash
# Acessar Neo4j Browser
# Abra http://localhost:7474 no navegador
# Login padrão: neo4j / password

# Verificar conexão Neo4j
docker-compose exec app php artisan tinker
# No tinker: app(Neo4jConnectionInterface::class)->testConnection()
```

## 📖 Documentação da API

A documentação interativa da API está disponível em `/docs` quando a aplicação estiver rodando. Ela é gerada automaticamente pelo Scramble baseada nas rotas e controllers.

### Arquitetura do Sistema de Recomendação

O sistema utiliza uma arquitetura híbrida combinando PostgreSQL e Neo4j:

1. **PostgreSQL**: Armazena dados relacionais (usuários, jogos, interações, preferências)
2. **Neo4j**: Armazena o grafo de relacionamentos (usuários ↔ jogos ↔ similaridades)
3. **Sincronização Automática**: Quando uma interação é registrada:
   - Salva no PostgreSQL
   - Sincroniza usuário, jogo e interação no Neo4j
   - Atualiza relacionamentos no grafo

#### Query de Recomendação (Cypher)

```cypher
MATCH (u:User {id: $userId})
MATCH (u)-[:LIKED|FAVORITED]->(g1:Game)<-[:SIMILAR_TO]-(g2:Game)
WHERE NOT (u)-[:LIKED|DISLIKED|SKIPPED]->(g2)
WITH g2, count(DISTINCT g1) as similarityScore
ORDER BY similarityScore DESC
LIMIT $limit
RETURN g2.id as game_id, similarityScore as score
```

Esta query encontra jogos similares aos que o usuário curtiu/favoritou, excluindo aqueles que já foram interagidos.

### Integração com Steam API

A API integra-se diretamente com a Steam API oficial para obter:
- Informações detalhadas dos jogos
- Requisitos de sistema
- Trailers e mídia
- Reviews e avaliações da comunidade
- Dados de desenvolvedores e publishers

### Sistema de Ratings

Os ratings de qualidade são calculados automaticamente através da análise de reviews da Steam:
- **Toxicidade**: Taxa de comentários tóxicos na comunidade
- **Bugs**: Frequência de relatos de bugs
- **Microtransações**: Presença e impacto de microtransações
- **Otimização**: Problemas de performance e otimização
- **Cheaters**: Taxa de jogadores que fazem trapaça (apenas multiplayer)
- **Not Recommended**: Taxa de usuários que não recomendam o jogo

O sistema de recomendação utiliza essas informações junto com as preferências do usuário para filtrar e priorizar recomendações.

## 🔄 Fluxo de Uso Recomendado

### 1️⃣ Autenticação
1. Usuário faz login via Google OAuth: `POST /api/v1/auth/login` (recebe JWT token e refresh token)
2. Verifica saúde do serviço: `GET /api/v1/auth/health`

### 2️⃣ Onboarding
1. Completa o processo de onboarding: `POST /api/onboarding/complete`
   - Este passo é obrigatório antes de acessar recomendações e preferências

### 3️⃣ Configuração Inicial
1. Atualiza preferências gerais: `PUT /api/user/preferences`
2. Atualiza preferências de monetização: `PUT /api/user/preferences/monetization`
3. Seleciona gêneros favoritos: `PUT /api/user/preferences/genres`
4. Seleciona categorias favoritas: `PUT /api/user/preferences/categories`

### 4️⃣ Descoberta de Jogos
1. Obtém recomendações personalizadas: `GET /api/recommendations`
2. Para cada jogo recomendado:
   - Registra visualização: `POST /api/games/{gameId}/view`
   - Vê detalhes: `GET /api/games/{id}`
   - Interage: `POST /api/games/{gameId}/like` ou `POST /api/games/{gameId}/dislike`
   - Opcional: `POST /api/games/{gameId}/favorite`

### 5️⃣ Exploração Adicional
1. Busca jogos similares: `GET /api/recommendations/similar/{gameId}`
2. Filtra catálogo: `GET /api/games?genre_id=1&platform=windows`
3. Lista gêneros disponíveis: `GET /api/genres`
4. Lista categorias disponíveis: `GET /api/categories`

### 6️⃣ Acompanhamento
1. Verifica estatísticas: `GET /api/recommendations/stats`
2. Consulta preferências: `GET /api/user/preferences`

### 7️⃣ Manutenção de Sessão
1. Renova token quando necessário: `POST /api/v1/auth/refresh`
2. Faz logout: `POST /api/v1/auth/logout`

## 🤝 Contribuição

1. Faça um fork do projeto
2. Crie uma branch para sua feature (`git checkout -b feature/AmazingFeature`)
3. Commit suas mudanças (`git commit -m 'feat: add some AmazingFeature'`)
4. Push para a branch (`git push origin feature/AmazingFeature`)
5. Abra um Pull Request

## 📝 Licença

Este projeto está sob a licença MIT. Veja o arquivo `LICENSE` para mais detalhes.

## 🆘 Suporte

Se você encontrar algum problema ou tiver dúvidas, por favor abra uma issue no repositório.
