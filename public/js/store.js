/* Aurum Organics — storefront interactions (no dependencies) */
(function () {
  'use strict';

  const $ = (sel, ctx = document) => ctx.querySelector(sel);
  const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));
  const overlay = $('#overlay');
  const drawer = $('#cartDrawer');
  const mobileNav = $('#mobileNav');

  /* ---------- Toast ---------- */
  let toastTimer;
  function toast(message, type = 'success') {
    const el = $('#toast');
    if (!el || !message) return;
    el.querySelector('span').textContent = message;
    el.classList.toggle('error', type === 'error');
    el.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => el.classList.remove('show'), 3200);
  }
  window.Store = Object.assign(window.Store || {}, { toast });
  if (window.Store.flash) setTimeout(() => toast(window.Store.flash, window.Store.flashType), 300);

  /* ---------- Overlays: cart drawer + mobile menu ---------- */
  function openCart() {
    drawer.classList.add('open');
    drawer.setAttribute('aria-hidden', 'false');
    overlay.classList.add('show');
    document.body.style.overflow = 'hidden';
  }
  function closeAll() {
    drawer && drawer.classList.remove('open');
    mobileNav && mobileNav.classList.remove('open');
    overlay && overlay.classList.remove('show');
    document.body.style.overflow = '';
  }
  document.addEventListener('click', (e) => {
    if (e.target.closest('[data-open-cart]')) { e.preventDefault(); openCart(); }
    if (e.target.closest('[data-close-cart]') || e.target === overlay) closeAll();
    if (e.target.closest('[data-open-menu]')) { mobileNav.classList.add('open'); overlay.classList.add('show'); }
    if (e.target.closest('[data-close-menu]')) closeAll();
  });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeAll(); });

  /* ---------- Sticky header shadow ---------- */
  const header = $('#siteHeader');
  const onScroll = () => header && header.classList.toggle('scrolled', window.scrollY > 10);
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  /* ---------- AJAX cart ---------- */
  async function cartRequest(url, method, body) {
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': window.Store.csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: (() => { const fd = body instanceof FormData ? body : new FormData(); if (method !== 'POST') fd.append('_method', method); return fd; })(),
    });
    const data = await res.json().catch(() => ({}));
    if (data.redirect) { window.location = data.redirect; return data; }
    if (data.drawer !== undefined) {
      $('#cartDrawerBody').innerHTML = data.drawer;
      $$('[data-cart-count]').forEach((el) => { el.textContent = data.count; el.dataset.count = data.count; });
    }
    if (!res.ok) toast(data.message || (data.errors && Object.values(data.errors)[0][0]) || 'Something went wrong.', 'error');
    return data;
  }
  window.Store.cartRequest = cartRequest;

  document.addEventListener('submit', async (e) => {
    const form = e.target.closest('form[data-ajax-cart]');
    if (!form) return;
    const submitter = e.submitter;
    if (submitter && submitter.name === 'buy_now') return; // normal POST → checkout
    e.preventDefault();
    const btn = form.querySelector('[type=submit]');
    btn && btn.classList.add('is-loading');
    try {
      const data = await cartRequest(form.action, 'POST', new FormData(form));
      if (data.ok) { openCart(); }
    } finally {
      btn && btn.classList.remove('is-loading');
    }
  });

  // Quantity steppers (product page + drawer + cart page)
  document.addEventListener('click', (e) => {
    const step = e.target.closest('.qty [data-step]');
    if (!step) return;
    const wrap = step.closest('.qty');
    const input = wrap.querySelector('input');
    const max = parseInt(input.max || '99', 10);
    const min = parseInt(input.min || '1', 10);
    const next = Math.min(max, Math.max(min, (parseInt(input.value, 10) || 0) + parseInt(step.dataset.step, 10)));
    if (next === parseInt(input.value, 10) && step.dataset.step > 0) { toast('Only ' + max + ' available.', 'error'); return; }
    input.value = next;
    input.dispatchEvent(new Event('change', { bubbles: true }));
  });

  let qtyTimer;
  document.addEventListener('change', (e) => {
    const wrap = e.target.closest('[data-cart-qty]');
    if (!wrap) return;
    clearTimeout(qtyTimer);
    qtyTimer = setTimeout(() => {
      const fd = new FormData();
      fd.append('qty', e.target.value);
      if (wrap.dataset.reload !== undefined) {
        cartRequest(wrap.dataset.url, 'PATCH', fd).then(() => window.location.reload());
      } else {
        cartRequest(wrap.dataset.url, 'PATCH', fd);
      }
    }, 350);
  });

  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-cart-remove]');
    if (!btn) return;
    e.preventDefault();
    cartRequest(btn.dataset.url, 'DELETE').then(() => { if (btn.dataset.reload !== undefined) window.location.reload(); });
  });

  /* ---------- Product gallery ---------- */
  $$('[data-gallery]').forEach((gallery) => {
    const main = $('[data-gallery-main]', gallery);
    $$('[data-gallery-thumb]', gallery).forEach((thumb) => {
      thumb.addEventListener('click', () => {
        $$('[data-gallery-thumb]', gallery).forEach((t) => t.classList.remove('active'));
        thumb.classList.add('active');
        main.style.opacity = 0;
        setTimeout(() => { main.src = thumb.dataset.src; main.style.opacity = 1; }, 180);
      });
    });
  });

  /* ---------- Copy to clipboard ---------- */
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-copy]');
    if (!btn) return;
    e.preventDefault();
    const text = btn.dataset.copy;
    const done = () => toast('Copied: ' + text);
    if (navigator.clipboard) navigator.clipboard.writeText(text).then(done, done);
    else { const t = document.createElement('textarea'); t.value = text; document.body.appendChild(t); t.select(); document.execCommand('copy'); t.remove(); done(); }
  });

  /* ---------- File inputs: show chosen file name ---------- */
  document.addEventListener('change', (e) => {
    if (!e.target.matches('.file-drop input[type=file]')) return;
    const label = e.target.closest('.file-drop').querySelector('[data-file-name]');
    if (label) label.textContent = e.target.files.length ? e.target.files[0].name : label.dataset.default;
    e.target.closest('.file-drop').classList.toggle('has-file', e.target.files.length > 0);
  });

  /* ---------- Reveal on scroll ---------- */
  // Scroll-position check (more reliable than IntersectionObserver on jumpy scrolls / anchor links).
  let pending = $$('.reveal');
  let ticking = false;
  function revealCheck() {
    ticking = false;
    const limit = window.innerHeight * 0.94;
    pending = pending.filter((el) => {
      if (el.getBoundingClientRect().top < limit) { el.classList.add('in'); return false; }
      return true;
    });
    if (!pending.length) window.removeEventListener('scroll', onRevealScroll);
  }
  function onRevealScroll() { if (!ticking) { ticking = true; requestAnimationFrame(revealCheck); } }
  window.addEventListener('scroll', onRevealScroll, { passive: true });
  window.addEventListener('resize', onRevealScroll);
  revealCheck();
  // Anything above the current scroll position (e.g. after a jump) must be visible too.
  window.addEventListener('load', revealCheck);

  /* ---------- Prevent double submit ---------- */
  document.addEventListener('submit', (e) => {
    const form = e.target;
    if (form.matches('[data-ajax-cart]') || e.defaultPrevented) return;
    const btn = form.querySelector('[data-loading]');
    if (btn) { btn.classList.add('is-loading'); btn.dataset.text = btn.innerHTML; btn.innerHTML = btn.dataset.loading; }
  });
})();
