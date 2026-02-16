<?php

declare(strict_types=1);

use App\Services\Scoring\SeoScore;
use App\Services\Scoring\SeoScorer;

// ──────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────

function makeFeedWithTitle(string $title): SimpleXMLElement
{
    $xml = <<<XML
    <?xml version="1.0" encoding="UTF-8"?>
    <rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">
      <channel>
        <title>{$title}</title>
        <description>A test podcast description that is long enough to be considered valid for SEO scoring purposes and analysis. This description contains enough text to pass the minimum length requirements for optimal scoring.</description>
      </channel>
    </rss>
    XML;

    return simplexml_load_string($xml);
}

function makeFeedWithDescription(string $description): SimpleXMLElement
{
    $xml = <<<XML
    <?xml version="1.0" encoding="UTF-8"?>
    <rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">
      <channel>
        <title>A Great Podcast About Technology and Life</title>
        <description>{$description}</description>
      </channel>
    </rss>
    XML;

    return simplexml_load_string($xml);
}

function makeFeedWithEpisodes(array $titles): SimpleXMLElement
{
    $items = '';
    foreach ($titles as $i => $title) {
        $items .= <<<XML
        <item>
          <title>{$title}</title>
          <enclosure url="https://example.com/ep{$i}.mp3" type="audio/mpeg"/>
        </item>
        XML;
    }

    $xml = <<<XML
    <?xml version="1.0" encoding="UTF-8"?>
    <rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">
      <channel>
        <title>A Great Podcast About Technology and Life</title>
        <description>A test podcast description that is long enough to be considered valid for SEO scoring purposes and analysis. This description contains enough text to pass the minimum length requirements for optimal scoring.</description>
        {$items}
      </channel>
    </rss>
    XML;

    return simplexml_load_string($xml);
}

function makeFeedWithItunesTitles(array $itunesTitles): SimpleXMLElement
{
    $items = '';
    foreach ($itunesTitles as $i => $title) {
        $items .= <<<XML
        <item>
          <title>Episode {$i}: Some Title</title>
          <itunes:title>{$title}</itunes:title>
          <enclosure url="https://example.com/ep{$i}.mp3" type="audio/mpeg"/>
        </item>
        XML;
    }

    $xml = <<<XML
    <?xml version="1.0" encoding="UTF-8"?>
    <rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">
      <channel>
        <title>A Great Podcast About Technology and Life</title>
        <description>A test podcast description that is long enough to be considered valid for SEO scoring purposes and analysis. This description contains enough text to pass the minimum length requirements for optimal scoring.</description>
        {$items}
      </channel>
    </rss>
    XML;

    return simplexml_load_string($xml);
}

function makeEmptyFeed(): SimpleXMLElement
{
    $xml = <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">
      <channel>
      </channel>
    </rss>
    XML;

    return simplexml_load_string($xml);
}

// ──────────────────────────────────────────────────
// SeoScore Value Object
// ──────────────────────────────────────────────────

describe('SeoScore — value object', function () {
    it('exposes overall and details as readonly properties', function () {
        $seoScore = new SeoScore(
            overall: 75,
            details: [
                'show_title' => ['score' => 100, 'status' => 'pass', 'message' => 'Good', 'suggestion' => null],
                'show_description' => ['score' => 70, 'status' => 'pass', 'message' => 'OK', 'suggestion' => 'Improve'],
                'episode_titles' => ['score' => 60, 'status' => 'warn', 'message' => 'Generic', 'suggestion' => 'Fix'],
            ],
        );

        expect($seoScore->overall)->toBe(75)
            ->and($seoScore->details)->toHaveKeys(['show_title', 'show_description', 'episode_titles']);
    });

    it('serializes to array', function () {
        $details = [
            'show_title' => ['score' => 100, 'status' => 'pass', 'message' => 'Good', 'suggestion' => null],
            'show_description' => ['score' => 70, 'status' => 'pass', 'message' => 'OK', 'suggestion' => null],
            'episode_titles' => ['score' => 80, 'status' => 'pass', 'message' => 'Good', 'suggestion' => null],
        ];

        $seoScore = new SeoScore(overall: 83, details: $details);

        expect($seoScore->toArray())->toBe([
            'overall' => 83,
            'details' => $details,
        ]);
    });
});

// ──────────────────────────────────────────────────
// Show Title Analysis
// ──────────────────────────────────────────────────

describe('SeoScorer — show title', function () {
    it('returns fail when title is missing', function () {
        $scorer = new SeoScorer;

        $score = $scorer->score(makeEmptyFeed());

        expect($score->details['show_title']['score'])->toBe(0)
            ->and($score->details['show_title']['status'])->toBe('fail')
            ->and($score->details['show_title']['suggestion'])->not->toBeNull();
    });

    it('warns when title is too short (under 20 chars)', function () {
        $scorer = new SeoScorer;

        $score = $scorer->score(makeFeedWithTitle('Short Pod'));

        expect($score->details['show_title']['score'])->toBe(40)
            ->and($score->details['show_title']['status'])->toBe('warn')
            ->and($score->details['show_title']['length'])->toBe(9);
    });

    it('passes but notes when title is slightly short (20-29 chars)', function () {
        $scorer = new SeoScorer;

        // 25 chars
        $score = $scorer->score(makeFeedWithTitle('The Technology Podcast'));

        expect($score->details['show_title']['score'])->toBe(70)
            ->and($score->details['show_title']['status'])->toBe('pass')
            ->and($score->details['show_title']['suggestion'])->not->toBeNull();
    });

    it('scores 100 for optimal length title (30-60 chars)', function () {
        $scorer = new SeoScorer;

        // 40 chars
        $score = $scorer->score(makeFeedWithTitle('The Ultimate Guide to Modern Technology'));

        expect($score->details['show_title']['score'])->toBe(100)
            ->and($score->details['show_title']['status'])->toBe('pass')
            ->and($score->details['show_title']['suggestion'])->toBeNull();
    });

    it('passes with note when title is slightly long (61-70 chars)', function () {
        $scorer = new SeoScorer;

        // 65 chars
        $title = 'The Ultimate Comprehensive Guide to Modern Technology and Design';
        $score = $scorer->score(makeFeedWithTitle($title));

        expect($score->details['show_title']['score'])->toBe(80)
            ->and($score->details['show_title']['status'])->toBe('pass')
            ->and($score->details['show_title']['suggestion'])->not->toBeNull();
    });

    it('warns when title is too long (over 70 chars)', function () {
        $scorer = new SeoScorer;

        // 85 chars
        $title = 'The Ultimate Comprehensive Guide to Modern Technology Innovation and Digital Design Art';
        $score = $scorer->score(makeFeedWithTitle($title));

        expect($score->details['show_title']['score'])->toBe(50)
            ->and($score->details['show_title']['status'])->toBe('warn');
    });

    it('detects keyword stuffing', function () {
        $scorer = new SeoScorer;

        // "podcast" repeated 3 times
        $score = $scorer->score(makeFeedWithTitle('Podcast Tips Podcast Advice Podcast Help'));

        expect($score->details['show_title']['status'])->toBe('warn')
            ->and($score->details['show_title']['message'])->toContain('keyword stuffing');
    });

    it('applies keyword stuffing penalty to score', function () {
        $scorer = new SeoScorer;

        // "tech" 3 times, length is in optimal range ~40 chars
        $score = $scorer->score(makeFeedWithTitle('Tech Talk About Tech News in Tech World'));

        // Would be 100 for length, minus 20 penalty = 80
        expect($score->details['show_title']['score'])->toBe(80);
    });

    it('does not flag keyword stuffing for short titles', function () {
        $scorer = new SeoScorer;

        // Only 2 words, below threshold
        $score = $scorer->score(makeFeedWithTitle('Hi Hi'));

        expect($score->details['show_title']['message'])->not->toContain('keyword stuffing');
    });

    it('includes the title text in the message', function () {
        $scorer = new SeoScorer;

        $score = $scorer->score(makeFeedWithTitle('My Amazing Tech Podcast Show'));

        expect($score->details['show_title']['message'])->toContain('My Amazing Tech Podcast Show');
    });

    it('reports title length correctly', function () {
        $scorer = new SeoScorer;
        $title = 'The Ultimate Guide to Modern Technology';

        $score = $scorer->score(makeFeedWithTitle($title));

        expect($score->details['show_title']['length'])->toBe(mb_strlen($title));
    });

    it('scores exactly at boundary of 30 chars as optimal', function () {
        $scorer = new SeoScorer;

        // Exactly 30 chars
        $title = 'A Great Podcast About Science';
        $score = $scorer->score(makeFeedWithTitle(str_pad($title, 30, '!')));

        expect($score->details['show_title']['score'])->toBe(100);
    });

    it('scores exactly at boundary of 60 chars as optimal', function () {
        $scorer = new SeoScorer;

        // Exactly 60 chars, no repeated words
        $title = 'A Great Podcast About Technology Innovation and Leadership';
        $title = str_pad($title, 60, 'x');
        $score = $scorer->score(makeFeedWithTitle($title));

        expect($score->details['show_title']['length'])->toBe(60)
            ->and($score->details['show_title']['score'])->toBe(100);
    });
});

// ──────────────────────────────────────────────────
// Show Description Analysis
// ──────────────────────────────────────────────────

describe('SeoScorer — show description', function () {
    it('returns fail when description is missing', function () {
        $scorer = new SeoScorer;

        $xml = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">
          <channel>
            <title>A Great Podcast About Technology and Life</title>
          </channel>
        </rss>
        XML;

        $score = $scorer->score(simplexml_load_string($xml));

        expect($score->details['show_description']['score'])->toBe(0)
            ->and($score->details['show_description']['status'])->toBe('fail')
            ->and($score->details['show_description']['suggestion'])->not->toBeNull();
    });

    it('warns when description is too short (under 100 chars)', function () {
        $scorer = new SeoScorer;

        $score = $scorer->score(makeFeedWithDescription('A short description.'));

        expect($score->details['show_description']['score'])->toBe(30)
            ->and($score->details['show_description']['status'])->toBe('warn');
    });

    it('passes but notes when description is moderate (100-249 chars)', function () {
        $scorer = new SeoScorer;

        $desc = str_repeat('A solid podcast about tech. ', 5); // ~135 chars
        $score = $scorer->score(makeFeedWithDescription($desc));

        expect($score->details['show_description']['score'])->toBe(70)
            ->and($score->details['show_description']['status'])->toBe('pass')
            ->and($score->details['show_description']['suggestion'])->not->toBeNull();
    });

    it('scores 100 for optimal length description (250-600 chars)', function () {
        $scorer = new SeoScorer;

        $desc = str_repeat('This is a comprehensive podcast description. ', 8); // ~360 chars
        $score = $scorer->score(makeFeedWithDescription($desc));

        expect($score->details['show_description']['score'])->toBe(100)
            ->and($score->details['show_description']['status'])->toBe('pass')
            ->and($score->details['show_description']['suggestion'])->toBeNull();
    });

    it('scores 90 for long but acceptable description (601-4000 chars)', function () {
        $scorer = new SeoScorer;

        $desc = str_repeat('A detailed description. ', 50); // ~1200 chars
        $score = $scorer->score(makeFeedWithDescription($desc));

        expect($score->details['show_description']['score'])->toBe(90)
            ->and($score->details['show_description']['status'])->toBe('pass');
    });

    it('warns when description exceeds 4000 chars', function () {
        $scorer = new SeoScorer;

        $desc = str_repeat('Long text here. ', 300); // ~4800 chars
        $score = $scorer->score(makeFeedWithDescription($desc));

        expect($score->details['show_description']['score'])->toBe(60)
            ->and($score->details['show_description']['status'])->toBe('warn');
    });

    it('falls back to itunes:summary when description is empty', function () {
        $scorer = new SeoScorer;

        $summary = str_repeat('This is the iTunes summary with good content. ', 8); // ~376 chars
        $xml = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">
          <channel>
            <title>A Great Podcast About Technology and Life</title>
            <itunes:summary>{$summary}</itunes:summary>
          </channel>
        </rss>
        XML;

        $score = $scorer->score(simplexml_load_string($xml));

        expect($score->details['show_description']['score'])->toBe(100)
            ->and($score->details['show_description']['status'])->toBe('pass');
    });

    it('reports description length', function () {
        $scorer = new SeoScorer;

        $desc = 'A short description here.';
        $score = $scorer->score(makeFeedWithDescription($desc));

        expect($score->details['show_description']['length'])->toBe(mb_strlen($desc));
    });
});

// ──────────────────────────────────────────────────
// Episode Title Analysis
// ──────────────────────────────────────────────────

describe('SeoScorer — episode titles', function () {
    it('returns 100 when there are no episodes', function () {
        $scorer = new SeoScorer;

        $score = $scorer->score(makeFeedWithEpisodes([]));

        expect($score->details['episode_titles']['score'])->toBe(100)
            ->and($score->details['episode_titles']['status'])->toBe('pass')
            ->and($score->details['episode_titles']['generic_count'])->toBe(0)
            ->and($score->details['episode_titles']['total_count'])->toBe(0);
    });

    it('returns 100 when all titles are descriptive', function () {
        $scorer = new SeoScorer;

        $score = $scorer->score(makeFeedWithEpisodes([
            'How to Start a Podcast in 2025',
            'Interview with John Doe on AI',
            'The Future of Web Development',
        ]));

        expect($score->details['episode_titles']['score'])->toBe(100)
            ->and($score->details['episode_titles']['status'])->toBe('pass')
            ->and($score->details['episode_titles']['generic_count'])->toBe(0)
            ->and($score->details['episode_titles']['total_count'])->toBe(3);
    });

    it('fails when all titles are generic', function () {
        $scorer = new SeoScorer;

        $score = $scorer->score(makeFeedWithEpisodes([
            'Episode 1',
            'Episode 2',
            'Episode 3',
        ]));

        expect($score->details['episode_titles']['score'])->toBe(0)
            ->and($score->details['episode_titles']['status'])->toBe('fail')
            ->and($score->details['episode_titles']['generic_count'])->toBe(3)
            ->and($score->details['episode_titles']['total_count'])->toBe(3);
    });

    it('warns when some titles are generic', function () {
        $scorer = new SeoScorer;

        $score = $scorer->score(makeFeedWithEpisodes([
            'How to Start a Podcast',
            'Episode 2',
            'The Future of AI',
        ]));

        expect($score->details['episode_titles']['score'])->toBe(67)
            ->and($score->details['episode_titles']['status'])->toBe('warn')
            ->and($score->details['episode_titles']['generic_count'])->toBe(1)
            ->and($score->details['episode_titles']['total_count'])->toBe(3);
    });

    it('detects various generic patterns', function () {
        $scorer = new SeoScorer;

        $score = $scorer->score(makeFeedWithEpisodes([
            'Ep 5',
            'Ep. 12',
            '#47',
            'Episode 100',
        ]));

        expect($score->details['episode_titles']['generic_count'])->toBe(4);
    });

    it('prefers itunes:title for analysis', function () {
        $scorer = new SeoScorer;

        // itunes:title has descriptive titles, <title> has generic ones
        $score = $scorer->score(makeFeedWithItunesTitles([
            'How to Start Investing',
            'Interview with Expert',
        ]));

        expect($score->details['episode_titles']['score'])->toBe(100)
            ->and($score->details['episode_titles']['generic_count'])->toBe(0);
    });

    it('caps episode analysis at 10 episodes', function () {
        $scorer = new SeoScorer;

        $titles = array_fill(0, 15, 'A Descriptive Episode Title');
        $score = $scorer->score(makeFeedWithEpisodes($titles));

        expect($score->details['episode_titles']['total_count'])->toBe(10);
    });
});

// ──────────────────────────────────────────────────
// Overall Score Calculation
// ──────────────────────────────────────────────────

describe('SeoScorer — overall score', function () {
    it('returns a SeoScore instance', function () {
        $scorer = new SeoScorer;

        $score = $scorer->score(makeFeedWithTitle('A Great Podcast About Technology and Life'));

        expect($score)->toBeInstanceOf(SeoScore::class);
    });

    it('includes all three detail sections', function () {
        $scorer = new SeoScorer;

        $score = $scorer->score(makeFeedWithTitle('A Great Podcast About Technology and Life'));

        expect($score->details)->toHaveKeys(['show_title', 'show_description', 'episode_titles']);
    });

    it('calculates weighted average of component scores', function () {
        $scorer = new SeoScorer;

        // Title: optimal (100), Description: optimal (100), Episodes: all descriptive (100)
        $xml = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">
          <channel>
            <title>A Great Podcast About Technology and Life</title>
            <description>This is a comprehensive podcast description that covers technology, science, and modern life. We explore cutting-edge topics, interview industry leaders, and provide actionable insights for our listeners. Whether you're a tech enthusiast or just curious about the future, this show is for you.</description>
            <item><title>How to Build a Successful Tech Startup</title></item>
            <item><title>Interview with a Leading AI Researcher</title></item>
          </channel>
        </rss>
        XML;

        $score = $scorer->score(simplexml_load_string($xml));

        expect($score->overall)->toBe(100);
    });

    it('returns 0 for a completely empty feed', function () {
        $scorer = new SeoScorer;

        $score = $scorer->score(makeEmptyFeed());

        // Title: 0 (missing), Description: 0 (missing), Episodes: 100 (no episodes to check)
        // Weighted: (0*30 + 0*30 + 100*40) / 100 = 40
        expect($score->overall)->toBe(40);
    });

    it('weights episode titles more heavily than individual components', function () {
        $scorer = new SeoScorer;

        // Build feed with optimal title, optimal description, but generic episodes
        $xml = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">
          <channel>
            <title>A Great Podcast About Technology and Life</title>
            <description>This is a comprehensive podcast description that covers technology, science, and modern life. We explore cutting-edge topics, interview industry leaders, and provide actionable insights for our listeners. Whether you're a tech enthusiast or just curious about the future, this show is for you.</description>
            <item><title>Episode 1</title></item>
            <item><title>Episode 2</title></item>
            <item><title>Episode 3</title></item>
          </channel>
        </rss>
        XML;

        // Title: optimal (100), Description: optimal (100), Episodes: all generic (0)
        // Weighted: (100*30 + 100*30 + 0*40) / 100 = 60
        $score = $scorer->score(simplexml_load_string($xml));

        expect($score->overall)->toBe(60);
    });

    it('scores a realistic mixed-quality feed', function () {
        $scorer = new SeoScorer;

        $xml = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">
          <channel>
            <title>The PodCheck Test Show</title>
            <description>A test podcast feed used for automated testing of the PodCheck feed health checker.</description>
            <item>
              <title>Episode 1: Getting Started</title>
              <itunes:title>Getting Started</itunes:title>
            </item>
            <item>
              <title>Episode 2: Advanced Validation</title>
              <itunes:title>Advanced Validation</itunes:title>
            </item>
          </channel>
        </rss>
        XML;

        $score = $scorer->score(simplexml_load_string($xml));

        // Title: "The PodCheck Test Show" = 22 chars → score 70
        // Description: ~82 chars → score 30 (< 100 chars)
        // Episodes: all descriptive via itunes:title → score 100
        // Weighted: (70*30 + 30*30 + 100*40) / 100 = 70
        expect($score->overall)->toBe(70);
    });
});

// ──────────────────────────────────────────────────
// Atom Feed Support
// ──────────────────────────────────────────────────

describe('SeoScorer — Atom feed', function () {
    it('extracts title and description from Atom feeds', function () {
        $scorer = new SeoScorer;

        $xml = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <feed xmlns="http://www.w3.org/2005/Atom">
          <title>A Great Podcast About Technology and Life</title>
          <subtitle>This is a comprehensive podcast description that covers technology, science, and modern life. We explore cutting-edge topics, interview industry leaders, and provide actionable insights for our listeners. Whether you're a tech enthusiast or just curious about the future, this show is for you.</subtitle>
        </feed>
        XML;

        $score = $scorer->score(simplexml_load_string($xml));

        expect($score->details['show_title']['score'])->toBe(100)
            ->and($score->details['show_title']['status'])->toBe('pass');
    });
});
