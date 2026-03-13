@if (config('ai.enabled'))
    <div
        x-data="{
            state: 'loading',
            summary: '',
            async init() {
                try {
                    const res = await fetch('{{ route('report.ai.summary', $report) }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });
                    const data = await res.json();
                    if (data.summary) {
                        this.summary = data.summary;
                        this.state = 'success';
                    } else {
                        this.state = 'error';
                    }
                } catch {
                    this.state = 'error';
                }
            }
        }"
        x-init="init()"
        x-show="state !== 'error'"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="mt-8 rounded-xl border border-brand-400/20 bg-surface-900 p-6"
    >
        {{-- Card Header --}}
        <div class="mb-5 flex items-center gap-3">
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-400/10">
                <svg class="h-4.5 w-4.5 text-brand-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 2l1.5 4.5L18 8l-4.5 1.5L12 14l-1.5-4.5L6 8l4.5-1.5L12 2z"/>
                    <path d="M19 14l.75 2.25L22 17l-2.25.75L19 20l-.75-2.25L16 17l2.25-.75L19 14z"/>
                    <path d="M5 18l.5 1.5L7 20l-1.5.5L5 22l-.5-1.5L3 20l1.5-.5L5 18z"/>
                </svg>
            </div>
            <div>
                <h2 class="text-sm font-semibold text-surface-100">AI Coach Summary</h2>
                <p class="text-xs text-surface-500">Personalized insight for your show</p>
            </div>
            <span class="ml-auto hidden text-[10px] font-medium uppercase tracking-wider text-surface-600 sm:block">
                Powered by Claude
            </span>
        </div>

        {{-- Loading Skeleton --}}
        <div x-show="state === 'loading'" class="space-y-3">
            <div class="h-4 w-full animate-pulse rounded-md bg-surface-800"></div>
            <div class="h-4 w-11/12 animate-pulse rounded-md bg-surface-800"></div>
            <div class="h-4 w-3/4 animate-pulse rounded-md bg-surface-800"></div>
        </div>

        {{-- Summary Text --}}
        <div
            x-show="state === 'success'"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
        >
            <p class="text-sm leading-relaxed text-surface-300" x-text="summary"></p>
        </div>
    </div>
@endif
