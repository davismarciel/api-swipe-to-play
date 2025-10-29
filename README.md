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
- **PgAdmin**: http://localhost:8080
- **Documentação da API**: http://localhost:8000/docs

## 📚 Endpoints Disponíveis

### Autenticação
- `POST /api/v1/auth/login` - Login de usuário (requer id_token do Google)
- `POST /api/v1/auth/logout` - Logout do usuário (requer autenticação JWT)
- `POST /api/v1/auth/refresh` - Renovar token JWT (requer autenticação JWT)
- `GET /api/v1/auth/me` - Obter dados do usuário logado (requer autenticação JWT)

### Usuários
- `GET /api/v1/users` - Listar usuários
- `POST /api/v1/users` - Criar novo usuário
- `GET /api/v1/users/{id}` - Buscar usuário específico
- `PUT /api/v1/users/{id}` - Atualizar usuário
- `PATCH /api/v1/users/{id}` - Atualizar usuário (parcial)
- `DELETE /api/v1/users/{id}` - Deletar usuário

### Sistema
- `GET /api/v1/test` - Teste de conectividade da API
- `GET /docs/api` - Documentação interativa da API
- `GET /docs/api.json` - Documentação da API em formato JSON

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
