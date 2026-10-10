import { Controller } from '@hotwired/stimulus';
import { jsonFetch, toast, money, escapeHtml, flyToCart } from '../teako';

/**
 * Global cart: one instance on <body>. Handles every "add to cart",
 * quantity change and removal, the header badge and the slide-in drawer.
 * Interactions are bound through delegated listeners so dynamically injected
 * drawer content keeps working.
 *
 * ─── CORRECTIONS APPORTÉES (bug « − supprime tout / + bloqué à 2 ») ───
 * 1. La quantité courante n'est plus lue dans le DOM du tiroir (lecture
 *    fragile qui échouait et retombait sur `return 1` : moins → 1-1 = 0 →
 *    suppression, plus → 1+1 = 2 → blocage à 2). Elle est maintenant lue
 *    dans `this.state.lines`, l'état synchronisé avec le serveur. La lecture
 *    DOM ne sert plus que de secours.
 * 2. Les réponses serveur sont normalisées : certains endpoints renvoient
 *    l'état au premier niveau (`{count, totalCents, lines}`), d'autres
 *    enveloppé (`{message, data: {...}}`). Avant, `this.state = data.data`
 *    pouvait valoir `undefined` et faire planter `_applyState()`, donc
 *    empêcher tout re-rendu du tiroir.
 * 3. Les noms de champs des lignes sont normalisés aussi (`qty|quantity`,
 *    `lineId|id|productId`, `lineTotalCents|totalCents|priceCents*qty`).
 * 4. Garde anti double-clic par ligne (un clic pendant une requête en cours
 *    est ignoré) pour éviter les sauts de quantité.
 * 5. Si le tiroir n'est pas re-rendu par Stimulus (targets Twig absents),
 *    la ligne affichée est retouchée en place (quantité, total, suppression).
 */
export default class extends Controller {
    static targets = [
        'badgeWrap', 'badgeCount', 'drawer', 'drawerBackdrop',
        'drawerLines', 'drawerEmpty', 'drawerFooter', 'drawerTotal', 'drawerCount',
    ];

    connect() {
        this.state = { count: 0, totalCents: 0, lines: [] };
        this._pending = new Set();
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
        this._adoptSync(data);
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

        await this._adopt(data);
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

    /**
     * Accepte les deux formes de réponse serveur et les deux jeux de noms
     * de champs, et garantit un état complet : { count, totalCents, lines[] }
     * avec, par ligne : lineId, quantity, priceCents, lineTotalCents, stock,
     * name, image, slug.
     */
    _normalize(raw) {
        const src = raw && Array.isArray(raw.lines)
            ? raw
            : (raw && raw.data && Array.isArray(raw.data.lines) ? raw.data : null);
        if (!src) return null;

        const lines = (src.lines || []).map((l) => {
            const quantity = Number(l.quantity ?? l.qty ?? 1);
            const priceCents = Number(l.priceCents ?? l.price ?? 0);
            return {
                ...l,
                lineId: String(l.lineId ?? l.id ?? l.productId ?? l.product_id ?? ''),
                quantity: Number.isFinite(quantity) ? quantity : 1,
                priceCents,
                stock: (l.stock ?? l.stockQuantity) != null ? Number(l.stock ?? l.stockQuantity) : null,
                lineTotalCents: Number(l.lineTotalCents ?? l.totalCents ?? priceCents * quantity),
            };
        });

        const count = Number.isFinite(src.count)
            ? Number(src.count)
            : lines.reduce((n, l) => n + l.quantity, 0);
        const totalCents = Number.isFinite(src.totalCents)
            ? Number(src.totalCents)
            : lines.reduce((n, l) => n + l.lineTotalCents, 0);

        return { count, totalCents, lines };
    }

    /** Synchrone : true si le payload a pu être adopté, false sinon. */
    _adoptSync(payload) {
        const next = this._normalize(payload);
        if (!next) return false;
        this.state = next;
        this._applyState();
        this._syncPageRows();
        return true;
    }

    /** Asynchrone : si la forme est inconnue, on se resynchronise via /panier/etat. */
    async _adopt(payload) {
        if (this._adoptSync(payload)) return;
        await this.refresh();
    }

    _findLine(lineId) {
        const id = String(lineId);
        return (this.state.lines || []).find((l) => l.lineId === id) || null;
    }

    /**
     * Quantité courante d'une ligne. Source de vérité : l'état synchronisé
     * avec le serveur. La lecture DOM n'est qu'un filet de secours.
     */
    _currentQty(lineId, btn = null) {
        const line = this._findLine(lineId);
        if (line) return line.quantity;

        if (btn) {
            const widget = btn.closest('[data-line-widget], [data-qty-wrap], [data-stepper]');
            const qtyEl = widget && (widget.querySelector('[data-line-qty]')
                || widget.querySelector('input[data-qty]')
                || widget.querySelector('span'));
            if (qtyEl) {
                const raw = qtyEl.tagName === 'INPUT' ? qtyEl.value : qtyEl.textContent;
                const parsed = parseInt(String(raw).trim(), 10);
                if (!Number.isNaN(parsed) && parsed > 0) return parsed;
            }
        }

        const row = document.querySelector(`[data-cart-row-id="${lineId}"]`);
        const input = row && row.querySelector('[data-qty]');
        if (input) return parseInt(input.value, 10) || 1;

        return 1;
    }

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
            <div class="cart-line flex gap-3 py-3 border-b divider">
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
                            <span data-line-qty class="w-6 text-center text-[13px] font-bold tabular-nums">${line.quantity}</span>
                            <button class="w-7 h-7 grid place-items-center text-soft hover:text-[var(--accent)]" data-cart-plus data-line-id="${line.lineId}" aria-label="Plus">+</button>
                        </div>
                        <span data-line-total class="text-[13px] font-extrabold tabular-nums">${money(line.lineTotalCents)}</span>
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
            const id = String(row.dataset.cartRowId);
            const line = lines.find((l) => l.lineId === id);
            const input = row.querySelector('[data-qty]');
            const total = row.querySelector('[data-row-total]');
            if (line) {
                if (input) input.value = line.quantity;
                if (total) this._bump(total, money(line.lineTotalCents));
                row.querySelectorAll('[data-cart-plus]').forEach((b) => { b.disabled = line.stock != null && line.quantity >= line.stock; });
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

    /**
     * Filet de sécurité : si le tiroir n'est pas rendu par Stimulus
     * (targets Twig absents), on retouche la ligne affichée en place.
     */
    _patchDrawerLine(lineId) {
        if (this.hasDrawerLinesTarget) return; // déjà re-rendu entièrement
        const line = this._findLine(lineId);
        const btn = this.element.querySelector(`[data-cart-minus][data-line-id="${lineId}"], [data-cart-plus][data-line-id="${lineId}"]`);
        const widget = btn && btn.closest('[data-line-widget], [data-qty-wrap], [data-stepper]');
        if (!widget) return;

        const qtyEl = widget.querySelector('[data-line-qty]') || widget.querySelector('input[data-qty]') || widget.querySelector('span');
        if (qtyEl && line) {
            if (qtyEl.tagName === 'INPUT') qtyEl.value = line.quantity;
            else qtyEl.textContent = line.quantity;
        }
        const totalEl = widget.parentElement && widget.parentElement.querySelector('[data-line-total], [data-row-total]');
        if (totalEl && line) totalEl.textContent = money(line.lineTotalCents);
    }

    /** Retrouve le conteneur « ligne » (tiroir ou page panier) pour l'animation/suppression DOM. */
    _drawerRow(lineId) {
        const rowById = document.querySelector(`[data-cart-row-id="${lineId}"]`);
        if (rowById) return rowById;
        const btn = this.element.querySelector(
            `[data-cart-minus][data-line-id="${lineId}"], [data-cart-plus][data-line-id="${lineId}"], [data-cart-remove][data-line-id="${lineId}"]`
        );
        if (!btn) return null;
        const direct = btn.closest('[data-drawer-line], .cart-line');
        if (direct) return direct;
        let node = btn.closest('[data-line-widget], [data-qty-wrap], [data-stepper]') || btn;
        for (let i = 0; i < 6 && node; i++) {
            node = node.parentElement;
            if (node && node.querySelector('[data-cart-remove]') && node.querySelector('img')) return node;
        }
        return null;
    }

    _bump(el, text) {
        el.textContent = text;
        el.classList.remove('total-bump');
        void el.offsetWidth;
        el.classList.add('total-bump');
    }

    /* ---------------- mutations ---------------- */

    async _changeQty(lineId, qty) {
        const key = `qty:${lineId}`;
        if (this._pending.has(key)) return; // anti double-clic
        this._pending.add(key);
        try {
            const { ok, data } = await jsonFetch(`/panier/maj/${lineId}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ qty }),
            });
            if (!ok) {
                toast(data?.message || 'Mise à jour impossible.', 'error');
                await this.refresh();
                this._patchDrawerLine(lineId);
                return;
            }
            await this._adopt(data);
            this._patchDrawerLine(lineId);
        } finally {
            this._pending.delete(key);
        }
    }

    async _removeLine(lineId) {
        const key = `qty:${lineId}`;
        if (this._pending.has(key)) return;
        this._pending.add(key);
        try {
            const row = this._drawerRow(lineId);
            if (row) {
                row.classList.add('leaving');
                await new Promise((r) => setTimeout(r, 360));
            }
            const { ok, data } = await jsonFetch(`/panier/supprimer/${lineId}`, { method: 'POST' });
            if (!ok) {
                row?.classList?.remove('leaving');
                toast(data?.message || 'Suppression impossible.', 'error');
                await this.refresh();
                return;
            }
            const rerendered = this._adoptSync(data);
            if (rerendered && this.hasDrawerLinesTarget) {
                row?.remove(); // ligne de la page panier, hors re-rendu Stimulus
            } else {
                row?.remove();
                if (!rerendered) await this.refresh();
            }
        } finally {
            this._pending.delete(key);
        }
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
            const lineId = plus.dataset.lineId;
            // Quantité lue dans l'état synchronisé serveur (plus dans le DOM).
            const currentQty = this._currentQty(lineId, plus);
            const line = this._findLine(lineId);
            if (line && line.stock != null && currentQty >= line.stock) {
                toast(`Stock maximum atteint (${line.stock}).`, 'info', 2200);
                return;
            }
            this._changeQty(lineId, currentQty + 1);
            return;
        }

        if (minus) {
            event.preventDefault();
            const lineId = minus.dataset.lineId;
            const currentQty = this._currentQty(lineId, minus);
            if (currentQty <= 1) {
                this._removeLine(lineId);
            } else {
                this._changeQty(lineId, currentQty - 1);
            }
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
