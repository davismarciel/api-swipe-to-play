# Swipe to Play (STP) - API de Recomendação de Jogos

Uma API RESTful desenvolvida em Laravel 12 para o aplicativo **Swipe to Play**, uma plataforma de recomendações personalizadas de jogos da Steam com elementos de gamificação.

## 🎯 Objetivo do Projeto

O **STP** (Show Me a Game) é um aplicativo voltado para recomendações personalizadas de jogos da Steam, oferecendo:

- **Recomendações Personalizadas** baseadas nas preferências do usuário
- **Integração com API da Steam** para dados oficiais dos jogos
- **Sistema de Gamificação** com curtidas, descurtidas e favoritos
- **Análise de Qualidade** com ratings de toxicidade, bugs e microtransações
- **Autenticação JWT** com Google OAuth para login social
- **Arquitetura Modular** usando Laravel Modules
- **Documentação Automática** com Scramble
- **Cache Redis** para performance otimizada

## 🚀 Tecnologias Utilizadas

- **Laravel 12** - Framework PHP
- **PostgreSQL** - Banco de dados principal
- **Redis** - Cache e sessões
- **JWT Auth** - Autenticação segura
- **Laravel Socialite** - Integração Google OAuth
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

# JWT
JWT_SECRET=sua-chave-jwt-secreta

# Google OAuth
GOOGLE_CLIENT_ID=seu-google-client-id
GOOGLE_CLIENT_SECRET=seu-google-client-secret
GOOGLE_REDIRECT_URL=http://localhost:8000/api/auth/google/callback

# Steam API
STEAM_API_KEY=sua-steam-api-key
STEAM_API_URL=https://store.steampowered.com/api
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
- **PgAdmin**: http://localhost:8080
- **Documentação da API**: http://localhost:8000/docs

## 📚 Endpoints Disponíveis

### Autenticação
- `POST /api/auth/login` - Login de usuário
- `POST /api/auth/register` - Registro de novo usuário
- `POST /api/auth/logout` - Logout do usuário
- `GET /api/auth/google` - Login com Google OAuth
- `GET /api/auth/google/callback` - Callback do Google OAuth

### Usuários e Perfil
- `GET /api/v1/users` - Listar usuários
- `GET /api/v1/users/{id}` - Buscar usuário específico
- `PUT /api/v1/users/{id}` - Atualizar perfil do usuário
- `DELETE /api/v1/users/{id}` - Deletar usuário
- `GET /api/v1/users/{id}/preferences` - Obter preferências do usuário
- `PUT /api/v1/users/{id}/preferences` - Atualizar preferências de jogos

### Jogos e Recomendações
- `GET /api/v1/games` - Listar jogos recomendados
- `GET /api/v1/games/{id}` - Obter detalhes de um jogo
- `POST /api/v1/games/{id}/like` - Curtir um jogo
- `POST /api/v1/games/{id}/dislike` - Descurtir um jogo
- `POST /api/v1/games/{id}/favorite` - Favoritar um jogo
- `GET /api/v1/games/search` - Buscar jogos por critérios
- `GET /api/v1/games/similar/{id}` - Obter jogos similares

### Sistema
- `GET /api/v1/test` - Teste de conectividade da API

## 🧪 Testando a API

### Teste básico de conectividade

```bash
curl http://localhost:8000/api/v1/test
```

### Login de usuário

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "usuario@exemplo.com",
    "password": "senha123"
  }'
```

### Obter jogos recomendados

```bash
curl -X GET http://localhost:8000/api/v1/games \
  -H "Authorization: Bearer SEU_JWT_TOKEN" \
  -H "Content-Type: application/json"
```

### Curtir um jogo

```bash
curl -X POST http://localhost:8000/api/v1/games/730/like \
  -H "Authorization: Bearer SEU_JWT_TOKEN" \
  -H "Content-Type: application/json"
```

### Atualizar preferências do usuário

```bash
curl -X PUT http://localhost:8000/api/v1/users/1/preferences \
  -H "Authorization: Bearer SEU_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "platforms": {
      "windows": true,
      "mac": false,
      "linux": true
    },
    "genres": ["Action", "RPG", "Indie"],
    "categories": ["Multi-player", "Co-op"],
    "play_style": ["Competitive", "Story-driven"],
    "monetization": {
      "free_to_play": true,
      "no_microtransactions": false,
      "time_spenter": "casual",
      "stress_taker": false
    }
  }'
```

## 🏗️ Estrutura do Projeto

```
api-stp/
├── app/
│   ├── Http/Controllers/
│   └── Traits/
├── Modules/
│   ├── Auth/           # Módulo de autenticação
│   ├── User/           # Módulo de usuários e perfis
│   ├── Game/           # Módulo de jogos e recomendações
│   └── Steam/          # Módulo de integração Steam API
├── config/
├── database/
│   └── migrations/
├── docker/
└── routes/
```

## 📊 Estrutura de Dados

### Entidade Usuário
- **Perfil**: avatar, bio, preferências de plataforma
- **Preferências**: gêneros, categorias, estilo de jogo, monetização
- **Atividade**: jogos curtidos, descurtidos, visualizados

### Entidade Jogo
- **Informações Básicas**: nome, descrição, desenvolvedores, publishers
- **Plataformas**: Windows, Mac, Linux com requisitos específicos
- **Mídia**: ícones, trailers, screenshots
- **Ratings de Qualidade**: toxicidade, bugs, microtransações, otimização
- **Avaliações**: reviews positivas/negativas, proporção de aprovação

## 🎮 Funcionalidades Principais

### Sistema de Recomendação
- Algoritmo baseado nas preferências do usuário
- Análise de compatibilidade com plataformas
- Consideração de ratings de qualidade dos jogos
- Sugestões de jogos similares

### Gamificação
- Sistema de curtidas/descurtidas
- Favoritos para acesso rápido
- Histórico de visualizações
- Perfil personalizado com preferências

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

## 📖 Documentação da API

A documentação interativa da API está disponível em `/docs` quando a aplicação estiver rodando. Ela é gerada automaticamente pelo Scramble baseada nas rotas e controllers.

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
