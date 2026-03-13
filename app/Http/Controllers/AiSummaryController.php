<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\AI\PodcastCoachService;
use App\Models\FeedReport;
use Illuminate\Http\JsonResponse;

class AiSummaryController extends Controller
{
    public function __construct(
        private readonly PodcastCoachService $coachService,
    ) {}

    public function generate(FeedReport $report): JsonResponse
    {
        $summary = $this->coachService->getSummary($report);

        return response()->json(['summary' => $summary]);
    }
}
