<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Checks\CheckInterface;
use App\Services\Checks\CheckResult;
use SimpleXMLElement;

class FeedValidator
{
    /** @var list<CheckInterface> */
    private array $channelChecks;

    /** @var list<CheckInterface> */
    private array $episodeChecks;

    private const int MAX_EPISODES = 10;

    /**
     * @param  list<CheckInterface>  $channelChecks  Checks that run against the channel element
     * @param  list<CheckInterface>  $episodeChecks  Checks that run against individual episode items
     */
    public function __construct(
        array $channelChecks = [],
        array $episodeChecks = [],
    ) {
        $this->channelChecks = $channelChecks;
        $this->episodeChecks = $episodeChecks;
    }

    /**
     * Run all registered checks against the given feed.
     *
     * Returns a structured array of results suitable for JSON serialization.
     *
     * @return array{channel: list<array>, episodes: list<array>}
     */
    public function validate(SimpleXMLElement $feed): array
    {
        return [
            'channel' => $this->runChannelChecks($feed),
            'episodes' => $this->runEpisodeChecks($feed),
        ];
    }

    /**
     * Run all channel-level checks against the feed.
     *
     * @return list<array{name: string, severity: string, status: string, message: string, suggestion: string|null}>
     */
    private function runChannelChecks(SimpleXMLElement $feed): array
    {
        $results = [];

        foreach ($this->channelChecks as $check) {
            $result = $check->run($feed);
            $results[] = $this->formatResult($check, $result);
        }

        return $results;
    }

    /**
     * Run all episode-level checks against up to MAX_EPISODES items.
     *
     * @return list<array{title: string, guid: string|null, results: list<array>}>
     */
    private function runEpisodeChecks(SimpleXMLElement $feed): array
    {
        if (count($this->episodeChecks) === 0) {
            return [];
        }

        $items = $this->getEpisodeItems($feed);

        if (count($items) === 0) {
            return [];
        }

        $episodeResults = [];

        foreach ($items as $index => $item) {
            $results = [];

            foreach ($this->episodeChecks as $check) {
                $result = $check->run($item);
                $results[] = $this->formatResult($check, $result);
            }

            $episodeResults[] = [
                'title' => $this->extractItemTitle($item, $index),
                'guid' => $this->extractItemGuid($item),
                'results' => $results,
            ];
        }

        return $episodeResults;
    }

    /**
     * Get up to MAX_EPISODES <item> elements from the feed.
     *
     * @return list<SimpleXMLElement>
     */
    private function getEpisodeItems(SimpleXMLElement $feed): array
    {
        $items = [];

        // RSS 2.0: <rss><channel><item>
        if ($feed->getName() === 'rss' && isset($feed->channel->item)) {
            foreach ($feed->channel->item as $item) {
                $items[] = $item;

                if (count($items) >= self::MAX_EPISODES) {
                    break;
                }
            }
        }

        // Atom: <feed><entry> (with default namespace handling)
        if ($feed->getName() === 'feed') {
            $namespaces = $feed->getNamespaces(true);
            $children = isset($namespaces['']) ? $feed->children($namespaces['']) : $feed;

            if (isset($children->entry)) {
                foreach ($children->entry as $entry) {
                    $items[] = $entry;

                    if (count($items) >= self::MAX_EPISODES) {
                        break;
                    }
                }
            }
        }

        return $items;
    }

    /**
     * Format a check result with its metadata for serialization.
     *
     * @return array{name: string, severity: string, status: string, message: string, suggestion: string|null}
     */
    private function formatResult(CheckInterface $check, CheckResult $result): array
    {
        return [
            'name' => $check->name(),
            'severity' => $check->severity(),
            ...$result->toArray(),
        ];
    }

    /**
     * Extract a title from an episode item for identification.
     */
    private function extractItemTitle(SimpleXMLElement $item, int $index): string
    {
        if (isset($item->title) && (string) $item->title !== '') {
            return (string) $item->title;
        }

        return 'Episode '.($index + 1);
    }

    /**
     * Extract the GUID from an episode item.
     */
    private function extractItemGuid(SimpleXMLElement $item): ?string
    {
        if (isset($item->guid) && (string) $item->guid !== '') {
            return (string) $item->guid;
        }

        return null;
    }

    /**
     * Get a summary of results across all checks.
     *
     * @param  array{channel: list<array>, episodes: list<array>}  $results
     * @return array{total: int, pass: int, warn: int, fail: int}
     */
    public static function summarize(array $results): array
    {
        $summary = ['total' => 0, 'pass' => 0, 'warn' => 0, 'fail' => 0];

        foreach ($results['channel'] as $result) {
            $summary['total']++;
            $summary[$result['status']]++;
        }

        foreach ($results['episodes'] as $episode) {
            foreach ($episode['results'] as $result) {
                $summary['total']++;
                $summary[$result['status']]++;
            }
        }

        return $summary;
    }
}
