<?php

namespace Modules\Recommendation\Console\Commands;

use Illuminate\Console\Command;
use Modules\Game\Models\Game;
use Modules\Recommendation\Services\Synchronization\Neo4jSynchronizationService;
use Illuminate\Support\Facades\Log;

class SyncGamesToNeo4jCommand extends Command
{
    protected $signature = 'neo4j:sync-games';

    protected $description = 'Synchronizes all games from PostgreSQL to Neo4j';

    public function __construct(
        private Neo4jSynchronizationService $syncService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Starting game synchronization to Neo4j...');

        try {
            // Cria constraints e índices
            $this->info('Creating constraints and indexes...');
            $this->syncService->createConstraints();
            $this->syncService->createIndexes();

            // Busca todos os games
            $games = Game::with(['genres', 'categories'])->get();
            $total = $games->count();

            if ($total === 0) {
                $this->warn('No games found in the database.');
                return Command::SUCCESS;
            }

            $this->info("Found {$total} games to synchronize.");

            $bar = $this->output->createProgressBar($total);
            $bar->start();

            $successCount = 0;
            $errorCount = 0;

            foreach ($games as $game) {
                try {
                    $this->syncService->syncGame($game);
                    $successCount++;
                } catch (\Exception $e) {
                    $errorCount++;
                    Log::error('Failed to sync game to Neo4j', [
                        'game_id' => $game->id,
                        'game_name' => $game->name,
                        'error' => $e->getMessage()
                    ]);
                }
                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);

            $this->info("Synchronization completed!");
            $this->info("{$successCount} games synchronized successfully");
            
            if ($errorCount > 0) {
                $this->warn("{$errorCount} games failed to synchronize (check the logs)");
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Error during synchronization: ' . $e->getMessage());
            Log::error('Failed to sync games to Neo4j', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }
}


