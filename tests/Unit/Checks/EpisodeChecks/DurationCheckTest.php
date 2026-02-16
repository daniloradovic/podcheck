<?php

declare(strict_types=1);

use App\Services\Checks\CheckStatus;
use App\Services\Checks\EpisodeChecks\DurationCheck;

// ──────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────

function buildItemWithDuration(?string $duration = '00:32:15'): SimpleXMLElement
{
    $durationTag = $duration !== null
        ? "<itunes:duration>{$duration}</itunes:duration>"
        : '';

    $xml = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">
          <channel>
            <item>
              <title>Test Episode</title>
              {$durationTag}
            </item>
          </channel>
        </rss>
        XML;

    return simplexml_load_string($xml)->channel->item;
}

function buildItemWithoutDuration(): SimpleXMLElement
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

test('name returns "Episode Duration"', function () {
    $check = new DurationCheck;

    expect($check->name())->toBe('Episode Duration');
});

test('severity returns "warning"', function () {
    $check = new DurationCheck;

    expect($check->severity())->toBe('warning');
});

test('implements CheckInterface', function () {
    $check = new DurationCheck;

    expect($check)->toBeInstanceOf(\App\Services\Checks\CheckInterface::class);
});

// ──────────────────────────────────────────────────
// Missing Duration — Warn
// ──────────────────────────────────────────────────

test('warns when duration tag is missing', function () {
    $check = new DurationCheck;
    $item = buildItemWithoutDuration();

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Warn)
        ->and($result->message)->toContain('missing')
        ->and($result->suggestion)->toContain('itunes:duration');
});

test('warns when duration is empty', function () {
    $check = new DurationCheck;
    $item = buildItemWithDuration('');

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Warn)
        ->and($result->message)->toContain('empty');
});

// ──────────────────────────────────────────────────
// Invalid Format — Warn
// ──────────────────────────────────────────────────

test('warns when duration format is not recognized', function () {
    $check = new DurationCheck;
    $item = buildItemWithDuration('thirty-two minutes');

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Warn)
        ->and($result->message)->toContain('not recognized');
});

test('warns when duration has too many colons', function () {
    $check = new DurationCheck;
    $item = buildItemWithDuration('01:02:03:04');

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Warn)
        ->and($result->message)->toContain('not recognized');
});

// ──────────────────────────────────────────────────
// Valid Duration — Pass
// ──────────────────────────────────────────────────

test('passes with HH:MM:SS format', function () {
    $check = new DurationCheck;
    $item = buildItemWithDuration('00:32:15');

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Pass)
        ->and($result->message)->toContain('present')
        ->and($result->message)->toContain('00:32:15');
});

test('passes with MM:SS format', function () {
    $check = new DurationCheck;
    $item = buildItemWithDuration('32:15');

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Pass);
});

test('passes with H:MM:SS format', function () {
    $check = new DurationCheck;
    $item = buildItemWithDuration('1:32:15');

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Pass);
});

test('passes with total seconds format', function () {
    $check = new DurationCheck;
    $item = buildItemWithDuration('1935');

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Pass);
});

test('passes with zero seconds', function () {
    $check = new DurationCheck;
    $item = buildItemWithDuration('0');

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Pass);
});
