<?php

declare(strict_types=1);

use App\Services\Checks\CheckStatus;
use App\Services\Checks\EpisodeChecks\DescriptionCheck;

// ──────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────

function buildItemWithDescription(?string $description = null, ?string $summary = null): SimpleXMLElement
{
    $descTag = $description !== null
        ? "<description>{$description}</description>"
        : '';

    $summaryTag = $summary !== null
        ? "<itunes:summary>{$summary}</itunes:summary>"
        : '';

    $xml = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">
          <channel>
            <item>
              <title>Test Episode</title>
              {$descTag}
              {$summaryTag}
            </item>
          </channel>
        </rss>
        XML;

    return simplexml_load_string($xml)->channel->item;
}

function buildItemWithoutDescription(): SimpleXMLElement
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

test('name returns "Episode Description"', function () {
    $check = new DescriptionCheck;

    expect($check->name())->toBe('Episode Description');
});

test('severity returns "warning"', function () {
    $check = new DescriptionCheck;

    expect($check->severity())->toBe('warning');
});

test('implements CheckInterface', function () {
    $check = new DescriptionCheck;

    expect($check)->toBeInstanceOf(\App\Services\Checks\CheckInterface::class);
});

// ──────────────────────────────────────────────────
// Missing Description — Fail
// ──────────────────────────────────────────────────

test('fails when both description and summary are missing', function () {
    $check = new DescriptionCheck;
    $item = buildItemWithoutDescription();

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Fail)
        ->and($result->message)->toContain('missing')
        ->and($result->suggestion)->toContain('description');
});

test('fails when description is empty and no summary', function () {
    $check = new DescriptionCheck;
    $item = buildItemWithDescription('', null);

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Fail)
        ->and($result->message)->toContain('missing');
});

test('fails when description is only whitespace', function () {
    $check = new DescriptionCheck;
    $item = buildItemWithDescription('   ', null);

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Fail);
});

// ──────────────────────────────────────────────────
// Short Description — Warn
// ──────────────────────────────────────────────────

test('warns when description is too short', function () {
    $check = new DescriptionCheck;
    $item = buildItemWithDescription('Short.');

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Warn)
        ->and($result->message)->toContain('too short')
        ->and($result->suggestion)->toContain('10');
});

test('warns when description is 9 characters', function () {
    $check = new DescriptionCheck;
    $item = buildItemWithDescription('123456789');

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Warn);
});

// ──────────────────────────────────────────────────
// Valid Description — Pass
// ──────────────────────────────────────────────────

test('passes with valid description', function () {
    $check = new DescriptionCheck;
    $item = buildItemWithDescription('In this episode, we cover the basics of podcast feed validation.');

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Pass)
        ->and($result->message)->toContain('present');
});

test('passes with description exactly at minimum length', function () {
    $check = new DescriptionCheck;
    $item = buildItemWithDescription(str_repeat('A', 10));

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Pass);
});

test('passes when only itunes:summary is present', function () {
    $check = new DescriptionCheck;
    $item = buildItemWithDescription(null, 'A great episode about technology and development trends.');

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Pass)
        ->and($result->message)->toContain('present');
});

test('prefers description over itunes:summary', function () {
    $check = new DescriptionCheck;
    $item = buildItemWithDescription(
        'This is the main episode description used for display.',
        'This is the itunes summary.'
    );

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Pass);
});
