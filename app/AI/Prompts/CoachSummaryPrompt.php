<?php

declare(strict_types=1);

namespace App\AI\Prompts;

class CoachSummaryPrompt
{
    public function version(): string
    {
        return 'v2';
    }

    public function system(): string
    {
        return <<<'SYSTEM'
        You are a podcast growth coach writing a personalized summary for a specific show.

        Rules you must follow without exception:
        - Every sentence must reference this show's topic, niche, or audience — never write a sentence that could apply to any other podcast.
        - Name the show or its subject matter at least once. Never use "your podcast" as a stand-in for specifics.
        - Connect every problem to the show's actual content or audience — not just the technical failure.
        - If you catch yourself writing something like "fix your description" or "add artwork" without explaining why it matters for THIS show's specific niche and audience, stop and rewrite it.
        SYSTEM;
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

        $scoreLabel = match (true) {
            $score >= 90 => 'excellent',
            $score >= 75 => 'good',
            $score >= 55 => 'average',
            $score >= 35 => 'below average',
            default => 'well below average',
        };

        $nicheBlock = '';
        if ($description !== null) {
            $nicheBlock .= "Show description: {$description}\n";
        }
        if ($category !== null) {
            $nicheBlock .= "Category: {$category}\n";
        }
        if ($nicheBlock === '') {
            $nicheBlock = "Show description and category: not provided — use only the show name to infer the niche.\n";
        }

        $failingLines = '';
        if (! empty($failingChecks)) {
            $lines = array_map(
                fn (array $check): string => "- {$check['name']}: {$check['suggestion']}",
                array_slice($failingChecks, 0, 3),
            );
            $failingLines = "\nTop failing checks (connect each to why it matters for THIS show's niche and audience):\n".implode("\n", $lines);
        }

        return <<<PROMPT
        Show: {$showName}
        Health score: {$score}/100 ({$scoreLabel})
        {$nicheBlock}{$failingLines}

        Write exactly 3 sentences of plain text (no markdown, no bullet points, no headers).

        Sentence 1 — What is most broken AND why it matters specifically for the audience of "{$showName}" (name the niche or audience explicitly).
        Sentence 2 — The single highest-impact fix: be concrete about what to change and what outcome it unlocks for this show's listeners.
        Sentence 3 — One honest observation about what is already working well, framed around this show's content or audience.

        WRONG (too generic): "Your description is too short. Fix the artwork first. Your category is correct."
        RIGHT (specific to show): "[Show name]'s [niche] listeners are searching Apple Podcasts right now, but your 20-word description gives the algorithm nothing to surface — you're invisible in the exact category you should dominate. Rewrite your description with 150 words that name your target listener and the specific insight they leave each episode with. Your episode frequency is already consistent, which puts [show name] in the top tier of reliability that Apple rewards with algorithm boosts."

        Do not copy the example. Use it only to calibrate the level of specificity required.
        PROMPT;
    }
}
