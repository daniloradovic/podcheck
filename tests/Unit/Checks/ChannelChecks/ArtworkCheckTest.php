<?php

declare(strict_types=1);

use App\Services\Checks\ChannelChecks\ArtworkCheck;
use App\Services\Checks\CheckStatus;
use Illuminate\Http\Client\Factory as HttpFactory;

// ──────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────

function buildFeedWithArtwork(?string $href = 'https://example.com/artwork.jpg'): SimpleXMLElement
{
    $imageTag = $href !== null
        ? "<itunes:image href=\"{$href}\"/>"
        : '';

    $xml = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">
          <channel>
            <title>Test Show</title>
            {$imageTag}
          </channel>
        </rss>
        XML;

    return simplexml_load_string($xml);
}

function buildFeedWithoutArtwork(): SimpleXMLElement
{
    $xml = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">
          <channel>
            <title>Test Show</title>
          </channel>
        </rss>
        XML;

    return simplexml_load_string($xml);
}

function createFakedHttp(array $fakes = []): HttpFactory
{
    $http = new HttpFactory();
    $http->fake($fakes);

    return $http;
}

function createArtworkCheck(array $httpFakes = []): ArtworkCheck
{
    return new ArtworkCheck(createFakedHttp($httpFakes));
}

// ──────────────────────────────────────────────────
// Metadata
// ──────────────────────────────────────────────────

test('name returns "Podcast Artwork"', function () {
    $check = createArtworkCheck();

    expect($check->name())->toBe('Podcast Artwork');
});

test('severity returns "error"', function () {
    $check = createArtworkCheck();

    expect($check->severity())->toBe('error');
});

test('implements CheckInterface', function () {
    $check = createArtworkCheck();

    expect($check)->toBeInstanceOf(\App\Services\Checks\CheckInterface::class);
});

// ──────────────────────────────────────────────────
// Missing Artwork — Fail
// ──────────────────────────────────────────────────

test('fails when itunes:image tag is missing', function () {
    $check = createArtworkCheck();
    $feed = buildFeedWithoutArtwork();

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Fail)
        ->and($result->message)->toContain('missing')
        ->and($result->suggestion)->toContain('itunes:image');
});

test('fails when itunes:image href is empty', function () {
    $check = createArtworkCheck();
    $feed = buildFeedWithArtwork('');

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Fail)
        ->and($result->message)->toContain('missing');
});

// ──────────────────────────────────────────────────
// Invalid Format — Fail
// ──────────────────────────────────────────────────

test('fails when artwork is not JPEG or PNG', function () {
    $check = createArtworkCheck([
        'example.com/artwork.gif' => HttpFactory::response('', 200, ['Content-Type' => 'image/gif']),
    ]);

    $feed = buildFeedWithArtwork('https://example.com/artwork.gif');

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Fail)
        ->and($result->message)->toContain('not a supported format')
        ->and($result->message)->toContain('image/gif')
        ->and($result->suggestion)->toContain('JPEG or PNG');
});

test('fails when artwork is WebP', function () {
    $check = createArtworkCheck([
        'example.com/artwork.webp' => HttpFactory::response('', 200, ['Content-Type' => 'image/webp']),
    ]);

    $feed = buildFeedWithArtwork('https://example.com/artwork.webp');

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Fail)
        ->and($result->message)->toContain('not a supported format');
});

// ──────────────────────────────────────────────────
// Content Type Handling
// ──────────────────────────────────────────────────

test('does not fail on format when content type includes charset parameter', function () {
    $check = createArtworkCheck([
        'example.com/artwork.jpg' => HttpFactory::response('', 200, ['Content-Type' => 'image/jpeg; charset=utf-8']),
    ]);

    $feed = buildFeedWithArtwork('https://example.com/artwork.jpg');

    $result = $check->run($feed);

    // JPEG is valid — should not fail for format (may warn about dimensions)
    expect($result->status)->not->toBe(CheckStatus::Fail);
});

test('does not fail on format when content type is image/png', function () {
    $check = createArtworkCheck([
        'example.com/artwork.png' => HttpFactory::response('', 200, ['Content-Type' => 'image/png']),
    ]);

    $feed = buildFeedWithArtwork('https://example.com/artwork.png');

    $result = $check->run($feed);

    // PNG is valid — should not fail for format
    expect($result->status)->not->toBe(CheckStatus::Fail);
});

// ──────────────────────────────────────────────────
// Dimension Warnings
// ──────────────────────────────────────────────────

test('warns when image dimensions cannot be determined', function () {
    $check = createArtworkCheck([
        'example.com/artwork.jpg' => HttpFactory::response('', 200, ['Content-Type' => 'image/jpeg']),
    ]);

    $feed = buildFeedWithArtwork('https://example.com/artwork.jpg');

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Warn)
        ->and($result->message)->toContain('could not verify dimensions')
        ->and($result->suggestion)->toContain('1400');
});

test('warns when HEAD request fails but image URL is present', function () {
    $check = createArtworkCheck([
        'example.com/artwork.jpg' => HttpFactory::response('', 500),
    ]);

    $feed = buildFeedWithArtwork('https://example.com/artwork.jpg');

    $result = $check->run($feed);

    // Content type is null (failed HEAD), dimensions will also fail → warn
    expect($result->status)->toBe(CheckStatus::Warn)
        ->and($result->message)->toContain('could not verify');
});

// ──────────────────────────────────────────────────
// Artwork from the valid test fixture
// ──────────────────────────────────────────────────

test('extracts artwork URL from valid RSS feed fixture', function () {
    $check = createArtworkCheck([
        'example.com/artwork.jpg' => HttpFactory::response('', 200, ['Content-Type' => 'image/jpeg']),
    ]);

    // Build the fixture XML inline (unit tests don't have base_path)
    $xml = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">
          <channel>
            <title>The PodCheck Test Show</title>
            <itunes:image href="https://example.com/artwork.jpg"/>
            <itunes:category text="Technology">
              <itunes:category text="Tech News"/>
            </itunes:category>
          </channel>
        </rss>
        XML;

    $feed = simplexml_load_string($xml);

    $result = $check->run($feed);

    // Should not fail — the feed has a valid itunes:image tag with JPEG content type
    expect($result->status)->not->toBe(CheckStatus::Fail);
});
