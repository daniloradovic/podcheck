@extends('layouts.app')

@section('title', ($report->feed_title ?? 'Feed Report') . ' — PodCheck')

@php
    $score = $report->overall_score;
    $artworkUrl = $report->results_json['artwork_url'] ?? null;
    $summary = $report->results_json['summary'] ?? null;
    $feedFormat = $report->results_json['feed_format'] ?? 'RSS';

    // Score ring SVG calculations
    $circumference = 339.292; // 2 * π * 54
    $offset = $circumference * (1 - $score / 100);

    // Color-coded score tiers
    if ($score >= 80) {
        $scoreColorClass = 'text-emerald-400';
        $ringColor = '#34d399';
        $badgeBg = 'bg-emerald-400/10';
        $badgeBorder = 'border-emerald-400/20';
        $scoreLabel = 'Great';
        $scoreMessage = 'Your podcast feed is in great shape! Keep up the good work.';
    } elseif ($score >= 50) {
        $scoreColorClass = 'text-amber-400';
        $ringColor = '#fbbf24';
        $badgeBg = 'bg-amber-400/10';
        $badgeBorder = 'border-amber-400/20';
        $scoreLabel = 'Needs Work';
        $scoreMessage = 'Your feed has some issues that should be addressed for better compatibility.';
    } else {
        $scoreColorClass = 'text-red-400';
        $ringColor = '#f87171';
        $badgeBg = 'bg-red-400/10';
        $badgeBorder = 'border-red-400/20';
        $scoreLabel = 'Critical';
        $scoreMessage = 'Your feed has critical issues that need to be fixed for proper distribution.';
    }

    // --- Group checks by category ---
    $categoryMap = [
        'Podcast Artwork' => 'compliance',
        'iTunes Category' => 'compliance',
        'Explicit Tag' => 'compliance',
        'Owner Email' => 'compliance',
        'iTunes Author' => 'best_practices',
        'Language Tag' => 'technical',
        'Website Link' => 'technical',
        'Channel Description' => 'best_practices',
        'Episode Enclosure' => 'compliance',
        'Episode GUID' => 'technical',
        'Episode Publication Date' => 'technical',
        'Episode Duration' => 'technical',
        'Episode Title' => 'best_practices',
        'Episode Description' => 'best_practices',
    ];

    $categoryMeta = [
        'compliance' => [
            'label' => 'Compliance',
            'description' => 'Apple & Spotify directory requirements',
            'icon' => 'shield',
        ],
        'technical' => [
            'label' => 'Technical',
            'description' => 'Feed structure and metadata format',
            'icon' => 'code',
        ],
        'best_practices' => [
            'label' => 'Best Practices',
            'description' => 'Quality and recommendations',
            'icon' => 'star',
        ],
        'seo' => [
            'label' => 'SEO',
            'description' => 'Search optimization for titles and descriptions',
            'icon' => 'search',
        ],
    ];

    $categoryOrder = ['compliance', 'technical', 'best_practices', 'seo'];

    // Group channel checks
    $groupedChecks = [];
    $channelChecks = $report->results_json['channel'] ?? [];
    foreach ($channelChecks as $check) {
        $cat = $categoryMap[$check['name']] ?? 'best_practices';
        $groupedChecks[$cat][] = $check;
    }

    // Aggregate episode checks by check name
    $episodes = $report->results_json['episodes'] ?? [];
    $episodeAggregates = [];
    foreach ($episodes as $episode) {
        foreach ($episode['results'] ?? [] as $result) {
            $name = $result['name'];
            if (!isset($episodeAggregates[$name])) {
                $episodeAggregates[$name] = [
                    'name' => $name,
                    'severity' => $result['severity'],
                    'suggestion' => $result['suggestion'],
                    'pass' => 0,
                    'warn' => 0,
                    'fail' => 0,
                    'total' => 0,
                    'worst_status' => 'pass',
                    'worst_message' => $result['message'],
                ];
            }
            $episodeAggregates[$name][$result['status']]++;
            $episodeAggregates[$name]['total']++;
            $statusPriority = ['pass' => 0, 'warn' => 1, 'fail' => 2];
            if ($statusPriority[$result['status']] > $statusPriority[$episodeAggregates[$name]['worst_status']]) {
                $episodeAggregates[$name]['worst_status'] = $result['status'];
                $episodeAggregates[$name]['worst_message'] = $result['message'];
                if ($result['suggestion']) {
                    $episodeAggregates[$name]['suggestion'] = $result['suggestion'];
                }
            }
        }
    }

    // Add episode aggregates to their categories
    foreach ($episodeAggregates as $aggregate) {
        $cat = $categoryMap[$aggregate['name']] ?? 'best_practices';
        $groupedChecks[$cat][] = [
            'name' => $aggregate['name'],
            'severity' => $aggregate['severity'],
            'status' => $aggregate['worst_status'],
            'message' => $aggregate['worst_message'],
            'suggestion' => $aggregate['suggestion'],
            'is_episode_check' => true,
            'episode_counts' => [
                'pass' => $aggregate['pass'],
                'warn' => $aggregate['warn'],
                'fail' => $aggregate['fail'],
                'total' => $aggregate['total'],
            ],
        ];
    }

    // Build SEO category from seo_score details
    $seoScore = $report->results_json['seo_score'] ?? null;
    $seoPass = 0;
    $seoWarn = 0;
    $seoFail = 0;
    if ($seoScore && isset($seoScore['details'])) {
        $seoNameMap = [
            'show_title' => 'Show Title',
            'show_description' => 'Show Description',
            'episode_titles' => 'Episode Titles',
        ];
        foreach ($seoScore['details'] as $key => $detail) {
            $groupedChecks['seo'][] = [
                'name' => $seoNameMap[$key] ?? $key,
                'severity' => 'recommended',
                'status' => $detail['status'],
                'message' => $detail['message'],
                'suggestion' => $detail['suggestion'] ?? null,
            ];
            match ($detail['status']) {
                'pass' => $seoPass++,
                'warn' => $seoWarn++,
                'fail' => $seoFail++,
                default => null,
            };
        }
    }

    // --- Build category score cards data ---
    $healthScore = $report->results_json['health_score'] ?? null;
    $healthCategories = $healthScore['categories'] ?? [];

    $categoryCards = [
        'compliance' => [
            'label' => 'Compliance',
            'icon' => 'shield',
            'score' => $healthCategories['compliance']['score'] ?? 100,
            'pass' => $healthCategories['compliance']['pass'] ?? 0,
            'warn' => $healthCategories['compliance']['warn'] ?? 0,
            'fail' => $healthCategories['compliance']['fail'] ?? 0,
        ],
        'technical' => [
            'label' => 'Technical',
            'icon' => 'code',
            'score' => $healthCategories['technical']['score'] ?? 100,
            'pass' => $healthCategories['technical']['pass'] ?? 0,
            'warn' => $healthCategories['technical']['warn'] ?? 0,
            'fail' => $healthCategories['technical']['fail'] ?? 0,
        ],
        'best_practices' => [
            'label' => 'Best Practices',
            'icon' => 'star',
            'score' => $healthCategories['best_practices']['score'] ?? 100,
            'pass' => $healthCategories['best_practices']['pass'] ?? 0,
            'warn' => $healthCategories['best_practices']['warn'] ?? 0,
            'fail' => $healthCategories['best_practices']['fail'] ?? 0,
        ],
        'seo' => [
            'label' => 'SEO',
            'icon' => 'search',
            'score' => $seoScore['overall'] ?? 100,
            'pass' => $seoPass,
            'warn' => $seoWarn,
            'fail' => $seoFail,
        ],
    ];
@endphp

@section('content')
    <div class="mx-auto max-w-5xl px-4 py-12 sm:px-6 lg:px-8">

        {{-- Report Header --}}
        <div class="rounded-xl border border-surface-800 bg-surface-900 p-6 sm:p-8">
            <div class="flex flex-col items-center gap-8 lg:flex-row lg:items-start">

                {{-- Left: Podcast Info --}}
                <div class="flex min-w-0 flex-1 flex-col items-center gap-5 sm:flex-row sm:items-start">

                    {{-- Artwork Thumbnail --}}
                    @if ($artworkUrl)
                        <div class="shrink-0">
                            <img
                                src="{{ $artworkUrl }}"
                                alt="{{ $report->feed_title ?? 'Podcast' }} artwork"
                                class="h-24 w-24 rounded-xl object-cover shadow-lg ring-1 ring-surface-700 sm:h-28 sm:w-28"
                                loading="lazy"
                                onerror="this.closest('div').style.display='none'"
                            >
                        </div>
                    @endif

                    {{-- Title & Metadata --}}
                    <div class="min-w-0 flex-1 text-center sm:text-left">
                        <h1 class="truncate text-2xl font-bold text-surface-50 sm:text-3xl">
                            {{ $report->feed_title ?? 'Unknown Podcast' }}
                        </h1>

                        <p class="mt-2 break-all text-sm text-surface-400">
                            {{ $report->feed_url }}
                        </p>

                        <div class="mt-3 flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-xs text-surface-500 sm:justify-start">
                            <span class="inline-flex items-center gap-1.5">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polyline points="12 6 12 12 16 14"/>
                                </svg>
                                {{ $report->created_at->diffForHumans() }}
                            </span>

                            <span class="text-surface-700">&middot;</span>

                            <span class="inline-flex items-center gap-1.5">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="4 7 4 4 20 4 20 7"/>
                                    <line x1="9" y1="20" x2="15" y2="20"/>
                                    <line x1="12" y1="4" x2="12" y2="20"/>
                                </svg>
                                {{ $feedFormat }}
                            </span>

                            @if ($summary)
                                <span class="text-surface-700">&middot;</span>
                                <span>{{ $summary['total'] ?? 0 }} checks run</span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Right: Score Badge --}}
                <div
                    class="flex shrink-0 flex-col items-center"
                    x-data="{ loaded: false }"
                    x-init="$nextTick(() => loaded = true)"
                >
                    <div class="relative h-32 w-32 sm:h-36 sm:w-36">
                        {{-- Score Ring SVG --}}
                        <svg viewBox="0 0 120 120" class="h-full w-full" aria-hidden="true">
                            {{-- Background track --}}
                            <circle
                                cx="60"
                                cy="60"
                                r="54"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="7"
                                class="text-surface-800"
                            />

                            {{-- Progress ring --}}
                            <circle
                                cx="60"
                                cy="60"
                                r="54"
                                fill="none"
                                stroke="{{ $ringColor }}"
                                stroke-width="7"
                                stroke-linecap="round"
                                stroke-dasharray="{{ $circumference }}"
                                :stroke-dashoffset="loaded ? {{ $offset }} : {{ $circumference }}"
                                transform="rotate(-90 60 60)"
                                style="transition: stroke-dashoffset 1s ease-out"
                            />
                        </svg>

                        {{-- Score Number Overlay --}}
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-3xl font-bold {{ $scoreColorClass }} sm:text-4xl">{{ $score }}</span>
                            <span class="text-xs font-medium text-surface-500">/100</span>
                        </div>
                    </div>

                    {{-- Score Label --}}
                    <span class="mt-2 inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold {{ $scoreColorClass }} {{ $badgeBg }} {{ $badgeBorder }}">
                        {{ $scoreLabel }}
                    </span>
                </div>

            </div>

            {{-- Status Message Bar --}}
            <div class="mt-6 flex flex-col items-center gap-4 border-t border-surface-800 pt-6 sm:flex-row sm:justify-between">
                <p class="text-sm text-surface-300">
                    {{ $scoreMessage }}
                </p>

                {{-- Pass / Warn / Fail Summary --}}
                @if ($summary)
                    <div class="flex shrink-0 items-center gap-3">
                        <span class="inline-flex items-center gap-1.5 text-xs font-medium text-emerald-400">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            {{ $summary['pass'] ?? 0 }} passed
                        </span>
                        <span class="inline-flex items-center gap-1.5 text-xs font-medium text-amber-400">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                                <line x1="12" y1="9" x2="12" y2="13"/>
                                <line x1="12" y1="17" x2="12.01" y2="17"/>
                            </svg>
                            {{ $summary['warn'] ?? 0 }} warnings
                        </span>
                        <span class="inline-flex items-center gap-1.5 text-xs font-medium text-red-400">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="15" y1="9" x2="9" y2="15"/>
                                <line x1="9" y1="9" x2="15" y2="15"/>
                            </svg>
                            {{ $summary['fail'] ?? 0 }} failed
                        </span>
                    </div>
                @endif
            </div>
        </div>

        {{-- Category Score Cards --}}
        <div class="mt-8 grid grid-cols-2 gap-4 lg:grid-cols-4">
            @foreach ($categoryCards as $cardKey => $card)
                @php
                    $cardScore = $card['score'];
                    if ($cardScore >= 80) {
                        $cardScoreColor = 'text-emerald-400';
                        $cardRingColor = '#34d399';
                        $cardTrackColor = 'text-emerald-400/10';
                    } elseif ($cardScore >= 50) {
                        $cardScoreColor = 'text-amber-400';
                        $cardRingColor = '#fbbf24';
                        $cardTrackColor = 'text-amber-400/10';
                    } else {
                        $cardScoreColor = 'text-red-400';
                        $cardRingColor = '#f87171';
                        $cardTrackColor = 'text-red-400/10';
                    }
                    $cardCircumference = 150.796; // 2 * π * 24
                    $cardOffset = $cardCircumference * (1 - $cardScore / 100);
                @endphp
                <div
                    class="rounded-xl border border-surface-800 bg-surface-900 p-5 transition-colors hover:border-surface-700"
                    x-data="{ loaded: false }"
                    x-init="$nextTick(() => loaded = true)"
                >
                    <div class="flex items-start justify-between gap-3">
                        {{-- Category Info --}}
                        <div class="min-w-0">
                            <div class="flex items-center gap-2.5">
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-surface-800">
                                    @if ($card['icon'] === 'shield')
                                        <svg class="h-4 w-4 text-brand-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                        </svg>
                                    @elseif ($card['icon'] === 'code')
                                        <svg class="h-4 w-4 text-brand-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="16 18 22 12 16 6"/>
                                            <polyline points="8 6 2 12 8 18"/>
                                        </svg>
                                    @elseif ($card['icon'] === 'star')
                                        <svg class="h-4 w-4 text-brand-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                                        </svg>
                                    @elseif ($card['icon'] === 'search')
                                        <svg class="h-4 w-4 text-brand-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <circle cx="11" cy="11" r="8"/>
                                            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                        </svg>
                                    @endif
                                </div>
                                <h3 class="truncate text-sm font-semibold text-surface-100">
                                    {{ $card['label'] }}
                                </h3>
                            </div>

                            {{-- Pass / Warn / Fail Counts --}}
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                @if ($card['pass'] > 0)
                                    <span class="inline-flex items-center gap-1 text-xs font-medium text-emerald-400">
                                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                        {{ $card['pass'] }}
                                    </span>
                                @endif
                                @if ($card['warn'] > 0)
                                    <span class="inline-flex items-center gap-1 text-xs font-medium text-amber-400">
                                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                                        {{ $card['warn'] }}
                                    </span>
                                @endif
                                @if ($card['fail'] > 0)
                                    <span class="inline-flex items-center gap-1 text-xs font-medium text-red-400">
                                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                                        {{ $card['fail'] }}
                                    </span>
                                @endif
                                @if ($card['pass'] === 0 && $card['warn'] === 0 && $card['fail'] === 0)
                                    <span class="text-xs text-surface-500">No checks</span>
                                @endif
                            </div>
                        </div>

                        {{-- Mini Score Ring --}}
                        <div class="relative h-14 w-14 shrink-0">
                            <svg viewBox="0 0 56 56" class="h-full w-full" aria-hidden="true">
                                <circle
                                    cx="28"
                                    cy="28"
                                    r="24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="4"
                                    class="text-surface-800"
                                />
                                <circle
                                    cx="28"
                                    cy="28"
                                    r="24"
                                    fill="none"
                                    stroke="{{ $cardRingColor }}"
                                    stroke-width="4"
                                    stroke-linecap="round"
                                    stroke-dasharray="{{ $cardCircumference }}"
                                    :stroke-dashoffset="loaded ? {{ $cardOffset }} : {{ $cardCircumference }}"
                                    transform="rotate(-90 28 28)"
                                    style="transition: stroke-dashoffset 1s ease-out"
                                />
                            </svg>
                            <div class="absolute inset-0 flex items-center justify-center">
                                <span class="text-sm font-bold {{ $cardScoreColor }}">{{ $cardScore }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Check Results by Category --}}
        <div class="mt-8 space-y-6">
            @foreach ($categoryOrder as $catKey)
                @php
                    $checks = $groupedChecks[$catKey] ?? [];
                    $meta = $categoryMeta[$catKey];
                    $catPass = collect($checks)->where('status', 'pass')->count();
                    $catWarn = collect($checks)->where('status', 'warn')->count();
                    $catFail = collect($checks)->where('status', 'fail')->count();
                @endphp

                <div
                    class="rounded-xl border border-surface-800 bg-surface-900 overflow-hidden"
                    x-data="{ open: {{ $catFail > 0 || $catWarn > 0 ? 'true' : 'false' }} }"
                >
                    {{-- Category Header --}}
                    <button
                        @click="open = !open"
                        class="flex w-full items-center justify-between gap-4 px-6 py-5 text-left transition-colors hover:bg-surface-800/50"
                    >
                        <div class="flex items-center gap-4">
                            {{-- Category Icon --}}
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-surface-800">
                                @if ($meta['icon'] === 'shield')
                                    <svg class="h-5 w-5 text-brand-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                    </svg>
                                @elseif ($meta['icon'] === 'code')
                                    <svg class="h-5 w-5 text-brand-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="16 18 22 12 16 6"/>
                                        <polyline points="8 6 2 12 8 18"/>
                                    </svg>
                                @elseif ($meta['icon'] === 'star')
                                    <svg class="h-5 w-5 text-brand-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                                    </svg>
                                @elseif ($meta['icon'] === 'search')
                                    <svg class="h-5 w-5 text-brand-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="11" cy="11" r="8"/>
                                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                    </svg>
                                @endif
                            </div>

                            <div>
                                <h2 class="text-base font-semibold text-surface-50">
                                    {{ $meta['label'] }}
                                </h2>
                                <p class="text-sm text-surface-400">
                                    {{ $meta['description'] }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            {{-- Counts --}}
                            @if (count($checks) > 0)
                                <div class="hidden items-center gap-2 sm:flex">
                                    @if ($catPass > 0)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-400/10 px-2 py-0.5 text-xs font-medium text-emerald-400">
                                            {{ $catPass }}
                                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                        </span>
                                    @endif
                                    @if ($catWarn > 0)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-400/10 px-2 py-0.5 text-xs font-medium text-amber-400">
                                            {{ $catWarn }}
                                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                                        </span>
                                    @endif
                                    @if ($catFail > 0)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-red-400/10 px-2 py-0.5 text-xs font-medium text-red-400">
                                            {{ $catFail }}
                                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                                        </span>
                                    @endif
                                </div>
                            @endif

                            {{-- Chevron --}}
                            <svg
                                class="h-5 w-5 text-surface-500 transition-transform duration-200"
                                :class="open && 'rotate-180'"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <polyline points="6 9 12 15 18 9"/>
                            </svg>
                        </div>
                    </button>

                    {{-- Check List --}}
                    <div
                        x-show="open"
                        x-collapse
                    >
                        <div class="border-t border-surface-800">
                            @forelse ($checks as $checkIndex => $check)
                                <div
                                    class="{{ $checkIndex > 0 ? 'border-t border-surface-800/60' : '' }}"
                                    x-data="{ expanded: false }"
                                >
                                    {{-- Check Row --}}
                                    <div
                                        class="flex items-start gap-3 px-6 py-4 {{ $check['suggestion'] ? 'cursor-pointer transition-colors hover:bg-surface-800/30' : '' }}"
                                        @if ($check['suggestion'])
                                            @click="expanded = !expanded"
                                            role="button"
                                            tabindex="0"
                                            @keydown.enter="expanded = !expanded"
                                            @keydown.space.prevent="expanded = !expanded"
                                        @endif
                                    >
                                        {{-- Status Icon --}}
                                        <div class="mt-0.5 shrink-0">
                                            @if ($check['status'] === 'pass')
                                                <div class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-400/10">
                                                    <svg class="h-3.5 w-3.5 text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                        <polyline points="20 6 9 17 4 12"/>
                                                    </svg>
                                                </div>
                                            @elseif ($check['status'] === 'warn')
                                                <div class="flex h-6 w-6 items-center justify-center rounded-full bg-amber-400/10">
                                                    <svg class="h-3.5 w-3.5 text-amber-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                                                        <line x1="12" y1="9" x2="12" y2="13"/>
                                                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                                                    </svg>
                                                </div>
                                            @else
                                                <div class="flex h-6 w-6 items-center justify-center rounded-full bg-red-400/10">
                                                    <svg class="h-3.5 w-3.5 text-red-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                        <circle cx="12" cy="12" r="10"/>
                                                        <line x1="15" y1="9" x2="9" y2="15"/>
                                                        <line x1="9" y1="9" x2="15" y2="15"/>
                                                    </svg>
                                                </div>
                                            @endif
                                        </div>

                                        {{-- Check Details --}}
                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="text-sm font-medium text-surface-100">
                                                    {{ $check['name'] }}
                                                </span>

                                                {{-- Severity Badge --}}
                                                @if ($check['severity'] === 'required')
                                                    <span class="rounded bg-surface-800 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wider text-surface-400">
                                                        Required
                                                    </span>
                                                @elseif ($check['severity'] === 'recommended')
                                                    <span class="rounded bg-surface-800 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wider text-surface-500">
                                                        Recommended
                                                    </span>
                                                @endif

                                                {{-- Episode aggregate badge --}}
                                                @if (!empty($check['is_episode_check']) && !empty($check['episode_counts']))
                                                    @php $ec = $check['episode_counts']; @endphp
                                                    <span class="rounded bg-surface-800 px-1.5 py-0.5 text-[10px] font-medium text-surface-400">
                                                        {{ $ec['total'] }} {{ Str::plural('episode', $ec['total']) }}
                                                    </span>
                                                @endif
                                            </div>

                                            <p class="mt-1 text-sm text-surface-400">
                                                {{ $check['message'] }}
                                            </p>

                                            {{-- Episode pass/warn/fail breakdown --}}
                                            @if (!empty($check['is_episode_check']) && !empty($check['episode_counts']))
                                                @php $ec = $check['episode_counts']; @endphp
                                                <div class="mt-2 flex items-center gap-3">
                                                    @if ($ec['pass'] > 0)
                                                        <span class="inline-flex items-center gap-1 text-xs text-emerald-400">
                                                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                                            {{ $ec['pass'] }} passed
                                                        </span>
                                                    @endif
                                                    @if ($ec['warn'] > 0)
                                                        <span class="inline-flex items-center gap-1 text-xs text-amber-400">
                                                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                                                            {{ $ec['warn'] }} warnings
                                                        </span>
                                                    @endif
                                                    @if ($ec['fail'] > 0)
                                                        <span class="inline-flex items-center gap-1 text-xs text-red-400">
                                                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                                                            {{ $ec['fail'] }} failed
                                                        </span>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>

                                        {{-- Expand indicator --}}
                                        @if ($check['suggestion'])
                                            <div class="mt-0.5 shrink-0">
                                                <svg
                                                    class="h-4 w-4 text-surface-600 transition-transform duration-150"
                                                    :class="expanded && 'rotate-180'"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="2"
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                >
                                                    <polyline points="6 9 12 15 18 9"/>
                                                </svg>
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Expandable Fix Suggestion --}}
                                    @if ($check['suggestion'])
                                        <div
                                            x-show="expanded"
                                            x-collapse
                                        >
                                            <div class="mx-6 mb-4 rounded-lg border border-surface-800 bg-surface-950 px-4 py-3">
                                                <div class="flex items-start gap-2.5">
                                                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-brand-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <circle cx="12" cy="12" r="10"/>
                                                        <path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/>
                                                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                                                    </svg>
                                                    <div>
                                                        <p class="text-xs font-medium text-surface-300">How to fix</p>
                                                        <p class="mt-1 text-sm text-surface-400">
                                                            {{ $check['suggestion'] }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @empty
                                <div class="px-6 py-4">
                                    <p class="text-sm text-surface-500">No checks in this category.</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Back Link --}}
        <div class="mt-8 text-center">
            <a
                href="{{ route('home') }}"
                class="inline-flex items-center gap-2 text-sm text-brand-400 transition-colors hover:text-brand-300"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="19" y1="12" x2="5" y2="12"/>
                    <polyline points="12 19 5 12 12 5"/>
                </svg>
                Check another feed
            </a>
        </div>

    </div>
@endsection
