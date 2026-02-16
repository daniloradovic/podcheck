<?php

declare(strict_types=1);

namespace App\Services\Checks\EpisodeChecks;

use App\Services\Checks\CheckInterface;
use App\Services\Checks\CheckResult;
use SimpleXMLElement;

class DescriptionCheck implements CheckInterface
{
    private const int MIN_LENGTH = 10;

    public function name(): string
    {
        return 'Episode Description';
    }

    public function run(SimpleXMLElement $feed): CheckResult
    {
        $description = $this->extractDescription($feed);

        if ($description === null) {
            return CheckResult::fail(
                'Episode is missing a description.',
                'Add a <description> tag to each episode with a summary of the episode content. Descriptions improve discoverability and help listeners decide whether to listen.'
            );
        }

        $length = mb_strlen($description);

        if ($length < self::MIN_LENGTH) {
            return CheckResult::warn(
                "Episode description is too short ({$length} characters).",
                'Write a description of at least '.self::MIN_LENGTH.' characters. Include a brief summary of the episode topic, key takeaways, or guest information to help listeners and improve SEO.'
            );
        }

        return CheckResult::pass(
            "Episode description is present ({$length} characters)."
        );
    }

    public function severity(): string
    {
        return 'warning';
    }

    /**
     * Extract the description from the episode item.
     *
     * Checks <description> first, then falls back to <itunes:summary>.
     */
    private function extractDescription(SimpleXMLElement $item): ?string
    {
        // Try <description> first (standard RSS)
        if (isset($item->description)) {
            $value = trim((string) $item->description);

            if ($value !== '') {
                return $value;
            }
        }

        // Fall back to <itunes:summary>
        $namespaces = $item->getNamespaces(true);
        $itunesNs = $namespaces['itunes'] ?? 'http://www.itunes.com/dtds/podcast-1.0.dtd';

        $itunes = $item->children($itunesNs);

        if (isset($itunes->summary)) {
            $value = trim((string) $itunes->summary);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}
