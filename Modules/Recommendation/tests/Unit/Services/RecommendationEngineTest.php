<?php

namespace Modules\Recommendation\Tests\Unit\Services;

use Tests\TestCase;
use Mockery as M;
use Modules\User\Models\User;
use Modules\Game\Models\Game;
use Modules\Recommendation\Services\RecommendationEngine;
use Modules\Recommendation\Services\ScoreCalculator;
use Modules\Recommendation\Services\GameFilterService;
use Modules\Recommendation\Services\BehaviorAnalysisService;
use Modules\Game\Services\DailyGameCacheService;
use Illuminate\Support\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class RecommendationEngineTest extends TestCase
{
    use RefreshDatabase;

    private RecommendationEngine $engine;
    
    /** @var \Mockery\MockInterface|ScoreCalculator */
    private $scoreCalculator;
    
    /** @var \Mockery\MockInterface|GameFilterService */
    private $filterService;
    
    /** @var \Mockery\MockInterface|BehaviorAnalysisService */
    private $behaviorAnalysis;

    /** @var \Mockery\MockInterface|DailyGameCacheService */
    private $dailyGameCache;

    protected function setUp(): void
    {
        parent::setUp();
        
        /** @phpstan-ignore-next-line */
        $this->scoreCalculator = M::mock(ScoreCalculator::class);
        /** @phpstan-ignore-next-line */
        $this->filterService = M::mock(GameFilterService::class);
        /** @phpstan-ignore-next-line */
        $this->behaviorAnalysis = M::mock(BehaviorAnalysisService::class);
        /** @phpstan-ignore-next-line */
        $this->dailyGameCache = M::mock(DailyGameCacheService::class);
        
        $this->engine = new RecommendationEngine(
            $this->scoreCalculator,
            $this->filterService,
            $this->behaviorAnalysis,
            $this->dailyGameCache
        );
    }

    protected function tearDown(): void
    {
        /** @phpstan-ignore-next-line */
        M::close();
        parent::tearDown();
    }

    #[Test]
    public function it_returns_default_recommendations_when_user_has_no_profile(): void
    {
        /** @phpstan-ignore-next-line */
        $user = M::mock(User::class)->makePartial();
        $user->shouldAllowMockingProtectedMethods();
        $user->shouldIgnoreMissing();
        $user->shouldReceive('setAttribute')->andReturnSelf();
        $user->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $user->id = 1;
        
        // Mock dos relacionamentos necessários
        $emptyGenres = collect([]);
        $emptyCategories = collect([]);
        $user->shouldReceive('load')->with(['preferredGenres', 'preferredCategories', 'preferences'])->andReturnUsing(function() use ($user, $emptyGenres, $emptyCategories) {
            $user->setRelation('preferredGenres', $emptyGenres);
            $user->setRelation('preferredCategories', $emptyCategories);
            return $user;
        });
        $user->setRelation('preferredGenres', $emptyGenres);
        $user->setRelation('preferredCategories', $emptyCategories);
        
        /** @phpstan-ignore-next-line */
        $queryBuilder = M::mock(\Illuminate\Database\Eloquent\Builder::class);
        $games = collect([
            /** @phpstan-ignore-next-line */
            M::mock(Game::class)->makePartial(),
            /** @phpstan-ignore-next-line */
            M::mock(Game::class)->makePartial(),
        ]);
        
        $this->behaviorAnalysis
            ->shouldReceive('buildOrUpdateProfile')
            ->with($user)
            ->andReturn(null);
        
        $this->filterService
            ->shouldReceive('filterGames')
            ->with($user)
            ->andReturn($queryBuilder);
        
        $queryBuilder->shouldReceive('with')->andReturnSelf();
        $queryBuilder->shouldReceive('orderByDesc')->andReturnSelf();
        $queryBuilder->shouldReceive('limit')->andReturnSelf();
        $queryBuilder->shouldReceive('get')->andReturn($games);
        
        $recommendations = $this->engine->getRecommendations($user, 10);
        
        $this->assertInstanceOf(Collection::class, $recommendations);
    }

    #[Test]
    public function it_handles_exceptions_gracefully(): void
    {
        /** @phpstan-ignore-next-line */
        $user = M::mock(User::class)->makePartial();
        $user->shouldAllowMockingProtectedMethods();
        $user->shouldIgnoreMissing();
        $user->shouldReceive('setAttribute')->andReturnSelf();
        $user->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $user->id = 1;
        
        // Mock dos relacionamentos necessários
        $emptyGenres = collect([]);
        $emptyCategories = collect([]);
        $user->shouldReceive('load')->with(['preferredGenres', 'preferredCategories', 'preferences'])->andReturnUsing(function() use ($user, $emptyGenres, $emptyCategories) {
            $user->setRelation('preferredGenres', $emptyGenres);
            $user->setRelation('preferredCategories', $emptyCategories);
            return $user;
        });
        $user->setRelation('preferredGenres', $emptyGenres);
        $user->setRelation('preferredCategories', $emptyCategories);
        
        $this->behaviorAnalysis
            ->shouldReceive('buildOrUpdateProfile')
            ->andThrow(new \Exception('Database error'));
        
        /** @phpstan-ignore-next-line */
        $queryBuilder = M::mock(\Illuminate\Database\Eloquent\Builder::class);
        $this->filterService
            ->shouldReceive('filterGames')
            ->andReturn($queryBuilder);
        
        $queryBuilder->shouldReceive('with')->andReturnSelf();
        $queryBuilder->shouldReceive('orderByDesc')->andReturnSelf();
        $queryBuilder->shouldReceive('limit')->andReturnSelf();
        $queryBuilder->shouldReceive('get')->andReturn(collect());
        
        // Não deve lançar exceção, deve retornar recomendações default
        $recommendations = $this->engine->getRecommendations($user, 10);
        
        $this->assertInstanceOf(Collection::class, $recommendations);
    }
}

