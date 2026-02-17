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

        {{-- Placeholder: Check results will be built in Tasks 18-20 --}}
        <div class="mt-8 rounded-xl border border-surface-800 bg-surface-900 p-6 sm:p-8">
            <h2 class="text-lg font-semibold text-surface-200">Detailed Results</h2>
            <p class="mt-2 text-sm text-surface-400">
                Check-by-check results, category breakdowns, and episode summaries will appear here.
            </p>

            <div class="mt-4 overflow-x-auto rounded-lg border border-surface-800 bg-surface-950 p-4">
                <pre class="text-xs text-surface-500"><code>{{ json_encode($report->results_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
            </div>
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
