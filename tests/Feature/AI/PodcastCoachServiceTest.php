<?php

declare(strict_types=1);

use Anthropic\Client as AnthropicClient;
use App\AI\PodcastCoachService;
use App\AI\Prompts\CoachSummaryPrompt;
use App\Models\FeedReport;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;

// ──────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────

function makeReport(array $overrides = []): FeedReport
{
    return FeedReport::create(array_merge([
        'feed_url' => 'https://example.com/feed.xml',
        'feed_title' => 'The Tech Founders Show',
        'overall_score' => 55,
        'results_json' => [
            'feed_format' => 'RSS 2.0',
            'total_episodes' => 10,
            'metadata' => [
                'show_description' => 'Interviews with B2B SaaS founders.',
                'show_category' => 'Technology',
            ],
            'health_score' => ['overall' => 55],
            'summary' => ['total' => 3, 'pass' => 1, 'warn' => 0, 'fail' => 2],
            'channel' => [
                [
                    'name' => 'Artwork',
                    'severity' => 'high',
                    'status' => 'fail',
                    'message' => 'No artwork found.',
                    'suggestion' => 'Add a 3000x3000 JPEG or PNG artwork image.',
                ],
                [
                    'name' => 'Channel Description',
                    'severity' => 'high',
                    'status' => 'fail',
                    'message' => 'Description is too short.',
                    'suggestion' => 'Write a description of 150–300 words.',
                ],
                [
                    'name' => 'Language',
                    'severity' => 'medium',
                    'status' => 'pass',
                    'message' => 'Language tag present.',
                    'suggestion' => null,
                ],
            ],
            'episodes' => [],
        ],
    ], $overrides));
}

function makeService(): PodcastCoachService
{
    return new PodcastCoachService(
        client: app(AnthropicClient::class),
        prompt: new CoachSummaryPrompt,
        cache: app(CacheRepository::class),
    );
}

// ──────────────────────────────────────────────────
// Cache miss — calls the API
// ──────────────────────────────────────────────────

test('cache miss calls the Anthropic API and returns the summary', function () {
    Cache::flush();

    $report = makeReport();
    $expectedSummary = 'Your show lacks artwork which hurts discoverability. Fix the artwork first — it is the single biggest ranking factor on Apple Podcasts. Your category is solid, so once artwork is done you are already ahead of most new shows.';

    $service = Mockery::mock(PodcastCoachService::class, [
        app(AnthropicClient::class),
        new CoachSummaryPrompt,
        app(CacheRepository::class),
    ])
        ->shouldAllowMockingProtectedMethods()
        ->makePartial();

    $service->shouldReceive('callAnthropicApi')
        ->once()
        ->andReturn($expectedSummary);

    $result = $service->getSummary($report);

    expect($result)->toBe($expectedSummary);
});

// ──────────────────────────────────────────────────
// Cache miss — caches the result for subsequent calls
// ──────────────────────────────────────────────────

test('cache miss stores the API response in the cache', function () {
    Cache::flush();

    $report = makeReport();
    $expectedSummary = 'A brand new summary that should be cached.';

    $service = Mockery::mock(PodcastCoachService::class, [
        app(AnthropicClient::class),
        new CoachSummaryPrompt,
        app(CacheRepository::class),
    ])
        ->shouldAllowMockingProtectedMethods()
        ->makePartial();

    $service->shouldReceive('callAnthropicApi')
        ->once()
        ->andReturn($expectedSummary);

    $service->getSummary($report);

    // Second call should use the cache, not the API
    $service->shouldReceive('callAnthropicApi')->never();
    $secondResult = $service->getSummary($report);

    expect($secondResult)->toBe($expectedSummary);
});

// ──────────────────────────────────────────────────
// Cache hit — skips the API
// ──────────────────────────────────────────────────

test('cache hit returns the cached value without calling the API', function () {
    Cache::flush();

    $report = makeReport();
    $cachedSummary = 'This was previously cached.';

    // Pre-warm the cache with the key the service will compute:
    // md5(show_title + failing_check_names) = md5('The Tech Founders Show' + 'ArtworkChannel Description')
    $prompt = new CoachSummaryPrompt;
    $cacheKey = 'coach:'.$prompt->version().':'.md5('The Tech Founders ShowArtworkChannel Description');
    Cache::put($cacheKey, $cachedSummary, 60);

    $service = Mockery::mock(PodcastCoachService::class, [
        app(AnthropicClient::class),
        $prompt,
        app(CacheRepository::class),
    ])
        ->shouldAllowMockingProtectedMethods()
        ->makePartial();

    $service->shouldReceive('callAnthropicApi')->never();

    $result = $service->getSummary($report);

    expect($result)->toBe($cachedSummary);
});

// ──────────────────────────────────────────────────
// API failure — returns null gracefully
// ──────────────────────────────────────────────────

test('API failure returns null so the core report continues to function', function () {
    Cache::flush();

    $report = makeReport();

    $service = Mockery::mock(PodcastCoachService::class, [
        app(AnthropicClient::class),
        new CoachSummaryPrompt,
        app(CacheRepository::class),
    ])
        ->shouldAllowMockingProtectedMethods()
        ->makePartial();

    $service->shouldReceive('callAnthropicApi')
        ->once()
        ->andReturn(null);

    $result = $service->getSummary($report);

    expect($result)->toBeNull();
});

// ──────────────────────────────────────────────────
// Cache key — version is included
// ──────────────────────────────────────────────────

test('cache key includes the prompt version so bumping version auto-invalidates cache', function () {
    Cache::flush();

    $report = makeReport();
    $summaryV1 = 'Summary under v1 prompt.';

    // Seed the cache with a v1 key
    $prompt = new CoachSummaryPrompt;
    $version = $prompt->version();
    $cacheKey = "coach:{$version}:".md5('The Tech Founders ShowArtworkChannel Description');
    Cache::put($cacheKey, $summaryV1, 60);

    $service = Mockery::mock(PodcastCoachService::class, [
        app(AnthropicClient::class),
        $prompt,
        app(CacheRepository::class),
    ])
        ->shouldAllowMockingProtectedMethods()
        ->makePartial();

    $service->shouldReceive('callAnthropicApi')->never();

    $result = $service->getSummary($report);

    expect($result)->toBe($summaryV1);
});

// ──────────────────────────────────────────────────
// Reports without metadata — handled gracefully
// ──────────────────────────────────────────────────

test('report without metadata block returns a summary without crashing', function () {
    Cache::flush();

    $report = makeReport([
        'results_json' => [
            'feed_format' => 'RSS 2.0',
            'total_episodes' => 5,
            'health_score' => ['overall' => 40],
            'summary' => ['total' => 1, 'pass' => 0, 'warn' => 0, 'fail' => 1],
            'channel' => [
                [
                    'name' => 'Artwork',
                    'severity' => 'high',
                    'status' => 'fail',
                    'message' => 'No artwork found.',
                    'suggestion' => 'Add artwork.',
                ],
            ],
            'episodes' => [],
        ],
    ]);

    $expectedSummary = 'Old report handled gracefully.';

    $service = Mockery::mock(PodcastCoachService::class, [
        app(AnthropicClient::class),
        new CoachSummaryPrompt,
        app(CacheRepository::class),
    ])
        ->shouldAllowMockingProtectedMethods()
        ->makePartial();

    $service->shouldReceive('callAnthropicApi')
        ->once()
        ->andReturn($expectedSummary);

    $result = $service->getSummary($report);

    expect($result)->toBe($expectedSummary);
});
