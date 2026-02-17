@extends('layouts.app')

@section('title', 'PodCheck')

@section('content')

    {{-- Hero Section --}}
    <div class="relative overflow-hidden">
        {{-- Subtle gradient background --}}
        <div class="absolute inset-0 -z-10">
            <div class="absolute left-1/2 top-0 -translate-x-1/2 -translate-y-1/2 h-[600px] w-[800px] rounded-full bg-brand-500/5 blur-3xl"></div>
        </div>

        <div class="mx-auto max-w-5xl px-4 pb-16 pt-24 sm:px-6 sm:pb-20 sm:pt-32 lg:px-8">
            <div class="text-center">
                <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-surface-800 bg-surface-900 px-4 py-1.5 text-xs font-medium text-surface-300">
                    <svg class="h-3.5 w-3.5 text-brand-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12" />
                    </svg>
                    Free &amp; open-source podcast feed checker
                </div>

                <h1 class="text-4xl font-bold tracking-tight text-surface-50 sm:text-5xl lg:text-6xl">
                    Check your podcast
                    <br class="hidden sm:block">
                    <span class="text-brand-400">RSS feed health</span>
                </h1>

                <p class="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-surface-400">
                    Validate your feed against Apple, Spotify, and Google requirements.
                    Get a detailed health report with SEO scoring and actionable fixes — in seconds.
                </p>
            </div>

            {{-- URL Input Form --}}
            <form
                method="POST"
                action="{{ route('feed.check') }}"
                class="mx-auto mt-10 max-w-2xl"
                x-data="feedChecker()"
                @submit="onSubmit"
            >
                @csrf

                <div class="flex flex-col gap-3 sm:flex-row">
                    <div class="flex-1">
                        <label for="url" class="sr-only">Podcast RSS feed URL</label>
                        <input
                            type="url"
                            id="url"
                            name="url"
                            x-model="url"
                            value="{{ old('url') }}"
                            placeholder="https://feeds.example.com/your-podcast"
                            required
                            :readonly="submitting"
                            :class="submitting && 'opacity-50 cursor-not-allowed'"
                            class="block w-full rounded-lg border bg-surface-900 px-4 py-3.5 text-surface-100 placeholder-surface-500 shadow-sm transition-colors focus:outline-none focus:ring-2 {{ $errors->any() ? 'border-red-400/50 focus:border-red-400 focus:ring-red-400/25' : 'border-surface-700 focus:border-brand-400 focus:ring-brand-400/25' }}"
                        >
                    </div>

                    <button
                        type="submit"
                        :disabled="submitting"
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-500 px-8 py-3.5 text-sm font-semibold text-white shadow-sm transition-all hover:bg-brand-600 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-brand-400/50 focus:ring-offset-2 focus:ring-offset-surface-950 disabled:opacity-70 disabled:cursor-not-allowed disabled:hover:bg-brand-500"
                    >
                        {{-- Default icon --}}
                        <svg x-show="!submitting" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 18V5l12-2v13" />
                            <circle cx="6" cy="18" r="3" />
                            <circle cx="18" cy="16" r="3" />
                        </svg>
                        {{-- Spinner --}}
                        <svg x-show="submitting" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span x-text="submitting ? 'Checking...' : 'Check Feed'"></span>
                    </button>
                </div>

                {{-- Error Alert --}}
                @if ($errors->any())
                    <div data-has-errors class="hidden"></div>
                    @php
                        $errorType = session('error_type', 'validation');
                        $errorMessage = $errors->first('url');

                        $errorConfig = match ($errorType) {
                            'unreachable' => [
                                'title' => 'Feed Unreachable',
                                'icon' => 'wifi-off',
                                'color' => 'amber',
                                'suggestions' => [
                                    'Double-check that the URL is correct and publicly accessible',
                                    'Make sure the feed server is online and responding',
                                    'Try again in a few moments — the server may be temporarily unavailable',
                                ],
                            ],
                            'not_podcast' => [
                                'title' => 'Not a Podcast Feed',
                                'icon' => 'file-x',
                                'color' => 'red',
                                'suggestions' => [
                                    'Make sure the URL points to an RSS or Atom feed, not a website',
                                    'Look for a feed URL in your podcast host\'s dashboard (usually ends in .xml or /feed)',
                                    'Common hosts: Buzzsprout, Libsyn, Anchor, Podbean, Transistor, Castos',
                                ],
                            ],
                            'invalid_url' => [
                                'title' => 'Invalid URL',
                                'icon' => 'link-off',
                                'color' => 'red',
                                'suggestions' => [
                                    'Enter a complete URL starting with https:// or http://',
                                    'Example: https://feeds.example.com/your-podcast',
                                ],
                            ],
                            'unexpected' => [
                                'title' => 'Something Went Wrong',
                                'icon' => 'alert-circle',
                                'color' => 'red',
                                'suggestions' => [
                                    'This is an unexpected error on our end — please try again',
                                    'If the problem persists, the feed may have unusual formatting',
                                ],
                            ],
                            default => [
                                'title' => 'Invalid Input',
                                'icon' => 'alert-circle',
                                'color' => 'red',
                                'suggestions' => [],
                            ],
                        };
                    @endphp

                    <div
                        class="mt-4 overflow-hidden rounded-xl border {{ $errorConfig['color'] === 'amber' ? 'border-amber-400/20 bg-amber-400/5' : 'border-red-400/20 bg-red-400/5' }}"
                        x-data="{ open: true }"
                        x-show="open"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 -translate-y-2"
                        x-transition:enter-end="opacity-100 translate-y-0"
                    >
                        <div class="px-5 py-4">
                            <div class="flex items-start gap-3">
                                {{-- Error Icon --}}
                                <div class="mt-0.5 shrink-0">
                                    @if ($errorConfig['icon'] === 'wifi-off')
                                        <svg class="h-5 w-5 {{ $errorConfig['color'] === 'amber' ? 'text-amber-400' : 'text-red-400' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <line x1="1" y1="1" x2="23" y2="23"/>
                                            <path d="M16.72 11.06A10.94 10.94 0 0119 12.55"/>
                                            <path d="M5 12.55a10.94 10.94 0 015.17-2.39"/>
                                            <path d="M10.71 5.05A16 16 0 0122.56 9"/>
                                            <path d="M1.42 9a15.91 15.91 0 014.7-2.88"/>
                                            <path d="M8.53 16.11a6 6 0 016.95 0"/>
                                            <line x1="12" y1="20" x2="12.01" y2="20"/>
                                        </svg>
                                    @elseif ($errorConfig['icon'] === 'file-x')
                                        <svg class="h-5 w-5 text-red-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                                            <polyline points="14 2 14 8 20 8"/>
                                            <line x1="9.5" y1="12.5" x2="14.5" y2="17.5"/>
                                            <line x1="14.5" y1="12.5" x2="9.5" y2="17.5"/>
                                        </svg>
                                    @elseif ($errorConfig['icon'] === 'link-off')
                                        <svg class="h-5 w-5 text-red-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M15 7h3a5 5 0 015 5 5 5 0 01-1.43 3.5"/>
                                            <path d="M9 17H6A5 5 0 016 7"/>
                                            <line x1="8" y1="12" x2="12" y2="12"/>
                                            <line x1="2" y1="2" x2="22" y2="22"/>
                                        </svg>
                                    @else
                                        <svg class="h-5 w-5 text-red-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <circle cx="12" cy="12" r="10"/>
                                            <line x1="12" y1="8" x2="12" y2="12"/>
                                            <line x1="12" y1="16" x2="12.01" y2="16"/>
                                        </svg>
                                    @endif
                                </div>

                                {{-- Error Content --}}
                                <div class="min-w-0 flex-1">
                                    <h3 class="text-sm font-semibold {{ $errorConfig['color'] === 'amber' ? 'text-amber-400' : 'text-red-400' }}">
                                        {{ $errorConfig['title'] }}
                                    </h3>
                                    <p class="mt-1 text-sm text-surface-300">
                                        {{ $errorMessage }}
                                    </p>

                                    @if (!empty($errorConfig['suggestions']))
                                        <ul class="mt-3 space-y-1.5">
                                            @foreach ($errorConfig['suggestions'] as $suggestion)
                                                <li class="flex items-start gap-2 text-xs text-surface-400">
                                                    <svg class="mt-0.5 h-3 w-3 shrink-0 {{ $errorConfig['color'] === 'amber' ? 'text-amber-400/50' : 'text-red-400/50' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <polyline points="9 18 15 12 9 6"/>
                                                    </svg>
                                                    {{ $suggestion }}
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>

                                {{-- Dismiss Button --}}
                                <button
                                    @click="open = false"
                                    class="shrink-0 rounded-lg p-1 text-surface-500 transition-colors hover:text-surface-300"
                                    aria-label="Dismiss error"
                                >
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="18" y1="6" x2="6" y2="18"/>
                                        <line x1="6" y1="6" x2="18" y2="18"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Loading Progress Panel --}}
                <div
                    x-show="submitting"
                    x-cloak
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 -translate-y-2"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    class="mt-6 overflow-hidden rounded-xl border border-surface-800 bg-surface-900"
                >
                    <div class="px-5 py-4">
                        <div class="flex items-center gap-3">
                            <div class="relative flex h-9 w-9 shrink-0 items-center justify-center">
                                <svg class="h-9 w-9 animate-spin text-brand-400/30" viewBox="0 0 24 24" fill="none">
                                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2.5"></circle>
                                </svg>
                                <svg class="absolute h-9 w-9 animate-spin text-brand-400" style="animation-duration: 1.5s" viewBox="0 0 24 24" fill="none">
                                    <path d="M12 2a10 10 0 010 20" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"></path>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-surface-100" x-text="currentStepLabel"></p>
                                <p class="mt-0.5 text-xs text-surface-500">This usually takes a few seconds</p>
                            </div>
                        </div>
                    </div>

                    {{-- Progress steps --}}
                    <div class="border-t border-surface-800 px-5 py-3">
                        <div class="space-y-2.5">
                            <template x-for="(step, index) in steps" :key="index">
                                <div class="flex items-center gap-3 text-sm">
                                    {{-- Step status icon --}}
                                    <div class="flex h-5 w-5 shrink-0 items-center justify-center">
                                        {{-- Completed --}}
                                        <svg x-show="step.status === 'done'" x-cloak class="h-4 w-4 text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="20 6 9 17 4 12" />
                                        </svg>
                                        {{-- Active --}}
                                        <div x-show="step.status === 'active'" x-cloak class="h-2 w-2 rounded-full bg-brand-400 animate-pulse"></div>
                                        {{-- Pending --}}
                                        <div x-show="step.status === 'pending'" class="h-1.5 w-1.5 rounded-full bg-surface-600"></div>
                                    </div>
                                    <span
                                        :class="{
                                            'text-surface-100': step.status === 'active',
                                            'text-surface-400': step.status === 'done',
                                            'text-surface-600': step.status === 'pending',
                                        }"
                                        x-text="step.label"
                                    ></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </form>

            {{-- Trust Indicators --}}
            <div class="mx-auto mt-8 flex max-w-2xl flex-wrap items-center justify-center gap-x-8 gap-y-3 text-sm text-surface-500">
                <div class="flex items-center gap-2">
                    <svg class="h-4 w-4 text-brand-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12" />
                    </svg>
                    <span>Free &amp; instant</span>
                </div>
                <div class="flex items-center gap-2">
                    <svg class="h-4 w-4 text-brand-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12" />
                    </svg>
                    <span>Apple &amp; Spotify compliant</span>
                </div>
                <div class="flex items-center gap-2">
                    <svg class="h-4 w-4 text-brand-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12" />
                    </svg>
                    <span>Shareable reports</span>
                </div>
            </div>

            {{-- Example Report Link --}}
            @if ($exampleReport)
                <div class="mt-6 text-center">
                    <a
                        href="{{ route('report.show', $exampleReport) }}"
                        class="inline-flex items-center gap-1.5 text-sm text-surface-500 transition-colors hover:text-brand-400"
                    >
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6" />
                            <polyline points="15 3 21 3 21 9" />
                            <line x1="10" y1="14" x2="21" y2="3" />
                        </svg>
                        See an example report
                    </a>
                </div>
            @endif
        </div>
    </div>

    {{-- How It Works Section --}}
    <div class="border-t border-surface-800/60">
        <div class="mx-auto max-w-5xl px-4 py-20 sm:px-6 sm:py-24 lg:px-8">

            <div class="text-center">
                <h2 class="text-2xl font-bold tracking-tight text-surface-50 sm:text-3xl">
                    How it works
                </h2>
                <p class="mx-auto mt-3 max-w-lg text-surface-400">
                    Get your podcast feed health report in three simple steps.
                </p>
            </div>

            <div class="mt-16 grid gap-8 sm:grid-cols-3 sm:gap-6 lg:gap-12">

                {{-- Step 1 --}}
                <div class="relative text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl border border-surface-800 bg-surface-900">
                        <svg class="h-6 w-6 text-brand-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71" />
                            <path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71" />
                        </svg>
                    </div>
                    <div class="mt-1 text-xs font-bold text-brand-400/60">01</div>
                    <h3 class="mt-3 text-lg font-semibold text-surface-100">
                        Paste your feed URL
                    </h3>
                    <p class="mt-2 text-sm leading-relaxed text-surface-400">
                        Enter your podcast RSS feed URL. We accept any valid RSS 2.0 or Atom feed from any hosting provider.
                    </p>
                </div>

                {{-- Step 2 --}}
                <div class="relative text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl border border-surface-800 bg-surface-900">
                        <svg class="h-6 w-6 text-brand-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                        </svg>
                    </div>
                    <div class="mt-1 text-xs font-bold text-brand-400/60">02</div>
                    <h3 class="mt-3 text-lg font-semibold text-surface-100">
                        We run 14+ checks
                    </h3>
                    <p class="mt-2 text-sm leading-relaxed text-surface-400">
                        Your feed is validated against Apple, Spotify, and Google requirements — including artwork, metadata, episodes, and SEO.
                    </p>
                </div>

                {{-- Step 3 --}}
                <div class="relative text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl border border-surface-800 bg-surface-900">
                        <svg class="h-6 w-6 text-brand-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
                            <polyline points="14 2 14 8 20 8" />
                            <line x1="16" y1="13" x2="8" y2="13" />
                            <line x1="16" y1="17" x2="8" y2="17" />
                            <polyline points="10 9 9 9 8 9" />
                        </svg>
                    </div>
                    <div class="mt-1 text-xs font-bold text-brand-400/60">03</div>
                    <h3 class="mt-3 text-lg font-semibold text-surface-100">
                        Get your report
                    </h3>
                    <p class="mt-2 text-sm leading-relaxed text-surface-400">
                        Receive a detailed health score, category breakdowns, and actionable fix suggestions you can share with your team.
                    </p>
                </div>

            </div>
        </div>
    </div>

    {{-- What We Check Section --}}
    <div class="border-t border-surface-800/60 bg-surface-900/30">
        <div class="mx-auto max-w-5xl px-4 py-20 sm:px-6 sm:py-24 lg:px-8">

            <div class="text-center">
                <h2 class="text-2xl font-bold tracking-tight text-surface-50 sm:text-3xl">
                    What we check
                </h2>
                <p class="mx-auto mt-3 max-w-lg text-surface-400">
                    Comprehensive validation across four key areas of podcast feed health.
                </p>
            </div>

            <div class="mt-14 grid gap-6 sm:grid-cols-2">

                {{-- Compliance --}}
                <div class="rounded-xl border border-surface-800 bg-surface-900 p-6 transition-colors hover:border-surface-700">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-surface-800">
                            <svg class="h-5 w-5 text-brand-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                            </svg>
                        </div>
                        <h3 class="text-base font-semibold text-surface-100">Compliance</h3>
                    </div>
                    <p class="mt-3 text-sm leading-relaxed text-surface-400">
                        Artwork dimensions, iTunes categories, explicit tags, owner email — everything Apple and Spotify require for directory listing.
                    </p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <span class="rounded-full bg-surface-800 px-2.5 py-1 text-xs text-surface-400">Artwork</span>
                        <span class="rounded-full bg-surface-800 px-2.5 py-1 text-xs text-surface-400">Categories</span>
                        <span class="rounded-full bg-surface-800 px-2.5 py-1 text-xs text-surface-400">Explicit Tag</span>
                        <span class="rounded-full bg-surface-800 px-2.5 py-1 text-xs text-surface-400">Owner Email</span>
                        <span class="rounded-full bg-surface-800 px-2.5 py-1 text-xs text-surface-400">Enclosure</span>
                    </div>
                </div>

                {{-- Technical --}}
                <div class="rounded-xl border border-surface-800 bg-surface-900 p-6 transition-colors hover:border-surface-700">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-surface-800">
                            <svg class="h-5 w-5 text-brand-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="16 18 22 12 16 6"/>
                                <polyline points="8 6 2 12 8 18"/>
                            </svg>
                        </div>
                        <h3 class="text-base font-semibold text-surface-100">Technical</h3>
                    </div>
                    <p class="mt-3 text-sm leading-relaxed text-surface-400">
                        Feed structure, language tags, valid GUIDs, publication dates, episode durations, and website links.
                    </p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <span class="rounded-full bg-surface-800 px-2.5 py-1 text-xs text-surface-400">Language</span>
                        <span class="rounded-full bg-surface-800 px-2.5 py-1 text-xs text-surface-400">GUIDs</span>
                        <span class="rounded-full bg-surface-800 px-2.5 py-1 text-xs text-surface-400">Pub Dates</span>
                        <span class="rounded-full bg-surface-800 px-2.5 py-1 text-xs text-surface-400">Duration</span>
                        <span class="rounded-full bg-surface-800 px-2.5 py-1 text-xs text-surface-400">Website Link</span>
                    </div>
                </div>

                {{-- Best Practices --}}
                <div class="rounded-xl border border-surface-800 bg-surface-900 p-6 transition-colors hover:border-surface-700">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-surface-800">
                            <svg class="h-5 w-5 text-brand-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                            </svg>
                        </div>
                        <h3 class="text-base font-semibold text-surface-100">Best Practices</h3>
                    </div>
                    <p class="mt-3 text-sm leading-relaxed text-surface-400">
                        Author attribution, show and episode descriptions, descriptive episode titles — the quality signals that set great podcasts apart.
                    </p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <span class="rounded-full bg-surface-800 px-2.5 py-1 text-xs text-surface-400">Author</span>
                        <span class="rounded-full bg-surface-800 px-2.5 py-1 text-xs text-surface-400">Descriptions</span>
                        <span class="rounded-full bg-surface-800 px-2.5 py-1 text-xs text-surface-400">Episode Titles</span>
                    </div>
                </div>

                {{-- SEO --}}
                <div class="rounded-xl border border-surface-800 bg-surface-900 p-6 transition-colors hover:border-surface-700">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-surface-800">
                            <svg class="h-5 w-5 text-brand-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="11" cy="11" r="8"/>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                            </svg>
                        </div>
                        <h3 class="text-base font-semibold text-surface-100">SEO Scoring</h3>
                    </div>
                    <p class="mt-3 text-sm leading-relaxed text-surface-400">
                        Title length analysis, description quality scoring, keyword stuffing detection, and episode title pattern analysis.
                    </p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <span class="rounded-full bg-surface-800 px-2.5 py-1 text-xs text-surface-400">Title Length</span>
                        <span class="rounded-full bg-surface-800 px-2.5 py-1 text-xs text-surface-400">Description Quality</span>
                        <span class="rounded-full bg-surface-800 px-2.5 py-1 text-xs text-surface-400">Keyword Stuffing</span>
                    </div>
                </div>

            </div>
        </div>
    </div>

    {{-- Bottom CTA --}}
    <div class="border-t border-surface-800/60">
        <div class="mx-auto max-w-5xl px-4 py-20 sm:px-6 sm:py-24 lg:px-8">
            <div class="text-center">
                <h2 class="text-2xl font-bold tracking-tight text-surface-50 sm:text-3xl">
                    Ready to check your feed?
                </h2>
                <p class="mx-auto mt-3 max-w-lg text-surface-400">
                    It takes less than 10 seconds. Paste your RSS feed URL and see how your podcast stacks up.
                </p>

                <div class="mt-8">
                    <a
                        href="#url"
                        onclick="document.getElementById('url').focus(); return false;"
                        class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-8 py-3.5 text-sm font-semibold text-white shadow-sm transition-all hover:bg-brand-600 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-brand-400/50 focus:ring-offset-2 focus:ring-offset-surface-950"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 18V5l12-2v13" />
                            <circle cx="6" cy="18" r="3" />
                            <circle cx="18" cy="16" r="3" />
                        </svg>
                        Check Your Feed Now
                    </a>
                </div>

                @if ($exampleReport)
                    <p class="mt-4 text-sm text-surface-500">
                        or
                        <a
                            href="{{ route('report.show', $exampleReport) }}"
                            class="text-brand-400 transition-colors hover:text-brand-300"
                        >see an example report</a>
                    </p>
                @endif
            </div>
        </div>
    </div>

@endsection
