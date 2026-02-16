<?php

declare(strict_types=1);

namespace App\Services\Checks\EpisodeChecks;

use App\Services\Checks\CheckInterface;
use App\Services\Checks\CheckResult;
use DateTimeImmutable;
use SimpleXMLElement;

class PubDateCheck implements CheckInterface
{
    public function name(): string
    {
        return 'Episode Publication Date';
    }

    public function run(SimpleXMLElement $feed): CheckResult
    {
        if (! isset($feed->pubDate)) {
            return CheckResult::fail(
                'Episode is missing a <pubDate> tag.',
                'Add a <pubDate> tag with an RFC 2822 formatted date (e.g., <pubDate>Mon, 10 Feb 2025 08:00:00 +0000</pubDate>). This determines episode ordering and tells podcast apps when the episode was published.'
            );
        }

        $pubDate = trim((string) $feed->pubDate);

        if ($pubDate === '') {
            return CheckResult::fail(
                'Episode <pubDate> tag is empty.',
                'Provide a valid RFC 2822 date in your <pubDate> tag (e.g., "Mon, 10 Feb 2025 08:00:00 +0000").'
            );
        }

        $parsed = $this->parseRfc2822Date($pubDate);

        if ($parsed === null) {
            return CheckResult::warn(
                "Episode publication date is not valid RFC 2822 format: \"{$pubDate}\".",
                'Use a properly formatted RFC 2822 date (e.g., "Mon, 10 Feb 2025 08:00:00 +0000"). Invalid dates may cause episodes to appear out of order or be skipped by podcast directories.'
            );
        }

        $now = new DateTimeImmutable;

        if ($parsed > $now) {
            return CheckResult::warn(
                'Episode publication date is in the future.',
                'Some podcast apps may not display episodes with future publication dates. If you\'re scheduling a release, make sure your hosting platform handles scheduled publishing correctly.'
            );
        }

        return CheckResult::pass(
            "Episode publication date is valid: \"{$pubDate}\"."
        );
    }

    public function severity(): string
    {
        return 'error';
    }

    /**
     * Attempt to parse a date string as RFC 2822.
     */
    private function parseRfc2822Date(string $date): ?DateTimeImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat(DateTimeImmutable::RFC2822, $date);

        if ($parsed !== false) {
            return $parsed;
        }

        // Try looser parsing — many feeds use slightly non-standard formats
        $timestamp = strtotime($date);

        if ($timestamp !== false) {
            return new DateTimeImmutable("@{$timestamp}");
        }

        return null;
    }
}
