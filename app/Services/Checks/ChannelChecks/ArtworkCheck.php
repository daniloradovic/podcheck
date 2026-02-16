<?php

declare(strict_types=1);

namespace App\Services\Checks\ChannelChecks;

use App\Services\Checks\CheckInterface;
use App\Services\Checks\CheckResult;
use Illuminate\Http\Client\Factory as HttpFactory;
use SimpleXMLElement;

class ArtworkCheck implements CheckInterface
{
    private const int MIN_DIMENSION = 1400;

    private const int MAX_DIMENSION = 3000;

    private const int HEAD_TIMEOUT_SECONDS = 5;

    private const array ALLOWED_CONTENT_TYPES = [
        'image/jpeg',
        'image/png',
    ];

    public function __construct(
        private readonly HttpFactory $http,
    ) {}

    public function name(): string
    {
        return 'Podcast Artwork';
    }

    public function run(SimpleXMLElement $feed): CheckResult
    {
        $imageUrl = $this->extractImageUrl($feed);

        if ($imageUrl === null) {
            return CheckResult::fail(
                'iTunes artwork image is missing.',
                'Add an <itunes:image href="https://example.com/artwork.jpg"/> tag to your channel with a URL to your podcast artwork (1400×1400 to 3000×3000 pixels, JPEG or PNG).'
            );
        }

        return $this->validateImage($imageUrl);
    }

    public function severity(): string
    {
        return 'error';
    }

    /**
     * Extract the artwork image URL from the feed's channel element.
     */
    private function extractImageUrl(SimpleXMLElement $feed): ?string
    {
        $namespaces = $feed->getNamespaces(true);
        $itunesNs = $namespaces['itunes'] ?? 'http://www.itunes.com/dtds/podcast-1.0.dtd';

        // RSS 2.0: <rss><channel><itunes:image href="..."/>
        if ($feed->getName() === 'rss' && isset($feed->channel)) {
            $itunes = $feed->channel->children($itunesNs);

            if (isset($itunes->image)) {
                $href = (string) $itunes->image->attributes()['href'];

                return $href !== '' ? $href : null;
            }
        }

        // Atom: <feed><itunes:image href="..."/>
        if ($feed->getName() === 'feed') {
            $itunes = $feed->children($itunesNs);

            if (isset($itunes->image)) {
                $href = (string) $itunes->image->attributes()['href'];

                return $href !== '' ? $href : null;
            }
        }

        return null;
    }

    /**
     * Validate the image at the given URL for content type and dimensions.
     */
    private function validateImage(string $imageUrl): CheckResult
    {
        $contentType = $this->fetchContentType($imageUrl);

        if ($contentType !== null && ! $this->isAllowedContentType($contentType)) {
            return CheckResult::fail(
                "Artwork image is not a supported format (detected: {$contentType}).",
                'Apple Podcasts requires artwork in JPEG or PNG format. Convert your image to one of these formats.'
            );
        }

        $dimensions = $this->fetchDimensions($imageUrl);

        if ($dimensions === null) {
            return CheckResult::warn(
                'Artwork image URL is present but could not verify dimensions.',
                'Make sure your artwork image is accessible and between 1400×1400 and 3000×3000 pixels.'
            );
        }

        [$width, $height] = $dimensions;

        if ($width !== $height) {
            return CheckResult::warn(
                "Artwork is not square ({$width}×{$height}).",
                'Apple Podcasts requires square artwork. Resize your image to have equal width and height (e.g., 3000×3000).'
            );
        }

        if ($width < self::MIN_DIMENSION || $height < self::MIN_DIMENSION) {
            return CheckResult::warn(
                "Artwork is too small ({$width}×{$height}).",
                'Apple Podcasts requires artwork to be at least 1400×1400 pixels. Resize your image to at least 1400×1400 (3000×3000 recommended).'
            );
        }

        if ($width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
            return CheckResult::warn(
                "Artwork is too large ({$width}×{$height}).",
                'Apple Podcasts recommends artwork no larger than 3000×3000 pixels. Resize your image to 3000×3000 or smaller.'
            );
        }

        return CheckResult::pass(
            "Artwork is valid ({$width}×{$height}, {$this->formatContentType($contentType)})."
        );
    }

    /**
     * Fetch the Content-Type of the image via a HEAD request.
     */
    private function fetchContentType(string $imageUrl): ?string
    {
        try {
            $response = $this->http
                ->timeout(self::HEAD_TIMEOUT_SECONDS)
                ->head($imageUrl);

            if ($response->successful()) {
                $contentType = $response->header('Content-Type');

                // Strip charset or other parameters (e.g., "image/jpeg; charset=utf-8")
                if ($contentType !== null && $contentType !== '') {
                    return strtolower(explode(';', $contentType)[0]);
                }
            }
        } catch (\Throwable) {
            // Swallow — we'll handle this in the dimensions check
        }

        return null;
    }

    /**
     * Fetch image dimensions using getimagesize().
     *
     * @return array{0: int, 1: int}|null Width and height, or null on failure
     */
    private function fetchDimensions(string $imageUrl): ?array
    {
        try {
            $size = @getimagesize($imageUrl);

            if ($size !== false && $size[0] > 0 && $size[1] > 0) {
                return [$size[0], $size[1]];
            }
        } catch (\Throwable) {
            // Swallow — unreachable image
        }

        return null;
    }

    /**
     * Check if the content type is an allowed image format.
     */
    private function isAllowedContentType(string $contentType): bool
    {
        return in_array($contentType, self::ALLOWED_CONTENT_TYPES, true);
    }

    /**
     * Format the content type for display.
     */
    private function formatContentType(?string $contentType): string
    {
        return match ($contentType) {
            'image/jpeg' => 'JPEG',
            'image/png' => 'PNG',
            default => $contentType ?? 'unknown format',
        };
    }
}
