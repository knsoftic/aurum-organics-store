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
  // Closed panels stay inert (not focusable, hidden from assistive tech and AI agents).
  function setOpen(panel, open) {
    if (!panel) return;
    panel.classList.toggle('open', open);
    panel.setAttribute('aria-hidden', open ? 'false' : 'true');
    panel.inert = !open;
  }
  function openCart() {
    setOpen(drawer, true);
    overlay.classList.add('show');
    document.body.style.overflow = 'hidden';
    const close = drawer.querySelector('[data-close-cart]');
    if (close) close.focus();
  }
  function closeAll() {
    setOpen(drawer, false);
    setOpen(mobileNav, false);
    overlay && overlay.classList.remove('show');
    document.body.style.overflow = '';
  }
  document.addEventListener('click', (e) => {
    if (e.target.closest('[data-open-cart]')) { e.preventDefault(); openCart(); }
    if (e.target.closest('[data-close-cart]') || e.target === overlay) closeAll();
    if (e.target.closest('[data-open-menu]')) { setOpen(mobileNav, true); overlay.classList.add('show'); }
    if (e.target.closest('[data-close-menu]')) closeAll();
  });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeAll(); });

  /* ---------- Sticky header shadow ---------- */
  const header = $('#siteHeader');
  const onScroll = () => header && header.classList.toggle('scrolled', window.scrollY > 10);
  window.addEventListener('scroll', onScroll, { passive: true });
  // Reading scrollY right away would force the first layout inside this script; wait for load.
  window.addEventListener('load', onScroll);

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
      if (data.ok) {
        openCart();
        if (data.track && window.knTrack) window.knTrack('add_to_cart', data.track);
      }
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
        setTimeout(() => {
          if (thumb.dataset.srcset) main.srcset = thumb.dataset.srcset; else main.removeAttribute('srcset');
          main.src = thumb.dataset.src; main.style.opacity = 1;
        }, 180);
      });
    });
  });

  /* ---------- Videos load only when they come near the screen ---------- */
  const loadVideo = (video) => {
    if (!video.dataset.src) return;
    video.src = video.dataset.src;
    video.preload = 'metadata';
    delete video.dataset.src;
  };
  const lazyVideos = $$('video[data-src]');
  if ('IntersectionObserver' in window) {
    const io = new IntersectionObserver((entries) => entries.forEach((entry) => {
      if (entry.isIntersecting) { loadVideo(entry.target); io.unobserve(entry.target); }
    }), { rootMargin: '300px' });
    lazyVideos.forEach((v) => io.observe(v));
  } else {
    lazyVideos.forEach(loadVideo);
  }

  /* ---------- Click-to-play videos (one at a time, with sound) ---------- */
  document.addEventListener('click', (e) => {
    const nav = e.target.closest('[data-video-scroll]');
    if (!nav) return;
    const track = nav.closest('[data-video-strip]').querySelector('[data-video-track]');
    const card = track.querySelector('.video-card');
    track.scrollBy({ left: (card ? card.offsetWidth + 22 : 300) * parseInt(nav.dataset.videoScroll, 10), behavior: 'smooth' });
  });
  document.addEventListener('click', (e) => {
    const card = e.target.closest('[data-video]');
    if (!card || card.classList.contains('playing')) return;
    $$('[data-video].playing').forEach((other) => {
      const v = other.querySelector('video');
      v.pause(); v.controls = false; other.classList.remove('playing');
    });
    const video = card.querySelector('video');
    loadVideo(video);
    video.muted = false;
    video.controls = true;
    card.classList.add('playing');
    video.play().catch(() => { video.muted = true; video.play(); });
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
  // IntersectionObserver never forces a layout, so sections below the fold stay un-laid-out
  // (content-visibility) until needed. When something comes into view, everything before it
  // in the page is revealed too, so jumping to #faq or #story never leaves blank blocks behind.
  const reveals = $$('.reveal');
  if ('IntersectionObserver' in window) {
    const revealIo = new IntersectionObserver((entries) => {
      let last = -1;
      entries.forEach((e) => {
        if (e.isIntersecting || e.boundingClientRect.bottom < 0) last = Math.max(last, reveals.indexOf(e.target));
      });
      for (let i = 0; i <= last; i++) {
        if (!reveals[i].classList.contains('in')) { reveals[i].classList.add('in'); revealIo.unobserve(reveals[i]); }
      }
    }, { rootMargin: '0px 0px -6% 0px' });
    reveals.forEach((el) => revealIo.observe(el));
  } else {
    reveals.forEach((el) => el.classList.add('in'));
  }

  /* ---------- Prevent double submit ---------- */
  document.addEventListener('submit', (e) => {
    const form = e.target;
    if (form.matches('[data-ajax-cart]') || e.defaultPrevented) return;
    const btn = form.querySelector('[data-loading]');
    if (btn) { btn.classList.add('is-loading'); btn.dataset.text = btn.innerHTML; btn.innerHTML = btn.dataset.loading; }
  });
})();
