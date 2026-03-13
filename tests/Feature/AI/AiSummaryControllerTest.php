<?php

declare(strict_types=1);

use App\AI\PodcastCoachService;
use App\Models\FeedReport;
use Illuminate\Support\Facades\RateLimiter;

// ──────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────

function makeSummaryReport(): FeedReport
{
    return FeedReport::create([
        'feed_url' => 'https://example.com/feed.xml',
        'feed_title' => 'The Founders Show',
        'overall_score' => 60,
        'results_json' => [
            'feed_format' => 'RSS 2.0',
            'total_episodes' => 5,
            'metadata' => [
                'show_description' => 'Interviews with startup founders.',
                'show_category' => 'Business',
            ],
            'health_score' => ['overall' => 60],
            'summary' => ['total' => 2, 'pass' => 1, 'warn' => 0, 'fail' => 1],
            'channel' => [
                [
                    'name' => 'Artwork',
                    'severity' => 'high',
                    'status' => 'fail',
                    'message' => 'No artwork found.',
                    'suggestion' => 'Add a 3000x3000 JPEG or PNG artwork image.',
                ],
            ],
            'episodes' => [],
        ],
    ]);
}

// ──────────────────────────────────────────────────
// POST /report/{report}/ai/summary — Success
// ──────────────────────────────────────────────────

test('returns summary when service returns a string', function () {
    $report = makeSummaryReport();
    $expectedSummary = 'Your show about startup founders lacks artwork, which is the first thing Apple evaluates. Add a 3000x3000 image this week — it is the highest-impact fix you can make right now. Your topic and category are focused, which puts you ahead of generic business podcasts.';

    $this->mock(PodcastCoachService::class)
        ->shouldReceive('getSummary')
        ->once()
        ->andReturn($expectedSummary);

    $response = $this->postJson(route('report.ai.summary', $report));

    $response->assertOk()
        ->assertJson(['summary' => $expectedSummary]);
});

// ──────────────────────────────────────────────────
// POST /report/{report}/ai/summary — Graceful failure
// ──────────────────────────────────────────────────

test('returns null summary when service returns null', function () {
    $report = makeSummaryReport();

    $this->mock(PodcastCoachService::class)
        ->shouldReceive('getSummary')
        ->once()
        ->andReturn(null);

    $response = $this->postJson(route('report.ai.summary', $report));

    $response->assertOk()
        ->assertJson(['summary' => null]);
});

// ──────────────────────────────────────────────────
// POST /report/{report}/ai/summary — 404 for unknown slug
// ──────────────────────────────────────────────────

test('returns 404 for non-existent report slug', function () {
    $response = $this->postJson('/report/nonexistent/ai/summary');

    $response->assertNotFound();
});

// ──────────────────────────────────────────────────
// POST /report/{report}/ai/summary — Response structure
// ──────────────────────────────────────────────────

test('response always contains the summary key', function () {
    $report = makeSummaryReport();

    $this->mock(PodcastCoachService::class)
        ->shouldReceive('getSummary')
        ->once()
        ->andReturn('A valid summary.');

    $response = $this->postJson(route('report.ai.summary', $report));

    $response->assertOk()
        ->assertJsonStructure(['summary']);
});

// ──────────────────────────────────────────────────
// POST /report/{report}/ai/summary — Rate limiting
// ──────────────────────────────────────────────────

test('returns 429 after exceeding 20 requests per hour', function () {
    RateLimiter::clear('');

    $report = makeSummaryReport();

    $this->mock(PodcastCoachService::class)
        ->shouldReceive('getSummary')
        ->andReturn('A summary.');

    for ($i = 0; $i < 20; $i++) {
        $this->postJson(route('report.ai.summary', $report))->assertOk();
    }

    $this->postJson(route('report.ai.summary', $report))->assertStatus(429);
});
