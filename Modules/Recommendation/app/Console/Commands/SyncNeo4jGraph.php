<?php

namespace Modules\Recommendation\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Game\Models\Game;
use Modules\User\Models\User;
use Modules\Recommendation\Models\GameInteraction;
use Modules\Recommendation\Services\Neo4jGraphSyncService;
use Modules\Recommendation\Services\Neo4jService;

class SyncNeo4jGraph extends Command
{
    protected $signature = 'recommendation:sync-neo4j 
                            {--full : Sincronizar todos os dados}
                            {--users : Sincronizar apenas usuários}
                            {--games : Sincronizar apenas jogos}
                            {--interactions : Sincronizar apenas interações}
                            {--limit=1000 : Limite de registros por operação}';

    protected $description = 'Sincroniza dados do PostgreSQL para o Neo4j';

    public function __construct(
        private Neo4jGraphSyncService $syncService,
        private Neo4jService $neo4j
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!config('recommendation.neo4j.enabled')) {
            $this->error('Neo4j está desabilitado. Configure NEO4J_ENABLED=true no .env');
            return Command::FAILURE;
        }

        if (!$this->neo4j->isConnected()) {
            $this->error('Não foi possível conectar ao Neo4j. Verifique as configurações.');
            return Command::FAILURE;
        }

        $this->info('🔄 Iniciando sincronização com Neo4j...');

        $full = $this->option('full');
        $limit = (int) $this->option('limit');

        try {
            if ($full || $this->option('users')) {
                $this->syncUsers($limit);
            }

            if ($full || $this->option('games')) {
                $this->syncGames($limit);
            }

            if ($full || $this->option('interactions')) {
                $this->syncInteractions($limit);
            }

            if (!$full && !$this->option('users') && !$this->option('games') && !$this->option('interactions')) {
                $this->info('Use --full para sincronizar tudo ou especifique --users, --games ou --interactions');
                return Command::FAILURE;
            }

            $this->info('✅ Sincronização concluída!');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Erro durante sincronização: ' . $e->getMessage());
            Log::error('Neo4j sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return Command::FAILURE;
        }
    }

    private function syncUsers(int $limit): void
    {
        $this->info('Sincronizando usuários...');
        $bar = $this->output->createProgressBar($limit);
        $bar->start();

        $users = User::limit($limit)->get();
        $count = 0;

        foreach ($users as $user) {
            try {
                $this->syncService->syncUser($user);
                $this->syncService->syncUserPreferences($user);
                $count++;
                $bar->advance();
            } catch (\Exception $e) {
                $this->warn("\nErro ao sincronizar usuário {$user->id}: " . $e->getMessage());
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info("✅ {$count} usuários sincronizados");
    }

    private function syncGames(int $limit): void
    {
        $this->info('Sincronizando jogos...');
        $bar = $this->output->createProgressBar($limit);
        $bar->start();

        $games = Game::where('is_active', true)
            ->with(['genres', 'categories', 'developers', 'publishers'])
            ->limit($limit)
            ->get();

        $count = 0;

        foreach ($games as $game) {
            try {
                $this->syncService->syncGame($game);
                $count++;
                $bar->advance();
            } catch (\Exception $e) {
                $this->warn("\nErro ao sincronizar jogo {$game->id}: " . $e->getMessage());
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info("✅ {$count} jogos sincronizados");
    }

    private function syncInteractions(int $limit): void
    {
        $this->info('Sincronizando interações...');
        $bar = $this->output->createProgressBar($limit);
        $bar->start();

        $interactions = GameInteraction::with(['user', 'game'])
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get();

        $count = 0;

        foreach ($interactions as $interaction) {
            try {
                $this->syncService->syncUser($interaction->user);
                $this->syncService->syncGame($interaction->game);
                $this->syncService->syncInteraction($interaction);
                $count++;
                $bar->advance();
            } catch (\Exception $e) {
                $this->warn("\nErro ao sincronizar interação {$interaction->id}: " . $e->getMessage());
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info("✅ {$count} interações sincronizadas");
    }
}

