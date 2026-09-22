/**
 * Teako Gourmet Drinks — Client Application Script
 */

(function () {
    /* ================= Helpers ================= */
    function csrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    async function jsonFetch(url, options = {}) {
        const headers = new Headers(options.headers || {});
        if (!(options.method && options.method.toUpperCase() === 'GET')) {
            headers.set('X-CSRF-TOKEN', csrfToken());
            headers.set('Accept', 'application/json');
        }
        const res = await fetch(url, { ...options, headers });
        let data = null;
        try { data = await res.json(); } catch (_) { /* no body */ }
        return { ok: res.ok, status: res.status, data };
    }

    let toastStack = null;
    function stack() {
        if (!toastStack) {
            toastStack = document.createElement('div');
            toastStack.id = 'toast-stack';
            document.body.appendChild(toastStack);
        }
        return toastStack;
    }

    function toast(message, type = 'success', duration = 3600) {
        const el = document.createElement('div');
        const icons = {
            success: '<svg viewBox="0 0 20 20" fill="none" class="w-5 h-5 shrink-0"><circle cx="10" cy="10" r="9" stroke="currentColor" opacity=".35"/><path d="M6 10.2l2.6 2.6L14 7.4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg>',
            error: '<svg viewBox="0 0 20 20" class="w-5 h-5 shrink-0" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm-1-11a1 1 0 112 0v4a1 1 0 11-2 0V7zm1 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>',
            info: '<svg viewBox="0 0 20 20" class="w-5 h-5 shrink-0" fill="currentColor"><path fill-rule="evenodd" d="M18 10A8 8 0 1110 2a8 8 0 018 8zm-8-3a1 1 0 100 2h.01a1 1 0 100-2H10zm1 4a1 1 0 10-2 0v3a1 1 0 102 0v-3z" clip-rule="evenodd"/></svg>',
        };
        el.className = 'toast pointer-events-auto flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-semibold max-w-full';
        if (type === 'success') el.style.color = '#7cc593';
        el.innerHTML = `
            ${icons[type] || icons.info}
            <span class="flex-1" style="color:var(--toast-fg)">${escapeHtml(message)}</span>
            <button class="opacity-60 hover:opacity-100" aria-label="Fermer" data-dismiss>✕</button>`;
        el.querySelector('[data-dismiss]').addEventListener('click', () => leave(el));
        stack().appendChild(el);
        setTimeout(() => leave(el), duration);
        return el;
    }

    function leave(el) {
        if (!el || !el.isConnected) return;
        el.classList.add('leaving');
        setTimeout(() => el.remove(), 320);
    }

    function money(cents) {
        const value = Math.round(Number(cents || 0) / 100);
        return new Intl.NumberFormat('fr-MG').format(value) + ' Ar';
    }

    function escapeHtml(str = '') {
        return String(str)
            .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;').replaceAll("'", '&#039;');
    }

    function countUp(el, to, duration = 900) {
        const start = performance.now();
        const from = 0;
        const step = (now) => {
            const p = Math.min(1, (now - start) / duration);
            const eased = 1 - Math.pow(1 - p, 3);
            el.textContent = Math.round(from + (to - from) * eased).toLocaleString('fr-FR');
            if (p < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
    }

    function flyToCart(img, targetSelector = '[data-cart-badge]') {
        const target = document.querySelector(targetSelector);
        const ghost = img.cloneNode(false);
        const rect = img.getBoundingClientRect();
        ghost.src = img.currentSrc || img.src;
        ghost.className = 'fly-ghost';
        ghost.style.cssText = `top:${rect.top}px;left:${rect.left}px;width:${rect.width}px;height:${rect.height}px;`;
        document.body.appendChild(ghost);
        if (target) {
            const t = target.getBoundingClientRect();
            requestAnimationFrame(() => {
                ghost.style.top = `${t.top + t.height / 2 - rect.height / 2}px`;
                ghost.style.left = `${t.left + t.width / 2 - rect.width / 2}px`;
                ghost.style.width = `${rect.width * 0.18}px`;
                ghost.style.height = `${rect.height * 0.18}px`;
                ghost.style.opacity = '0.5';
            });
        }
        setTimeout(() => ghost.remove(), 750);
        if (target) {
            target.classList.remove('cart-badge-bump');
            void target.offsetWidth;
            target.classList.add('cart-badge-bump');
        }
    }

    window.Teako = { csrfToken, jsonFetch, toast, money, escapeHtml, countUp, flyToCart };

    /* ================= Page-wide behaviours ================= */
    function revealOnScroll() {
        const reveal = (el) => {
            el.classList.add('is-in');
            el.querySelectorAll('[data-count-up]').forEach((c) => {
                if (c.dataset.done) return;
                c.dataset.done = '1';
                countUp(c, parseInt(c.dataset.countUp || '0', 10));
            });
            el.querySelectorAll('[data-chart-bar]').forEach((bar) => {
                bar.style.width = '0%';
                requestAnimationFrame(() => {
                    requestAnimationFrame(() => {
                        bar.style.width = (bar.dataset.chartBar || '0') + '%';
                    });
                });
            });
            observer.unobserve(el);
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) reveal(entry.target);
            });
        }, { threshold: 0.12 });

        document.querySelectorAll('[data-reveal], [data-stagger-grid]').forEach((el) => observer.observe(el));
    }

    function blurUpImages() {
        document.querySelectorAll('img[data-real]').forEach((img) => {
            const frame = img.closest('.blur-frame');
            const reveal = () => {
                img.classList.add('opacity-100');
                img.classList.remove('opacity-0');
                frame?.classList?.add('loaded');
            };
            if (img.complete && img.naturalWidth > 0) reveal();
            else img.addEventListener('load', reveal, { once: true });
        });
    }

    async function toggleFavorite(id, btn) {
        const { ok, data } = await jsonFetch(`/favoris/basculer/${id}`, { method: 'POST' });
        if (!ok) { toast('Impossible de mettre à jour les favoris.', 'error'); return; }
        const active = !!data.active;
        btn.classList.add('heart-pop');
        setTimeout(() => btn.classList.remove('heart-pop'), 500);
        if (active) {
            btn.classList.remove('text-[var(--ink-faint)]');
            btn.classList.add('text-[var(--negative)]');
            btn.setAttribute('aria-pressed', 'true');
            btn.dataset.favActive = '1';
            toast('Ajouté à vos favoris ♥', 'success', 1800);
        } else {
            btn.classList.add('text-[var(--ink-faint)]');
            btn.classList.remove('text-[var(--negative)]');
            btn.setAttribute('aria-pressed', 'false');
            btn.dataset.favActive = '0';
            toast('Retiré des favoris', 'info', 1800);
            const card = btn.closest('[data-fav-card]');
            if (card) {
                card.classList.add('leaving');
                setTimeout(() => card.remove(), 350);
            }
        }
        document.querySelectorAll(`[data-fav-toggle="${id}"]`).forEach((b) => {
            if (b !== btn) b.dataset.favActive = active ? '1' : '0';
        });
        const chip = document.querySelector('[data-fav-count]');
        if (chip) chip.textContent = document.querySelectorAll('[data-fav-toggle][data-fav-active="1"]').length;
    }

    function favToggleHandler() {
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-fav-toggle]');
            if (btn) {
                e.preventDefault();
                e.stopPropagation();
                const id = btn.dataset.favToggle;
                toggleFavorite(id, btn);
            }
        });
    }

    function steppers() {
        document.addEventListener('click', (e) => {
            const dir = e.target.closest('[data-step]');
            if (!dir) return;
            const wrap = dir.closest('[data-stepper]') || dir.closest('[data-qty-wrap]');
            if (!wrap) return;
            const input = wrap.querySelector('input[data-qty]');
            if (!input) return;
            const delta = dir.dataset.step === 'plus' ? 1 : -1;
            const min = parseInt(input.min || '1', 10);
            const max = parseInt(input.max || '99', 10);
            const value = Math.max(min, Math.min(max, (parseInt(input.value, 10) || min) + delta));
            input.value = value;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    function progressBar() {
        const bar = document.querySelector('#scroll-progress');
        if (!bar) return;
        const onScroll = () => {
            const h = document.documentElement;
            const pct = h.scrollTop / Math.max(1, h.scrollHeight - h.clientHeight);
            bar.style.width = `${(pct * 100).toFixed(1)}%`;
        };
        document.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }

    function badges() {
        const csrf = csrfToken();
        document.querySelectorAll('[data-csrf-form]').forEach((form) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = '_token';
            input.value = csrf;
            form.appendChild(input);
        });
        document.querySelectorAll('[data-year]').forEach((el) => { el.textContent = new Date().getFullYear(); });
    }

    /* ================= Stimulus Controllers ================= */
    function initStimulus() {
        if (!window.Stimulus) return;
        const { Application, Controller } = window.Stimulus;
        const app = Application.start();

        // Theme Controller
        app.register('theme', class extends Controller {
            static targets = ['button'];
            connect() {
                const KEY = 'tk-theme';
                const saved = localStorage.getItem(KEY);
                const systemDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                this.apply(saved ? saved === 'dark' : systemDark);
                window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
                    if (!localStorage.getItem(KEY)) this.apply(e.matches);
                });
            }
            toggle() {
                this.apply(document.documentElement.dataset.theme !== 'dark');
                if (this.hasButtonTarget) {
                    this.buttonTarget.classList.add('heart-pop');
                    setTimeout(() => this.buttonTarget.classList.remove('heart-pop'), 500);
                }
            }
            apply(dark) {
                const root = document.documentElement;
                root.dataset.theme = dark ? 'dark' : 'light';
                localStorage.setItem('tk-theme', dark ? 'dark' : 'light');
                const meta = document.querySelector('meta[name="theme-color"]');
                if (meta) meta.setAttribute('content', dark ? '#1c1511' : '#fdfaf4');
                if (this.hasButtonTarget) {
                    this.buttonTarget.innerHTML = dark
                        ? '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="4.2"/><path d="M12 2.5v2.4M12 19.1v2.4M2.5 12h2.4M19.1 12h2.4M4.9 4.9l1.7 1.7M17.4 17.4l1.7 1.7M4.9 19.1l1.7-1.7M17.4 6.6l1.7-1.7"/></svg>'
                        : '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M20.6 14.2A8.5 8.5 0 0 1 9.8 3.4a8.5 8.5 0 1 0 10.8 10.8z"/></svg>';
                }
            }
        });

        // Menu Controller
        app.register('menu', class extends Controller {
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
        });

        // Cart Controller
        app.register('cart', class extends Controller {
            static targets = [
                'badgeWrap', 'badgeCount', 'drawer', 'drawerBackdrop',
                'drawerLines', 'drawerEmpty', 'drawerFooter', 'drawerTotal', 'drawerCount',
            ];
            connect() {
                this.state = { count: 0, totalCents: 0, lines: [] };
                this._delegate = (e) => {
                    const addBtn = e.target.closest('[data-add-to-cart]');
                    if (addBtn) { e.preventDefault(); this.addFrom(addBtn); return; }
                    const openBtn = e.target.closest('[data-open-cart]');
                    if (openBtn) { e.preventDefault(); this.openDrawer(); return; }
                    const closeBtn = e.target.closest('[data-close-cart], [data-cart-backdrop]');
                    if (closeBtn) { e.preventDefault(); this.closeDrawer(); return; }
                    const rmBtn = e.target.closest('[data-cart-remove]');
                    if (rmBtn) { e.preventDefault(); this.removeLine(rmBtn.dataset.cartRemove); return; }
                };
                this._delegateChange = (e) => {
                    const input = e.target.closest('[data-cart-qty]');
                    if (input) this.updateQty(input.dataset.cartQty, parseInt(input.value, 10));
                };
                this.element.addEventListener('click', this._delegate);
                this.element.addEventListener('change', this._delegateChange);
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape' && this.hasDrawerTarget && this.drawerTarget.classList.contains('open')) {
                        this.closeDrawer();
                    }
                });
                this.refresh();
            }
            async refresh() {
                const { ok, data } = await jsonFetch('/panier/etat');
                if (!ok) return;
                this.state = data;
                this._applyState();
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
            }
            async removeLine(id) {
                const { ok, data } = await jsonFetch(`/panier/supprimer/${id}`, { method: 'POST' });
                if (!ok) return;
                this.state = data;
                this._applyState();
            }
            async updateQty(id, qty) {
                if (qty <= 0) return this.removeLine(id);
                const { ok, data } = await jsonFetch(`/panier/modifier/${id}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ qty }),
                });
                if (!ok) return;
                this.state = data;
                this._applyState();
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
            _applyState() {
                const { count, totalCents, lines } = this.state || { count: 0, totalCents: 0, lines: [] };
                if (this.hasBadgeWrapTarget) this.badgeWrapTarget.classList.toggle('hidden', count === 0);
                if (this.hasBadgeCountTarget) this.badgeCountTarget.textContent = count;
                if (this.hasDrawerCountTarget) this.drawerCountTarget.textContent = count;
                if (this.hasDrawerTotalTarget) this.drawerTotalTarget.textContent = money(totalCents);
                if (this.hasDrawerEmptyTarget && this.hasDrawerLinesTarget && this.hasDrawerFooterTarget) {
                    const empty = count === 0;
                    this.drawerEmptyTarget.classList.toggle('hidden', !empty);
                    this.drawerEmptyTarget.classList.toggle('flex', empty);
                    this.drawerLinesTarget.classList.toggle('hidden', empty);
                    this.drawerFooterTarget.classList.toggle('hidden', empty);
                    if (!empty) {
                        this.drawerLinesTarget.innerHTML = lines.map((l) => `
                            <div class="cart-line flex items-center gap-3 py-3 border-b divider">
                                <img src="${l.image || ''}" class="w-12 h-12 rounded-lg object-cover bg-surface-2" alt="">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-bold truncate">${escapeHtml(l.name)}</p>
                                    <p class="text-xs text-soft">${money(l.priceCents)} × ${l.qty}</p>
                                </div>
                                <button type="button" data-cart-remove="${l.productId}" class="text-faint hover:text-negative p-1 text-xs">✕</button>
                            </div>`).join('');
                    }
                }
            }
        });

        // Checkout Controller
        app.register('checkout', class extends Controller {
            static targets = ['addressField', 'pickupNote'];
            connect() {
                this.sync();
                this.element.querySelectorAll('input[name$="[deliveryMode]"]').forEach((r) => r.addEventListener('change', () => this.sync()));
            }
            sync() {
                const mode = this.element.querySelector('input[name$="[deliveryMode]"]:checked')?.value;
                const delivery = mode === 'delivery';
                if (this.hasAddressFieldTarget) {
                    this.addressFieldTarget.classList.toggle('hidden', !delivery);
                    this.addressFieldTarget.querySelectorAll('input,textarea').forEach((el) => { el.required = delivery; });
                }
                if (this.hasPickupNoteTarget) this.pickupNoteTarget.classList.toggle('hidden', delivery);
            }
        });

        // Gallery Controller
        app.register('gallery', class extends Controller {
            static targets = ['stage', 'slide', 'thumb', 'lens', 'count', 'lightbox', 'lightboxImg'];
            connect() {
                this.index = 0;
                this.n = this.slideTargets.length;
                if (this.n > 0) this.go(0, false);
            }
            go(next) {
                const clamped = Math.max(0, Math.min(this.n - 1, next));
                this.index = clamped;
                this.slideTargets.forEach((el, i) => el.classList.toggle('active', i === clamped));
                (this.thumbTargets || []).forEach((el, i) => {
                    el.classList.toggle('active', i === clamped);
                    el.setAttribute('aria-current', i === clamped ? 'true' : 'false');
                });
                if (this.hasCountTarget) this.countTarget.textContent = `${clamped + 1} / ${this.n}`;
            }
            next() { this.go(this.index + 1); }
            prev() { this.go(this.index - 1); }
            pick(e) { this.go(parseInt(e.currentTarget.dataset.i, 10)); }
        });
    }

    function boot() {
        initStimulus();
        revealOnScroll();
        blurUpImages();
        favToggleHandler();
        steppers();
        progressBar();
        badges();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
