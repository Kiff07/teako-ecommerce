import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['panel', 'backdrop'];

    toggle() {
        const open = this.panelTarget.classList.toggle('open');
        this.panelTarget.classList.toggle('-translate-x-0', open);
        this.panelTarget.classList.toggle('-translate-x-full', !open);
        this.backdropTarget.classList.toggle('hidden', !open);
        document.body.style.overflow = open ? 'hidden' : '';
    }

    close() {
        this.panelTarget.classList.remove('open', '-translate-x-0');
        this.panelTarget.classList.add('-translate-x-full');
        this.backdropTarget.classList.add('hidden');
        document.body.style.overflow = '';
    }
}
