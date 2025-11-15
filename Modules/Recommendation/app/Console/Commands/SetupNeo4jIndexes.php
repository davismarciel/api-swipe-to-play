<?php

namespace Modules\Recommendation\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Recommendation\Services\Neo4jService;

class SetupNeo4jIndexes extends Command
{
    protected $signature = 'recommendation:setup-neo4j-indexes 
                            {--drop : Remove índices existentes antes de criar}';

    protected $description = 'Configura índices e constraints no Neo4j para otimizar performance';

    public function __construct(
        private Neo4jService $neo4j
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!config('recommendation.neo4j.enabled')) {
            $this->error('❌ Neo4j está desabilitado na configuração');
            return self::FAILURE;
        }

        if (!$this->neo4j->isConnected()) {
            $this->error('❌ Não foi possível conectar ao Neo4j');
            return self::FAILURE;
        }

        $this->info('🔧 Configurando índices e constraints no Neo4j...');
        $this->newLine();

        if ($this->option('drop')) {
            $this->warn('⚠️  Removendo índices existentes...');
            $this->dropIndexes();
            $this->newLine();
        }

        $success = true;

        // Constraints (garantem unicidade)
        $success = $this->createConstraints() && $success;
        
        // Índices (otimizam queries)
        $success = $this->createIndexes() && $success;
        
        // Índices compostos
        $success = $this->createCompositeIndexes() && $success;

        $this->newLine();
        
        if ($success) {
            $this->info('✅ Índices e constraints configurados com sucesso!');
            $this->showIndexInfo();
            return self::SUCCESS;
        }

        $this->error('❌ Alguns índices falharam ao ser criados. Verifique os logs.');
        return self::FAILURE;
    }

    private function createConstraints(): bool
    {
        $this->info('📋 Criando constraints...');
        
        $constraints = [
            // Unicidade de IDs
            'CREATE CONSTRAINT user_id_unique IF NOT EXISTS FOR (u:User) REQUIRE u.id IS UNIQUE',
            'CREATE CONSTRAINT game_id_unique IF NOT EXISTS FOR (g:Game) REQUIRE g.id IS UNIQUE',
            'CREATE CONSTRAINT genre_id_unique IF NOT EXISTS FOR (g:Genre) REQUIRE g.id IS UNIQUE',
            'CREATE CONSTRAINT category_id_unique IF NOT EXISTS FOR (c:Category) REQUIRE c.id IS UNIQUE',
            'CREATE CONSTRAINT developer_id_unique IF NOT EXISTS FOR (d:Developer) REQUIRE d.id IS UNIQUE',
            'CREATE CONSTRAINT publisher_id_unique IF NOT EXISTS FOR (p:Publisher) REQUIRE p.id IS UNIQUE',
        ];

        $success = true;
        foreach ($constraints as $constraint) {
            try {
                $this->neo4j->executeQuery($constraint);
                $name = $this->extractConstraintName($constraint);
                $this->line("  ✓ $name");
            } catch (\Exception $e) {
                $this->error("  ✗ Erro: " . $e->getMessage());
                $success = false;
            }
        }

        return $success;
    }

    private function createIndexes(): bool
    {
        $this->newLine();
        $this->info('📋 Criando índices simples...');
        
        $indexes = [
            // Índices para User
            'CREATE INDEX user_name_idx IF NOT EXISTS FOR (u:User) ON (u.name)',
            
            // Índices para Game
            'CREATE INDEX game_name_idx IF NOT EXISTS FOR (g:Game) ON (g.name)',
            'CREATE INDEX game_is_active_idx IF NOT EXISTS FOR (g:Game) ON (g.is_active)',
            'CREATE INDEX game_positive_ratio_idx IF NOT EXISTS FOR (g:Game) ON (g.positive_ratio)',
            'CREATE INDEX game_total_reviews_idx IF NOT EXISTS FOR (g:Game) ON (g.total_reviews)',
            'CREATE INDEX game_is_free_idx IF NOT EXISTS FOR (g:Game) ON (g.is_free)',
            'CREATE INDEX game_required_age_idx IF NOT EXISTS FOR (g:Game) ON (g.required_age)',
            
            // Índices para relacionamentos
            'CREATE INDEX interaction_score_idx IF NOT EXISTS FOR ()-[r:INTERACTED_WITH]-() ON (r.score)',
            'CREATE INDEX interaction_type_idx IF NOT EXISTS FOR ()-[r:INTERACTED_WITH]-() ON (r.type)',
            'CREATE INDEX interaction_date_idx IF NOT EXISTS FOR ()-[r:INTERACTED_WITH]-() ON (r.interacted_at)',
            
            // Índices para Genre, Category, Developer, Publisher
            'CREATE INDEX genre_name_idx IF NOT EXISTS FOR (g:Genre) ON (g.name)',
            'CREATE INDEX category_name_idx IF NOT EXISTS FOR (c:Category) ON (c.name)',
            'CREATE INDEX developer_name_idx IF NOT EXISTS FOR (d:Developer) ON (d.name)',
            'CREATE INDEX publisher_name_idx IF NOT EXISTS FOR (p:Publisher) ON (p.name)',
        ];

        $success = true;
        foreach ($indexes as $index) {
            try {
                $this->neo4j->executeQuery($index);
                $name = $this->extractIndexName($index);
                $this->line("  ✓ $name");
            } catch (\Exception $e) {
                $this->error("  ✗ Erro: " . $e->getMessage());
                $success = false;
            }
        }

        return $success;
    }

    private function createCompositeIndexes(): bool
    {
        $this->newLine();
        $this->info('📋 Criando índices compostos...');
        
        $compositeIndexes = [
            // Índice composto para queries de recomendação
            'CREATE INDEX game_active_rating_idx IF NOT EXISTS FOR (g:Game) ON (g.is_active, g.positive_ratio)',
            'CREATE INDEX game_active_reviews_idx IF NOT EXISTS FOR (g:Game) ON (g.is_active, g.total_reviews)',
            
            // Índice para filtragem de interações positivas
            'CREATE INDEX interaction_score_type_idx IF NOT EXISTS FOR ()-[r:INTERACTED_WITH]-() ON (r.score, r.type)',
        ];

        $success = true;
        foreach ($compositeIndexes as $index) {
            try {
                $this->neo4j->executeQuery($index);
                $name = $this->extractIndexName($index);
                $this->line("  ✓ $name");
            } catch (\Exception $e) {
                $this->error("  ✗ Erro: " . $e->getMessage());
                $success = false;
            }
        }

        return $success;
    }

    private function dropIndexes(): void
    {
        try {
            // Lista todos os índices
            $result = $this->neo4j->executeQuery('SHOW INDEXES');
            
            foreach ($result as $row) {
                $indexName = $row['name'] ?? null;
                if ($indexName && !str_starts_with($indexName, 'constraint_')) {
                    try {
                        $this->neo4j->executeQuery("DROP INDEX $indexName IF EXISTS");
                        $this->line("  ✓ Removido: $indexName");
                    } catch (\Exception $e) {
                        $this->warn("  ⚠ Não foi possível remover: $indexName");
                    }
                }
            }
        } catch (\Exception $e) {
            $this->warn('Não foi possível listar índices existentes');
        }
    }

    private function showIndexInfo(): void
    {
        $this->newLine();
        $this->info('📊 Estatísticas dos índices:');
        
        try {
            $result = $this->neo4j->executeQuery('SHOW INDEXES YIELD name, type, state, populationPercent');
            
            $this->table(
                ['Nome', 'Tipo', 'Estado', 'População %'],
                array_map(fn($row) => [
                    $row['name'] ?? 'N/A',
                    $row['type'] ?? 'N/A',
                    $row['state'] ?? 'N/A',
                    number_format($row['populationPercent'] ?? 0, 2) . '%',
                ], $result)
            );
        } catch (\Exception $e) {
            $this->warn('Não foi possível obter estatísticas dos índices');
        }
    }

    private function extractConstraintName(string $query): string
    {
        if (preg_match('/CONSTRAINT (\w+)/', $query, $matches)) {
            return $matches[1];
        }
        return 'Constraint';
    }

    private function extractIndexName(string $query): string
    {
        if (preg_match('/INDEX (\w+)/', $query, $matches)) {
            return $matches[1];
        }
        return 'Index';
    }
}

