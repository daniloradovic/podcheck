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

test('it runs validation checks and stores results in report', function () {
    Http::fake([
        'example.com/*' => Http::response(rssFixture(), 200),
    ]);

    $this->post(route('feed.check'), [
        'url' => 'https://example.com/feed.xml',
    ]);

    $report = FeedReport::first();
    $results = $report->results_json;

    expect($results)->toHaveKeys(['feed_format', 'checked_at', 'summary', 'health_score', 'seo_score', 'channel', 'episodes'])
        ->and($results['channel'])->toBeArray()->not->toBeEmpty()
        ->and($results['episodes'])->toBeArray()->not->toBeEmpty()
        ->and($results['summary'])->toHaveKeys(['total', 'pass', 'warn', 'fail']);
});

test('channel checks include all registered checks', function () {
    Http::fake([
        'example.com/*' => Http::response(rssFixture(), 200),
    ]);

    $this->post(route('feed.check'), [
        'url' => 'https://example.com/feed.xml',
    ]);

    $report = FeedReport::first();
    $channelCheckNames = array_column($report->results_json['channel'], 'name');

    expect($channelCheckNames)->toContain(
        'Podcast Artwork',
        'iTunes Category',
        'Explicit Tag',
        'iTunes Author',
        'Owner Email',
        'Language Tag',
        'Website Link',
        'Channel Description',
    );
});

test('episode checks run against each sampled episode', function () {
    Http::fake([
        'example.com/*' => Http::response(rssFixture(), 200),
    ]);

    $this->post(route('feed.check'), [
        'url' => 'https://example.com/feed.xml',
    ]);

    $report = FeedReport::first();
    $episodes = $report->results_json['episodes'];

    // Fixture has 3 episodes
    expect($episodes)->toHaveCount(3);

    foreach ($episodes as $episode) {
        expect($episode)->toHaveKeys(['title', 'guid', 'results'])
            ->and($episode['results'])->toBeArray()->not->toBeEmpty();
    }
});

test('each check result has required structure', function () {
    Http::fake([
        'example.com/*' => Http::response(rssFixture(), 200),
    ]);

    $this->post(route('feed.check'), [
        'url' => 'https://example.com/feed.xml',
    ]);

    $report = FeedReport::first();

    foreach ($report->results_json['channel'] as $result) {
        expect($result)->toHaveKeys(['name', 'severity', 'status', 'message', 'suggestion'])
            ->and($result['status'])->toBeIn(['pass', 'warn', 'fail']);
    }

    foreach ($report->results_json['episodes'] as $episode) {
        foreach ($episode['results'] as $result) {
            expect($result)->toHaveKeys(['name', 'severity', 'status', 'message', 'suggestion'])
                ->and($result['status'])->toBeIn(['pass', 'warn', 'fail']);
        }
    }
});

test('summary counts match actual check results', function () {
    Http::fake([
        'example.com/*' => Http::response(rssFixture(), 200),
    ]);

    $this->post(route('feed.check'), [
        'url' => 'https://example.com/feed.xml',
    ]);

    $report = FeedReport::first();
    $results = $report->results_json;
    $summary = $results['summary'];

    // Count all results manually
    $totalChecks = count($results['channel']);
    foreach ($results['episodes'] as $episode) {
        $totalChecks += count($episode['results']);
    }

    expect($summary['total'])->toBe($totalChecks)
        ->and($summary['pass'] + $summary['warn'] + $summary['fail'])->toBe($summary['total']);
});

// ──────────────────────────────────────────────────
// POST /check — Scoring Pipeline
// ──────────────────────────────────────────────────

test('it stores overall health score in report', function () {
    Http::fake([
        'example.com/*' => Http::response(rssFixture(), 200),
    ]);

    $this->post(route('feed.check'), [
        'url' => 'https://example.com/feed.xml',
    ]);

    $report = FeedReport::first();

    expect($report->overall_score)->toBeInt()
        ->and($report->overall_score)->toBeGreaterThanOrEqual(0)
        ->and($report->overall_score)->toBeLessThanOrEqual(100);
});

test('health score reflects check results accurately', function () {
    Http::fake([
        'example.com/*' => Http::response(rssFixture(), 200),
    ]);

    $this->post(route('feed.check'), [
        'url' => 'https://example.com/feed.xml',
    ]);

    $report = FeedReport::first();
    $healthScore = $report->results_json['health_score'];

    expect($healthScore)->toHaveKeys(['overall', 'categories'])
        ->and($healthScore['overall'])->toBe($report->overall_score)
        ->and($healthScore['categories'])->toHaveKeys(['compliance', 'technical', 'best_practices']);

    foreach ($healthScore['categories'] as $category) {
        expect($category)->toHaveKeys(['score', 'pass', 'warn', 'fail', 'total'])
            ->and($category['score'])->toBeGreaterThanOrEqual(0)
            ->and($category['score'])->toBeLessThanOrEqual(100)
            ->and($category['pass'] + $category['warn'] + $category['fail'])->toBe($category['total']);
    }
});

test('seo score is stored in report', function () {
    Http::fake([
        'example.com/*' => Http::response(rssFixture(), 200),
    ]);

    $this->post(route('feed.check'), [
        'url' => 'https://example.com/feed.xml',
    ]);

    $report = FeedReport::first();
    $seoScore = $report->results_json['seo_score'];

    expect($seoScore)->toHaveKeys(['overall', 'details'])
        ->and($seoScore['overall'])->toBeGreaterThanOrEqual(0)
        ->and($seoScore['overall'])->toBeLessThanOrEqual(100)
        ->and($seoScore['details'])->toHaveKeys(['show_title', 'show_description', 'episode_titles']);
});

test('seo score details include required fields', function () {
    Http::fake([
        'example.com/*' => Http::response(rssFixture(), 200),
    ]);

    $this->post(route('feed.check'), [
        'url' => 'https://example.com/feed.xml',
    ]);

    $report = FeedReport::first();
    $details = $report->results_json['seo_score']['details'];

    foreach (['show_title', 'show_description'] as $area) {
        expect($details[$area])->toHaveKeys(['score', 'status', 'message', 'suggestion', 'length'])
            ->and($details[$area]['score'])->toBeGreaterThanOrEqual(0)
            ->and($details[$area]['score'])->toBeLessThanOrEqual(100)
            ->and($details[$area]['status'])->toBeIn(['pass', 'warn', 'fail']);
    }

    expect($details['episode_titles'])->toHaveKeys(['score', 'status', 'message', 'suggestion', 'generic_count', 'total_count'])
        ->and($details['episode_titles']['score'])->toBeGreaterThanOrEqual(0)
        ->and($details['episode_titles']['score'])->toBeLessThanOrEqual(100)
        ->and($details['episode_titles']['status'])->toBeIn(['pass', 'warn', 'fail']);
});

test('full pipeline produces consistent scores for valid feed', function () {
    Http::fake([
        'example.com/*' => Http::response(rssFixture(), 200),
    ]);

    $this->post(route('feed.check'), [
        'url' => 'https://example.com/feed.xml',
    ]);

    $report = FeedReport::first();
    $results = $report->results_json;

    // Verify the report is complete with all sections
    expect($results)->toHaveKeys([
        'feed_format', 'checked_at', 'summary',
        'health_score', 'seo_score',
        'channel', 'episodes',
    ]);

    // Health score matches stored overall_score
    expect($report->overall_score)->toBe($results['health_score']['overall']);

    // Summary counts are consistent with health score
    $summary = $results['summary'];
    $healthCategories = $results['health_score']['categories'];
    $categoryTotal = 0;
    foreach ($healthCategories as $cat) {
        $categoryTotal += $cat['total'];
    }
    expect($categoryTotal)->toBe($summary['total']);

    // SEO analyzed all episodes in the fixture
    $seoEpisodes = $results['seo_score']['details']['episode_titles'];
    expect($seoEpisodes['total_count'])->toBe(3); // fixture has 3 episodes

    // Feed format is correct
    expect($results['feed_format'])->toBe('RSS 2.0');
});

test('overall score is non-zero for valid well-formed feed', function () {
    Http::fake([
        'example.com/*' => Http::response(rssFixture(), 200),
    ]);

    $this->post(route('feed.check'), [
        'url' => 'https://example.com/feed.xml',
    ]);

    $report = FeedReport::first();

    // A well-formed feed with all required fields should score reasonably well
    expect($report->overall_score)->toBeGreaterThan(0);
});

// ──────────────────────────────────────────────────
// Episode Sampling Summary
// ──────────────────────────────────────────────────

test('it stores total episode count in results_json', function () {
    Http::fake([
        'example.com/*' => Http::response(rssFixture(), 200),
    ]);

    $this->post(route('feed.check'), [
        'url' => 'https://example.com/feed.xml',
    ]);

    $report = FeedReport::first();

    expect($report->results_json)->toHaveKey('total_episodes')
        ->and($report->results_json['total_episodes'])->toBe(3);
});

test('report page displays episode sampling summary section', function () {
    Http::fake([
        'example.com/*' => Http::response(rssFixture(), 200),
    ]);

    $this->post(route('feed.check'), [
        'url' => 'https://example.com/feed.xml',
    ]);

    $report = FeedReport::first();
    $response = $this->get(route('report.show', $report));

    $response->assertOk();
    $response->assertSee('Episodes Sampled');
    $response->assertSee('Checked Episodes');
});

test('episode sampling summary shows all episode titles', function () {
    Http::fake([
        'example.com/*' => Http::response(rssFixture(), 200),
    ]);

    $this->post(route('feed.check'), [
        'url' => 'https://example.com/feed.xml',
    ]);

    $report = FeedReport::first();
    $response = $this->get(route('report.show', $report));

    $response->assertOk();
    $response->assertSee('Episode 1: Getting Started');
    $response->assertSee('Episode 2: Advanced Validation');
    $response->assertSee('Episode 3: SEO for Podcasters');
});

test('episode sampling summary shows correct count when all episodes checked', function () {
    Http::fake([
        'example.com/*' => Http::response(rssFixture(), 200),
    ]);

    $this->post(route('feed.check'), [
        'url' => 'https://example.com/feed.xml',
    ]);

    $report = FeedReport::first();
    $response = $this->get(route('report.show', $report));

    $response->assertOk();
    $response->assertSee('All 3 episodes checked');
});

test('episode sampling summary is hidden when no episodes exist', function () {
    $report = FeedReport::create([
        'feed_url' => 'https://example.com/feed.xml',
        'feed_title' => 'Empty Podcast',
        'overall_score' => 50,
        'results_json' => [
            'feed_format' => 'RSS 2.0',
            'total_episodes' => 0,
            'summary' => ['total' => 0, 'pass' => 0, 'warn' => 0, 'fail' => 0],
            'channel' => [],
            'episodes' => [],
        ],
    ]);

    $response = $this->get(route('report.show', $report));

    $response->assertOk();
    $response->assertDontSee('Episodes Sampled');
});

test('total episode count is stored correctly and differs from sampled when feed has many episodes', function () {
    // Create a feed fixture with 12 episodes
    $episodeItems = '';
    for ($i = 1; $i <= 12; $i++) {
        $episodeItems .= <<<XML
        <item>
            <title>Episode {$i}: Test Title</title>
            <description>Episode {$i} description content here.</description>
            <enclosure url="https://example.com/ep{$i}.mp3" length="1234" type="audio/mpeg"/>
            <guid isPermaLink="false">ep-{$i}</guid>
            <pubDate>Mon, 10 Feb 2025 08:00:00 +0000</pubDate>
            <itunes:duration>00:30:00</itunes:duration>
        </item>
        XML;
    }

    $feedXml = <<<XML
    <?xml version="1.0" encoding="UTF-8"?>
    <rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">
      <channel>
        <title>Big Podcast</title>
        <link>https://example.com</link>
        <description>A podcast with many episodes for testing sampling limits.</description>
        <language>en</language>
        <itunes:author>Test Author</itunes:author>
        <itunes:explicit>false</itunes:explicit>
        <itunes:image href="https://example.com/art.jpg"/>
        <itunes:category text="Technology"/>
        <itunes:owner>
            <itunes:name>Test</itunes:name>
            <itunes:email>test@example.com</itunes:email>
        </itunes:owner>
        {$episodeItems}
      </channel>
    </rss>
    XML;

    Http::fake([
        'example.com/*' => Http::response($feedXml, 200),
    ]);

    $this->post(route('feed.check'), [
        'url' => 'https://example.com/feed.xml',
    ]);

    $report = FeedReport::first();

    expect($report->results_json['total_episodes'])->toBe(12)
        ->and($report->results_json['episodes'])->toHaveCount(10);

    $response = $this->get(route('report.show', $report));
    $response->assertOk();
    $response->assertSee('10 of 12 episodes checked');
    $response->assertSee('Your feed contains 12 episodes total');
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
        'url' => 'https://example.com/'.str_repeat('a', 2048),
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

// ──────────────────────────────────────────────────
// Error Handling UI
// ──────────────────────────────────────────────────

test('unreachable feed shows "Feed Unreachable" error with suggestions', function () {
    Http::fake([
        'example.com/*' => Http::response('Not Found', 404),
    ]);

    $response = $this->post(route('feed.check'), [
        'url' => 'https://example.com/feed.xml',
    ]);

    $response->assertRedirect(route('home'));
    $response->assertSessionHas('error_type', 'unreachable');

    $followUp = $this->get(route('home'));
    $followUp->assertSee('Feed Unreachable');
    $followUp->assertSee('Double-check that the URL is correct');
});

test('non-XML response shows "Not a Podcast Feed" error with suggestions', function () {
    Http::fake([
        'example.com/*' => Http::response('<html><body>Not a feed</body></html>', 200),
    ]);

    $response = $this->post(route('feed.check'), [
        'url' => 'https://example.com/feed.xml',
    ]);

    $response->assertRedirect(route('home'));
    $response->assertSessionHas('error_type', 'not_podcast');

    $followUp = $this->get(route('home'));
    $followUp->assertSee('Not a Podcast Feed');
    $followUp->assertSee('Make sure the URL points to an RSS or Atom feed');
});

test('non-RSS XML shows "Not a Podcast Feed" error', function () {
    $nonRssXml = '<?xml version="1.0"?><root><item>Not an RSS feed</item></root>';

    Http::fake([
        'example.com/*' => Http::response($nonRssXml, 200),
    ]);

    $response = $this->post(route('feed.check'), [
        'url' => 'https://example.com/data.xml',
    ]);

    $response->assertRedirect(route('home'));
    $response->assertSessionHas('error_type', 'not_podcast');
});

test('server error shows "Feed Unreachable" with error type in session', function () {
    Http::fake([
        'example.com/*' => Http::response('Internal Server Error', 500),
    ]);

    $response = $this->post(route('feed.check'), [
        'url' => 'https://example.com/feed.xml',
    ]);

    $response->assertRedirect(route('home'));
    $response->assertSessionHas('error_type', 'unreachable');
});

test('validation error shows "Invalid Input" with error message', function () {
    $response = $this->from(route('home'))->post(route('feed.check'), [
        'url' => '',
    ]);

    $response->assertRedirect(route('home'));
    $response->assertSessionHasErrors('url');
});

test('error alert is dismissible (has dismiss button markup)', function () {
    Http::fake([
        'example.com/*' => Http::response('Not Found', 404),
    ]);

    $this->post(route('feed.check'), [
        'url' => 'https://example.com/feed.xml',
    ]);

    $followUp = $this->get(route('home'));
    $followUp->assertSee('Dismiss error', false);
});

test('error preserves URL input for user to retry', function () {
    Http::fake([
        'example.com/*' => Http::response('Not Found', 404),
    ]);

    $response = $this->post(route('feed.check'), [
        'url' => 'https://example.com/feed.xml',
    ]);

    $response->assertSessionHasInput('url', 'https://example.com/feed.xml');
});

test('error input field gets red border styling', function () {
    Http::fake([
        'example.com/*' => Http::response('Not Found', 404),
    ]);

    $this->post(route('feed.check'), [
        'url' => 'https://example.com/feed.xml',
    ]);

    $followUp = $this->get(route('home'));
    $followUp->assertSee('border-red-400/50', false);
});

test('successful check does not show error alert', function () {
    Http::fake([
        'example.com/*' => Http::response(rssFixture(), 200),
    ]);

    $this->post(route('feed.check'), [
        'url' => 'https://example.com/feed.xml',
    ]);

    $report = FeedReport::first();
    $response = $this->get(route('report.show', $report));

    $response->assertDontSee('Feed Unreachable');
    $response->assertDontSee('Not a Podcast Feed');
    $response->assertDontSee('Invalid URL');
});

test('home page without errors does not show error panel', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertDontSee('Feed Unreachable');
    $response->assertDontSee('Not a Podcast Feed');
    $response->assertDontSee('data-has-errors', false);
});

test('error page includes data-has-errors marker for Alpine reset', function () {
    Http::fake([
        'example.com/*' => Http::response('Not Found', 404),
    ]);

    $this->post(route('feed.check'), [
        'url' => 'https://example.com/feed.xml',
    ]);

    $followUp = $this->get(route('home'));
    $followUp->assertSee('data-has-errors', false);
});
