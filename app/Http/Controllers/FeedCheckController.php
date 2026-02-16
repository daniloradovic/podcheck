<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\FeedFetchException;
use App\Models\FeedReport;
use App\Services\FeedFetcher;
use App\Services\FeedValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use SimpleXMLElement;

class FeedCheckController extends Controller
{
    public function __construct(
        private readonly FeedFetcher $feedFetcher,
        private readonly FeedValidator $feedValidator,
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

        $report = FeedReport::create([
            'feed_url' => $url,
            'feed_title' => $feedTitle,
            'overall_score' => 0,
            'results_json' => [
                'feed_format' => $feed->getName() === 'rss' ? 'RSS 2.0' : 'Atom',
                'checked_at' => now()->toIso8601String(),
                'summary' => $summary,
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
}
