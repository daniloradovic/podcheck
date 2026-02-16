<?php

declare(strict_types=1);

use App\Services\Checks\CheckStatus;
use App\Services\Checks\EpisodeChecks\EnclosureCheck;
use Illuminate\Http\Client\Factory as HttpFactory;

// ──────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────

function buildItemWithEnclosure(
    ?string $url = 'https://example.com/episode.mp3',
    ?string $type = 'audio/mpeg',
    ?string $length = '12345678',
): SimpleXMLElement {
    $attrs = [];

    if ($url !== null) {
        $attrs[] = "url=\"{$url}\"";
    }

    if ($type !== null) {
        $attrs[] = "type=\"{$type}\"";
    }

    if ($length !== null) {
        $attrs[] = "length=\"{$length}\"";
    }

    $attrsStr = implode(' ', $attrs);
    $enclosureTag = $attrsStr !== '' ? "<enclosure {$attrsStr}/>" : '<enclosure/>';

    $xml = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">
          <channel>
            <item>
              <title>Test Episode</title>
              {$enclosureTag}
            </item>
          </channel>
        </rss>
        XML;

    return simplexml_load_string($xml)->channel->item;
}

function buildItemWithoutEnclosure(): SimpleXMLElement
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

function createEnclosureCheck(array $httpFakes = []): EnclosureCheck
{
    $http = new HttpFactory;
    $http->fake($httpFakes);

    return new EnclosureCheck($http);
}

// ──────────────────────────────────────────────────
// Metadata
// ──────────────────────────────────────────────────

test('name returns "Episode Enclosure"', function () {
    $check = createEnclosureCheck();

    expect($check->name())->toBe('Episode Enclosure');
});

test('severity returns "error"', function () {
    $check = createEnclosureCheck();

    expect($check->severity())->toBe('error');
});

test('implements CheckInterface', function () {
    $check = createEnclosureCheck();

    expect($check)->toBeInstanceOf(\App\Services\Checks\CheckInterface::class);
});

// ──────────────────────────────────────────────────
// Missing Enclosure — Fail
// ──────────────────────────────────────────────────

test('fails when enclosure tag is missing', function () {
    $check = createEnclosureCheck();
    $item = buildItemWithoutEnclosure();

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Fail)
        ->and($result->message)->toContain('missing')
        ->and($result->suggestion)->toContain('enclosure');
});

test('fails when enclosure URL is empty', function () {
    $check = createEnclosureCheck();
    $item = buildItemWithEnclosure(url: '', type: 'audio/mpeg');

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Fail)
        ->and($result->message)->toContain('missing a URL');
});

test('fails when enclosure URL is missing', function () {
    $check = createEnclosureCheck();
    $item = buildItemWithEnclosure(url: null, type: 'audio/mpeg');

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Fail)
        ->and($result->message)->toContain('missing a URL');
});

// ──────────────────────────────────────────────────
// Missing or Invalid Type — Warn
// ──────────────────────────────────────────────────

test('warns when enclosure type is missing', function () {
    $check = createEnclosureCheck();
    $item = buildItemWithEnclosure(type: null);

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Warn)
        ->and($result->message)->toContain('missing a type');
});

test('warns when enclosure type is empty', function () {
    $check = createEnclosureCheck();
    $item = buildItemWithEnclosure(type: '');

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Warn)
        ->and($result->message)->toContain('missing a type');
});

test('warns when enclosure type is unrecognized', function () {
    $check = createEnclosureCheck([
        'example.com/*' => HttpFactory::response('', 200),
    ]);
    $item = buildItemWithEnclosure(type: 'application/pdf');

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Warn)
        ->and($result->message)->toContain('unrecognized media type')
        ->and($result->message)->toContain('application/pdf');
});

// ──────────────────────────────────────────────────
// Unreachable URL — Warn
// ──────────────────────────────────────────────────

test('warns when enclosure URL is unreachable', function () {
    $check = createEnclosureCheck([
        'example.com/*' => HttpFactory::response('', 404),
    ]);
    $item = buildItemWithEnclosure();

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Warn)
        ->and($result->message)->toContain('could not be reached');
});

test('warns when HEAD request throws an exception', function () {
    $check = createEnclosureCheck([
        'example.com/*' => fn () => throw new \Exception('Connection timeout'),
    ]);
    $item = buildItemWithEnclosure();

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Warn)
        ->and($result->message)->toContain('could not be reached');
});

// ──────────────────────────────────────────────────
// Valid Enclosure — Pass
// ──────────────────────────────────────────────────

test('passes with valid audio/mpeg enclosure', function () {
    $check = createEnclosureCheck([
        'example.com/*' => HttpFactory::response('', 200),
    ]);
    $item = buildItemWithEnclosure();

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Pass)
        ->and($result->message)->toContain('valid')
        ->and($result->message)->toContain('audio/mpeg');
});

test('passes with audio/x-m4a type', function () {
    $check = createEnclosureCheck([
        'example.com/*' => HttpFactory::response('', 200),
    ]);
    $item = buildItemWithEnclosure(type: 'audio/x-m4a');

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Pass);
});

test('passes with audio/mp4 type', function () {
    $check = createEnclosureCheck([
        'example.com/*' => HttpFactory::response('', 200),
    ]);
    $item = buildItemWithEnclosure(type: 'audio/mp4');

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Pass);
});

test('passes with video/mp4 type for video podcasts', function () {
    $check = createEnclosureCheck([
        'example.com/*' => HttpFactory::response('', 200),
    ]);
    $item = buildItemWithEnclosure(type: 'video/mp4');

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Pass);
});

test('type matching is case-insensitive', function () {
    $check = createEnclosureCheck([
        'example.com/*' => HttpFactory::response('', 200),
    ]);
    $item = buildItemWithEnclosure(type: 'Audio/MPEG');

    $result = $check->run($item);

    expect($result->status)->toBe(CheckStatus::Pass);
});
