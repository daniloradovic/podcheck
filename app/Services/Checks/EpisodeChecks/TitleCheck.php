<?php

declare(strict_types=1);

namespace App\Services\Checks\EpisodeChecks;

use App\Services\Checks\CheckInterface;
use App\Services\Checks\CheckResult;
use SimpleXMLElement;

class TitleCheck implements CheckInterface
{
    /**
     * Pattern matching generic, non-descriptive titles like "Episode 47" or "Ep. 5".
     */
    private const string GENERIC_TITLE_PATTERN = '/^(ep\.?|episode|#)\s*\d+$/i';

    public function name(): string
    {
        return 'Episode Title';
    }

    public function run(SimpleXMLElement $feed): CheckResult
    {
        $title = $this->extractTitle($feed);

        if ($title === null) {
            return CheckResult::fail(
                'Episode is missing a title.',
                'Add a <title> or <itunes:title> tag to each episode. Descriptive titles help listeners decide which episodes to play and improve discoverability in search results.'
            );
        }

        if (preg_match(self::GENERIC_TITLE_PATTERN, $title)) {
            return CheckResult::warn(
                "Episode title is generic: \"{$title}\".",
                'Use a descriptive title instead of just a number (e.g., "How to Start a Podcast" instead of "Episode 5"). Descriptive titles improve SEO and help listeners find relevant episodes.'
            );
        }

        return CheckResult::pass(
            "Episode title is present: \"{$title}\"."
        );
    }

    public function severity(): string
    {
        return 'warning';
    }

    /**
     * Extract the title from the episode item.
     *
     * Prefers <itunes:title> for cleaner titles, falls back to <title>.
     */
    private function extractTitle(SimpleXMLElement $item): ?string
    {
        // Try <itunes:title> first (cleaner, without show name prefix)
        $namespaces = $item->getNamespaces(true);
        $itunesNs = $namespaces['itunes'] ?? 'http://www.itunes.com/dtds/podcast-1.0.dtd';

        $itunes = $item->children($itunesNs);

        if (isset($itunes->title)) {
            $value = trim((string) $itunes->title);

            if ($value !== '') {
                return $value;
            }
        }

        // Fall back to <title>
        if (isset($item->title)) {
            $value = trim((string) $item->title);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}
