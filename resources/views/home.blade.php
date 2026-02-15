@extends('layouts.app')

@section('title', 'PodCheck')

@section('content')
    <div class="mx-auto max-w-5xl px-4 py-24 sm:px-6 lg:px-8">

        {{-- Hero --}}
        <div class="text-center">
            <h1 class="text-4xl font-bold tracking-tight text-surface-50 sm:text-5xl">
                Check your podcast
                <span class="text-brand-400">RSS feed health</span>
            </h1>
            <p class="mx-auto mt-4 max-w-2xl text-lg text-surface-400">
                Validate your feed against Apple, Spotify, and Google requirements.
                Get actionable fixes in seconds.
            </p>
        </div>

        {{-- URL Input Form --}}
        <form
            method="POST"
            action="{{ route('feed.check') }}"
            class="mx-auto mt-12 max-w-2xl"
            x-data="{ url: '' }"
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
                        class="block w-full rounded-lg border border-surface-700 bg-surface-900 px-4 py-3 text-surface-100 placeholder-surface-500 shadow-sm transition-colors focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-400/25"
                    >
                </div>

                <button
                    type="submit"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-500 px-6 py-3 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-400/50 focus:ring-offset-2 focus:ring-offset-surface-950"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 18V5l12-2v13" />
                        <circle cx="6" cy="18" r="3" />
                        <circle cx="18" cy="16" r="3" />
                    </svg>
                    Check Feed
                </button>
            </div>

            @error('url')
                <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </form>

        {{-- Trust Indicators --}}
        <div class="mx-auto mt-16 flex max-w-2xl flex-wrap items-center justify-center gap-x-8 gap-y-4 text-sm text-surface-500">
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

    </div>
@endsection
