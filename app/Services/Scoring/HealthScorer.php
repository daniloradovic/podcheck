<?php

declare(strict_types=1);

namespace App\Services\Scoring;

class HealthScorer
{
    /**
     * Map each check name to a scoring category.
     *
     * Compliance = Apple/Spotify directory requirements
     * Technical  = Feed structure, format, metadata
     * Best Practices = Quality and recommendations
     */
    private const array CATEGORY_MAP = [
        // Channel checks
        'Podcast Artwork' => 'compliance',
        'iTunes Category' => 'compliance',
        'Explicit Tag' => 'compliance',
        'Owner Email' => 'compliance',
        'iTunes Author' => 'best_practices',
        'Language Tag' => 'technical',
        'Website Link' => 'technical',
        'Channel Description' => 'best_practices',

        // Episode checks
        'Episode Enclosure' => 'compliance',
        'Episode GUID' => 'technical',
        'Episode Publication Date' => 'technical',
        'Episode Duration' => 'technical',
        'Episode Title' => 'best_practices',
        'Episode Description' => 'best_practices',
    ];

    private const int FAIL_DEDUCTION = 10;

    private const int WARN_DEDUCTION = 3;

    private const int MIN_SCORE = 0;

    private const int MAX_SCORE = 100;

    private const string DEFAULT_CATEGORY = 'best_practices';

    /**
     * Calculate health scores from validation results.
     *
     * @param  array{channel: list<array>, episodes: list<array>}  $results  Output from FeedValidator::validate()
     */
    public function score(array $results): HealthScore
    {
        $allResults = $this->collectAllResults($results);

        $overallScore = $this->calculateScore($allResults);
        $categoryScores = $this->calculateCategoryScores($allResults);

        return new HealthScore(
            overall: $overallScore,
            categories: $categoryScores,
        );
    }

    /**
     * Flatten all check results (channel + episode) into a single list.
     *
     * @return list<array{name: string, severity: string, status: string, message: string, suggestion: string|null}>
     */
    private function collectAllResults(array $results): array
    {
        $all = [];

        foreach ($results['channel'] ?? [] as $result) {
            $all[] = $result;
        }

        foreach ($results['episodes'] ?? [] as $episode) {
            foreach ($episode['results'] ?? [] as $result) {
                $all[] = $result;
            }
        }

        return $all;
    }

    /**
     * Calculate a score from 0-100 based on check results.
     *
     * Starts at 100 and deducts points: -10 per fail, -3 per warn.
     *
     * @param  list<array>  $results
     */
    private function calculateScore(array $results): int
    {
        if (count($results) === 0) {
            return self::MAX_SCORE;
        }

        $deductions = 0;

        foreach ($results as $result) {
            $deductions += match ($result['status']) {
                'fail' => self::FAIL_DEDUCTION,
                'warn' => self::WARN_DEDUCTION,
                default => 0,
            };
        }

        return max(self::MIN_SCORE, self::MAX_SCORE - $deductions);
    }

    /**
     * Calculate per-category scores and counts.
     *
     * @param  list<array>  $results
     * @return array<string, array{score: int, pass: int, warn: int, fail: int, total: int}>
     */
    private function calculateCategoryScores(array $results): array
    {
        $grouped = $this->groupByCategory($results);

        $scores = [];

        foreach ($grouped as $category => $categoryResults) {
            $pass = 0;
            $warn = 0;
            $fail = 0;

            foreach ($categoryResults as $result) {
                match ($result['status']) {
                    'pass' => $pass++,
                    'warn' => $warn++,
                    'fail' => $fail++,
                    default => null,
                };
            }

            $scores[$category] = [
                'score' => $this->calculateScore($categoryResults),
                'pass' => $pass,
                'warn' => $warn,
                'fail' => $fail,
                'total' => $pass + $warn + $fail,
            ];
        }

        // Ensure all three categories always appear, even if empty
        foreach (['compliance', 'technical', 'best_practices'] as $category) {
            if (! isset($scores[$category])) {
                $scores[$category] = [
                    'score' => self::MAX_SCORE,
                    'pass' => 0,
                    'warn' => 0,
                    'fail' => 0,
                    'total' => 0,
                ];
            }
        }

        // Sort by canonical order
        $ordered = [];
        foreach (['compliance', 'technical', 'best_practices'] as $category) {
            $ordered[$category] = $scores[$category];
        }

        return $ordered;
    }

    /**
     * Group check results by their scoring category.
     *
     * @param  list<array>  $results
     * @return array<string, list<array>>
     */
    private function groupByCategory(array $results): array
    {
        $grouped = [];

        foreach ($results as $result) {
            $category = self::CATEGORY_MAP[$result['name']] ?? self::DEFAULT_CATEGORY;
            $grouped[$category][] = $result;
        }

        return $grouped;
    }
}
