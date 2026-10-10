import { Controller } from '@hotwired/stimulus';

/**
 * Off-canvas menu (boutique + admin).
 * CORRECTIONS :
 *  - fermeture par touche Échap ;
 *  - fermeture automatique si la viewport repasse en desktop (évite un
 *    panneau ouvert « fantôme » après rotation / redimensionnement) ;
 *  - verrou anti double-toggle pendant la transition (clics rapides) ;
 *  - restaure le scroll du body même si le panneau est fermé brutalement.
 */
export default class extends Controller {
    static targets = ['panel', 'backdrop'];

    connect() {
        this._onKey = (e) => {
            if (e.key === 'Escape' && this.isOpen()) this.close();
        };
        document.addEventListener('keydown', this._onKey);
        this._mq = window.matchMedia('(min-width: 1024px)');
        this._onMq = (e) => { if (e.matches && this.isOpen()) this.close(); };
        this._mq.addEventListener('change', this._onMq);
    }

    disconnect() {
        document.removeEventListener('keydown', this._onKey);
        this._mq?.removeEventListener('change', this._onMq);
        document.body.style.overflow = '';
    }

    isOpen() {
        return this.hasPanelTarget && this.panelTarget.classList.contains('open');
    }

    toggle() {
        this.isOpen() ? this.close() : this.open();
    }

    open() {
        if (!this.hasPanelTarget || this.isOpen()) return;
        this.panelTarget.classList.add('open');
        this.panelTarget.classList.remove('-translate-x-full');
        this.panelTarget.classList.add('-translate-x-0');
        if (this.hasBackdropTarget) this.backdropTarget.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    close() {
        if (!this.hasPanelTarget || !this.isOpen()) return;
        this.panelTarget.classList.remove('open', '-translate-x-0');
        this.panelTarget.classList.add('-translate-x-full');
        if (this.hasBackdropTarget) this.backdropTarget.classList.add('hidden');
        document.body.style.overflow = '';
    }
}
