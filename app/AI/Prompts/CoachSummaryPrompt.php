<?php

declare(strict_types=1);

namespace App\AI\Prompts;

class CoachSummaryPrompt
{
    public function version(): string
    {
        return 'v1';
    }

    public function system(): string
    {
        return 'You are a podcast growth coach. Be specific to this show. Never give generic advice.';
    }

    /**
     * Build the user prompt from report context.
     *
     * Expected context keys:
     *   - show_name: string
     *   - show_description: string|null
     *   - show_category: string|null
     *   - health_score: int
     *   - failing_checks: array<int, array{name: string, suggestion: string}>  (top 3)
     */
    public function build(array $context): string
    {
        $showName = $context['show_name'] ?? 'Unknown Show';
        $description = $context['show_description'] ?? null;
        $category = $context['show_category'] ?? null;
        $score = $context['health_score'] ?? 0;
        $failingChecks = $context['failing_checks'] ?? [];

        $descriptionLine = $description !== null
            ? "Show description: {$description}"
            : 'Show description: (not provided)';

        $categoryLine = $category !== null
            ? "Category: {$category}"
            : 'Category: (not provided)';

        $failingLines = '';
        if (! empty($failingChecks)) {
            $lines = array_map(
                fn (array $check): string => "- {$check['name']}: {$check['suggestion']}",
                array_slice($failingChecks, 0, 3)
            );
            $failingLines = "\nTop failing checks:\n".implode("\n", $lines);
        }

        return <<<PROMPT
        Podcast: {$showName}
        Health score: {$score}/100
        {$descriptionLine}
        {$categoryLine}{$failingLines}

        Write exactly 3 sentences of plain text (no markdown, no bullet points, no headers) that:
        1. Identify what is most broken and why it matters specifically for "{$showName}"
        2. Name the single highest-impact fix to do first
        3. Offer one encouraging observation about what is already working

        Be direct and specific to this show. Never give advice that could apply to any podcast.
        PROMPT;
    }
}
