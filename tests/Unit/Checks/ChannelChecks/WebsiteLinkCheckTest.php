<?php

declare(strict_types=1);

use App\Services\Checks\ChannelChecks\WebsiteLinkCheck;
use App\Services\Checks\CheckStatus;

// ──────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────

function buildFeedWithLink(?string $link = 'https://example.com/podcast'): SimpleXMLElement
{
    $linkTag = $link !== null
        ? "<link>{$link}</link>"
        : '';

    $xml = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">
          <channel>
            <title>Test Show</title>
            {$linkTag}
          </channel>
        </rss>
        XML;

    return simplexml_load_string($xml);
}

function buildFeedWithoutLink(): SimpleXMLElement
{
    $xml = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">
          <channel>
            <title>Test Show</title>
          </channel>
        </rss>
        XML;

    return simplexml_load_string($xml);
}

// ──────────────────────────────────────────────────
// Metadata
// ──────────────────────────────────────────────────

test('name returns "Website Link"', function () {
    $check = new WebsiteLinkCheck;

    expect($check->name())->toBe('Website Link');
});

test('severity returns "warning"', function () {
    $check = new WebsiteLinkCheck;

    expect($check->severity())->toBe('warning');
});

test('implements CheckInterface', function () {
    $check = new WebsiteLinkCheck;

    expect($check)->toBeInstanceOf(\App\Services\Checks\CheckInterface::class);
});

// ──────────────────────────────────────────────────
// Missing Link — Fail
// ──────────────────────────────────────────────────

test('fails when link tag is missing', function () {
    $check = new WebsiteLinkCheck;
    $feed = buildFeedWithoutLink();

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Fail)
        ->and($result->message)->toContain('missing')
        ->and($result->suggestion)->toContain('link');
});

test('fails when link tag is empty', function () {
    $check = new WebsiteLinkCheck;
    $feed = buildFeedWithLink('');

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Fail)
        ->and($result->message)->toContain('missing');
});

// ──────────────────────────────────────────────────
// Invalid URL — Warn
// ──────────────────────────────────────────────────

test('warns when link is not a valid URL', function () {
    $check = new WebsiteLinkCheck;
    $feed = buildFeedWithLink('not-a-url');

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Warn)
        ->and($result->message)->toContain('not appear to be a valid URL')
        ->and($result->message)->toContain('not-a-url');
});

test('warns when link uses ftp scheme', function () {
    $check = new WebsiteLinkCheck;
    $feed = buildFeedWithLink('ftp://files.example.com');

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Warn)
        ->and($result->message)->toContain('not appear to be a valid URL');
});

test('warns when link is missing scheme', function () {
    $check = new WebsiteLinkCheck;
    $feed = buildFeedWithLink('example.com/podcast');

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Warn);
});

// ──────────────────────────────────────────────────
// Valid Link — Pass
// ──────────────────────────────────────────────────

test('passes with valid https URL', function () {
    $check = new WebsiteLinkCheck;
    $feed = buildFeedWithLink('https://example.com/podcast');

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Pass)
        ->and($result->message)->toContain('https://example.com/podcast');
});

test('passes with valid http URL', function () {
    $check = new WebsiteLinkCheck;
    $feed = buildFeedWithLink('http://example.com');

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Pass)
        ->and($result->message)->toContain('http://example.com');
});

test('passes with URL that has path and query string', function () {
    $check = new WebsiteLinkCheck;
    $feed = buildFeedWithLink('https://example.com/podcast?ref=rss');

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Pass);
});
