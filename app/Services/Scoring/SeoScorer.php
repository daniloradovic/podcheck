<?php

declare(strict_types=1);

namespace App\Services\Scoring;

use SimpleXMLElement;

class SeoScorer
{
    private const int TITLE_MIN_LENGTH = 20;

    private const int TITLE_OPTIMAL_MIN = 30;

    private const int TITLE_OPTIMAL_MAX = 60;

    private const int TITLE_MAX_LENGTH = 70;

    private const int DESC_MIN_LENGTH = 100;

    private const int DESC_OPTIMAL_MIN = 250;

    private const int DESC_OPTIMAL_MAX = 600;

    private const int DESC_MAX_LENGTH = 4000;

    private const int MAX_EPISODES = 10;

    private const string GENERIC_TITLE_PATTERN = '/^(ep\.?|episode|#)\s*\d+$/i';

    private const int KEYWORD_STUFFING_THRESHOLD = 3;

    /**
     * Weights for the overall score calculation.
     */
    private const int WEIGHT_TITLE = 30;

    private const int WEIGHT_DESCRIPTION = 30;

    private const int WEIGHT_EPISODES = 40;

    /**
     * Analyze the SEO quality of a podcast feed.
     */
    public function score(SimpleXMLElement $feed): SeoScore
    {
        $showTitle = $this->analyzeShowTitle($feed);
        $showDescription = $this->analyzeShowDescription($feed);
        $episodeTitles = $this->analyzeEpisodeTitles($feed);

        $overall = $this->calculateOverall(
            $showTitle['score'],
            $showDescription['score'],
            $episodeTitles['score'],
        );

        return new SeoScore(
            overall: $overall,
            details: [
                'show_title' => $showTitle,
                'show_description' => $showDescription,
                'episode_titles' => $episodeTitles,
            ],
        );
    }

    /**
     * Analyze the show title for SEO quality.
     *
     * @return array{score: int, status: string, message: string, suggestion: string|null, length: int|null}
     */
    private function analyzeShowTitle(SimpleXMLElement $feed): array
    {
        $title = $this->extractShowTitle($feed);

        if ($title === null || $title === '') {
            return [
                'score' => 0,
                'status' => 'fail',
                'message' => 'Show title is missing.',
                'suggestion' => 'Add a descriptive title to your podcast. A good title includes relevant keywords and clearly describes your show\'s topic.',
                'length' => null,
            ];
        }

        $length = mb_strlen($title);
        $score = $this->scoreTitleLength($length);

        if ($this->hasKeywordStuffing($title)) {
            $score = max(0, $score - 20);

            return [
                'score' => $score,
                'status' => 'warn',
                'message' => "Show title may contain keyword stuffing ({$length} chars): \"{$title}\".",
                'suggestion' => 'Avoid repeating the same words excessively in your title. A natural, readable title performs better in search results.',
                'length' => $length,
            ];
        }

        if ($length < self::TITLE_MIN_LENGTH) {
            return [
                'score' => $score,
                'status' => 'warn',
                'message' => "Show title is too short ({$length} chars): \"{$title}\".",
                'suggestion' => 'Aim for 30-60 characters. Include keywords that describe your podcast\'s topic to improve discoverability.',
                'length' => $length,
            ];
        }

        if ($length > self::TITLE_MAX_LENGTH) {
            return [
                'score' => $score,
                'status' => 'warn',
                'message' => "Show title is too long ({$length} chars): \"{$title}\".",
                'suggestion' => 'Keep your title under 70 characters. Long titles get truncated in podcast directories and look cluttered.',
                'length' => $length,
            ];
        }

        if ($length < self::TITLE_OPTIMAL_MIN) {
            return [
                'score' => $score,
                'status' => 'pass',
                'message' => "Show title is a bit short ({$length} chars): \"{$title}\".",
                'suggestion' => 'Consider expanding to 30-60 characters with descriptive keywords for better SEO.',
                'length' => $length,
            ];
        }

        if ($length > self::TITLE_OPTIMAL_MAX) {
            return [
                'score' => $score,
                'status' => 'pass',
                'message' => "Show title is slightly long ({$length} chars): \"{$title}\".",
                'suggestion' => 'Consider shortening to 30-60 characters. Concise titles are easier to read in podcast apps.',
                'length' => $length,
            ];
        }

        return [
            'score' => $score,
            'status' => 'pass',
            'message' => "Show title length is optimal ({$length} chars): \"{$title}\".",
            'suggestion' => null,
            'length' => $length,
        ];
    }

    /**
     * Analyze the show description for SEO quality.
     *
     * @return array{score: int, status: string, message: string, suggestion: string|null, length: int|null}
     */
    private function analyzeShowDescription(SimpleXMLElement $feed): array
    {
        $description = $this->extractShowDescription($feed);

        if ($description === null || $description === '') {
            return [
                'score' => 0,
                'status' => 'fail',
                'message' => 'Show description is missing.',
                'suggestion' => 'Add a description to your podcast. Use 250-600 characters that clearly explain what your show is about and include relevant keywords.',
                'length' => null,
            ];
        }

        $length = mb_strlen($description);
        $score = $this->scoreDescriptionLength($length);

        if ($length < self::DESC_MIN_LENGTH) {
            return [
                'score' => $score,
                'status' => 'warn',
                'message' => "Show description is too short ({$length} chars).",
                'suggestion' => 'Expand your description to at least 250 characters. Include what the show covers, who it\'s for, and relevant keywords for search discoverability.',
                'length' => $length,
            ];
        }

        if ($length > self::DESC_MAX_LENGTH) {
            return [
                'score' => $score,
                'status' => 'warn',
                'message' => "Show description is excessively long ({$length} chars).",
                'suggestion' => 'Shorten your description to under 4000 characters. Apple Podcasts may truncate very long descriptions. Put the most important information first.',
                'length' => $length,
            ];
        }

        if ($length < self::DESC_OPTIMAL_MIN) {
            return [
                'score' => $score,
                'status' => 'pass',
                'message' => "Show description could be more detailed ({$length} chars).",
                'suggestion' => 'Consider expanding to 250-600 characters. Describe your show\'s topics, audience, and what makes it unique.',
                'length' => $length,
            ];
        }

        if ($length > self::DESC_OPTIMAL_MAX) {
            return [
                'score' => $score,
                'status' => 'pass',
                'message' => "Show description is detailed ({$length} chars).",
                'suggestion' => null,
                'length' => $length,
            ];
        }

        return [
            'score' => $score,
            'status' => 'pass',
            'message' => "Show description length is optimal ({$length} chars).",
            'suggestion' => null,
            'length' => $length,
        ];
    }

    /**
     * Analyze episode titles for SEO patterns.
     *
     * @return array{score: int, status: string, message: string, suggestion: string|null, generic_count: int, total_count: int}
     */
    private function analyzeEpisodeTitles(SimpleXMLElement $feed): array
    {
        $titles = $this->extractEpisodeTitles($feed);
        $totalCount = count($titles);

        if ($totalCount === 0) {
            return [
                'score' => 100,
                'status' => 'pass',
                'message' => 'No episodes to analyze.',
                'suggestion' => null,
                'generic_count' => 0,
                'total_count' => 0,
            ];
        }

        $genericCount = 0;

        foreach ($titles as $title) {
            if (preg_match(self::GENERIC_TITLE_PATTERN, $title)) {
                $genericCount++;
            }
        }

        $descriptiveCount = $totalCount - $genericCount;
        $score = (int) round(($descriptiveCount / $totalCount) * 100);

        if ($genericCount === 0) {
            return [
                'score' => 100,
                'status' => 'pass',
                'message' => "All {$totalCount} episode titles are descriptive.",
                'suggestion' => null,
                'generic_count' => 0,
                'total_count' => $totalCount,
            ];
        }

        if ($genericCount === $totalCount) {
            return [
                'score' => $score,
                'status' => 'fail',
                'message' => "All {$totalCount} episode titles are generic (e.g., \"Episode 1\").",
                'suggestion' => 'Use descriptive episode titles that include topic keywords. For example, "How to Start Investing in 2025" performs much better than "Episode 12" in search results.',
                'generic_count' => $genericCount,
                'total_count' => $totalCount,
            ];
        }

        return [
            'score' => $score,
            'status' => 'warn',
            'message' => "{$genericCount} of {$totalCount} episode titles are generic.",
            'suggestion' => 'Replace generic titles like "Episode 5" with descriptive titles that include topic keywords. Descriptive titles improve discoverability in podcast search.',
            'generic_count' => $genericCount,
            'total_count' => $totalCount,
        ];
    }

    /**
     * Score title length on a 0-100 scale.
     */
    private function scoreTitleLength(int $length): int
    {
        if ($length === 0) {
            return 0;
        }

        if ($length < self::TITLE_MIN_LENGTH) {
            return 40;
        }

        if ($length < self::TITLE_OPTIMAL_MIN) {
            return 70;
        }

        if ($length <= self::TITLE_OPTIMAL_MAX) {
            return 100;
        }

        if ($length <= self::TITLE_MAX_LENGTH) {
            return 80;
        }

        return 50;
    }

    /**
     * Score description length on a 0-100 scale.
     */
    private function scoreDescriptionLength(int $length): int
    {
        if ($length === 0) {
            return 0;
        }

        if ($length < self::DESC_MIN_LENGTH) {
            return 30;
        }

        if ($length < self::DESC_OPTIMAL_MIN) {
            return 70;
        }

        if ($length <= self::DESC_OPTIMAL_MAX) {
            return 100;
        }

        if ($length <= self::DESC_MAX_LENGTH) {
            return 90;
        }

        return 60;
    }

    /**
     * Detect keyword stuffing in a title.
     *
     * Checks for words repeated more than the threshold number of times.
     */
    private function hasKeywordStuffing(string $title): bool
    {
        $words = str_word_count(mb_strtolower($title), 1);

        if (count($words) < self::KEYWORD_STUFFING_THRESHOLD) {
            return false;
        }

        $counts = array_count_values($words);

        foreach ($counts as $count) {
            if ($count >= self::KEYWORD_STUFFING_THRESHOLD) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate the weighted overall SEO score.
     */
    private function calculateOverall(int $titleScore, int $descriptionScore, int $episodeScore): int
    {
        $weighted = ($titleScore * self::WEIGHT_TITLE)
            + ($descriptionScore * self::WEIGHT_DESCRIPTION)
            + ($episodeScore * self::WEIGHT_EPISODES);

        return (int) round($weighted / (self::WEIGHT_TITLE + self::WEIGHT_DESCRIPTION + self::WEIGHT_EPISODES));
    }

    /**
     * Extract the show title from the feed.
     */
    private function extractShowTitle(SimpleXMLElement $feed): ?string
    {
        $channel = $this->getChannel($feed);

        if ($channel === null) {
            return null;
        }

        if (isset($channel->title)) {
            $value = trim((string) $channel->title);

            return $value !== '' ? $value : null;
        }

        return null;
    }

    /**
     * Extract the show description from the feed.
     *
     * Prefers <description>, falls back to <itunes:summary>.
     */
    private function extractShowDescription(SimpleXMLElement $feed): ?string
    {
        $channel = $this->getChannel($feed);

        if ($channel === null) {
            return null;
        }

        if (isset($channel->description)) {
            $value = trim((string) $channel->description);

            if ($value !== '') {
                return $value;
            }
        }

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
     * Extract up to MAX_EPISODES episode titles from the feed.
     *
     * @return list<string>
     */
    private function extractEpisodeTitles(SimpleXMLElement $feed): array
    {
        $titles = [];

        if ($feed->getName() === 'rss' && isset($feed->channel->item)) {
            foreach ($feed->channel->item as $item) {
                $title = $this->extractItemTitle($item, $feed);

                if ($title !== null) {
                    $titles[] = $title;
                }

                if (count($titles) >= self::MAX_EPISODES) {
                    break;
                }
            }
        }

        if ($feed->getName() === 'feed') {
            $namespaces = $feed->getNamespaces(true);
            $children = isset($namespaces['']) ? $feed->children($namespaces['']) : $feed;

            if (isset($children->entry)) {
                foreach ($children->entry as $entry) {
                    if (isset($entry->title)) {
                        $value = trim((string) $entry->title);

                        if ($value !== '') {
                            $titles[] = $value;
                        }
                    }

                    if (count($titles) >= self::MAX_EPISODES) {
                        break;
                    }
                }
            }
        }

        return $titles;
    }

    /**
     * Extract a title from an episode item.
     *
     * Prefers <itunes:title>, falls back to <title>.
     */
    private function extractItemTitle(SimpleXMLElement $item, SimpleXMLElement $feed): ?string
    {
        $namespaces = $feed->getNamespaces(true);
        $itunesNs = $namespaces['itunes'] ?? 'http://www.itunes.com/dtds/podcast-1.0.dtd';
        $itunes = $item->children($itunesNs);

        if (isset($itunes->title)) {
            $value = trim((string) $itunes->title);

            if ($value !== '') {
                return $value;
            }
        }

        if (isset($item->title)) {
            $value = trim((string) $item->title);

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
