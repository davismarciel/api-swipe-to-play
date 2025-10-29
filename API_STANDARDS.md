# 📘 Padrões da API - Swipe to Play

## 🎯 Padronização de Respostas JSON

Todos os endpoints da API seguem um padrão consistente usando:

1. **Trait `ApiResponseFormat`** - Padroniza o formato das respostas JSON
2. **API Resources** - Transforma os dados de cada modelo/endpoint

### 📦 Arquitetura

```
Controller → Resource (transforma dados) → Trait (formata resposta) → JSON Response
```

## 🛠️ Trait ApiResponseFormat

Localizada em `app/Traits/ApiResponseFormat.php`, fornece métodos para padronizar respostas.

### Métodos Disponíveis

#### 1. `successResponse($data = null, ?string $message = null, int $statusCode = 200)`
Retorna uma resposta de sucesso padrão.

**Uso:**
```php
return $this->successResponse(new GameResource($game), 'Game retrieved successfully');
```

**Output:**
```json
{
    "success": true,
    "message": "Game retrieved successfully",
    "data": {
        // dados transformados pela Resource
    }
}
```

#### 2. `createdResponse($data = null, ?string $message = 'Resource created successfully')`
Retorna uma resposta de criação (201).

**Uso:**
```php
return $this->createdResponse(new GameInteractionResource($interaction), 'Game liked successfully');
```

**Output:**
```json
{
    "success": true,
    "message": "Game liked successfully",
    "data": {
        // dados transformados
    }
}
```

#### 3. `paginatedResponse($data, ?string $message = null)`
Retorna uma resposta paginada com metadados.

**Uso:**
```php
$games = Game::paginate(15);
return $this->paginatedResponse(GameResource::collection($games));
```

**Output:**
```json
{
    "success": true,
    "data": [
        // array de recursos transformados
    ],
    "pagination": {
        "current_page": 1,
        "from": 1,
        "last_page": 10,
        "per_page": 15,
        "to": 15,
        "total": 150
    }
}
```

#### 4. `errorResponse(string $message, int $statusCode = 400, $errors = null)`
Retorna uma resposta de erro genérica.

#### 5. `validationErrorResponse($errors)`
Retorna uma resposta de erro de validação (422).

**Output:**
```json
{
    "success": false,
    "message": "Validation failed",
    "errors": {
        "field_name": ["Error message"]
    }
}
```

#### 6. `unauthorizedResponse(string $message = 'Unauthorized')`
Retorna uma resposta de não autorizado (401).

#### 7. `forbiddenResponse(string $message = 'Forbidden')`
Retorna uma resposta de proibido (403).

#### 8. `notFoundResponse(string $message = 'Resource not found')`
Retorna uma resposta de não encontrado (404).

#### 9. `serverErrorResponse(string $message = 'Internal server error')`
Retorna uma resposta de erro interno (500).

## 📦 API Resources

Cada módulo/endpoint deve ter sua própria **Resource** para transformar os dados.

### Resources Criadas

#### Game Module
- `GameResource` - Transforma dados de um jogo
- `GameCollection` - Transforma coleção de jogos

#### User Module
- `UserResource` - Transforma dados de usuário
- `GenreResource` - Transforma dados de gênero
- `CategoryResource` - Transforma dados de categoria
- `UserPreferenceResource` - Transforma preferências do usuário
- `UserMonetizationPreferenceResource` - Transforma preferências de monetização

#### Auth Module
- `AuthResource` - Transforma resposta de autenticação (token, user)

#### Recommendation Module
- `GameInteractionResource` - Transforma dados de interação

### Exemplo de Resource

```php
<?php

namespace Modules\Game\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GameResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'is_free' => $this->is_free,
            // Eager loading condicional
            'genres' => $this->whenLoaded('genres'),
            'developers' => $this->whenLoaded('developers'),
            'created_at' => $this->created_at,
        ];
    }
}
```

## 📝 Exemplo de Controller Padronizado

```php
<?php

namespace Modules\Game\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Game\Models\Game;
use Modules\Game\Http\Resources\GameResource;

class GameController extends Controller
{
    /**
     * Lista jogos com paginação
     */
    public function index(Request $request): JsonResponse
    {
        $games = Game::with(['genres', 'categories'])->paginate(15);

        // Resource transforma os dados, Trait formata a resposta
        return $this->paginatedResponse(GameResource::collection($games));
    }

    /**
     * Exibe um jogo específico
     */
    public function show(int $id): JsonResponse
    {
        $game = Game::with(['genres', 'developers'])->findOrFail($id);

        // Resource transforma, Trait formata
        return $this->successResponse(new GameResource($game));
    }

    /**
     * Cria um novo jogo
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
            ]);

            $game = Game::create($validated);

            return $this->createdResponse(
                new GameResource($game),
                'Game created successfully'
            );
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        }
    }

    /**
     * Remove um jogo
     */
    public function destroy(int $id): JsonResponse
    {
        $game = Game::findOrFail($id);
        $game->delete();

        return $this->successResponse(null, 'Game deleted successfully');
    }
}
```

## ✅ Checklist de Padronização

Ao criar um novo endpoint, certifique-se de:

- [ ] Controller estende `App\Http\Controllers\Controller` (já usa `ApiResponseFormat` trait)
- [ ] Criar uma **Resource** específica para transformar os dados do modelo
- [ ] Usar `new ResourceName($data)` para item único
- [ ] Usar `ResourceName::collection($data)` para coleções
- [ ] Usar `$this->successResponse()` para respostas de sucesso
- [ ] Usar `$this->createdResponse()` para criação de recursos (POST)
- [ ] Usar `$this->paginatedResponse()` para listas paginadas
- [ ] Usar métodos de erro da trait (`validationErrorResponse`, `unauthorizedResponse`, etc.)
- [ ] Incluir `JsonResponse` como return type
- [ ] Incluir mensagens descritivas quando apropriado
- [ ] Usar `whenLoaded()` nas Resources para relacionamentos opcionais

## 🎨 Convenções de Nomenclatura

### Controllers
- PascalCase + sufixo `Controller` (ex: `GameController`)
- Métodos: camelCase (ex: `getUserPreferences`)

### Resources
- PascalCase + sufixo `Resource` (ex: `GameResource`)
- Localização: `Modules/{Module}/app/Http/Resources/`

### Rotas
- kebab-case (ex: `/api/user-preferences`)

### JSON (Responses)
- snake_case (ex: `user_id`, `created_at`)
- Sempre incluir `success: true/false`
- Sempre incluir `data` quando há dados
- Incluir `message` quando apropriado
- Incluir `pagination` para listas paginadas
- Incluir `errors` para erros de validação

## 📦 Estrutura de Resposta Padrão

### Sucesso com dados únicos
```json
{
    "success": true,
    "message": "Resource retrieved successfully",
    "data": {
        "id": 1,
        "name": "Example"
    }
}
```

### Sucesso com coleção
```json
{
    "success": true,
    "data": [
        {"id": 1},
        {"id": 2}
    ]
}
```

### Sucesso com paginação
```json
{
    "success": true,
    "data": [],
    "pagination": {
        "current_page": 1,
        "per_page": 15,
        "total": 100,
        "last_page": 7
    }
}
```

### Erro
```json
{
    "success": false,
    "message": "Error message"
}
```

### Erro de validação
```json
{
    "success": false,
    "message": "Validation failed",
    "errors": {
        "field": ["Error message"]
    }
}
```

## 🚀 Exemplo Completo

```php
// 1. Criar a Resource
// Modules/Game/app/Http/Resources/GameResource.php
class GameResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'genres' => $this->whenLoaded('genres'),
        ];
    }
}

// 2. Usar no Controller
// Modules/Game/app/Http/Controllers/Api/GameController.php
class GameController extends Controller
{
    public function show(int $id): JsonResponse
    {
        $game = Game::with('genres')->findOrFail($id);
        
        // Resource transforma, Trait formata
        return $this->successResponse(new GameResource($game));
    }
}

// 3. Resposta JSON
{
    "success": true,
    "data": {
        "id": 1,
        "name": "Game Name",
        "genres": [...]
    }
}
```

---

**Última atualização:** 29 de outubro de 2025

### 📦 API Resources Disponíveis

A API utiliza três resources principais localizados em `app/Http/Resources/`:

1. **ApiSuccessResource** - Respostas de sucesso (200, 201)
2. **ApiErrorResource** - Respostas de erro (400, 401, 403, 404, 422, 500)
3. **ApiPaginatedResource** - Respostas com paginação (200)

### ✅ Respostas de Sucesso

#### Sucesso Simples (200)
```php
return ApiSuccessResource::success($data, 'Operation completed successfully');
```

**Output:**
```json
{
    "success": true,
    "message": "Operation completed successfully",
    "data": {
        // dados do recurso
    }
}
```

#### Sucesso com Criação (201)
```php
return ApiSuccessResource::created($data, 'Resource created successfully');
```

**Output:**
```json
{
    "success": true,
    "message": "Resource created successfully",
    "data": {
        // dados do recurso criado
    }
}
```

#### Sucesso com Paginação (200)
```php
$games = Game::paginate(15);
return ApiPaginatedResource::paginated($games);
```

**Output:**
```json
{
    "success": true,
    "data": [
        // array de itens
    ],
    "pagination": {
        "current_page": 1,
        "from": 1,
        "last_page": 10,
        "per_page": 15,
        "to": 15,
        "total": 150
    }
}
```

### ❌ Respostas de Erro

#### Erro de Validação (422)
```php
return ApiErrorResource::validation($errors);
```

**Output:**
```json
{
    "success": false,
    "message": "Validation failed",
    "errors": {
        "field_name": [
            "Error message 1",
            "Error message 2"
        ]
    }
}
```

#### Erro de Autenticação (401)
```php
return ApiErrorResource::unauthorized('Invalid credentials');
```

**Output:**
```json
{
    "success": false,
    "message": "Invalid credentials"
}
```

#### Erro de Autorização (403)
```php
return ApiErrorResource::forbidden('You do not have permission');
```

**Output:**
```json
{
    "success": false,
    "message": "You do not have permission"
}
```

#### Recurso Não Encontrado (404)
```php
return ApiErrorResource::notFound('Game not found');
```

**Output:**
```json
{
    "success": false,
    "message": "Game not found"
}
```

#### Erro Interno do Servidor (500)
```php
return ApiErrorResource::serverError('Database connection failed');
```

**Output:**
```json
{
    "success": false,
    "message": "Database connection failed"
}
```

## 🛠️ API Resources - Métodos Disponíveis

### ApiSuccessResource

**Métodos estáticos:**

1. **`success($resource, ?string $message = null, int $statusCode = 200)`**
   ```php
   return ApiSuccessResource::success($user, 'User retrieved successfully');
   ```

2. **`created($resource, ?string $message = 'Resource created successfully')`**
   ```php
   return ApiSuccessResource::created($game, 'Game created successfully');
   ```

### ApiErrorResource

**Métodos estáticos:**

1. **`validation($errors)`** - Retorna erro 422
   ```php
   return ApiErrorResource::validation($validator->errors());
   ```

2. **`unauthorized(string $message = 'Unauthorized')`** - Retorna erro 401
   ```php
   return ApiErrorResource::unauthorized('Invalid token');
   ```

3. **`forbidden(string $message = 'Forbidden')`** - Retorna erro 403
   ```php
   return ApiErrorResource::forbidden('Access denied');
   ```

4. **`notFound(string $message = 'Resource not found')`** - Retorna erro 404
   ```php
   return ApiErrorResource::notFound('User not found');
   ```

5. **`serverError(string $message = 'Internal server error')`** - Retorna erro 500
   ```php
   return ApiErrorResource::serverError('Database error');
   ```

### ApiPaginatedResource

**Método estático:**

1. **`paginated($resource, ?string $message = null)`**
   ```php
   $games = Game::paginate(15);
   return ApiPaginatedResource::paginated($games);
   ```

## 📝 Exemplo de Controller Padronizado

```php
<?php

namespace Modules\Game\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiSuccessResource;
use App\Http\Resources\ApiPaginatedResource;
use App\Http\Resources\ApiErrorResource;
use Illuminate\Http\Request;
use Modules\Game\Models\Game;

class GameController extends Controller
{
    /**
     * Lista jogos com paginação
     */
    public function index(Request $request)
    {
        $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:50',
        ]);

        $games = Game::paginate($request->input('per_page', 15));

        return ApiPaginatedResource::paginated($games);
    }

    /**
     * Exibe um jogo específico
     */
    public function show(int $id)
    {
        $game = Game::findOrFail($id);

        return ApiSuccessResource::success($game);
    }

    /**
     * Cria um novo jogo
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                // mais validações...
            ]);

            $game = Game::create($validated);

            return ApiSuccessResource::created($game, 'Game created successfully');
        } catch (ValidationException $e) {
            return ApiErrorResource::validation($e->errors());
        } catch (\Exception $e) {
            return ApiErrorResource::serverError($e->getMessage());
        }
    }

    /**
     * Atualiza um jogo existente
     */
    public function update(Request $request, int $id)
    {
        $game = Game::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            // mais validações...
        ]);

        $game->update($validated);

        return ApiSuccessResource::success($game, 'Game updated successfully');
    }

    /**
     * Remove um jogo
     */
    public function destroy(int $id)
    {
        $game = Game::findOrFail($id);
        $game->delete();

        return ApiSuccessResource::success(null, 'Game deleted successfully');
    }
}
```

## ✅ Checklist de Padronização

Ao criar um novo controller, certifique-se de:

- [ ] Importar os API Resources necessários
- [ ] **NUNCA** usar `response()->json()` diretamente
- [ ] **NUNCA** usar a trait `ApiResponse` (foi substituída por Resources)
- [ ] Usar `ApiSuccessResource::success()` para respostas de sucesso
- [ ] Usar `ApiSuccessResource::created()` para criação de recursos (POST)
- [ ] Usar `ApiPaginatedResource::paginated()` para listas paginadas
- [ ] Usar métodos do `ApiErrorResource` para erros (`validation`, `unauthorized`, `notFound`, etc.)
- [ ] **NÃO** adicionar type hint `JsonResponse` nos métodos (Resources são Responsable)
- [ ] Incluir mensagens descritivas quando apropriado
- [ ] Documentar cada método com PHPDoc

### ✅ Respostas de Sucesso

#### Sucesso Simples (200)
```json
{
    "success": true,
    "message": "Operation completed successfully",
    "data": {
        // dados do recurso
    }
}
```

#### Sucesso com Criação (201)
```json
{
    "success": true,
    "message": "Resource created successfully",
    "data": {
        // dados do recurso criado
    }
}
```

#### Sucesso com Paginação (200)
```json
{
    "success": true,
    "data": [
        // array de itens
    ],
    "pagination": {
        "current_page": 1,
        "from": 1,
        "last_page": 10,
        "per_page": 15,
        "to": 15,
        "total": 150
    }
}
```

### ❌ Respostas de Erro

#### Erro de Validação (422)
```json
{
    "success": false,
    "message": "Validation failed",
    "errors": {
        "field_name": [
            "Error message 1",
            "Error message 2"
        ]
    }
}
```

#### Erro de Autenticação (401)
```json
{
    "success": false,
    "message": "Unauthorized"
}
```

#### Erro de Autorização (403)
```json
{
    "success": false,
    "message": "Forbidden"
}
```

#### Recurso Não Encontrado (404)
```json
{
    "success": false,
    "message": "Resource not found"
}
```

#### Erro Interno do Servidor (500)
```json
{
    "success": false,
    "message": "Internal server error"
}
```

## 🛠️ Trait ApiResponse

A trait `ApiResponse` está localizada em `app/Traits/ApiResponse.php` e fornece os seguintes métodos:

### Métodos Disponíveis

#### 1. `successResponse($data = null, ?string $message = null, int $statusCode = 200)`
Retorna uma resposta de sucesso padrão.

**Uso:**
```php
return $this->successResponse($user, 'User retrieved successfully');
```

#### 2. `createdResponse($data = null, ?string $message = 'Resource created successfully')`
Retorna uma resposta de criação (201).

**Uso:**
```php
return $this->createdResponse($game, 'Game created successfully');
```

#### 3. `paginatedResponse($data, ?string $message = null)`
Retorna uma resposta paginada com metadados.

**Uso:**
```php
$games = Game::paginate(15);
return $this->paginatedResponse($games);
```

#### 4. `errorResponse(string $message, int $statusCode = 400, $errors = null)`
Retorna uma resposta de erro genérica.

**Uso:**
```php
return $this->errorResponse('Something went wrong', 400);
```

#### 5. `validationErrorResponse($errors)`
Retorna uma resposta de erro de validação (422).

**Uso:**
```php
return $this->validationErrorResponse($validator->errors());
```

#### 6. `unauthorizedResponse(string $message = 'Unauthorized')`
Retorna uma resposta de não autorizado (401).

**Uso:**
```php
return $this->unauthorizedResponse('Invalid credentials');
```

#### 7. `forbiddenResponse(string $message = 'Forbidden')`
Retorna uma resposta de proibido (403).

**Uso:**
```php
return $this->forbiddenResponse('You do not have permission');
```

#### 8. `notFoundResponse(string $message = 'Resource not found')`
Retorna uma resposta de não encontrado (404).

**Uso:**
```php
return $this->notFoundResponse('Game not found');
```

#### 9. `serverErrorResponse(string $message = 'Internal server error')`
Retorna uma resposta de erro interno (500).

**Uso:**
```php
return $this->serverErrorResponse('Database connection failed');
```

## 📝 Exemplo de Controller Padronizado

```php
<?php

namespace Modules\Game\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Game\Models\Game;

class GameController extends Controller
{
    /**
     * Lista jogos com paginação
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:50',
        ]);

        $games = Game::paginate($request->input('per_page', 15));

        return $this->paginatedResponse($games);
    }

    /**
     * Exibe um jogo específico
     */
    public function show(int $id): JsonResponse
    {
        $game = Game::findOrFail($id);

        return $this->successResponse($game);
    }

    /**
     * Cria um novo jogo
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            // mais validações...
        ]);

        $game = Game::create($validated);

        return $this->createdResponse($game, 'Game created successfully');
    }

    /**
     * Atualiza um jogo existente
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $game = Game::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            // mais validações...
        ]);

        $game->update($validated);

        return $this->successResponse($game, 'Game updated successfully');
    }

    /**
     * Remove um jogo
     */
    public function destroy(int $id): JsonResponse
    {
        $game = Game::findOrFail($id);
        $game->delete();

        return $this->successResponse(null, 'Game deleted successfully');
    }
}
```

## ✅ Checklist de Padronização

Ao criar um novo controller, certifique-se de:

- [ ] Estender `App\Http\Controllers\Controller` (que já usa a trait `ApiResponse`)
- [ ] **NUNCA** usar `response()->json()` diretamente
- [ ] Usar `$this->successResponse()` para respostas de sucesso
- [ ] Usar `$this->createdResponse()` para criação de recursos (POST)
- [ ] Usar `$this->paginatedResponse()` para listas paginadas
- [ ] Usar métodos de erro apropriados (`unauthorizedResponse`, `notFoundResponse`, etc.)
- [ ] Incluir mensagens descritivas quando apropriado
- [ ] Documentar cada método com PHPDoc

## 🔒 Autenticação

Todos os endpoints protegidos devem usar o middleware `auth:api`:

```php
Route::middleware(['auth:api'])->prefix('api')->group(function () {
    Route::get('/games', [GameController::class, 'index']);
});
```

## 📊 Validação

Use Laravel Form Requests ou validação inline:

```php
$request->validate([
    'name' => 'required|string|max:255',
    'email' => 'required|email|unique:users',
]);
```

Para erros de validação, o Laravel automaticamente retorna uma resposta 422 com os erros.

## 🎨 Convenções de Nomenclatura

- **Controllers**: PascalCase + sufixo `Controller` (ex: `GameController`)
- **Métodos**: camelCase (ex: `getUserPreferences`)
- **Rotas**: kebab-case (ex: `/api/user-preferences`)
- **Variáveis JSON**: snake_case (ex: `user_id`, `created_at`)
- **Relacionamentos**: camelCase plural para muitos (ex: `preferredGenres`)

## 📦 Estrutura de Dados

### Timestamps
Sempre inclua timestamps nos modelos:
```php
'created_at' => '2025-10-29T10:30:00.000000Z',
'updated_at' => '2025-10-29T10:30:00.000000Z'
```

### Relacionamentos
Use eager loading para evitar N+1:
```php
$games = Game::with(['genres', 'categories', 'developers'])->get();
```

### Soft Deletes
Para recursos com soft delete, use o trait `SoftDeletes` do Laravel.

## 🚀 Versionamento da API

Todas as rotas devem incluir versionamento:
- Versão atual: `/api/v1/...`
- Endpoints legados podem existir sem prefixo `/v1/` mas devem ser migrados

## 📚 Documentação

A documentação da API é gerada automaticamente usando **Scramble**:
- URL de desenvolvimento: `http://localhost/docs/api`
- Configuração: `config/scramble.php`

---

**Última atualização:** 29 de outubro de 2025
