<?php

declare(strict_types=1);

use App\Services\Checks\CheckStatus;
use App\Services\Checks\EpisodeChecks\PubDateCheck;

// ──────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────

function buildItemWithPubDate(?string $pubDate = 'Mon, 10 Feb 2025 08:00:00 +0000'): SimpleXMLElement
{
    $pubDateTag = $pubDate !== null
        ? "<pubDate>{$pubDate}</pubDate>"
        : '';

    $xml = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">
          <channel>
            <item>
              <title>Test Episode</title>
              {$pubDateTag}
            </item>
          </channel>
        </rss>
        XML;

    return simplexml_load_string($xml)->channel->item;
}

function buildItemWithoutPubDate(): SimpleXMLElement
{
    $xml = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">
          <channel>
            <item>
              <title>Test Episode</title>
            </item>
          </channel>
        </rss>
        XML;

    return simplexml_load_string($xml)->channel->item;
}

// ──────────────────────────────────────────────────
// Metadata
// ──────────────────────────────────────────────────

test('name returns "Episode Publication Date"', function () {
    $check = new PubDateCheck;

    expect($check->name())->toBe('Episode Publication Date');
});

test('severity returns "error"', function () {
    $check = new PubDateCheck;

    expect($check->severity())->toBe('error');
});

test('implements CheckInterface', function () {
    $check = new PubDateCheck;

    expect($check)->toBeInstanceOf(\App\Services\Checks\CheckInterface::class);
});

// ──────────────────────────────────────────────────
// Missing pubDate — Fail
// ──────────────────────────────────────────────────

test('fails when pubDate tag is missing', function () {
    $check = new PubDateCheck;
    $item = buildItemWithoutPubDate();

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Fail)
        ->and($result->message)->toContain('missing')
        ->and($result->suggestion)->toContain('pubDate');
});

test('fails when pubDate is empty', function () {
    $check = new PubDateCheck;
    $item = buildItemWithPubDate('');

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Fail)
        ->and($result->message)->toContain('empty');
});

test('fails when pubDate is only whitespace', function () {
    $check = new PubDateCheck;
    $item = buildItemWithPubDate('   ');

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Fail)
        ->and($result->message)->toContain('empty');
});

// ──────────────────────────────────────────────────
// Invalid Format — Warn
// ──────────────────────────────────────────────────

test('warns when pubDate is completely invalid', function () {
    $check = new PubDateCheck;
    $item = buildItemWithPubDate('not-a-date');

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Warn)
        ->and($result->message)->toContain('not valid RFC 2822')
        ->and($result->suggestion)->toContain('RFC 2822');
});

test('warns when pubDate is in the future', function () {
    $check = new PubDateCheck;
    $futureDate = date('r', strtotime('+1 year'));
    $item = buildItemWithPubDate($futureDate);

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Warn)
        ->and($result->message)->toContain('future');
});

// ──────────────────────────────────────────────────
// Valid pubDate — Pass
// ──────────────────────────────────────────────────

test('passes with valid RFC 2822 date', function () {
    $check = new PubDateCheck;
    $item = buildItemWithPubDate('Mon, 10 Feb 2025 08:00:00 +0000');

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Pass)
        ->and($result->message)->toContain('valid');
});

test('passes with RFC 2822 date including timezone name', function () {
    $check = new PubDateCheck;
    $item = buildItemWithPubDate('Mon, 10 Feb 2025 08:00:00 GMT');

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Pass);
});

test('passes with slightly non-standard but parseable date', function () {
    $check = new PubDateCheck;
    $item = buildItemWithPubDate('10 Feb 2025 08:00:00 +0000');

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Pass);
});

test('passes with ISO 8601 style date that strtotime can parse', function () {
    $check = new PubDateCheck;
    $item = buildItemWithPubDate('2025-02-10T08:00:00+00:00');

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Pass);
});
