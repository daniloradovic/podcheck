<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', 'PodCheck') — Podcast RSS Feed Health Checker</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen flex flex-col bg-surface-950 text-surface-100 antialiased">

        {{-- Navigation --}}
        <nav class="border-b border-surface-800">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 items-center justify-between">
                    <a href="{{ url('/') }}" class="flex items-center gap-2">
                        <svg class="h-7 w-7 text-brand-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 18V5l12-2v13" />
                            <circle cx="6" cy="18" r="3" />
                            <circle cx="18" cy="16" r="3" />
                        </svg>
                        <span class="text-lg font-semibold tracking-tight text-surface-50">
                            Pod<span class="text-brand-400">Check</span>
                        </span>
                    </a>

                    <a
                        href="https://github.com"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-sm text-surface-400 transition-colors hover:text-surface-200"
                    >
                        GitHub
                    </a>
                </div>
            </div>
        </nav>

        {{-- Main Content --}}
        <main class="flex-1">
            @yield('content')
        </main>

        {{-- Footer --}}
        <footer class="border-t border-surface-800">
            <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
                <div class="flex flex-col items-center justify-between gap-4 sm:flex-row">
                    <p class="text-sm text-surface-500">
                        &copy; {{ date('Y') }} PodCheck. Free podcast feed health checker.
                    </p>
                    <div class="flex items-center gap-6 text-sm text-surface-500">
                        <span>Built with Laravel</span>
                    </div>
                </div>
            </div>
        </footer>

    </body>
</html>
