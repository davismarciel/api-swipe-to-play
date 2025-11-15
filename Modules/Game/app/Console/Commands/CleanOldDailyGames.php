<?php

namespace Modules\Game\Console\Commands;

use Illuminate\Console\Command;
use Modules\Game\Models\UserDailyGame;
use Carbon\Carbon;

class CleanOldDailyGames extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'games:clean-daily {--days=30 : Número de dias para manter os registros}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove registros antigos de jogos diários (mantém apenas os últimos N dias)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoffDate = Carbon::now()->subDays($days);

        $this->info("Cleaning old daily games before {$cutoffDate->format('Y-m-d')}...");

        $deleted = UserDailyGame::where('date', '<', $cutoffDate)->delete();

        $this->info("{$deleted} records removed successfully!");

        return Command::SUCCESS;
    }
}

