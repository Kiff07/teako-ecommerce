/* Shared helpers exposed on window.Teako */

export function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

export async function jsonFetch(url, options = {}) {
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

export function toast(message, type = 'success', duration = 3600) {
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

export function money(cents) {
    const value = Math.round(Number(cents || 0) / 100);
    return new Intl.NumberFormat('fr-MG').format(value) + ' Ar';
}

export function escapeHtml(str = '') {
    return String(str)
        .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;').replaceAll("'", '&#039;');
}

/* Smooth number counter for dashboard stats */
export function countUp(el, to, duration = 900) {
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

/* Animate a single product image element flying into the cart button */
export function flyToCart(img, targetSelector = '[data-cart-badge]') {
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
