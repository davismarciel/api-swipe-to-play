<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🌱 Executando seeds...');

        // Check if database is already seeded
        if (DB::table('games')->count() > 0) {
            $this->command->warn('⚠️  Database já contém dados. Pulando seeds.');
            return;
        }

        // Seed tables that need slug generation using models
        $this->seedWithModels();

        // Seed remaining tables with raw SQL
        $this->seedRawSql();

        $this->command->info('✅ Seeds executados com sucesso!');
    }

    /**
     * Seed tables that require slug generation (categories, genres, developers, publishers)
     */
    private function seedWithModels(): void
    {
        $seedsPath = database_path('seeds');

        // Categories
        $this->command->info("Executando: 01_categories.sql");
        $sql = File::get($seedsPath . '/01_categories.sql');
        $this->seedTableWithSlug('categories', $sql, \Modules\User\Models\Category::class);

        // Genres
        $this->command->info("Executando: 02_genres.sql");
        $sql = File::get($seedsPath . '/02_genres.sql');
        $this->seedTableWithSlug('genres', $sql, \Modules\User\Models\Genre::class);

        // Developers
        $this->command->info("Executando: 03_developers.sql");
        $sql = File::get($seedsPath . '/03_developers.sql');
        $this->seedTableWithSlug('developers', $sql, \Modules\Game\Models\Developer::class);

        // Publishers
        $this->command->info("Executando: 04_publishers.sql");
        $sql = File::get($seedsPath . '/04_publishers.sql');
        $this->seedTableWithSlug('publishers', $sql, \Modules\Game\Models\Publisher::class);
    }

    /**
     * Extract names from SQL INSERT and create records with slugs
     */
    private function seedTableWithSlug(string $table, string $sql, string $modelClass): void
    {
        preg_match_all("/\('((?:\\.|[^'])*?)'\)/", $sql, $matches);
        
        if (!empty($matches[1])) {
            $created = 0;
            $skipped = 0;
            
            foreach ($matches[1] as $name) {
                // Unescape SQL-escaped quotes: \' becomes '
                $name = str_replace("\\'", "'", $name);
                
                try {
                    $modelClass::firstOrCreate(
                        ['name' => $name],
                        ['slug' => Str::slug($name)]
                    );
                    $created++;
                } catch (\Exception $e) {
                    $skipped++;
                }
            }
            
            $this->command->info("✅ {$table} executado com sucesso ({$created} criados, {$skipped} já existiam)");
        }
    }

    /**
     * Seed tables with raw SQL (games and pivot tables)
     */
    private function seedRawSql(): void
    {
        $sqlFiles = [
            '05_games.sql',
            '05b_game_platforms.sql',
            '05c_game_requirements.sql',
            '05d_game_community_ratings.sql',
            '06_game_category.sql',
            '07_game_genre.sql',
            '08_game_developer.sql',
            '09_game_publisher.sql',
            '10_game_media.sql',
        ];

        $seedsPath = database_path('seeds');

        foreach ($sqlFiles as $file) {
            $filePath = $seedsPath . '/' . $file;

            if (File::exists($filePath)) {
                $this->command->info("Executando: {$file}");
                
                try {
                    $sql = File::get($filePath);
                    
                    // Fix PostgreSQL escaping: replace \' with ''
                    // Skip replacement for files containing JSON columns to avoid corrupting
                    // already-escaped JSON data (generate_sql.php already uses '' for JSON)
                    $jsonColumnFiles = ['05_games.sql', '10_game_media.sql'];
                    $isJsonFile = in_array($file, $jsonColumnFiles);
                    
                    if (!$isJsonFile) {
                        // Only apply replacement to non-JSON files
                        $sql = str_replace("\\'", "''", $sql);
                    }
                    
                    DB::unprepared($sql);
                    $this->command->info("✅ {$file} executado com sucesso");
                } catch (\Exception $e) {
                    $this->command->error("❌ Erro ao executar {$file}: " . $e->getMessage());
                    throw $e;
                }
            } else {
                $this->command->warn("⚠️  Arquivo não encontrado: {$file}");
            }
        }
    }
}
