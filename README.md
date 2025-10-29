# API STP - Sistema de Gestão

Uma API RESTful desenvolvida em Laravel 12 com arquitetura modular para gerenciamento de usuários e autenticação.

## 🎯 Objetivo do Projeto

Esta API foi desenvolvida para fornecer uma base sólida para sistemas de gestão, oferecendo:

- **Autenticação JWT** para segurança de API
- **Integração com Google OAuth** para login social
- **Arquitetura Modular** usando Laravel Modules
- **Documentação Automática** com Scramble
- **Respostas Padronizadas** para consistência da API
- **Cache Redis** para performance
- **Containerização** com Docker

## 🚀 Tecnologias Utilizadas

- **Laravel 12** - Framework PHP
- **PostgreSQL** - Banco de dados
- **Redis** - Cache e sessões
- **JWT Auth** - Autenticação
- **Laravel Socialite** - OAuth
- **Scramble** - Documentação da API
- **Docker** - Containerização
- **Nginx** - Servidor web

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
- `POST /api/auth/login` - Login
- `POST /api/auth/register` - Registro
- `POST /api/auth/logout` - Logout
- `GET /api/auth/google` - Login com Google

### Usuários
- `GET /api/v1/users` - Listar usuários
- `GET /api/v1/users/{id}` - Buscar usuário
- `DELETE /api/v1/users/{id}` - Deletar usuário
- `GET /api/v1/test` - Teste de conectividade

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

## 🏗️ Estrutura do Projeto

```
api-stp/
├── app/
│   ├── Http/Controllers/
│   └── Traits/
├── Modules/
│   ├── Auth/           # Módulo de autenticação
│   └── User/           # Módulo de usuários
├── config/
├── database/
│   └── migrations/
├── docker/
└── routes/
```

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
