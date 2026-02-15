@extends('layouts.app')

@section('title', ($report->feed_title ?? 'Feed Report') . ' — PodCheck')

@section('content')
    <div class="mx-auto max-w-5xl px-4 py-12 sm:px-6 lg:px-8">

        {{-- Report Header --}}
        <div class="rounded-xl border border-surface-800 bg-surface-900 p-6 sm:p-8">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 flex-1">
                    <h1 class="truncate text-2xl font-bold text-surface-50 sm:text-3xl">
                        {{ $report->feed_title ?? 'Unknown Podcast' }}
                    </h1>
                    <p class="mt-2 break-all text-sm text-surface-400">
                        {{ $report->feed_url }}
                    </p>
                    <p class="mt-1 text-xs text-surface-500">
                        Checked {{ $report->created_at->diffForHumans() }}
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center rounded-full bg-surface-800 px-3 py-1 text-sm font-medium text-surface-300">
                        Score: {{ $report->overall_score }}/100
                    </span>
                </div>
            </div>
        </div>

        {{-- Raw Results Data --}}
        <div class="mt-8">
            <h2 class="text-lg font-semibold text-surface-200">Raw Report Data</h2>
            <p class="mt-1 text-sm text-surface-400">
                Full validation results will appear here once the validation engine is wired in.
            </p>

            <div class="mt-4 overflow-x-auto rounded-lg border border-surface-800 bg-surface-900 p-4">
                <pre class="text-sm text-surface-300"><code>{{ json_encode($report->results_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
            </div>
        </div>

        {{-- Report Metadata --}}
        <div class="mt-8 rounded-lg border border-surface-800 bg-surface-900 p-6">
            <h2 class="text-lg font-semibold text-surface-200">Report Details</h2>
            <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-sm font-medium text-surface-400">Report ID</dt>
                    <dd class="mt-1 text-sm text-surface-200">{{ $report->slug }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-surface-400">Feed URL</dt>
                    <dd class="mt-1 break-all text-sm text-surface-200">{{ $report->feed_url }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-surface-400">Feed Title</dt>
                    <dd class="mt-1 text-sm text-surface-200">{{ $report->feed_title ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-surface-400">Checked At</dt>
                    <dd class="mt-1 text-sm text-surface-200">{{ $report->created_at->format('M j, Y g:i A') }}</dd>
                </div>
            </dl>
        </div>

        {{-- Back Link --}}
        <div class="mt-8 text-center">
            <a
                href="{{ route('home') }}"
                class="inline-flex items-center gap-2 text-sm text-brand-400 transition-colors hover:text-brand-300"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="19" y1="12" x2="5" y2="12" />
                    <polyline points="12 19 5 12 12 5" />
                </svg>
                Check another feed
            </a>
        </div>

    </div>
@endsection
