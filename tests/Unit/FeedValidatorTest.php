<?php

declare(strict_types=1);

use App\Services\Checks\CheckInterface;
use App\Services\Checks\CheckResult;
use App\Services\FeedValidator;

// ──────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────

function makeCheck(string $name, CheckResult $result, string $severity = 'error'): CheckInterface
{
    return new class($name, $result, $severity) implements CheckInterface
    {
        public function __construct(
            private readonly string $checkName,
            private readonly CheckResult $checkResult,
            private readonly string $checkSeverity,
        ) {}

        public function name(): string
        {
            return $this->checkName;
        }

        public function run(SimpleXMLElement $feed): CheckResult
        {
            return $this->checkResult;
        }

        public function severity(): string
        {
            return $this->checkSeverity;
        }
    };
}

// ──────────────────────────────────────────────────
// Channel Checks
// ──────────────────────────────────────────────────

test('validate with no checks returns empty results', function () {
    $validator = new FeedValidator;
    $feed = loadFeedFixture();

    $results = $validator->validate($feed);

    expect($results)->toBe([
        'channel' => [],
        'episodes' => [],
    ]);
});

test('validate runs a single channel check and returns formatted result', function () {
    $check = makeCheck('Artwork', CheckResult::pass('Artwork is present'), 'error');
    $validator = new FeedValidator(channelChecks: [$check]);
    $feed = loadFeedFixture();

    $results = $validator->validate($feed);

    expect($results['channel'])->toHaveCount(1)
        ->and($results['channel'][0])->toBe([
            'name' => 'Artwork',
            'severity' => 'error',
            'status' => 'pass',
            'message' => 'Artwork is present',
            'suggestion' => null,
        ]);
});

test('validate runs multiple channel checks in order', function () {
    $checks = [
        makeCheck('Artwork', CheckResult::pass('Artwork OK')),
        makeCheck('Category', CheckResult::warn('No subcategory', 'Add a subcategory'), 'warning'),
        makeCheck('Explicit Tag', CheckResult::fail('Missing explicit tag', 'Add <itunes:explicit>'), 'error'),
    ];

    $validator = new FeedValidator(channelChecks: $checks);
    $feed = loadFeedFixture();

    $results = $validator->validate($feed);

    expect($results['channel'])->toHaveCount(3)
        ->and($results['channel'][0]['name'])->toBe('Artwork')
        ->and($results['channel'][0]['status'])->toBe('pass')
        ->and($results['channel'][1]['name'])->toBe('Category')
        ->and($results['channel'][1]['status'])->toBe('warn')
        ->and($results['channel'][1]['suggestion'])->toBe('Add a subcategory')
        ->and($results['channel'][2]['name'])->toBe('Explicit Tag')
        ->and($results['channel'][2]['status'])->toBe('fail')
        ->and($results['channel'][2]['suggestion'])->toBe('Add <itunes:explicit>');
});

test('validate includes severity in channel check results', function () {
    $checks = [
        makeCheck('Critical Check', CheckResult::pass('OK'), 'error'),
        makeCheck('Minor Check', CheckResult::pass('OK'), 'info'),
    ];

    $validator = new FeedValidator(channelChecks: $checks);
    $feed = loadFeedFixture();

    $results = $validator->validate($feed);

    expect($results['channel'][0]['severity'])->toBe('error')
        ->and($results['channel'][1]['severity'])->toBe('info');
});

// ──────────────────────────────────────────────────
// Episode Checks
// ──────────────────────────────────────────────────

test('validate runs episode checks against individual items', function () {
    $check = makeCheck('Enclosure', CheckResult::pass('Enclosure present'), 'error');
    $validator = new FeedValidator(episodeChecks: [$check]);
    $feed = loadFeedFixture();

    $results = $validator->validate($feed);

    expect($results['episodes'])->toHaveCount(3)
        ->and($results['episodes'][0]['title'])->toBe('Episode 1: Getting Started')
        ->and($results['episodes'][0]['results'])->toHaveCount(1)
        ->and($results['episodes'][0]['results'][0]['name'])->toBe('Enclosure')
        ->and($results['episodes'][0]['results'][0]['status'])->toBe('pass');
});

test('validate extracts episode titles and guids', function () {
    $check = makeCheck('Title', CheckResult::pass('OK'), 'info');
    $validator = new FeedValidator(episodeChecks: [$check]);
    $feed = loadFeedFixture();

    $results = $validator->validate($feed);

    expect($results['episodes'][0]['title'])->toBe('Episode 1: Getting Started')
        ->and($results['episodes'][0]['guid'])->toBe('ep-001-getting-started')
        ->and($results['episodes'][1]['title'])->toBe('Episode 2: Advanced Validation')
        ->and($results['episodes'][1]['guid'])->toBe('ep-002-advanced-validation')
        ->and($results['episodes'][2]['title'])->toBe('Episode 3: SEO for Podcasters')
        ->and($results['episodes'][2]['guid'])->toBe('ep-003-seo-for-podcasters');
});

test('validate limits episode checks to 10 items', function () {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>'
        .'<rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">'
        .'<channel><title>Big Show</title>';

    for ($i = 1; $i <= 15; $i++) {
        $xml .= "<item><title>Episode {$i}</title><guid>ep-{$i}</guid></item>";
    }

    $xml .= '</channel></rss>';

    $feed = simplexml_load_string($xml);
    $check = makeCheck('Title', CheckResult::pass('OK'), 'info');
    $validator = new FeedValidator(episodeChecks: [$check]);

    $results = $validator->validate($feed);

    expect($results['episodes'])->toHaveCount(10);
});

test('validate returns empty episodes when no episode checks registered', function () {
    $channelCheck = makeCheck('Artwork', CheckResult::pass('OK'));
    $validator = new FeedValidator(channelChecks: [$channelCheck]);
    $feed = loadFeedFixture();

    $results = $validator->validate($feed);

    expect($results['episodes'])->toBe([]);
});

test('validate returns empty episodes when feed has no items', function () {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>'
        .'<rss version="2.0"><channel><title>Empty Show</title></channel></rss>';
    $feed = simplexml_load_string($xml);

    $check = makeCheck('Enclosure', CheckResult::pass('OK'), 'error');
    $validator = new FeedValidator(episodeChecks: [$check]);

    $results = $validator->validate($feed);

    expect($results['episodes'])->toBe([]);
});

test('validate handles episodes without title using fallback', function () {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>'
        .'<rss version="2.0"><channel><title>Show</title>'
        .'<item><guid>ep-1</guid></item>'
        .'<item><title></title><guid>ep-2</guid></item>'
        .'</channel></rss>';
    $feed = simplexml_load_string($xml);

    $check = makeCheck('Title', CheckResult::pass('OK'), 'info');
    $validator = new FeedValidator(episodeChecks: [$check]);

    $results = $validator->validate($feed);

    expect($results['episodes'][0]['title'])->toBe('Episode 1')
        ->and($results['episodes'][1]['title'])->toBe('Episode 2');
});

test('validate handles episodes without guid', function () {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>'
        .'<rss version="2.0"><channel><title>Show</title>'
        .'<item><title>No GUID Episode</title></item>'
        .'</channel></rss>';
    $feed = simplexml_load_string($xml);

    $check = makeCheck('GUID', CheckResult::fail('Missing', 'Add guid'), 'error');
    $validator = new FeedValidator(episodeChecks: [$check]);

    $results = $validator->validate($feed);

    expect($results['episodes'][0]['guid'])->toBeNull();
});

// ──────────────────────────────────────────────────
// Combined Channel + Episode Checks
// ──────────────────────────────────────────────────

test('validate runs both channel and episode checks together', function () {
    $channelCheck = makeCheck('Artwork', CheckResult::pass('Artwork OK'));
    $episodeCheck = makeCheck('Enclosure', CheckResult::pass('Audio OK'), 'error');

    $validator = new FeedValidator(
        channelChecks: [$channelCheck],
        episodeChecks: [$episodeCheck],
    );
    $feed = loadFeedFixture();

    $results = $validator->validate($feed);

    expect($results['channel'])->toHaveCount(1)
        ->and($results['episodes'])->toHaveCount(3)
        ->and($results['channel'][0]['name'])->toBe('Artwork')
        ->and($results['episodes'][0]['results'][0]['name'])->toBe('Enclosure');
});

// ──────────────────────────────────────────────────
// Summary
// ──────────────────────────────────────────────────

test('summarize counts pass, warn, and fail across all results', function () {
    $results = [
        'channel' => [
            ['name' => 'A', 'severity' => 'error', 'status' => 'pass', 'message' => '', 'suggestion' => null],
            ['name' => 'B', 'severity' => 'error', 'status' => 'fail', 'message' => '', 'suggestion' => 'Fix B'],
            ['name' => 'C', 'severity' => 'warning', 'status' => 'warn', 'message' => '', 'suggestion' => 'Fix C'],
        ],
        'episodes' => [
            [
                'title' => 'Ep 1',
                'guid' => 'ep-1',
                'results' => [
                    ['name' => 'D', 'severity' => 'error', 'status' => 'pass', 'message' => '', 'suggestion' => null],
                    ['name' => 'E', 'severity' => 'error', 'status' => 'fail', 'message' => '', 'suggestion' => 'Fix E'],
                ],
            ],
        ],
    ];

    $summary = FeedValidator::summarize($results);

    expect($summary)->toBe([
        'total' => 5,
        'pass' => 2,
        'warn' => 1,
        'fail' => 2,
    ]);
});

test('summarize returns zeros for empty results', function () {
    $results = ['channel' => [], 'episodes' => []];

    $summary = FeedValidator::summarize($results);

    expect($summary)->toBe([
        'total' => 0,
        'pass' => 0,
        'warn' => 0,
        'fail' => 0,
    ]);
});

// ──────────────────────────────────────────────────
// Atom Feed Support
// ──────────────────────────────────────────────────

test('validate handles Atom feeds with entry elements', function () {
    $feed = loadFeedFixture('valid-atom-feed.xml');
    $check = makeCheck('Title', CheckResult::pass('OK'), 'info');
    $validator = new FeedValidator(episodeChecks: [$check]);

    $results = $validator->validate($feed);

    expect($results['episodes'])->not->toBe([]);
});
