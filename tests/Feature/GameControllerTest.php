<?php

namespace Tests\Feature;

use Tests\TestCase;
use Modules\User\Models\User;
use Modules\Game\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tymon\JWTAuth\Facades\JWTAuth;

class GameControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Testa se o endpoint retorna 20 jogos por padrão
     */
    public function test_games_index_returns_default_20_games(): void
    {
        $user = User::factory()->create();
        $token = JWTAuth::fromUser($user);

        $totalGames = 25;
        Game::factory()->count($totalGames)->create([
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/games');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'pagination' => [
                    'current_page',
                    'per_page',
                    'total',
                    'from',
                    'to',
                ],
            ]);

        $response->assertJson([
            'pagination' => [
                'per_page' => 20,
            ],
        ]);
        
        $this->assertCount(20, $response->json('data'));
    }

    /**
     * Testa se o endpoint respeita o parâmetro per_page
     */
    public function test_games_index_respects_per_page_parameter(): void
    {
        $user = User::factory()->create();
        $token = JWTAuth::fromUser($user);

        Game::factory()->count(30)->create([
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/games?per_page=10&skip_daily_limit=1');

        $response->assertStatus(200)
            ->assertJson([
                'pagination' => [
                    'per_page' => 10,
                ],
            ]);

        $this->assertCount(10, $response->json('data'));

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/games?per_page=50&skip_daily_limit=1');

        $response->assertStatus(200)
            ->assertJson([
                'pagination' => [
                    'per_page' => 50,
                ],
            ]);

        $this->assertCount(30, $response->json('data')); // Apenas 30 jogos disponíveis
    }

    /**
     * Testa se retorna menos jogos quando há menos disponíveis
     */
    public function test_games_index_returns_available_games_when_less_than_default(): void
    {
        $user = User::factory()->create();
        $token = JWTAuth::fromUser($user);

        // Criar apenas 5 jogos
        Game::factory()->count(5)->create([
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/games');

        $response->assertStatus(200);
        
        // Deve retornar 5 jogos (menos que o padrão de 20)
        $this->assertCount(5, $response->json('data'));
        $this->assertEquals(5, $response->json('pagination.total'));
        $this->assertEquals(20, $response->json('pagination.per_page')); // per_page ainda é 20
    }

    /**
     * Testa se apenas jogos ativos são retornados
     */
    public function test_games_index_only_returns_active_games(): void
    {
        $user = User::factory()->create();
        $token = JWTAuth::fromUser($user);

        Game::factory()->count(10)->create(['is_active' => true]);
        Game::factory()->count(5)->create(['is_active' => false]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/games');

        $response->assertStatus(200);
        
        $this->assertCount(10, $response->json('data'));
        $this->assertEquals(10, $response->json('pagination.total'));
        
        foreach ($response->json('data') as $game) {
            $this->assertNotNull($game['id']);
        }
    }
}

