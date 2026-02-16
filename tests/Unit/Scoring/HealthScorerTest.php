<?php

declare(strict_types=1);

use App\Services\Scoring\HealthScore;
use App\Services\Scoring\HealthScorer;

// ──────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────

function makeResult(string $name, string $status, string $severity = 'error', ?string $suggestion = null): array
{
    return [
        'name' => $name,
        'severity' => $severity,
        'status' => $status,
        'message' => "Check {$name}: {$status}",
        'suggestion' => $suggestion,
    ];
}

function emptyResults(): array
{
    return ['channel' => [], 'episodes' => []];
}

// ──────────────────────────────────────────────────
// Overall Score Calculation
// ──────────────────────────────────────────────────

describe('HealthScorer — overall score', function () {
    it('returns 100 when all checks pass', function () {
        $scorer = new HealthScorer;
        $results = [
            'channel' => [
                makeResult('Podcast Artwork', 'pass'),
                makeResult('iTunes Category', 'pass'),
                makeResult('Explicit Tag', 'pass'),
            ],
            'episodes' => [],
        ];

        $score = $scorer->score($results);

        expect($score)->toBeInstanceOf(HealthScore::class)
            ->and($score->overall)->toBe(100);
    });

    it('returns 100 for empty results', function () {
        $scorer = new HealthScorer;

        $score = $scorer->score(emptyResults());

        expect($score->overall)->toBe(100);
    });

    it('deducts 10 points per fail', function () {
        $scorer = new HealthScorer;
        $results = [
            'channel' => [
                makeResult('Podcast Artwork', 'fail', suggestion: 'Add artwork'),
                makeResult('iTunes Category', 'fail', suggestion: 'Add category'),
            ],
            'episodes' => [],
        ];

        $score = $scorer->score($results);

        expect($score->overall)->toBe(80);
    });

    it('deducts 3 points per warn', function () {
        $scorer = new HealthScorer;
        $results = [
            'channel' => [
                makeResult('iTunes Author', 'warn', 'warning', 'Shorten author name'),
                makeResult('Language Tag', 'warn', 'warning', 'Use standard code'),
            ],
            'episodes' => [],
        ];

        $score = $scorer->score($results);

        expect($score->overall)->toBe(94);
    });

    it('deducts correctly for mixed pass, warn, and fail', function () {
        $scorer = new HealthScorer;
        $results = [
            'channel' => [
                makeResult('Podcast Artwork', 'pass'),
                makeResult('iTunes Category', 'fail', suggestion: 'Add category'),
                makeResult('Explicit Tag', 'warn', 'error', 'Use true/false'),
            ],
            'episodes' => [],
        ];

        // 100 - 10 (fail) - 3 (warn) = 87
        $score = $scorer->score($results);

        expect($score->overall)->toBe(87);
    });

    it('never goes below zero', function () {
        $scorer = new HealthScorer;
        $results = [
            'channel' => array_fill(0, 15, makeResult('Podcast Artwork', 'fail', suggestion: 'Fix')),
            'episodes' => [],
        ];

        // 15 fails * 10 = 150 deduction, but min is 0
        $score = $scorer->score($results);

        expect($score->overall)->toBe(0);
    });

    it('includes episode results in overall score', function () {
        $scorer = new HealthScorer;
        $results = [
            'channel' => [
                makeResult('Podcast Artwork', 'pass'),
            ],
            'episodes' => [
                [
                    'title' => 'Ep 1',
                    'guid' => 'ep-1',
                    'results' => [
                        makeResult('Episode Enclosure', 'fail', suggestion: 'Add enclosure'),
                        makeResult('Episode GUID', 'pass'),
                    ],
                ],
            ],
        ];

        // 100 - 10 (enclosure fail) = 90
        $score = $scorer->score($results);

        expect($score->overall)->toBe(90);
    });

    it('counts all episodes towards overall score', function () {
        $scorer = new HealthScorer;
        $results = [
            'channel' => [],
            'episodes' => [
                [
                    'title' => 'Ep 1',
                    'guid' => 'ep-1',
                    'results' => [
                        makeResult('Episode Enclosure', 'fail', suggestion: 'Fix'),
                    ],
                ],
                [
                    'title' => 'Ep 2',
                    'guid' => 'ep-2',
                    'results' => [
                        makeResult('Episode Enclosure', 'fail', suggestion: 'Fix'),
                    ],
                ],
                [
                    'title' => 'Ep 3',
                    'guid' => 'ep-3',
                    'results' => [
                        makeResult('Episode Enclosure', 'warn', 'error', 'Check type'),
                    ],
                ],
            ],
        ];

        // 100 - 10 - 10 - 3 = 77
        $score = $scorer->score($results);

        expect($score->overall)->toBe(77);
    });
});

// ──────────────────────────────────────────────────
// Category Scores
// ──────────────────────────────────────────────────

describe('HealthScorer — category scores', function () {
    it('returns all three categories even when empty', function () {
        $scorer = new HealthScorer;

        $score = $scorer->score(emptyResults());

        expect($score->categories)->toHaveKeys(['compliance', 'technical', 'best_practices'])
            ->and($score->categories['compliance'])->toBe([
                'score' => 100, 'pass' => 0, 'warn' => 0, 'fail' => 0, 'total' => 0,
            ])
            ->and($score->categories['technical'])->toBe([
                'score' => 100, 'pass' => 0, 'warn' => 0, 'fail' => 0, 'total' => 0,
            ])
            ->and($score->categories['best_practices'])->toBe([
                'score' => 100, 'pass' => 0, 'warn' => 0, 'fail' => 0, 'total' => 0,
            ]);
    });

    it('maps compliance checks correctly', function () {
        $scorer = new HealthScorer;
        $results = [
            'channel' => [
                makeResult('Podcast Artwork', 'pass'),
                makeResult('iTunes Category', 'fail', suggestion: 'Add category'),
                makeResult('Explicit Tag', 'pass'),
                makeResult('Owner Email', 'warn', 'error', 'Fix email'),
            ],
            'episodes' => [],
        ];

        $score = $scorer->score($results);

        expect($score->categories['compliance'])->toBe([
            'score' => 87,  // 100 - 10 - 3
            'pass' => 2,
            'warn' => 1,
            'fail' => 1,
            'total' => 4,
        ]);
    });

    it('maps technical checks correctly', function () {
        $scorer = new HealthScorer;
        $results = [
            'channel' => [
                makeResult('Language Tag', 'pass'),
                makeResult('Website Link', 'warn', 'warning', 'Use HTTPS'),
            ],
            'episodes' => [
                [
                    'title' => 'Ep 1',
                    'guid' => 'ep-1',
                    'results' => [
                        makeResult('Episode GUID', 'pass'),
                        makeResult('Episode Publication Date', 'pass'),
                        makeResult('Episode Duration', 'fail', suggestion: 'Add duration'),
                    ],
                ],
            ],
        ];

        $score = $scorer->score($results);

        expect($score->categories['technical'])->toBe([
            'score' => 87,  // 100 - 10 - 3
            'pass' => 3,
            'warn' => 1,
            'fail' => 1,
            'total' => 5,
        ]);
    });

    it('maps best practices checks correctly', function () {
        $scorer = new HealthScorer;
        $results = [
            'channel' => [
                makeResult('iTunes Author', 'pass'),
                makeResult('Channel Description', 'warn', 'error', 'Expand description'),
            ],
            'episodes' => [
                [
                    'title' => 'Ep 1',
                    'guid' => 'ep-1',
                    'results' => [
                        makeResult('Episode Title', 'pass'),
                        makeResult('Episode Description', 'pass'),
                    ],
                ],
            ],
        ];

        $score = $scorer->score($results);

        expect($score->categories['best_practices'])->toBe([
            'score' => 97,  // 100 - 3
            'pass' => 3,
            'warn' => 1,
            'fail' => 0,
            'total' => 4,
        ]);
    });

    it('calculates independent scores per category', function () {
        $scorer = new HealthScorer;
        $results = [
            'channel' => [
                // Compliance: 1 fail
                makeResult('Podcast Artwork', 'fail', suggestion: 'Add artwork'),
                // Technical: 1 warn
                makeResult('Language Tag', 'warn', 'warning', 'Fix lang'),
                // Best Practices: all pass
                makeResult('iTunes Author', 'pass'),
            ],
            'episodes' => [],
        ];

        $score = $scorer->score($results);

        expect($score->categories['compliance']['score'])->toBe(90)  // 100 - 10
            ->and($score->categories['technical']['score'])->toBe(97)  // 100 - 3
            ->and($score->categories['best_practices']['score'])->toBe(100);
    });

    it('assigns episode checks to the correct categories', function () {
        $scorer = new HealthScorer;
        $results = [
            'channel' => [],
            'episodes' => [
                [
                    'title' => 'Ep 1',
                    'guid' => 'ep-1',
                    'results' => [
                        makeResult('Episode Enclosure', 'fail', suggestion: 'Add audio'),  // compliance
                        makeResult('Episode GUID', 'pass'),  // technical
                        makeResult('Episode Publication Date', 'pass'),  // technical
                        makeResult('Episode Duration', 'pass'),  // technical
                        makeResult('Episode Title', 'pass'),  // best_practices
                        makeResult('Episode Description', 'pass'),  // best_practices
                    ],
                ],
            ],
        ];

        $score = $scorer->score($results);

        expect($score->categories['compliance']['fail'])->toBe(1)
            ->and($score->categories['compliance']['total'])->toBe(1)
            ->and($score->categories['technical']['pass'])->toBe(3)
            ->and($score->categories['technical']['total'])->toBe(3)
            ->and($score->categories['best_practices']['pass'])->toBe(2)
            ->and($score->categories['best_practices']['total'])->toBe(2);
    });

    it('defaults unknown check names to best_practices', function () {
        $scorer = new HealthScorer;
        $results = [
            'channel' => [
                makeResult('Some Future Check', 'warn', 'warning', 'Improve this'),
            ],
            'episodes' => [],
        ];

        $score = $scorer->score($results);

        expect($score->categories['best_practices']['warn'])->toBe(1)
            ->and($score->categories['best_practices']['total'])->toBe(1);
    });

    it('keeps categories in canonical order', function () {
        $scorer = new HealthScorer;
        $results = [
            'channel' => [
                makeResult('iTunes Author', 'pass'),  // best_practices first in input
                makeResult('Language Tag', 'pass'),  // technical second
                makeResult('Podcast Artwork', 'pass'),  // compliance last
            ],
            'episodes' => [],
        ];

        $score = $scorer->score($results);

        $keys = array_keys($score->categories);

        expect($keys)->toBe(['compliance', 'technical', 'best_practices']);
    });
});

// ──────────────────────────────────────────────────
// HealthScore Value Object
// ──────────────────────────────────────────────────

describe('HealthScore — value object', function () {
    it('exposes overall and categories as readonly properties', function () {
        $healthScore = new HealthScore(
            overall: 85,
            categories: [
                'compliance' => ['score' => 90, 'pass' => 3, 'warn' => 1, 'fail' => 0, 'total' => 4],
                'technical' => ['score' => 80, 'pass' => 2, 'warn' => 0, 'fail' => 1, 'total' => 3],
                'best_practices' => ['score' => 100, 'pass' => 4, 'warn' => 0, 'fail' => 0, 'total' => 4],
            ],
        );

        expect($healthScore->overall)->toBe(85)
            ->and($healthScore->categories)->toHaveKeys(['compliance', 'technical', 'best_practices']);
    });

    it('serializes to array', function () {
        $categories = [
            'compliance' => ['score' => 90, 'pass' => 3, 'warn' => 1, 'fail' => 0, 'total' => 4],
            'technical' => ['score' => 100, 'pass' => 2, 'warn' => 0, 'fail' => 0, 'total' => 2],
            'best_practices' => ['score' => 97, 'pass' => 3, 'warn' => 1, 'fail' => 0, 'total' => 4],
        ];

        $healthScore = new HealthScore(overall: 92, categories: $categories);

        expect($healthScore->toArray())->toBe([
            'overall' => 92,
            'categories' => $categories,
        ]);
    });
});

// ──────────────────────────────────────────────────
// Full Realistic Scenario
// ──────────────────────────────────────────────────

describe('HealthScorer — realistic scenario', function () {
    it('scores a typical podcast feed with mixed results', function () {
        $scorer = new HealthScorer;
        $results = [
            'channel' => [
                makeResult('Podcast Artwork', 'pass'),
                makeResult('iTunes Category', 'pass'),
                makeResult('Explicit Tag', 'pass'),
                makeResult('Owner Email', 'pass'),
                makeResult('iTunes Author', 'pass'),
                makeResult('Language Tag', 'pass'),
                makeResult('Website Link', 'warn', 'warning', 'Use HTTPS'),
                makeResult('Channel Description', 'pass'),
            ],
            'episodes' => [
                [
                    'title' => 'Episode 1',
                    'guid' => 'ep-1',
                    'results' => [
                        makeResult('Episode Enclosure', 'pass'),
                        makeResult('Episode GUID', 'pass'),
                        makeResult('Episode Publication Date', 'pass'),
                        makeResult('Episode Duration', 'pass'),
                        makeResult('Episode Title', 'warn', 'warning', 'Use descriptive title'),
                        makeResult('Episode Description', 'pass'),
                    ],
                ],
                [
                    'title' => 'Episode 2',
                    'guid' => 'ep-2',
                    'results' => [
                        makeResult('Episode Enclosure', 'pass'),
                        makeResult('Episode GUID', 'pass'),
                        makeResult('Episode Publication Date', 'pass'),
                        makeResult('Episode Duration', 'fail', suggestion: 'Add duration tag'),
                        makeResult('Episode Title', 'pass'),
                        makeResult('Episode Description', 'fail', suggestion: 'Add description'),
                    ],
                ],
            ],
        ];

        $score = $scorer->score($results);

        // Overall: 100 - 3 (link warn) - 3 (title warn) - 10 (duration fail) - 10 (desc fail) = 74
        expect($score->overall)->toBe(74)
            // Compliance: Artwork, Category, Explicit, Owner Email + 2x Enclosure = 6 pass
            ->and($score->categories['compliance']['score'])->toBe(100)
            ->and($score->categories['compliance']['pass'])->toBe(6)
            ->and($score->categories['compliance']['total'])->toBe(6)
            // Technical: Language + Website(warn) + 2xGUID + 2xPubDate + Duration(pass) + Duration(fail) = 6p 1w 1f
            ->and($score->categories['technical']['score'])->toBe(87)  // 100 - 3 - 10
            ->and($score->categories['technical']['pass'])->toBe(6)
            ->and($score->categories['technical']['warn'])->toBe(1)
            ->and($score->categories['technical']['fail'])->toBe(1)
            // Best Practices: Author + Description + Title(warn) + Description(pass) + Title(pass) + Description(fail)
            ->and($score->categories['best_practices']['score'])->toBe(87)  // 100 - 3 - 10
            ->and($score->categories['best_practices']['pass'])->toBe(4)
            ->and($score->categories['best_practices']['warn'])->toBe(1)
            ->and($score->categories['best_practices']['fail'])->toBe(1);
    });
});
