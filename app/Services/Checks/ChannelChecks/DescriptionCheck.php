<?php

declare(strict_types=1);

namespace App\Services\Checks\ChannelChecks;

use App\Services\Checks\CheckInterface;
use App\Services\Checks\CheckResult;
use SimpleXMLElement;

class DescriptionCheck implements CheckInterface
{
    private const int MIN_LENGTH = 20;

    private const int WARN_MAX_LENGTH = 4000;

    public function name(): string
    {
        return 'Channel Description';
    }

    public function run(SimpleXMLElement $feed): CheckResult
    {
        $description = $this->extractDescription($feed);

        if ($description === null) {
            return CheckResult::fail(
                'Channel description is missing.',
                'Add a <description> and/or <itunes:summary> tag to your channel. A clear, keyword-rich description helps listeners discover your podcast in search results.'
            );
        }

        $length = mb_strlen($description);

        if ($length < self::MIN_LENGTH) {
            return CheckResult::warn(
                "Channel description is too short ({$length} characters).",
                'Write a description of at least '.self::MIN_LENGTH.' characters. A good podcast description is 1-2 paragraphs that explains what the show is about and who it\'s for.'
            );
        }

        if ($length > self::WARN_MAX_LENGTH) {
            return CheckResult::warn(
                "Channel description is very long ({$length} characters).",
                'Apple Podcasts may truncate descriptions over '.self::WARN_MAX_LENGTH.' characters. Consider shortening your description to keep the most important information visible.'
            );
        }

        return CheckResult::pass(
            "Channel description is present ({$length} characters)."
        );
    }

    public function severity(): string
    {
        return 'error';
    }

    /**
     * Extract the description from the feed's channel element.
     *
     * Checks both <description> and <itunes:summary>, preferring <description>
     * since it's the standard RSS element.
     */
    private function extractDescription(SimpleXMLElement $feed): ?string
    {
        $channel = $this->getChannel($feed);

        if ($channel === null) {
            return null;
        }

        // Try <description> first (standard RSS)
        if (isset($channel->description)) {
            $value = trim((string) $channel->description);

            if ($value !== '') {
                return $value;
            }
        }

        // Fall back to <itunes:summary>
        $namespaces = $feed->getNamespaces(true);
        $itunesNs = $namespaces['itunes'] ?? 'http://www.itunes.com/dtds/podcast-1.0.dtd';

        $itunes = $channel->children($itunesNs);

        if (isset($itunes->summary)) {
            $value = trim((string) $itunes->summary);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * Get the channel element from the feed (RSS 2.0 or Atom).
     */
    private function getChannel(SimpleXMLElement $feed): ?SimpleXMLElement
    {
        if ($feed->getName() === 'rss' && isset($feed->channel)) {
            return $feed->channel;
        }

        if ($feed->getName() === 'feed') {
            return $feed;
        }

        return null;
    }
}
