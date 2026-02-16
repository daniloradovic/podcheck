<?php

declare(strict_types=1);

namespace App\Services\Checks\EpisodeChecks;

use App\Services\Checks\CheckInterface;
use App\Services\Checks\CheckResult;
use Illuminate\Http\Client\Factory as HttpFactory;
use SimpleXMLElement;

class EnclosureCheck implements CheckInterface
{
    private const int HEAD_TIMEOUT_SECONDS = 5;

    private const array VALID_AUDIO_TYPES = [
        'audio/mpeg',
        'audio/x-m4a',
        'audio/mp4',
        'audio/ogg',
        'audio/wav',
        'audio/aac',
        'audio/x-wav',
        'video/mp4',
        'video/x-m4v',
    ];

    public function __construct(
        private readonly HttpFactory $http,
    ) {}

    public function name(): string
    {
        return 'Episode Enclosure';
    }

    public function run(SimpleXMLElement $feed): CheckResult
    {
        if (! isset($feed->enclosure)) {
            return CheckResult::fail(
                'Episode is missing an <enclosure> tag.',
                'Add an <enclosure url="https://example.com/episode.mp3" length="12345678" type="audio/mpeg"/> tag to each episode item. This is required for podcast players to find and play your audio file.'
            );
        }

        $attributes = $feed->enclosure->attributes();

        $url = isset($attributes['url']) ? trim((string) $attributes['url']) : '';
        $type = isset($attributes['type']) ? trim((string) $attributes['type']) : '';

        if ($url === '') {
            return CheckResult::fail(
                'Enclosure tag is missing a URL.',
                'Add a url attribute to your <enclosure> tag pointing to the audio file (e.g., url="https://example.com/episode.mp3").'
            );
        }

        if ($type === '') {
            return CheckResult::warn(
                'Enclosure is missing a type attribute.',
                'Add a type attribute to your <enclosure> tag (e.g., type="audio/mpeg" for MP3 files). This helps podcast players identify the media format.'
            );
        }

        $normalizedType = strtolower($type);

        if (! in_array($normalizedType, self::VALID_AUDIO_TYPES, true)) {
            return CheckResult::warn(
                "Enclosure has an unrecognized media type: \"{$type}\".",
                'Use a standard podcast media type such as "audio/mpeg" (MP3), "audio/x-m4a" (M4A), or "audio/mp4". Non-standard types may cause playback issues in some podcast apps.'
            );
        }

        $reachable = $this->isUrlReachable($url);

        if (! $reachable) {
            return CheckResult::warn(
                'Enclosure URL could not be reached.',
                'Make sure the audio file URL is publicly accessible. A HEAD request to the URL failed, which may indicate the file is missing or the server is blocking requests.'
            );
        }

        return CheckResult::pass(
            "Enclosure is valid (type: {$type})."
        );
    }

    public function severity(): string
    {
        return 'error';
    }

    /**
     * Check if the enclosure URL is reachable via a HEAD request.
     */
    private function isUrlReachable(string $url): bool
    {
        try {
            $response = $this->http
                ->timeout(self::HEAD_TIMEOUT_SECONDS)
                ->head($url);

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }
}
