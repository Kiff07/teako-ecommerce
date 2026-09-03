import './styles/app.css';

import { Application } from '@hotwired/stimulus';
import { jsonFetch, toast, csrfToken, countUp } from './js/teako';

import cart from './js/controllers/cart_controller';
import theme from './js/controllers/theme_controller';
import menu from './js/controllers/menu_controller';
import gallery from './js/controllers/gallery_controller';
import checkout from './js/controllers/checkout_controller';
import uploader from './js/controllers/admin_uploader_controller';

const app = Application.start();
app.register('cart', cart);
app.register('theme', theme);
app.register('menu', menu);
app.register('gallery', gallery);
app.register('checkout', checkout);
app.register('uploader', uploader);

/* ================= page-wide behaviours ================= */

/* ---- scroll reveal + stagger + count up + chart bars ---- */
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

/* ---- blur-up images ---- */
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

/* ---- favourites heart toggle ---- */
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

/* ---- qty steppers on product pages ---- */
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

/* ---- scroll progress bar ---- */
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

/* ---- current year + misc ---- */
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
    // prefill delivery checkbox disabled logic handled by controller
}

function boot() {
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
