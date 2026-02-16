<?php

declare(strict_types=1);

use App\Services\Checks\ChannelChecks\ExplicitTagCheck;
use App\Services\Checks\CheckStatus;

// ──────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────

function buildFeedWithExplicit(?string $value = 'false'): SimpleXMLElement
{
    $explicitTag = $value !== null
        ? "<itunes:explicit>{$value}</itunes:explicit>"
        : '';

    $xml = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">
          <channel>
            <title>Test Show</title>
            {$explicitTag}
          </channel>
        </rss>
        XML;

    return simplexml_load_string($xml);
}

function buildFeedWithoutExplicit(): SimpleXMLElement
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

test('name returns "Explicit Tag"', function () {
    $check = new ExplicitTagCheck;

    expect($check->name())->toBe('Explicit Tag');
});

test('severity returns "error"', function () {
    $check = new ExplicitTagCheck;

    expect($check->severity())->toBe('error');
});

test('implements CheckInterface', function () {
    $check = new ExplicitTagCheck;

    expect($check)->toBeInstanceOf(\App\Services\Checks\CheckInterface::class);
});

// ──────────────────────────────────────────────────
// Missing Tag — Fail
// ──────────────────────────────────────────────────

test('fails when itunes:explicit tag is missing', function () {
    $check = new ExplicitTagCheck;
    $feed = buildFeedWithoutExplicit();

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Fail)
        ->and($result->message)->toContain('missing')
        ->and($result->suggestion)->toContain('itunes:explicit');
});

test('fails when itunes:explicit tag is empty', function () {
    $check = new ExplicitTagCheck;
    $feed = buildFeedWithExplicit('');

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Fail)
        ->and($result->message)->toContain('missing');
});

// ──────────────────────────────────────────────────
// Non-standard Values — Warn
// ──────────────────────────────────────────────────

test('warns when explicit tag has non-standard value', function () {
    $check = new ExplicitTagCheck;
    $feed = buildFeedWithExplicit('clean');

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Warn)
        ->and($result->message)->toContain('non-standard')
        ->and($result->message)->toContain('clean');
});

test('warns when explicit tag uses legacy "yes" value', function () {
    $check = new ExplicitTagCheck;
    $feed = buildFeedWithExplicit('yes');

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Warn)
        ->and($result->message)->toContain('legacy')
        ->and($result->suggestion)->toContain('true');
});

test('warns when explicit tag uses legacy "no" value', function () {
    $check = new ExplicitTagCheck;
    $feed = buildFeedWithExplicit('no');

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Warn)
        ->and($result->message)->toContain('legacy')
        ->and($result->suggestion)->toContain('true');
});

// ──────────────────────────────────────────────────
// Valid Values — Pass
// ──────────────────────────────────────────────────

test('passes when explicit tag is "false"', function () {
    $check = new ExplicitTagCheck;
    $feed = buildFeedWithExplicit('false');

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Pass)
        ->and($result->message)->toContain('false');
});

test('passes when explicit tag is "true"', function () {
    $check = new ExplicitTagCheck;
    $feed = buildFeedWithExplicit('true');

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Pass)
        ->and($result->message)->toContain('true');
});

test('passes with case-insensitive values', function () {
    $check = new ExplicitTagCheck;
    $feed = buildFeedWithExplicit('False');

    $result = $check->run($feed);

    expect($result->status)->toBe(CheckStatus::Pass);
});
