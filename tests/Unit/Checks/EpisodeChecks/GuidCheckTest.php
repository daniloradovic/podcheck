<?php

declare(strict_types=1);

use App\Services\Checks\CheckStatus;
use App\Services\Checks\EpisodeChecks\GuidCheck;

// ──────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────

function buildItemWithGuid(?string $guid = 'ep-001-unique-id'): SimpleXMLElement
{
    $guidTag = $guid !== null
        ? "<guid isPermaLink=\"false\">{$guid}</guid>"
        : '';

    $xml = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">
          <channel>
            <item>
              <title>Test Episode</title>
              {$guidTag}
            </item>
          </channel>
        </rss>
        XML;

    return simplexml_load_string($xml)->channel->item;
}

function buildItemWithoutGuid(): SimpleXMLElement
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

function buildTwoItemsWithGuids(string $guid1, string $guid2): array
{
    $xml = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">
          <channel>
            <item>
              <title>Episode 1</title>
              <guid isPermaLink="false">{$guid1}</guid>
            </item>
            <item>
              <title>Episode 2</title>
              <guid isPermaLink="false">{$guid2}</guid>
            </item>
          </channel>
        </rss>
        XML;

    $feed = simplexml_load_string($xml);

    return [
        $feed->channel->item[0],
        $feed->channel->item[1],
    ];
}

// ──────────────────────────────────────────────────
// Metadata
// ──────────────────────────────────────────────────

test('name returns "Episode GUID"', function () {
    $check = new GuidCheck;

    expect($check->name())->toBe('Episode GUID');
});

test('severity returns "error"', function () {
    $check = new GuidCheck;

    expect($check->severity())->toBe('error');
});

test('implements CheckInterface', function () {
    $check = new GuidCheck;

    expect($check)->toBeInstanceOf(\App\Services\Checks\CheckInterface::class);
});

// ──────────────────────────────────────────────────
// Missing GUID — Fail
// ──────────────────────────────────────────────────

test('fails when guid tag is missing', function () {
    $check = new GuidCheck;
    $item = buildItemWithoutGuid();

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Fail)
        ->and($result->message)->toContain('missing')
        ->and($result->suggestion)->toContain('guid');
});

test('fails when guid is empty', function () {
    $check = new GuidCheck;
    $item = buildItemWithGuid('');

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Fail)
        ->and($result->message)->toContain('empty');
});

test('fails when guid is only whitespace', function () {
    $check = new GuidCheck;
    $item = buildItemWithGuid('   ');

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Fail)
        ->and($result->message)->toContain('empty');
});

// ──────────────────────────────────────────────────
// Duplicate GUID — Fail
// ──────────────────────────────────────────────────

test('fails when guid is duplicated across episodes', function () {
    $check = new GuidCheck;
    [$item1, $item2] = buildTwoItemsWithGuids('same-guid', 'same-guid');

    $result1 = $check->run($item1);
    $result2 = $check->run($item2);

    expect($result1->status)->toBe(CheckStatus::Pass)
        ->and($result2->status)->toBe(CheckStatus::Fail)
        ->and($result2->message)->toContain('Duplicate')
        ->and($result2->message)->toContain('same-guid');
});

// ──────────────────────────────────────────────────
// Valid GUID — Pass
// ──────────────────────────────────────────────────

test('passes with valid unique guid', function () {
    $check = new GuidCheck;
    $item = buildItemWithGuid('ep-001-unique-id');

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Pass)
        ->and($result->message)->toContain('present and unique')
        ->and($result->message)->toContain('ep-001-unique-id');
});

test('passes when multiple episodes have different guids', function () {
    $check = new GuidCheck;
    [$item1, $item2] = buildTwoItemsWithGuids('guid-1', 'guid-2');

    $result1 = $check->run($item1);
    $result2 = $check->run($item2);

    expect($result1->status)->toBe(CheckStatus::Pass)
        ->and($result2->status)->toBe(CheckStatus::Pass);
});

test('passes with URL-style guid', function () {
    $check = new GuidCheck;
    $item = buildItemWithGuid('https://example.com/episodes/ep-001');

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Pass);
});

test('passes with UUID-style guid', function () {
    $check = new GuidCheck;
    $item = buildItemWithGuid('550e8400-e29b-41d4-a716-446655440000');

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Pass);
});
