import { Controller } from '@hotwired/stimulus';
import { jsonFetch, toast, money, escapeHtml, flyToCart } from '../teako';

/**
 * Global cart: one instance on <body>. Handles every "add to cart",
 * quantity change and removal, the header badge and the slide-in drawer.
 * Interactions are bound through delegated listeners so dynamically injected
 * drawer content keeps working.
 */
export default class extends Controller {
    static targets = [
        'badgeWrap', 'badgeCount', 'drawer', 'drawerBackdrop',
        'drawerLines', 'drawerEmpty', 'drawerFooter', 'drawerTotal', 'drawerCount',
    ];

    connect() {
        this.state = { count: 0, totalCents: 0, lines: [] };
        this.element.addEventListener('click', this._delegate);
        this.element.addEventListener('change', this._delegateChange);
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.hasDrawerTarget && this.drawerTarget.classList.contains('open')) {
                this.closeDrawer();
            }
        });
        this.refresh();
    }

    /* ---------------- API ---------------- */

    async refresh() {
        const { ok, data } = await jsonFetch('/panier/etat');
        if (!ok) return;
        this.state = data;
        this._applyState();
        this._syncPageRows();
    }

    async addFrom(btn) {
        if (btn.disabled || btn.dataset.busy === '1') return;
        const container = btn.closest('[data-product-block]') || btn;
        const id = btn.dataset.productId || container.dataset.productId || btn.dataset.id;
        if (!id) return;

        const qtyEl = btn.closest('[data-qty-wrap]')?.querySelector('[data-qty]') || container.querySelector('[data-qty]');
        let qty = qtyEl ? parseInt(qtyEl.value, 10) : NaN;
        if (!(qty > 0)) qty = parseInt(btn.dataset.qty || container.dataset.qty || '1', 10);

        btn.disabled = true;
        btn.dataset.busy = '1';
        const label = btn.querySelector('[data-btn-label]');
        if (label) label.textContent = 'Ajout…';

        const { ok, data, status } = await jsonFetch(`/panier/ajouter/${id}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ qty }),
        });

        if (!ok) {
            btn.disabled = false;
            delete btn.dataset.busy;
            const msg = data?.message || (status === 409 ? 'Ce produit est en rupture de stock.' : 'Une erreur est survenue.');
            toast(msg, 'error');
            if (label) label.textContent = btn.dataset.restoreLabel || 'Ajouter au panier';
            return;
        }

        const img = container.querySelector('[data-fly-img] img') || container.querySelector('img');
        if (img) flyToCart(img);
        if (label) label.textContent = 'Ajouté ✓';
        toast(data?.message || 'Ajouté au panier !', 'success', 2200);
        btn.classList.add('btn-added');
        setTimeout(() => {
            if (label) label.textContent = btn.dataset.restoreLabel || 'Ajouter au panier';
            btn.classList.remove('btn-added');
        }, 1700);
        btn.disabled = false;
        delete btn.dataset.busy;

        this.state = data.data;
        this._applyState();
        this._syncPageRows();
    }

    openDrawer() {
        if (!this.hasDrawerTarget) return;
        this.drawerTarget.classList.add('open');
        if (this.hasDrawerBackdropTarget) this.drawerBackdropTarget.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    closeDrawer() {
        if (!this.hasDrawerTarget) return;
        this.drawerTarget.classList.remove('open');
        if (this.hasDrawerBackdropTarget) this.drawerBackdropTarget.classList.add('hidden');
        document.body.style.overflow = '';
    }

    /* ---------------- state sync ---------------- */

    _applyState() {
        const count = this.state.count || 0;
        if (this.hasBadgeCountTarget) this.badgeCountTarget.textContent = count;
        if (this.hasBadgeWrapTarget) this.badgeWrapTarget.classList.toggle('hidden', count === 0);
        this._renderDrawer();
    }

    _renderDrawer() {
        if (!this.hasDrawerLinesTarget) return;
        const lines = this.state.lines || [];
        const empty = lines.length === 0;

        this.drawerLinesTarget.innerHTML = empty ? '' : lines.map((line) => `
            <div class="flex gap-3 py-3 border-b divider">
                <a href="/produit/${escapeHtml(line.slug || '')}" class="shrink-0 w-16 h-16 rounded-xl overflow-hidden bg-surface-3 ring-line">
                    <img src="${escapeHtml(line.image)}" alt="" class="w-full h-full object-cover" loading="lazy">
                </a>
                <div class="flex-1 min-w-0 flex flex-col">
                    <div class="flex items-start justify-between gap-2">
                        <a href="/produit/${escapeHtml(line.slug || '')}" class="text-sm font-bold leading-snug hover:text-[var(--accent)]">${escapeHtml(line.name)}</a>
                        <button class="shrink-0 text-faint hover:text-[var(--negative)] p-0.5 -mt-0.5" data-cart-remove data-line-id="${line.lineId}" aria-label="Retirer">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                        </button>
                    </div>
                    <div class="mt-auto flex items-center justify-between pt-1.5">
                        <div class="inline-flex items-center rounded-full border divider overflow-hidden" data-line-widget>
                            <button class="w-7 h-7 grid place-items-center text-soft hover:text-[var(--accent)]" data-cart-minus data-line-id="${line.lineId}" aria-label="Moins">−</button>
                            <span class="w-6 text-center text-[13px] font-bold tabular-nums">${line.quantity}</span>
                            <button class="w-7 h-7 grid place-items-center text-soft hover:text-[var(--accent)]" data-cart-plus data-line-id="${line.lineId}" aria-label="Plus">+</button>
                        </div>
                        <span class="text-[13px] font-extrabold tabular-nums">${money(line.lineTotalCents)}</span>
                    </div>
                </div>
            </div>`).join('');

        this.drawerLinesTarget.classList.toggle('hidden', empty);
        if (this.hasDrawerEmptyTarget) this.drawerEmptyTarget.classList.toggle('hidden', !empty);
        if (this.hasDrawerFooterTarget) this.drawerFooterTarget.classList.toggle('hidden', empty);
        if (this.hasDrawerTotalTarget) {
            this._bump(this.drawerTotalTarget, money(this.state.totalCents || 0));
        }
        if (this.hasDrawerCountTarget) this.drawerCountTarget.textContent = this.state.count || 0;
    }

    _syncPageRows() {
        const page = document.querySelector('[data-cart-page]');
        if (!page) return;
        const lines = this.state.lines || [];

        page.querySelectorAll('[data-cart-row-id]').forEach((row) => {
            const id = parseInt(row.dataset.cartRowId, 10);
            const line = lines.find((l) => l.lineId === id);
            const input = row.querySelector('[data-qty]');
            const total = row.querySelector('[data-row-total]');
            if (line) {
                if (input) input.value = line.quantity;
                if (total) this._bump(total, money(line.lineTotalCents));
                row.querySelectorAll('[data-cart-plus]').forEach((b) => { b.disabled = line.quantity >= line.stock; });
                row.querySelectorAll('[data-cart-minus]').forEach((b) => { b.disabled = line.quantity <= 1; });
            }
        });

        const emptyWrap = page.querySelector('[data-cart-empty]');
        const bodyWrap = page.querySelector('[data-cart-body]');
        if (emptyWrap && bodyWrap) {
            const empty = lines.length === 0;
            emptyWrap.classList.toggle('hidden', !empty);
            bodyWrap.classList.toggle('hidden', empty);
        }
        const totalEl = page.querySelector('[data-cart-total]');
        if (totalEl) this._bump(totalEl, money(this.state.totalCents || 0));
        const countEl = page.querySelector('[data-cart-count]');
        if (countEl) countEl.textContent = this.state.count || 0;
    }

    _bump(el, text) {
        el.textContent = text;
        el.classList.remove('total-bump');
        void el.offsetWidth;
        el.classList.add('total-bump');
    }

    /* ---------------- mutations ---------------- */

    async _changeQty(lineId, qty) {
        const { ok, data } = await jsonFetch(`/panier/maj/${lineId}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ qty }),
        });
        if (!ok) { toast(data?.message || 'Mise à jour impossible.', 'error'); return; }
        this.state = data.data;
        this._applyState();
        this._syncPageRows();
    }

    async _removeLine(lineId) {
        const row = document.querySelector(`[data-cart-row-id="${lineId}"]`);
        if (row) {
            row.classList.add('leaving');
            await new Promise((r) => setTimeout(r, 360));
            row.remove();
        }
        const { ok, data } = await jsonFetch(`/panier/supprimer/${lineId}`, { method: 'POST' });
        if (!ok) { toast(data?.message || 'Suppression impossible.', 'error'); return; }
        this.state = data.data;
        this._applyState();
        this._syncPageRows();
    }

    _lineQtyFrom(lineId) {
        const row = document.querySelector(`[data-cart-row-id="${lineId}"]`);
        if (row) {
            const input = row.querySelector('[data-qty]');
            if (input) return parseInt(input.value, 10) || 1;
        }
        const widget = document.querySelector(`[data-line-id="${lineId}"]`);
        const span = widget ? widget.parentElement.querySelector('span.tabular-nums') : null;
        return span ? parseInt(span.textContent, 10) || 1 : 1;
    }

    _delegate = (event) => {
        const plus = event.target.closest('[data-cart-plus]');
        const minus = event.target.closest('[data-cart-minus]');
        const remove = event.target.closest('[data-cart-remove]');
        const addBtn = event.target.closest('[data-add-to-cart]');
        const open = event.target.closest('[data-open-cart]');
        const close = event.target.closest('[data-close-cart]');
        const onBackdrop = event.target.hasAttribute('data-cart-backdrop');

        if (addBtn) { event.preventDefault(); this.addFrom(addBtn); return; }
        if (open) { event.preventDefault(); this.openDrawer(); return; }
        if (close || onBackdrop) { this.closeDrawer(); return; }

        if (plus) {
            event.preventDefault();
            this._changeQty(plus.dataset.lineId, this._lineQtyFrom(plus.dataset.lineId) + 1);
            return;
        }
        if (minus) {
            event.preventDefault();
            this._changeQty(minus.dataset.lineId, this._lineQtyFrom(minus.dataset.lineId) - 1);
            return;
        }
        if (remove) {
            event.preventDefault();
            this._removeLine(remove.dataset.lineId);
        }
    };

    _delegateChange = (event) => {
        const qtyInput = event.target.closest('[data-qty]');
        if (qtyInput && qtyInput.dataset.lineId) {
            let q = parseInt(qtyInput.value, 10);
            if (Number.isNaN(q) || q < 1) q = 1;
            qtyInput.value = q;
            this._changeQty(qtyInput.dataset.lineId, q);
        }
    };
}
