import { Controller } from '@hotwired/stimulus';

/**
 * Interactive product gallery:
 *  - crossfade slides, thumbnail strip with hover scale
 *  - keyboard navigation (← →), swipe on touch devices
 *  - magnifier lens following the pointer (desktop, zoomable images)
 *  - fullscreen lightbox mode with transition
 */
export default class extends Controller {
    static targets = ['stage', 'slide', 'thumb', 'lens', 'count', 'lightbox', 'lightboxImg'];

    connect() {
        this.index = 0;
        this.n = this.slideTargets.length;
        this._onKey = (e) => this._keyboard(e);
        this._onScroll = () => this._parallax();
        document.addEventListener('keydown', this._onKey);

        if (this.n > 0) this.go(0, false);

        this.stageTarget.addEventListener('pointerdown', (e) => this._pointerStart(e));
        this.stageTarget.addEventListener('pointerup', (e) => this._pointerEnd(e));
        this.stageTarget.addEventListener('pointermove', (e) => this._zoomMove(e));
        this.stageTarget.addEventListener('pointerleave', () => this._zoomOff());

        window.addEventListener('scroll', this._onScroll, { passive: true });
        this._parallax();
    }

    disconnect() {
        document.removeEventListener('keydown', this._onKey);
        window.removeEventListener('scroll', this._onScroll);
    }

    go(next, animate = true) {
        const clamped = Math.max(0, Math.min(this.n - 1, next));
        this.index = clamped;
        this.slideTargets.forEach((el, i) => el.classList.toggle('active', i === clamped));
        (this.thumbTargets || []).forEach((el, i) => {
            el.classList.toggle('active', i === clamped);
            el.setAttribute('aria-current', i === clamped ? 'true' : 'false');
        });
        this._zoomOff();
        if (this.hasCountTarget) this.countTarget.textContent = `${clamped + 1} / ${this.n}`;
        if (this.hasLightboxImgTarget && this.lightboxTarget.classList.contains('open')) {
            this.lightboxImgTarget.src = this.slideTargets[clamped].dataset.full;
        }
    }

    next() { this.go(this.index + 1); }
    prev() { this.go(this.index - 1); }

    pick(e) {
        this.go(parseInt(e.currentTarget.dataset.i, 10));
    }

    openLightbox() {
        if (this.n === 0) return;
        this.lightboxTarget.classList.add('open');
        document.body.style.overflow = 'hidden';
        this.go(this.index, false);
    }

    closeLightbox() {
        this.lightboxTarget.classList.remove('open');
        document.body.style.overflow = '';
    }

    /* zoom magnifier */
    _zoomMove(e) {
        if (window.innerWidth < 900 || !this.hasLensTarget) return;
        const slide = this.slideTargets[this.index];
        const img = slide.querySelector('img');
        if (!img || img.dataset.noZoom) return;
        const rect = this.stageTarget.getBoundingClientRect();
        const lx = e.clientX - rect.left;
        const ly = e.clientY - rect.top;
        const lens = this.lensTarget;
        const size = 190;
        lens.style.width = `${size}px`;
        lens.style.height = `${size}px`;
        lens.style.display = 'block';
        lens.classList.add('on');
        const scale = 2;
        const bgW = (rect.width * scale).toFixed(0);
        const bgH = (rect.height * scale).toFixed(0);
        lens.style.backgroundImage = `url('${img.dataset.zoom}')`;
        lens.style.backgroundSize = `${bgW}px ${bgH}px`;
        const px = lx * scale;
        const py = ly * scale;
        lens.style.backgroundPosition = `${-(px - size / 2)}px ${-(py - size / 2)}px`;
        const left = Math.min(Math.max(0, lx - size / 2), rect.width - size);
        const top = Math.min(Math.max(0, ly - size / 2), rect.height - size);
        lens.style.left = `${left}px`;
        lens.style.top = `${top}px`;
    }

    _zoomOff() {
        if (this.hasLensTarget) this.lensTarget.classList.remove('on');
    }

    /* touch swipe */
    _pointerStart(e) {
        this._sx = e.clientX;
        this._sy = e.clientY;
        this._swiping = true;
    }

    _pointerEnd(e) {
        if (!this._swiping) return;
        this._swiping = false;
        const dx = e.clientX - this._sx;
        const dy = e.clientY - this._sy;
        if (Math.abs(dx) > 55 && Math.abs(dx) > Math.abs(dy) * 1.4) {
            if (dx < 0) this.next(); else this.prev();
        }
    }

    _keyboard(e) {
        const tag = (document.activeElement?.tagName || '').toLowerCase();
        if (['input', 'textarea', 'select'].includes(tag)) return;
        if (e.key === 'ArrowRight') { this.next(); e.preventDefault(); }
        else if (e.key === 'ArrowLeft') { this.prev(); e.preventDefault(); }
        else if (e.key === 'Escape' && this.hasLightboxTarget && this.lightboxTarget.classList.contains('open')) {
            this.closeLightbox();
        }
    }

    _parallax() {
        // subtle parallax on the active picture while scrolling
        if (!this.hasStageTarget) return;
        const rect = this.stageTarget.getBoundingClientRect();
        const vh = window.innerHeight;
        const rel = (rect.top + rect.height / 2 - vh / 2) / vh; // -0.5..0.5
        const shift = Math.max(-16, Math.min(16, -rel * 30));
        this.slideTargets.forEach((s) => {
            const img = s.querySelector('img');
            if (img) img.style.translate = `0 ${s.classList.contains('active') ? shift : 0}px`;
        });
    }
}
