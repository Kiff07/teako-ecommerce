import { Controller } from '@hotwired/stimulus';

const KEY = 'tk-theme';

export default class extends Controller {
    static targets = ['button'];

    connect() {
        const saved = localStorage.getItem(KEY);
        const systemDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        this.apply(saved ? saved === 'dark' : systemDark);
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
            if (!localStorage.getItem(KEY)) this.apply(e.matches);
        });
    }

    toggle() {
        this.apply(document.documentElement.dataset.theme !== 'dark');
        this.buttonTarget.classList.add('heart-pop');
        setTimeout(() => this.buttonTarget.classList.remove('heart-pop'), 500);
    }

    apply(dark) {
        const root = document.documentElement;
        root.dataset.theme = dark ? 'dark' : 'light';
        localStorage.setItem(KEY, dark ? 'dark' : 'light');
        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) meta.setAttribute('content', dark ? '#1c1511' : '#fdfaf4');
        if (this.hasButtonTarget) {
            this.buttonTarget.innerHTML = dark
                ? '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="4.2"/><path d="M12 2.5v2.4M12 19.1v2.4M2.5 12h2.4M19.1 12h2.4M4.9 4.9l1.7 1.7M17.4 17.4l1.7 1.7M4.9 19.1l1.7-1.7M17.4 6.6l1.7-1.7"/></svg>'
                : '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M20.6 14.2A8.5 8.5 0 0 1 9.8 3.4a8.5 8.5 0 1 0 10.8 10.8z"/></svg>';
        }
    }
}
