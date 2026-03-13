<?php

declare(strict_types=1);

namespace App\AI;

use Anthropic\Client as AnthropicClient;
use Anthropic\Messages\TextBlock;
use App\AI\Prompts\CoachSummaryPrompt;
use App\Models\FeedReport;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

class PodcastCoachService
{
    public function __construct(
        private readonly AnthropicClient $client,
        private readonly CoachSummaryPrompt $prompt,
        private readonly CacheRepository $cache,
    ) {}

    public function getSummary(FeedReport $report): ?string
    {
        $showTitle = (string) ($report->feed_title ?? '');
        $failingChecks = $this->extractFailingChecks($report);
        $cacheKey = $this->buildCacheKey($showTitle, $failingChecks);

        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return (string) $cached;
        }

        $context = $this->buildContext($report, $failingChecks);
        $summary = $this->callAnthropicApi(
            messages: [['role' => 'user', 'content' => $this->prompt->build($context)]],
            system: $this->prompt->system(),
        );

        if ($summary !== null) {
            $this->cache->put($cacheKey, $summary, (int) config('ai.cache_ttl'));
        }

        return $summary;
    }

    /**
     * Call the Anthropic API and return the text response.
     *
     * Returns null on any failure so the caller can degrade gracefully.
     *
     * @param  list<array{role: string, content: string}>  $messages
     */
    protected function callAnthropicApi(array $messages, string $system): ?string
    {
        try {
            $response = $this->client->messages->create(
                maxTokens: (int) config('ai.anthropic.max_tokens', 300),
                messages: $messages,
                model: (string) config('ai.anthropic.model', 'claude-haiku-4-5'),
                system: $system,
            );

            foreach ($response->content as $block) {
                if ($block instanceof TextBlock) {
                    return $block->text;
                }
            }

            return null;
        } catch (\Throwable $e) {
            logger()->error('PodcastCoachService API failure', [
                'message' => $e->getMessage(),
                'class' => $e::class,
            ]);

            return null;
        }
    }

    /**
     * Extract the top 3 failing channel checks from the report.
     *
     * @return list<array{name: string, suggestion: string}>
     */
    private function extractFailingChecks(FeedReport $report): array
    {
        $channel = $report->results_json['channel'] ?? [];

        $failing = array_filter(
            $channel,
            fn (array $check): bool => ($check['status'] ?? '') === 'fail',
        );

        return array_values(array_map(
            fn (array $check): array => [
                'name' => (string) $check['name'],
                'suggestion' => (string) ($check['suggestion'] ?? ''),
            ],
            array_slice(array_values($failing), 0, 3),
        ));
    }

    /**
     * Build a stable cache key from the show title and failing check names.
     *
     * Including the prompt version in the key means bumping the version
     * auto-invalidates all cached responses without a manual cache flush.
     *
     * @param  list<array{name: string, suggestion: string}>  $failingChecks
     */
    private function buildCacheKey(string $showTitle, array $failingChecks): string
    {
        $checkNames = implode('', array_column($failingChecks, 'name'));
        $hash = md5($showTitle.$checkNames);

        return "coach:{$this->prompt->version()}:{$hash}";
    }

    /**
     * Assemble the context array for the prompt builder.
     *
     * @param  list<array{name: string, suggestion: string}>  $failingChecks
     * @return array{show_name: string, show_description: string|null, show_category: string|null, health_score: int, failing_checks: list<array{name: string, suggestion: string}>}
     */
    private function buildContext(FeedReport $report, array $failingChecks): array
    {
        $metadata = $report->results_json['metadata'] ?? [];

        return [
            'show_name' => (string) ($report->feed_title ?? 'Unknown Show'),
            'show_description' => isset($metadata['show_description']) && $metadata['show_description'] !== null
                ? (string) $metadata['show_description']
                : null,
            'show_category' => isset($metadata['show_category']) && $metadata['show_category'] !== null
                ? (string) $metadata['show_category']
                : null,
            'health_score' => (int) ($report->results_json['health_score']['overall'] ?? 0),
            'failing_checks' => $failingChecks,
        ];
    }
}
