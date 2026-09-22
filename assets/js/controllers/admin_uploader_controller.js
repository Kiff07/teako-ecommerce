import { Controller } from '@hotwired/stimulus';
import { jsonFetch, toast, csrfToken } from '../teako';

/**
 * Admin product-form image manager:
 *  - drag & drop drop-zone (multiple files)
 *  - upload queue with previews, alt text, "main image" star, crop & remove
 *  - HTML5 drag & drop reordering
 *  - client-side crop modal with aspect presets (calls the server crop endpoint)
 * Writes the serialized state into the hidden `imagesJson` field before submit.
 */
export default class extends Controller {
    static targets = ['drop', 'input', 'queue', 'imagesJson', 'cropModal', 'cropImg', 'cropBox', 'spinner', 'hint'];

    connect() {
        this.items = this._loadInitial();
        this.dragIndex = null;
        this.cropItem = null;
        this.cropRatio = 1;
        this.cropRect = null;

        ['dragenter', 'dragover'].forEach((ev) => this.dropTarget.addEventListener(ev, (e) => {
            e.preventDefault();
            this.dropTarget.classList.add('drop-target');
        }));
        ['dragleave', 'drop'].forEach((ev) => this.dropTarget.addEventListener(ev, (e) => {
            e.preventDefault();
            this.dropTarget.classList.remove('drop-target');
        }));
        this.dropTarget.addEventListener('drop', (e) => this._upload(e.dataTransfer.files));
        this.dropTarget.addEventListener('click', () => this.choose());
        this.dropTarget.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); this.choose(); }
        });

        // keyboard delete on queue items
        this.queueTarget.addEventListener('click', (e) => {
            const remove = e.target.closest('[data-action-remove]');
            const crop = e.target.closest('[data-action-crop]');
            const main = e.target.closest('[data-action-main]');
            if (remove) this.remove(remove.closest('li'));
            if (crop) this.openCrop(crop.closest('li'));
            if (main) this.setMain(main.closest('li'));
        });
        this.queueTarget.addEventListener('input', (e) => {
            const alt = e.target.closest('[data-alt]');
            if (alt) this.serialize();
        });
        this.queueTarget.addEventListener('change', (e) => {
            const alt = e.target.closest('[data-alt]');
            if (alt) this.serialize();
        });

        this._dragSetup();
        this.render();
    }

    /* ------------ file upload ------------ */

    choose() { this.inputTarget.click(); }
    handleFiles() { if (this.inputTarget.files.length) this._upload(this.inputTarget.files); this.inputTarget.value = ''; }

    async _upload(files) {
        const token = this.token;
        const productId = this.data.get('productId') || '';
        for (const file of Array.from(files || [])) {
            const body = new FormData();
            body.append('file', file);
            if (productId) body.append('productId', productId);
            body.append('token', token);
            const { ok, data } = await jsonFetch('/admin/produits/upload', { method: 'POST', body });
            if (!ok) {
                toast(data?.message || 'Import impossible.', 'error');
                continue;
            }
            this.items.push({ filename: data.url, thumb: data.thumb, alt: file.name.replace(/\.[a-zA-Z0-9]+$/, '').replace(/[-_]+/g, ' '), main: this.items.length === 0 });
            this.render();
            toast('Image importée ✓', 'success', 1800);
        }
    }

    /* ------------ list operations ------------ */

    remove(li) {
        const item = this.items[li?.dataset.i];
        if (!item) return;
        const url = item.filename;
        this.items.splice(parseInt(li.dataset.i, 10), 1);
        this.render();
        jsonFetch('/admin/produits/fichier', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ url }),
        }).then(({ ok }) => {
            if (!ok) toast('Le fichier sera supprimé à la sauvegarde.', 'info', 2600);
        });
    }

    setMain(li) {
        const idx = parseInt(li.dataset.i, 10);
        this.items.forEach((it) => { it.main = false; });
        this.items[idx].main = true;
        this.render();
    }

    serialize() {
        this.queueTarget.querySelectorAll('li[data-i]').forEach((li) => {
            const idx = parseInt(li.dataset.i, 10);
            const item = this.items[idx];
            const alt = li.querySelector('[data-alt]');
            if (item && alt) item.alt = alt.value;
        });
        const payload = this.items.map((it, i) => ({ filename: it.filename, alt: it.alt, main: !!it.main, order: i }));
        this.imagesJsonTarget.value = JSON.stringify(payload);
    }

    /* ------------ reorder (drag & drop) ------------ */

    _dragSetup() {
        this.queueTarget.addEventListener('dragstart', (e) => {
            const li = e.target.closest('li[data-i]');
            if (!li) return;
            this.dragIndex = parseInt(li.dataset.i, 10);
            li.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });
        this.queueTarget.addEventListener('dragover', (e) => {
            e.preventDefault();
            const li = e.target.closest('li[data-i]');
            if (li && li.dataset.i !== String(this.dragIndex)) li.classList.add('drop-target');
        });
        this.queueTarget.addEventListener('dragleave', (e) => {
            const li = e.target.closest('li[data-i]');
            li?.classList.remove('drop-target');
        });
        this.queueTarget.addEventListener('drop', (e) => {
            e.preventDefault();
            const li = e.target.closest('li[data-i]');
            const to = li ? parseInt(li.dataset.i, 10) : this.items.length - 1;
            if (this.dragIndex !== null && to !== this.dragIndex) {
                const [moved] = this.items.splice(this.dragIndex, 1);
                this.items.splice(to, 0, moved);
            }
            this.queueTarget.querySelectorAll('.drop-target').forEach((el) => el.classList.remove('drop-target'));
            this.render();
        });
        this.queueTarget.addEventListener('dragend', () => {
            this.queueTarget.querySelectorAll('.dragging,.drop-target').forEach((el) => el.classList.remove('dragging', 'drop-target'));
        });
    }

    /* ------------ rendering ------------ */

    render() {
        this.queueTarget.innerHTML = this.items.map((item, i) => `
            <li class="group relative rounded-2xl border divider overflow-hidden bg-surface cursor-grab active:cursor-grabbing" data-i="${i}" draggable="true" title="Glisser pour réordonner">
                <div class="relative aspect-square bg-surface-3">
                    <img src="${item.thumb || this._thumb(item.filename)}" alt="" class="w-full h-full object-cover" loading="lazy">
                    ${item.main ? '<span class="absolute top-2 left-2 px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wide" style="background:#d05f3a;color:#fff">Principale</span>' : ''}
                    <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/50 to-transparent p-1.5 opacity-0 group-hover:opacity-100 transition">
                        <div class="flex justify-center gap-1">
                            <button type="button" data-action-remove class="p-1.5 rounded-lg bg-white/90 text-red-500 hover:bg-white" title="Supprimer">✕</button>
                            <button type="button" data-action-crop class="p-1.5 rounded-lg bg-white/90 text-neutral-700 hover:bg-white" title="Recadrer">⊞</button>
                            <button type="button" data-action-main class="p-1.5 rounded-lg bg-white/90 text-amber-500 hover:bg-white" title="Image principale">★</button>
                        </div>
                    </div>
                </div>
                <div class="p-2">
                    <input type="text" data-alt value="${this._esc(item.alt)}" placeholder="Texte alternatif"
                        class="w-full text-[11px] bg-transparent border border-transparent hover:border-neutral-300 rounded-md px-1.5 py-1 focus:border-[var(--accent)] focus:outline-none">
                </div>
            </li>`).join('');

        const empty = this.items.length === 0;
        this.hintTarget?.classList.toggle('hidden', !empty);
        this.serialize();
    }

    /* ------------ crop modal ------------ */

    openCrop(li) {
        const item = this.items[parseInt(li.dataset.i, 10)];
        if (!item) return;
        this.cropItem = item;
        this.cropImgTarget.src = item.thumb || this._thumb(item.filename);
        this.cropImgTarget.onload = () => this._layoutCrop();
        this.cropModalTarget.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    closeCrop() {
        this.cropModalTarget.classList.add('hidden');
        document.body.style.overflow = '';
        this.cropItem = null;
    }

    setRatio(e) {
        this.cropRatio = parseFloat(e.currentTarget.dataset.ratio || '0');
        this._layoutCrop();
    }

    _layoutCrop() {
        // work in display pixels of the <img> element, then convert to %
        const el = this.cropImgTarget;
        const w = el.clientWidth || el.naturalWidth;
        const h = el.clientHeight || el.naturalHeight;
        let cw = w, ch = h;
        if (this.cropRatio > 0) {
            if (w / h > this.cropRatio) { ch = h; cw = h * this.cropRatio; } else { cw = w; ch = w / this.cropRatio; }
        }
        this.cropRect = { x: (w - cw) / 2, y: (h - ch) / 2, w: cw, h: ch };
        this._paint();
    }

    _paint() {
        if (!this.cropRect) return;
        const r = this.cropRect;
        this.cropBoxTarget.style.left = `${r.x}px`;
        this.cropBoxTarget.style.top = `${r.y}px`;
        this.cropBoxTarget.style.width = `${r.w}px`;
        this.cropBoxTarget.style.height = `${r.h}px`;
    }

    _startDrag(e) {
        e.preventDefault();
        const origin = { ...this.cropRect, sx: e.clientX, sy: e.clientY };
        const move = (ev) => {
            const dx = ev.clientX - origin.sx;
            const dy = ev.clientY - origin.sy;
            const dw = this.cropImgTarget.clientWidth || this.cropImgTarget.naturalWidth;
            const dh = this.cropImgTarget.clientHeight || this.cropImgTarget.naturalHeight;
            const maxX = Math.max(0, dw - this.cropRect.w);
            const maxY = Math.max(0, dh - this.cropRect.h);
            this.cropRect.x = Math.max(0, Math.min(origin.x + dx, maxX));
            this.cropRect.y = Math.max(0, Math.min(origin.y + dy, maxY));
            this._paint();
        };
        const up = () => { window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', up); };
        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', up);
    }

    async applyCrop() {
        if (!this.cropItem || !this.cropRect) return;
        const item = this.cropItem;
        const img = this.cropImgTarget;
        const totalW = img.clientWidth || img.naturalWidth;
        const totalH = img.clientHeight || img.naturalHeight;
        const r = this.cropRect;
        const payload = {
            url: item.filename,
            x: +(r.x / totalW).toFixed(4),
            y: +(r.y / totalH).toFixed(4),
            w: +(r.w / totalW).toFixed(4),
            h: +(r.h / totalH).toFixed(4),
        };
        this.spinnerTarget.classList.remove('hidden');
        const { ok, data } = await jsonFetch('/admin/produits/recadrer', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        this.spinnerTarget.classList.add('hidden');
        if (!ok) { toast(data?.message || 'Recadrage impossible.', 'error'); return; }
        item.filename = data.url;
        item.thumb = data.thumb;
        this.closeCrop();
        this.render();
        toast('Image recadrée ✓', 'success', 2000);
    }

    /* ------------ helpers ------------ */

    get token() {
        return this.data.get('token') || 'u' + Math.random().toString(36).slice(2, 10);
    }

    _loadInitial() {
        try {
            const parsed = JSON.parse(this.imagesJsonTarget.value || '[]');
            return Array.isArray(parsed) ? parsed.map((p) => ({
                filename: p.filename, alt: p.alt || '', main: !!p.main,
            })) : [];
        } catch (_) {
            return [];
        }
    }

    _thumb(url) {
        if (url.startsWith('http')) return url;
        return url.replace(/\.[a-zA-Z0-9]+$/, '-thumb.jpg');
    }

    _esc(s) {
        return String(s || '').replaceAll('&', '&amp;').replaceAll('"', '&quot;').replaceAll('<', '&lt;').replaceAll('>', '&gt;');
    }
}
