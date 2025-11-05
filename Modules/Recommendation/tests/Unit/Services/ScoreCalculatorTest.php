<?php

namespace Modules\Recommendation\Tests\Unit\Services;

use Tests\TestCase;
use Mockery as M;
use Modules\User\Models\User;
use Modules\Game\Models\Game;
use Modules\Recommendation\Services\ScoreCalculator;
use Modules\Recommendation\Services\BehaviorAnalysisService;
use Modules\Recommendation\Models\UserBehaviorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class ScoreCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private ScoreCalculator $calculator;
    
    /** @var \Mockery\MockInterface|BehaviorAnalysisService */
    private $behaviorAnalysis;

    protected function setUp(): void
    {
        parent::setUp();
        
        /** @phpstan-ignore-next-line */
        $this->behaviorAnalysis = M::mock(BehaviorAnalysisService::class);
        $this->calculator = new ScoreCalculator($this->behaviorAnalysis);
    }

    protected function tearDown(): void
    {
        /** @phpstan-ignore-next-line */
        M::close();
        parent::tearDown();
    }

    #[Test]
    public function it_throws_exception_when_user_is_null(): void
    {
        $this->expectException(\TypeError::class);
        
        $user = null;
        /** @phpstan-ignore-next-line */
        $game = M::mock(Game::class);
        /** @phpstan-ignore-next-line */
        $profile = M::mock(UserBehaviorProfile::class);
        
        // PHP type hint will catch this, not our validation
        @$this->calculator->calculateScoreWithProfile($user, $game, $profile);
    }

    #[Test]
    public function it_throws_exception_when_profile_does_not_belong_to_user(): void
    {
        /** @phpstan-ignore-next-line */
        $user = M::mock(User::class)->makePartial();
        $user->shouldAllowMockingProtectedMethods();
        $user->shouldIgnoreMissing();
        $user->shouldReceive('setAttribute')->andReturnSelf();
        $user->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $user->id = 1;
        
        /** @phpstan-ignore-next-line */
        $game = M::mock(Game::class)->makePartial();
        $game->shouldIgnoreMissing();
        
        /** @phpstan-ignore-next-line */
        $profile = M::mock(UserBehaviorProfile::class)->makePartial();
        $profile->shouldAllowMockingProtectedMethods();
        $profile->shouldIgnoreMissing();
        $profile->shouldReceive('setAttribute')->andReturnSelf();
        $profile->shouldReceive('getAttribute')->with('user_id')->andReturn(2); // Diferente do user_id
        $profile->user_id = 2;
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Profile does not belong to the specified user');
        
        $this->calculator->calculateScoreWithProfile($user, $game, $profile);
    }

    #[Test]
    public function it_uses_default_weights_when_profile_has_no_adaptive_weights(): void
    {
        /** @phpstan-ignore-next-line */
        $user = M::mock(User::class)->makePartial();
        $user->shouldAllowMockingProtectedMethods();
        $user->shouldIgnoreMissing();
        $user->shouldReceive('setAttribute')->andReturnSelf();
        $user->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $user->shouldReceive('getAttribute')->with('preferences')->andReturn(null);
        $user->shouldReceive('preferences')->andReturn(null);
        $user->id = 1;
        
        /** @phpstan-ignore-next-line */
        $game = M::mock(Game::class)->makePartial();
        $game->shouldAllowMockingProtectedMethods();
        $game->shouldIgnoreMissing();
        $game->shouldReceive('setAttribute')->andReturnSelf();
        
        // Mock genres relationship
        /** @phpstan-ignore-next-line */
        $genresRelation = M::mock('Illuminate\Database\Eloquent\Relations\BelongsToMany');
        $genresRelation->shouldReceive('pluck')->with('genres.id')->andReturn(collect());
        $game->shouldReceive('genres')->andReturn($genresRelation);
        
        // Mock categories relationship
        /** @phpstan-ignore-next-line */
        $categoriesRelation = M::mock('Illuminate\Database\Eloquent\Relations\BelongsToMany');
        $categoriesRelation->shouldReceive('pluck')->with('categories.id')->andReturn(collect());
        $game->shouldReceive('categories')->andReturn($categoriesRelation);
        $game->platform = null;
        $game->developers = collect();
        $game->publishers = collect();
        $game->communityRating = null;
        $game->is_free = false;
        $game->required_age = 0;
        $game->total_reviews = 0;
        $game->positive_ratio = null;
        $game->release_date = null;
        
        /** @phpstan-ignore-next-line */
        $profile = M::mock(UserBehaviorProfile::class)->makePartial();
        $profile->shouldAllowMockingProtectedMethods();
        $profile->shouldIgnoreMissing();
        $profile->shouldReceive('setAttribute')->andReturnSelf();
        $profile->shouldReceive('getAttribute')->with('user_id')->andReturn(1);
        $profile->shouldReceive('getAttribute')->with('total_interactions')->andReturn(10);
        $profile->shouldReceive('getAttribute')->with('adaptive_weights')->andReturn(null);
        $profile->shouldReceive('getAttribute')->with('liked_genres_stats')->andReturn([]);
        $profile->shouldReceive('getAttribute')->with('disliked_genres_stats')->andReturn([]);
        $profile->shouldReceive('getAttribute')->with('liked_categories_stats')->andReturn([]);
        $profile->shouldReceive('getAttribute')->with('disliked_categories_stats')->andReturn([]);
        $profile->shouldReceive('getAttribute')->with('top_developers')->andReturn([]);
        $profile->shouldReceive('getAttribute')->with('top_publishers')->andReturn([]);
        $profile->shouldReceive('getAttribute')->with('free_to_play_preference')->andReturn(0.0);
        $profile->shouldReceive('getAttribute')->with('mature_content_tolerance')->andReturn(0.5);
        $profile->shouldReceive('getAttribute')->with('toxicity_tolerance')->andReturn(0.5);
        $profile->shouldReceive('getAttribute')->with('cheater_tolerance')->andReturn(0.5);
        $profile->shouldReceive('getAttribute')->with('bug_tolerance')->andReturn(0.5);
        $profile->shouldReceive('getAttribute')->with('microtransaction_tolerance')->andReturn(0.5);
        $profile->shouldReceive('getAttribute')->with('optimization_tolerance')->andReturn(0.5);
        $profile->shouldReceive('getAttribute')->with('not_recommended_tolerance')->andReturn(0.5);
        $profile->shouldReceive('getAttribute')->with('last_analyzed_at')->andReturn(now());
        $profile->user_id = 1;
        $profile->total_interactions = 10;
        $profile->adaptive_weights = null;
        $profile->liked_genres_stats = [];
        $profile->disliked_genres_stats = [];
        $profile->liked_categories_stats = [];
        $profile->disliked_categories_stats = [];
        $profile->top_developers = [];
        $profile->top_publishers = [];
        $profile->free_to_play_preference = 0.0;
        $profile->mature_content_tolerance = 0.5;
        $profile->toxicity_tolerance = 0.5;
        $profile->cheater_tolerance = 0.5;
        $profile->bug_tolerance = 0.5;
        $profile->microtransaction_tolerance = 0.5;
        $profile->optimization_tolerance = 0.5;
        $profile->not_recommended_tolerance = 0.5;
        $profile->last_analyzed_at = now();
        
        $this->behaviorAnalysis->shouldReceive('getRejectedDevelopers')->andReturn([]);
        $this->behaviorAnalysis->shouldReceive('getRejectedPublishers')->andReturn([]);
        
        $score = $this->calculator->calculateScoreWithProfile($user, $game, $profile);
        
        $this->assertIsFloat($score);
        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThanOrEqual(100, $score);
    }
}

