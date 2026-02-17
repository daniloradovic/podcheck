import './bootstrap';

import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';

Alpine.plugin(collapse);

Alpine.data('feedChecker', () => ({
    url: '',
    submitting: false,
    currentStep: -1,
    steps: [
        { label: 'Fetching RSS feed...', status: 'pending' },
        { label: 'Parsing feed data...', status: 'pending' },
        { label: 'Running validation checks...', status: 'pending' },
        { label: 'Analyzing SEO & scoring...', status: 'pending' },
        { label: 'Generating report...', status: 'pending' },
    ],

    get currentStepLabel() {
        if (this.currentStep < 0) return 'Starting analysis...';
        if (this.currentStep >= this.steps.length) return 'Redirecting to your report...';
        return this.steps[this.currentStep].label;
    },

    onSubmit() {
        if (this.submitting) return;
        this.submitting = true;
        this.advanceStep();
    },

    advanceStep() {
        this.currentStep++;
        if (this.currentStep < this.steps.length) {
            this.steps[this.currentStep].status = 'active';
            const delay = 400 + Math.random() * 400;
            setTimeout(() => {
                this.steps[this.currentStep].status = 'done';
                this.advanceStep();
            }, delay);
        }
    },

    resetState() {
        this.submitting = false;
        this.currentStep = -1;
        this.steps.forEach((step) => (step.status = 'pending'));
    },

    init() {
        const oldUrl = this.$el.querySelector('input[name="url"]')?.value;
        if (oldUrl) this.url = oldUrl;

        // Reset loading state if page has errors (redirect after failed check)
        if (this.$el.querySelector('[data-has-errors]')) {
            this.resetState();
        }

        // Reset loading state on back/forward navigation (bfcache)
        window.addEventListener('pageshow', (event) => {
            if (event.persisted) {
                this.resetState();
            }
        });
    },
}));

window.Alpine = Alpine;
Alpine.start();
