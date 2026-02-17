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
        if (this.currentStep >= this.steps.length) return 'Preparing your report...';
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
            const delay = this.currentStep === 0 ? 1500 : 2000 + Math.random() * 1500;
            setTimeout(() => {
                this.steps[this.currentStep].status = 'done';
                this.advanceStep();
            }, delay);
        }
    },

    init() {
        const oldUrl = this.$el.querySelector('input[name="url"]')?.value;
        if (oldUrl) this.url = oldUrl;
    },
}));

window.Alpine = Alpine;
Alpine.start();
