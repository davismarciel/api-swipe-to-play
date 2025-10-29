# API Documentation - Swipe to Play

## 📋 Base URL
```
http://localhost:8000/api
```

## 🔐 Autenticação
Todas as rotas requerem autenticação via JWT Token.

Header:
```
Authorization: Bearer {token}
```

---

## 🎮 Módulo de Recomendações

### 1. Obter Recomendações Personalizadas
**GET** `/recommendations`

**Query Parameters:**
- `limit` (opcional, integer, 1-50): Quantidade de jogos a retornar (padrão: 10)

**Response:**
```json
{
  "success": true,
  "data": [...],
  "meta": {
    "count": 10,
    "limit": 10
  }
}
```

---

### 2. Obter Jogos Similares
**GET** `/recommendations/similar/{gameId}`

**Query Parameters:**
- `limit` (opcional, integer, 1-20): Quantidade de jogos similares (padrão: 5)

**Response:**
```json
{
  "success": true,
  "data": [...],
  "meta": {
    "game_id": 123,
    "game_name": "Nome do Jogo",
    "count": 5
  }
}
```

---

### 3. Obter Estatísticas do Usuário
**GET** `/recommendations/stats`

**Response:**
```json
{
  "success": true,
  "data": {
    "level": 5,
    "experience_points": 450,
    "total_likes": 25,
    "total_dislikes": 10,
    "total_favorites": 8,
    "total_views": 150,
    "interactions_count": 200,
    "favorite_genres": [...],
    "favorite_categories": [...]
  }
}
```

---

## 👍 Módulo de Interações com Jogos

### 4. Like em um Jogo
**POST** `/games/{gameId}/like`

**Response:**
```json
{
  "success": true,
  "message": "Game liked successfully",
  "data": {...}
}
```

---

### 5. Dislike em um Jogo
**POST** `/games/{gameId}/dislike`

---

### 6. Favoritar um Jogo
**POST** `/games/{gameId}/favorite`

---

### 7. Registrar Visualização
**POST** `/games/{gameId}/view`

---

### 8. Pular um Jogo
**POST** `/games/{gameId}/skip`

---

### 9. Histórico de Interações
**GET** `/interactions/history`

**Query Parameters:**
- `limit` (opcional, integer, 1-100): Quantidade de interações (padrão: 20)

**Response:**
```json
{
  "success": true,
  "data": [...],
  "meta": {
    "count": 20,
    "limit": 20
  }
}
```

---

### 10. Obter Jogos Favoritos
**GET** `/interactions/favorites`

**Response:**
```json
{
  "success": true,
  "data": [...],
  "meta": {
    "count": 8
  }
}
```

---

## ⚙️ Módulo de Preferências do Usuário

### 11. Obter Preferências
**GET** `/user/preferences`

**Response:**
```json
{
  "success": true,
  "data": {
    "preferences": {...},
    "monetization_preferences": {...},
    "preferred_genres": [...],
    "preferred_categories": [...],
    "profile": {...}
  }
}
```

---

### 12. Atualizar Preferências Gerais
**PUT** `/user/preferences`

**Body:**
```json
{
  "prefer_windows": true,
  "prefer_mac": false,
  "prefer_linux": false,
  "preferred_languages": ["en", "pt-BR"],
  "prefer_single_player": true,
  "prefer_multiplayer": true,
  "prefer_coop": true,
  "prefer_competitive": false,
  "min_age_rating": 0,
  "avoid_violence": false,
  "avoid_nudity": false,
  "max_price": 50.00,
  "prefer_free_to_play": false,
  "include_early_access": true
}
```

---

### 13. Atualizar Preferências de Monetização
**PUT** `/user/preferences/monetization`

**Body:**
```json
{
  "tolerance_microtransactions": 5,
  "tolerance_dlc": 7,
  "tolerance_season_pass": 5,
  "tolerance_loot_boxes": 3,
  "tolerance_battle_pass": 5,
  "tolerance_ads": 2,
  "tolerance_pay_to_win": 0,
  "prefer_cosmetic_only": true,
  "avoid_subscription": false,
  "prefer_one_time_purchase": true
}
```

**Nota:** Valores de tolerância variam de 0 (recusa completamente) a 10 (aceita totalmente)

---

### 14. Atualizar Gêneros Preferidos
**PUT** `/user/preferences/genres`

**Body:**
```json
{
  "genres": [
    {
      "genre_id": 1,
      "preference_weight": 10
    },
    {
      "genre_id": 3,
      "preference_weight": 8
    }
  ]
}
```

**Nota:** `preference_weight` varia de 1 (pouco interesse) a 10 (muito interesse)

---

### 15. Atualizar Categorias Preferidas
**PUT** `/user/preferences/categories`

**Body:**
```json
{
  "categories": [
    {
      "category_id": 1,
      "preference_weight": 9
    },
    {
      "category_id": 2,
      "preference_weight": 7
    }
  ]
}
```

---

## 🎮 Módulo de Jogos

### 16. Listar Jogos
**GET** `/games`

**Query Parameters:**
- `search` (opcional, string): Busca por nome ou descrição
- `genre_id` (opcional, integer): Filtrar por gênero
- `category_id` (opcional, integer): Filtrar por categoria
- `is_free` (opcional, boolean): Filtrar free-to-play
- `platform` (opcional, enum: windows|mac|linux): Filtrar por plataforma
- `per_page` (opcional, integer, 1-50): Itens por página (padrão: 15)

**Response:**
```json
{
  "success": true,
  "data": [...],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 150,
    "last_page": 10
  }
}
```

---

### 17. Detalhes de um Jogo
**GET** `/games/{id}`

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "steam_id": "123456",
    "name": "Nome do Jogo",
    "slug": "nome-do-jogo",
    "short_description": "Descrição curta",
    "detailed_description": "Descrição detalhada",
    "developer": {...},
    "publisher": {...},
    "genres": [...],
    "categories": [...],
    "platform": {
      "windows": true,
      "mac": false,
      "linux": true
    },
    "requirements": [...],
    "ratings": [...],
    "media": [...],
    "price": 29.99,
    "is_free": false,
    "release_date": "2024-01-15",
    "positive_reviews": 1500,
    "negative_reviews": 100
  }
}
```

---

### 18. Listar Gêneros
**GET** `/genres`

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Action",
      "slug": "action",
      "description": "..."
    }
  ]
}
```

---

### 19. Listar Categorias
**GET** `/categories`

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Single-player",
      "slug": "single-player",
      "description": "..."
    }
  ]
}
```

---

## 📊 Códigos de Status HTTP

- `200 OK`: Requisição bem-sucedida
- `201 Created`: Recurso criado com sucesso
- `400 Bad Request`: Dados de entrada inválidos
- `401 Unauthorized`: Token inválido ou ausente
- `404 Not Found`: Recurso não encontrado
- `422 Unprocessable Entity`: Erro de validação
- `500 Internal Server Error`: Erro no servidor

---

## 🔄 Fluxo de Uso Recomendado

### 1️⃣ Configuração Inicial
1. Usuário faz login (recebe JWT token)
2. Atualiza preferências gerais: `PUT /user/preferences`
3. Atualiza preferências de monetização: `PUT /user/preferences/monetization`
4. Seleciona gêneros favoritos: `PUT /user/preferences/genres`
5. Seleciona categorias favoritas: `PUT /user/preferences/categories`

### 2️⃣ Descoberta de Jogos
1. Obtém recomendações personalizadas: `GET /recommendations`
2. Para cada jogo recomendado:
   - Registra visualização: `POST /games/{id}/view`
   - Vê detalhes: `GET /games/{id}`
   - Interage: `POST /games/{id}/like` ou `POST /games/{id}/dislike`
   - Opcional: `POST /games/{id}/favorite`

### 3️⃣ Exploração Adicional
1. Busca jogos similares: `GET /recommendations/similar/{gameId}`
2. Filtra catálogo: `GET /games?genre_id=1&platform=windows`

### 4️⃣ Acompanhamento
1. Verifica estatísticas: `GET /recommendations/stats`
2. Revisa histórico: `GET /interactions/history`
3. Acessa favoritos: `GET /interactions/favorites`

---

## 🎯 Exemplo de Uso com cURL

```bash
# Obter recomendações
curl -X GET "http://localhost:8000/api/recommendations?limit=5" \
  -H "Authorization: Bearer {token}"

# Like em um jogo
curl -X POST "http://localhost:8000/api/games/123/like" \
  -H "Authorization: Bearer {token}"

# Atualizar preferências
curl -X PUT "http://localhost:8000/api/user/preferences" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "prefer_windows": true,
    "max_price": 50.00,
    "prefer_single_player": true
  }'
```

---

## 📝 Notas Importantes

1. **Autenticação**: Todas as rotas requerem JWT token válido
2. **Rate Limiting**: Implementar rate limiting em produção
3. **Paginação**: Endpoints de listagem usam paginação padrão do Laravel
4. **Validação**: Todos os inputs são validados antes do processamento
5. **Erros**: Respostas de erro seguem padrão JSON com mensagens descritivas

