<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\FeedFetchException;
use App\Models\FeedReport;
use App\Services\FeedFetcher;
use App\Services\FeedValidator;
use App\Services\Scoring\HealthScorer;
use App\Services\Scoring\SeoScorer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use SimpleXMLElement;

class FeedCheckController extends Controller
{
    public function __construct(
        private readonly FeedFetcher $feedFetcher,
        private readonly FeedValidator $feedValidator,
        private readonly HealthScorer $healthScorer,
        private readonly SeoScorer $seoScorer,
    ) {}

    public function index(): View
    {
        return view('home');
    }

    public function check(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
        ]);

        $url = $validated['url'];

        try {
            $feed = $this->feedFetcher->fetch($url);
        } catch (FeedFetchException $e) {
            return redirect()
                ->route('home')
                ->withInput()
                ->withErrors(['url' => $e->getMessage()]);
        }

        $feedTitle = $this->extractFeedTitle($feed);
        $validationResults = $this->feedValidator->validate($feed);
        $summary = FeedValidator::summarize($validationResults);
        $healthScore = $this->healthScorer->score($validationResults);
        $seoScore = $this->seoScorer->score($feed);

        $report = FeedReport::create([
            'feed_url' => $url,
            'feed_title' => $feedTitle,
            'overall_score' => $healthScore->overall,
            'results_json' => [
                'feed_format' => $feed->getName() === 'rss' ? 'RSS 2.0' : 'Atom',
                'checked_at' => now()->toIso8601String(),
                'artwork_url' => $this->extractArtworkUrl($feed),
                'total_episodes' => $this->countTotalEpisodes($feed),
                'summary' => $summary,
                'health_score' => $healthScore->toArray(),
                'seo_score' => $seoScore->toArray(),
                'channel' => $validationResults['channel'],
                'episodes' => $validationResults['episodes'],
            ],
        ]);

        return redirect()->route('report.show', $report);
    }

    public function show(FeedReport $report): View
    {
        return view('report', compact('report'));
    }

    /**
     * Extract the feed title from the parsed XML.
     */
    private function extractFeedTitle(SimpleXMLElement $feed): ?string
    {
        // RSS 2.0: <rss><channel><title>
        if ($feed->getName() === 'rss' && isset($feed->channel->title)) {
            $title = (string) $feed->channel->title;

            return $title !== '' ? $title : null;
        }

        // Atom: <feed><title>
        if ($feed->getName() === 'feed' && isset($feed->title)) {
            $title = (string) $feed->title;

            return $title !== '' ? $title : null;
        }

        return null;
    }

    /**
     * Count total episode items in the feed (not capped).
     */
    private function countTotalEpisodes(SimpleXMLElement $feed): int
    {
        if ($feed->getName() === 'rss' && isset($feed->channel->item)) {
            return count($feed->channel->item);
        }

        if ($feed->getName() === 'feed') {
            $namespaces = $feed->getNamespaces(true);
            $children = isset($namespaces['']) ? $feed->children($namespaces['']) : $feed;

            if (isset($children->entry)) {
                return count($children->entry);
            }
        }

        return 0;
    }

    /**
     * Extract the artwork image URL from the feed's itunes:image element.
     */
    private function extractArtworkUrl(SimpleXMLElement $feed): ?string
    {
        $namespaces = $feed->getNamespaces(true);
        $itunesNs = $namespaces['itunes'] ?? 'http://www.itunes.com/dtds/podcast-1.0.dtd';

        // RSS 2.0: <rss><channel><itunes:image href="..."/>
        if ($feed->getName() === 'rss' && isset($feed->channel)) {
            $itunes = $feed->channel->children($itunesNs);

            if (isset($itunes->image)) {
                $href = (string) $itunes->image->attributes()['href'];

                return $href !== '' ? $href : null;
            }
        }

        // Atom: <feed><itunes:image href="..."/>
        if ($feed->getName() === 'feed') {
            $itunes = $feed->children($itunesNs);

            if (isset($itunes->image)) {
                $href = (string) $itunes->image->attributes()['href'];

                return $href !== '' ? $href : null;
            }
        }

        return null;
    }
}
