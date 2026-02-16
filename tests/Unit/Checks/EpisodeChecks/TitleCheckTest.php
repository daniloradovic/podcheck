<?php

declare(strict_types=1);

use App\Services\Checks\CheckStatus;
use App\Services\Checks\EpisodeChecks\TitleCheck;

// ──────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────

function buildItemWithTitle(?string $title = null, ?string $itunesTitle = null): SimpleXMLElement
{
    $titleTag = $title !== null
        ? "<title>{$title}</title>"
        : '';

    $itunesTitleTag = $itunesTitle !== null
        ? "<itunes:title>{$itunesTitle}</itunes:title>"
        : '';

    $xml = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">
          <channel>
            <item>
              {$titleTag}
              {$itunesTitleTag}
            </item>
          </channel>
        </rss>
        XML;

    return simplexml_load_string($xml)->channel->item;
}

function buildItemWithoutTitle(): SimpleXMLElement
{
    $xml = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">
          <channel>
            <item>
              <description>An episode with no title.</description>
            </item>
          </channel>
        </rss>
        XML;

    return simplexml_load_string($xml)->channel->item;
}

// ──────────────────────────────────────────────────
// Metadata
// ──────────────────────────────────────────────────

test('name returns "Episode Title"', function () {
    $check = new TitleCheck;

    expect($check->name())->toBe('Episode Title');
});

test('severity returns "warning"', function () {
    $check = new TitleCheck;

    expect($check->severity())->toBe('warning');
});

test('implements CheckInterface', function () {
    $check = new TitleCheck;

    expect($check)->toBeInstanceOf(\App\Services\Checks\CheckInterface::class);
});

// ──────────────────────────────────────────────────
// Missing Title — Fail
// ──────────────────────────────────────────────────

test('fails when both title and itunes:title are missing', function () {
    $check = new TitleCheck;
    $item = buildItemWithoutTitle();

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Fail)
        ->and($result->message)->toContain('missing')
        ->and($result->suggestion)->toContain('title');
});

test('fails when title is empty', function () {
    $check = new TitleCheck;
    $item = buildItemWithTitle('');

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Fail)
        ->and($result->message)->toContain('missing');
});

test('fails when both title and itunes:title are empty', function () {
    $check = new TitleCheck;
    $item = buildItemWithTitle('', '');

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Fail);
});

test('fails when title is only whitespace', function () {
    $check = new TitleCheck;
    $item = buildItemWithTitle('   ');

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Fail);
});

// ──────────────────────────────────────────────────
// Generic Title — Warn
// ──────────────────────────────────────────────────

test('warns when title is just "Episode N"', function () {
    $check = new TitleCheck;
    $item = buildItemWithTitle('Episode 47');

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Warn)
        ->and($result->message)->toContain('generic')
        ->and($result->suggestion)->toContain('descriptive');
});

test('warns when title is "Ep. N"', function () {
    $check = new TitleCheck;
    $item = buildItemWithTitle('Ep. 5');

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Warn)
        ->and($result->message)->toContain('generic');
});

test('warns when title is "Ep N" without period', function () {
    $check = new TitleCheck;
    $item = buildItemWithTitle('Ep 12');

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Warn);
});

test('warns when title is "#N"', function () {
    $check = new TitleCheck;
    $item = buildItemWithTitle('#42');

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Warn);
});

test('warns on generic title case-insensitively', function () {
    $check = new TitleCheck;
    $item = buildItemWithTitle('EPISODE 10');

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Warn);
});

// ──────────────────────────────────────────────────
// Valid Title — Pass
// ──────────────────────────────────────────────────

test('passes with descriptive title', function () {
    $check = new TitleCheck;
    $item = buildItemWithTitle('How to Start a Podcast in 2025');

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Pass)
        ->and($result->message)->toContain('present')
        ->and($result->message)->toContain('How to Start a Podcast in 2025');
});

test('passes when title has episode number plus description', function () {
    $check = new TitleCheck;
    $item = buildItemWithTitle('Episode 5: How to Start a Podcast');

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Pass);
});

test('prefers itunes:title over title', function () {
    $check = new TitleCheck;
    $item = buildItemWithTitle('Show Name - Episode 1', 'Getting Started');

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Pass)
        ->and($result->message)->toContain('Getting Started');
});

test('falls back to title when itunes:title is missing', function () {
    $check = new TitleCheck;
    $item = buildItemWithTitle('A Great Episode About Testing');

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Pass)
        ->and($result->message)->toContain('A Great Episode About Testing');
});

test('falls back to title when itunes:title is empty', function () {
    $check = new TitleCheck;
    $item = buildItemWithTitle('Fallback Title', '');

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Pass)
        ->and($result->message)->toContain('Fallback Title');
});
