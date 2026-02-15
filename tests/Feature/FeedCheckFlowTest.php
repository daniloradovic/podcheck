<?php

declare(strict_types=1);

use App\Models\FeedReport;
use Illuminate\Support\Facades\Http;

// ──────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────

function rssFixture(): string
{
    return file_get_contents(base_path('tests/Fixtures/valid-rss-feed.xml'));
}

// ──────────────────────────────────────────────────
// POST /check — Successful Flow
// ──────────────────────────────────────────────────

test('it fetches feed, creates report, and redirects to report page', function () {
    Http::fake([
        'example.com/*' => Http::response(rssFixture(), 200),
    ]);

    $response = $this->post(route('feed.check'), [
        'url' => 'https://example.com/feed.xml',
    ]);

    $report = FeedReport::first();

    expect($report)->not->toBeNull()
        ->and($report->feed_url)->toBe('https://example.com/feed.xml')
        ->and($report->feed_title)->toBe('The PodCheck Test Show')
        ->and($report->slug)->not->toBeEmpty()
        ->and($report->results_json)->toBeArray()
        ->and($report->results_json['feed_format'])->toBe('RSS 2.0');

    $response->assertRedirect(route('report.show', $report));
});

test('it stores only one report per submission', function () {
    Http::fake([
        'example.com/*' => Http::response(rssFixture(), 200),
    ]);

    $this->post(route('feed.check'), [
        'url' => 'https://example.com/feed.xml',
    ]);

    expect(FeedReport::count())->toBe(1);
});

// ──────────────────────────────────────────────────
// POST /check — Validation Errors
// ──────────────────────────────────────────────────

test('it rejects missing URL', function () {
    $response = $this->post(route('feed.check'), []);

    $response->assertSessionHasErrors('url');
    expect(FeedReport::count())->toBe(0);
});

test('it rejects invalid URL format', function () {
    $response = $this->post(route('feed.check'), [
        'url' => 'not-a-url',
    ]);

    $response->assertSessionHasErrors('url');
    expect(FeedReport::count())->toBe(0);
});

test('it rejects URL exceeding max length', function () {
    $response = $this->post(route('feed.check'), [
        'url' => 'https://example.com/' . str_repeat('a', 2048),
    ]);

    $response->assertSessionHasErrors('url');
    expect(FeedReport::count())->toBe(0);
});

// ──────────────────────────────────────────────────
// POST /check — Feed Fetch Errors
// ──────────────────────────────────────────────────

test('it redirects back with error when feed is unreachable', function () {
    Http::fake([
        'example.com/*' => Http::response('Not Found', 404),
    ]);

    $response = $this->post(route('feed.check'), [
        'url' => 'https://example.com/feed.xml',
    ]);

    $response->assertRedirect(route('home'));
    $response->assertSessionHasErrors('url');
    expect(FeedReport::count())->toBe(0);
});

test('it redirects back with error when response is not XML', function () {
    Http::fake([
        'example.com/*' => Http::response('<html><body>Not a feed</body></html>', 200),
    ]);

    $response = $this->post(route('feed.check'), [
        'url' => 'https://example.com/feed.xml',
    ]);

    $response->assertRedirect(route('home'));
    $response->assertSessionHasErrors('url');
    expect(FeedReport::count())->toBe(0);
});

test('it preserves submitted URL in session after fetch error', function () {
    Http::fake([
        'example.com/*' => Http::response('Not Found', 404),
    ]);

    $response = $this->post(route('feed.check'), [
        'url' => 'https://example.com/feed.xml',
    ]);

    $response->assertSessionHasInput('url', 'https://example.com/feed.xml');
});

// ──────────────────────────────────────────────────
// GET /report/{slug} — Show Report
// ──────────────────────────────────────────────────

test('it displays the report page', function () {
    $report = FeedReport::create([
        'feed_url' => 'https://example.com/feed.xml',
        'feed_title' => 'My Test Podcast',
        'overall_score' => 0,
        'results_json' => ['feed_format' => 'RSS 2.0'],
    ]);

    $response = $this->get(route('report.show', $report));

    $response->assertOk();
    $response->assertSee('My Test Podcast');
    $response->assertSee('https://example.com/feed.xml');
});

test('it returns 404 for non-existent report slug', function () {
    $response = $this->get('/report/nonexistent');

    $response->assertNotFound();
});

test('report page shows feed title when available', function () {
    $report = FeedReport::create([
        'feed_url' => 'https://example.com/feed.xml',
        'feed_title' => 'The Great Podcast',
        'overall_score' => 0,
        'results_json' => ['feed_format' => 'RSS 2.0'],
    ]);

    $response = $this->get(route('report.show', $report));

    $response->assertOk();
    $response->assertSee('The Great Podcast');
});

test('report page handles missing feed title gracefully', function () {
    $report = FeedReport::create([
        'feed_url' => 'https://example.com/feed.xml',
        'feed_title' => null,
        'overall_score' => 0,
        'results_json' => ['feed_format' => 'RSS 2.0'],
    ]);

    $response = $this->get(route('report.show', $report));

    $response->assertOk();
    $response->assertSee('Unknown Podcast');
});
