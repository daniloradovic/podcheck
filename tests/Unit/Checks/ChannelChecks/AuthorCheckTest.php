<?php

declare(strict_types=1);

use App\Services\Checks\ChannelChecks\AuthorCheck;
use App\Services\Checks\CheckStatus;

// ──────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────

function buildFeedWithAuthor(?string $author = 'Jane Podcaster'): SimpleXMLElement
{
    $authorTag = $author !== null
        ? "<itunes:author>{$author}</itunes:author>"
        : '';

    $xml = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">
          <channel>
            <title>Test Show</title>
            {$authorTag}
          </channel>
        </rss>
        XML;

    return simplexml_load_string($xml);
}

function buildFeedWithoutAuthor(): SimpleXMLElement
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

test('name returns "iTunes Author"', function () {
    $check = new AuthorCheck;

    expect($check->name())->toBe('iTunes Author');
});

test('severity returns "warning"', function () {
    $check = new AuthorCheck;

    expect($check->severity())->toBe('warning');
});

test('implements CheckInterface', function () {
    $check = new AuthorCheck;

    expect($check)->toBeInstanceOf(\App\Services\Checks\CheckInterface::class);
});

// ──────────────────────────────────────────────────
// Missing Author — Fail
// ──────────────────────────────────────────────────

test('fails when itunes:author is missing', function () {
    $check = new AuthorCheck;
    $feed = buildFeedWithoutAuthor();

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Fail)
        ->and($result->message)->toContain('missing')
        ->and($result->suggestion)->toContain('itunes:author');
});

test('fails when itunes:author is empty', function () {
    $check = new AuthorCheck;
    $feed = buildFeedWithAuthor('');

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Fail)
        ->and($result->message)->toContain('missing');
});

test('fails when itunes:author is only whitespace', function () {
    $check = new AuthorCheck;
    $feed = buildFeedWithAuthor('   ');

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Fail)
        ->and($result->message)->toContain('missing');
});

// ──────────────────────────────────────────────────
// Excessively Long — Warn
// ──────────────────────────────────────────────────

test('warns when author name is excessively long', function () {
    $check = new AuthorCheck;
    $longAuthor = str_repeat('A', 256);
    $feed = buildFeedWithAuthor($longAuthor);

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Warn)
        ->and($result->message)->toContain('excessively long')
        ->and($result->message)->toContain('256');
});

// ──────────────────────────────────────────────────
// Valid Author — Pass
// ──────────────────────────────────────────────────

test('passes when itunes:author is present', function () {
    $check = new AuthorCheck;
    $feed = buildFeedWithAuthor('Jane Podcaster');

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Pass)
        ->and($result->message)->toContain('Jane Podcaster');
});

test('passes with author name at 255 characters', function () {
    $check = new AuthorCheck;
    $author = str_repeat('A', 255);
    $feed = buildFeedWithAuthor($author);

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Pass);
});
