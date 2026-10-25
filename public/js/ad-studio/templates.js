/* Ad Studio — data-driven ad templates.
   AdTemplates.build(format, spec) -> composition { w, h, duration, scenes, cues, voices }
   spec = { brand: {name, mark}, t: <config.text>, media: {hero, wide, square, thumb, clipOpen, clipHowto, clipWide}, voiceDur: {key: seconds} }
   Scenes stretch to fit the voice-over line recorded/generated for them. */
(function () {
  'use strict';
  const E = window.AdEngine;
  const { C, F, clamp, lerp, ease, pr, txt, reveal, measure, card, glow, pill, checkIcon, crossIcon, logo, bgLight, bgDark, sweepLight, rrPath, archPath, cover } = E;
  const LEAVES = ['#6b4a2b', '#7fae3c', '#3f8f4a', '#e0393e', '#5f8f7a', '#c99a3b'];

  function popScale(ctx, cx, cy, p, from = 0.6) {
    const s = lerp(from, 1, ease.back(clamp(p)));
    ctx.translate(cx, cy); ctx.scale(s, s); ctx.translate(-cx, -cy);
  }

  function chipRow(ctx, labels, cx, y, h, fs, lt, start, step) {
    labels = (labels || []).filter(Boolean); if (!labels.length) return;
    const style = { f: F.body, w: 600, s: fs, ls: 1 };
    const widths = labels.map((l) => measure(ctx, l, style) + h * 0.9);
    const gap = h * 0.25;
    let x = cx - (widths.reduce((a, b) => a + b, 0) + gap * (labels.length - 1)) / 2;
    labels.forEach((label, i) => {
      const p = pr(lt, start + i * step, 0.5);
      if (p > 0) {
        ctx.save(); ctx.globalAlpha *= clamp(p * 2);
        popScale(ctx, x + widths[i] / 2, y + h / 2, p);
        ctx.shadowColor = 'rgba(0,0,0,.16)'; ctx.shadowBlur = 24; ctx.shadowOffsetY = 8;
        pill(ctx, x, y, widths[i], h, '#ffffff');
        ctx.shadowColor = 'transparent';
        txt(ctx, label, x + widths[i] / 2, y + h / 2 + fs * 0.36, { ...style, c: C.purple });
        ctx.restore();
      }
      x += widths[i] + gap;
    });
  }

  function tagCloud(ctx, labels, x0, maxW, y0, h, fs, lt, start, step) {
    labels = (labels || []).filter(Boolean);
    const style = { f: F.body, w: 600, s: fs, ls: 1 };
    const gap = h * 0.22;
    const rows = [[]]; let rowW = 0;
    labels.forEach((l) => {
      const w = measure(ctx, l, style) + h * 0.8;
      if (rowW + w > maxW && rows[rows.length - 1].length) { rows.push([]); rowW = 0; }
      rows[rows.length - 1].push([l, w]); rowW += w + gap;
    });
    let i = 0;
    rows.forEach((row, r) => {
      const total = row.reduce((a, [, w]) => a + w, 0) + gap * (row.length - 1);
      let x = x0 + (maxW - total) / 2;
      const y = y0 + r * (h + gap);
      row.forEach(([l, w]) => {
        const p = pr(lt, start + i * step, 0.45);
        if (p > 0) {
          ctx.save(); ctx.globalAlpha *= clamp(p * 2);
          popScale(ctx, x + w / 2, y + h / 2, p, 0.4);
          pill(ctx, x, y, w, h, 'rgba(255,255,255,.1)', 'rgba(255,255,255,.45)');
          txt(ctx, l, x + w / 2, y + h / 2 + fs * 0.36, { ...style, c: '#fff' });
          ctx.restore();
        }
        x += w + gap; i++;
      });
    });
  }

  function benefitChip(ctx, label, x, y, w, h, p) {
    if (p <= 0 || !label) return;
    ctx.save(); ctx.globalAlpha *= clamp(p * 2);
    popScale(ctx, x + w / 2, y + h / 2, p, 0.7);
    ctx.shadowColor = 'rgba(0,0,0,.18)'; ctx.shadowBlur = 34; ctx.shadowOffsetY = 12;
    pill(ctx, x, y, w, h, '#ffffff');
    ctx.shadowColor = 'transparent';
    checkIcon(ctx, x + h * 0.55, y + h / 2, h * 0.31, C.purple);
    txt(ctx, label, x + h * 1.08, y + h / 2 + h * 0.16, { f: F.sans, w: 700, s: h * 0.44, c: C.ink, a: 'left', max: w - h * 1.3 });
    ctx.restore();
  }

  // Price badge in three shapes (circle, starburst, ribbon) chosen by the video's look.
  function priceBadge(ctx, t, cx, cy, r, p, time, shape = 'circle') {
    if (p <= 0 || !t.price) return;
    ctx.save(); ctx.globalAlpha *= clamp(p * 2);
    popScale(ctx, cx, cy, p, 0.3);
    const g = ctx.createLinearGradient(cx - r, cy - r, cx + r, cy + r);
    g.addColorStop(0, C.royal); g.addColorStop(1, C.deep);
    if (shape === 'ribbon') {
      ctx.translate(cx, cy); ctx.rotate(-0.12); ctx.translate(-cx, -cy);
      const w = r * 2.3, h = r * 1.35;
      ctx.shadowColor = 'rgba(0,0,0,.4)'; ctx.shadowBlur = r * 0.4; ctx.shadowOffsetY = r * 0.12;
      ctx.beginPath();
      ctx.moveTo(cx - w / 2, cy - h / 2); ctx.lineTo(cx + w / 2, cy - h / 2); ctx.lineTo(cx + w / 2 - r * 0.22, cy);
      ctx.lineTo(cx + w / 2, cy + h / 2); ctx.lineTo(cx - w / 2, cy + h / 2); ctx.lineTo(cx - w / 2 + r * 0.22, cy); ctx.closePath();
      ctx.fillStyle = g; ctx.fill(); ctx.shadowColor = 'transparent';
      ctx.setLineDash([r * 0.06, r * 0.06]); ctx.lineWidth = r * 0.02; ctx.strokeStyle = 'rgba(255,255,255,.5)';
      rrPath(ctx, cx - w / 2 + r * 0.3, cy - h / 2 + r * 0.14, w - r * 0.6, h - r * 0.28, r * 0.06); ctx.stroke(); ctx.setLineDash([]);
      txt(ctx, 'ONLY  ' + (t.currency || 'Rs.'), cx, cy - r * 0.2, { f: F.body, w: 600, s: r * 0.2, ls: r * 0.03, c: C.lav });
      txt(ctx, t.price, cx, cy + r * 0.38, { f: F.sans, w: 900, s: r * 0.56, c: '#fff', max: w - r * 0.8 });
      ctx.restore();
      return;
    }
    ctx.save(); ctx.translate(cx, cy); ctx.rotate(time * 0.5);
    ctx.setLineDash([r * 0.07, r * 0.09]); ctx.lineWidth = r * 0.025; ctx.strokeStyle = C.violet;
    ctx.beginPath(); ctx.arc(0, 0, r * 1.14, 0, Math.PI * 2); ctx.stroke();
    ctx.restore();
    ctx.shadowColor = 'rgba(0,0,0,.4)'; ctx.shadowBlur = r * 0.4; ctx.shadowOffsetY = r * 0.12;
    ctx.beginPath();
    if (shape === 'burst') {
      for (let i = 0; i < 32; i++) { const a = (i / 32) * Math.PI * 2 + time * 0.15, rr = i % 2 ? r * 0.9 : r * 1.04; ctx.lineTo(cx + Math.cos(a) * rr, cy + Math.sin(a) * rr); }
      ctx.closePath();
    } else {
      ctx.arc(cx, cy, r, 0, Math.PI * 2);
    }
    ctx.fillStyle = g; ctx.fill();
    ctx.shadowColor = 'transparent';
    ctx.lineWidth = r * 0.02; ctx.strokeStyle = 'rgba(255,255,255,.35)';
    ctx.beginPath(); ctx.arc(cx, cy, r * 0.8, 0, Math.PI * 2); ctx.stroke();
    txt(ctx, 'ONLY', cx, cy - r * 0.4, { f: F.body, w: 600, s: r * 0.2, ls: r * 0.04, c: C.lav });
    txt(ctx, t.currency || 'Rs.', cx, cy - r * 0.08, { f: F.sans, w: 800, s: r * 0.27, c: '#fff' });
    txt(ctx, t.price, cx, cy + r * 0.46, { f: F.sans, w: 900, s: r * 0.6, c: '#fff', max: r * 1.5 });
    ctx.restore();
  }

  // ---------- Looks: every render can pick a different one ----------
  const LOOKS = {
    elegant: { transition: ['sweep', 'iris'], text: ['rise'], hero: ['arch', 'circle'], wide: ['round'], badge: ['circle', 'burst'], particles: ['bokeh', 'leaves'], pace: 1 },
    bold: { transition: ['slide', 'flash'], text: ['pop', 'slide'], hero: ['tilt', 'round'], wide: ['tilt', 'full'], badge: ['burst', 'ribbon'], particles: ['sparkle', 'bokeh'], pace: 0.95 },
    energetic: { transition: ['flash', 'slide', 'iris'], text: ['pop', 'type'], hero: ['circle', 'tilt'], wide: ['full', 'tilt'], badge: ['burst'], particles: ['sparkle'], pace: 0.9 },
    minimal: { transition: ['slide', 'sweep'], text: ['rise', 'slide'], hero: ['round', 'arch'], wide: ['round', 'full'], badge: ['circle', 'ribbon'], particles: ['none', 'leaves'], pace: 1 },
  };
  const FONT_PAIRS = [
    { name: 'Classic', sans: 'Montserrat', serif: 'Cormorant Garamond', body: 'Jost' },
    { name: 'Modern', sans: 'Poppins', serif: 'Playfair Display', body: 'Poppins' },
    { name: 'Soft', sans: 'Raleway', serif: 'Lora', body: 'Raleway' },
    { name: 'Clean', sans: 'Outfit', serif: 'DM Serif Display', body: 'Outfit' },
  ];
  const ALL = { transition: ['sweep', 'iris', 'slide', 'flash'], text: ['rise', 'slide', 'pop', 'type'], bg: ['glow', 'mesh', 'stripes', 'dots', 'split'] };

  // Everything random about a video comes from its seed, so a look can be reproduced exactly.
  function variation(seed, preset = 'auto') {
    const r = E.rng(seed || 1), pick = (arr) => arr[Math.floor(r() * arr.length) % arr.length];
    const chance = (p) => r() < p;
    const name = LOOKS[preset] ? preset : pick(Object.keys(LOOKS));
    const L = LOOKS[name];
    const wild = preset === 'auto';
    const fonts = pick(FONT_PAIRS);
    // Per-cut transitions and per-scene text motion: mostly from the look, sometimes anything.
    const transitions = Array.from({ length: 6 }, () => pick(wild && chance(0.35) ? ALL.transition : L.transition));
    const texts = Array.from({ length: 6 }, () => pick(wild && chance(0.3) ? ALL.text : L.text));
    return {
      seed, look: name, fonts, fontName: fonts.name,
      transition: transitions[0], transitions, text: texts[0], texts,
      hero: pick(L.hero), wide: pick(L.wide), badge: pick(L.badge), particles: pick(L.particles),
      bg: pick(name === 'minimal' ? ['glow', 'dots', 'split'] : ALL.bg),
      accent: pick(['mono', 'mono', 'gold', 'warm', 'cool', 'complement']),
      layouts: { hook: pick(['A', 'B']), reveal: pick(['A', 'B']), benefits: pick(['A', 'B']), offer: pick(['A', 'B']) },
      mirror: chance(0.5),
      pace: L.pace, kb: chance(0.5) ? 1 : -1, panX: r() * 2 - 1, darkHook: chance(0.35), hookIndex: Math.floor(r() * 3), swapMiddle: chance(0.4),
      music: Math.floor(r() * 4), enhance: true,
    };
  }

  // Accent colour per look: same brand hue, or a harmonious accent next to it.
  function paletteFor(hue, accent) {
    const AN = window.AdAnalyze, base = AN.paletteFrom(hue.h, hue.s);
    let shift = { warm: 35, cool: -40, complement: 160 }[accent];
    // Yellow/lime accents wash out on the light scenes, so turn the other way round the wheel.
    const yellow = (h) => { h = (h + 360) % 360; return h > 48 && h < 85; };
    if (shift && yellow(hue.h + shift)) shift = yellow(hue.h - shift) ? 0 : -shift;
    const alt = accent === 'gold' ? AN.paletteFrom(42, 0.72) : shift ? AN.paletteFrom((hue.h + shift + 360) % 360, Math.max(0.5, hue.s)) : null;
    return alt ? { ...base, violet: alt.violet, petal: alt.petal, glow: alt.glow, glowDark: alt.glowDark } : base;
  }
  const zoomFor = (S, p, strength = 0.14) => (S.v.kb > 0 ? lerp(1.02, 1 + strength, p) : lerp(1 + strength + 0.04, 1, ease.out(p)));
  function hookText(S) {
    const vars = (S.t.hookVariants || []).filter((h) => h && (h.lines || []).filter(Boolean).length >= 2);
    if (vars.length && S.v.hookIndex !== undefined) {
      const h = vars[S.v.hookIndex % vars.length];
      return { lines: lines(h.lines), accent: h.accent || S.t.hookAccent, urdu: h.urdu || S.t.hookUrdu };
    }
    return { lines: lines(S.t.hook), accent: S.t.hookAccent, urdu: S.t.hookUrdu };
  }
  function heroFrame(ctx, S, x, y, w, h, o) {
    const shape = S.v.hero || 'arch';
    if (shape === 'arch') {
      ctx.save(); ctx.globalAlpha *= o.alpha; ctx.strokeStyle = C.violet; ctx.lineWidth = 3; archPath(ctx, x - 24, y - 24, w, h); ctx.stroke(); ctx.restore();
    }
    card(ctx, S.media.hero, x, y, w, h, { ...o, shape, r: 48, tilt: -0.05, panX: S.v.panX });
  }

  function ctaButton(ctx, x, y, w, h, p, lt, label) {
    if (p <= 0) return;
    label = label || 'SHOP NOW';
    const pulse = lt > 0 ? 1 + 0.03 * Math.sin(lt * 6) : 1;
    ctx.save(); ctx.globalAlpha *= clamp(p * 2);
    popScale(ctx, x + w / 2, y + h / 2, p, 0.6);
    ctx.translate(x + w / 2, y + h / 2); ctx.scale(pulse, pulse); ctx.translate(-(x + w / 2), -(y + h / 2));
    const g = ctx.createLinearGradient(x, y, x + w, y + h);
    g.addColorStop(0, C.royal); g.addColorStop(1, C.purple);
    ctx.shadowColor = 'rgba(0,0,0,.35)'; ctx.shadowBlur = 40; ctx.shadowOffsetY = 16;
    pill(ctx, x, y, w, h, g);
    ctx.shadowColor = 'transparent';
    ctx.save(); rrPath(ctx, x, y, w, h, h / 2); ctx.clip(); sweepLight(ctx, x, y, w, h, ((lt + 10) % 1.8) / 1.1); ctx.restore();
    const fs = h * 0.4, style = { f: F.sans, w: 800, s: fs, ls: fs * 0.08, c: '#fff' };
    const tw = Math.min(measure(ctx, label, style), w - h * 1.4);
    const ax = x + w / 2 + tw / 2 + fs * 0.2, nudge = Math.sin(lt * 6) * fs * 0.12;
    txt(ctx, label, x + w / 2 - fs * 0.45, y + h / 2 + fs * 0.36, { ...style, max: w - h * 1.4 });
    ctx.strokeStyle = '#fff'; ctx.lineWidth = fs * 0.12; ctx.lineCap = 'round'; ctx.lineJoin = 'round';
    ctx.beginPath(); ctx.moveTo(ax - fs * 0.35 + nudge, y + h / 2); ctx.lineTo(ax + fs * 0.3 + nudge, y + h / 2);
    ctx.moveTo(ax + nudge, y + h / 2 - fs * 0.32); ctx.lineTo(ax + fs * 0.32 + nudge, y + h / 2); ctx.lineTo(ax + nudge, y + h / 2 + fs * 0.32); ctx.stroke();
    ctx.restore();
  }

  function stamp(ctx, t, cx, cy, r, p) {
    if (p <= 0) return;
    ctx.save(); ctx.globalAlpha *= clamp(p * 2);
    ctx.translate(cx, cy); ctx.rotate(-0.14); const s = lerp(1.7, 1, ease.out(p)); ctx.scale(s, s);
    ctx.lineWidth = r * 0.04; ctx.strokeStyle = C.lav;
    ctx.beginPath(); ctx.arc(0, 0, r, 0, Math.PI * 2); ctx.stroke();
    ctx.setLineDash([r * 0.06, r * 0.06]); ctx.lineWidth = r * 0.02;
    ctx.beginPath(); ctx.arc(0, 0, r * 0.84, 0, Math.PI * 2); ctx.stroke(); ctx.setLineDash([]);
    txt(ctx, t.stamp1 || '100%', 0, -r * 0.02, { f: F.sans, w: 900, s: r * 0.46, c: '#fff', max: r * 1.5 });
    txt(ctx, t.stamp2 || 'NATURAL', 0, r * 0.32, { f: F.sans, w: 800, s: r * 0.2, ls: r * 0.05, c: C.lav, max: r * 1.5 });
    ctx.restore();
  }

  function leafDot(ctx, cx, cy, r, color) {
    ctx.beginPath(); ctx.arc(cx, cy, r, 0, Math.PI * 2); ctx.fillStyle = color; ctx.fill();
    ctx.save(); ctx.translate(cx, cy); ctx.rotate(-0.7); ctx.fillStyle = 'rgba(255,255,255,.92)';
    ctx.beginPath(); ctx.ellipse(0, 0, r * 0.55, r * 0.26, 0, 0, Math.PI * 2); ctx.fill();
    ctx.strokeStyle = color; ctx.lineWidth = r * 0.07; ctx.beginPath(); ctx.moveTo(-r * 0.5, 0); ctx.lineTo(r * 0.5, 0); ctx.stroke();
    ctx.restore();
  }

  function grade(ctx, W, H) {
    ctx.fillStyle = C.deep; ctx.globalAlpha = 0.16; ctx.fillRect(0, 0, W, H); ctx.globalAlpha = 1;
    let g = ctx.createLinearGradient(0, H * 0.42, 0, H);
    g.addColorStop(0, 'rgba(0,0,0,0)'); g.addColorStop(1, C.ink);
    ctx.save(); ctx.globalAlpha = 0.92; ctx.fillStyle = g; ctx.fillRect(0, 0, W, H); ctx.restore();
    g = ctx.createLinearGradient(0, 0, 0, H * 0.2);
    g.addColorStop(0, C.ink); g.addColorStop(1, 'rgba(0,0,0,0)');
    ctx.save(); ctx.globalAlpha = 0.55; ctx.fillStyle = g; ctx.fillRect(0, 0, W, H); ctx.restore();
  }

  const lines = (arr) => (arr || []).filter((s) => s && String(s).trim());

  // ===================== 9:16 (1080x1920) =====================
  function portraitScenes(S) {
    const t = S.t, M = S.media, B = S.brand;
    const useB = (scene) => S.v.layouts && S.v.layouts[scene] === 'B';

    // Alternative compositions, picked per video for more design variety.
    const layoutB = {
      // Big product photo on top, hook underneath.
      hook(ctx, k) {
        const { W, lt } = k, a = (d, l = 0.6) => pr(lt, d, l);
        const dark = S.v.darkHook, h0 = hookText(S);
        const main = dark ? '#fff' : C.purple, accent = dark ? C.lav : C.violet, sub = dark ? C.lav : C.deep;
        (dark ? bgDark : bgLight)(ctx, W, k.H, k.t);
        const ap = ease.out(a(0, 0.8));
        const shape = S.v.hero === 'circle' ? 'circle' : S.v.hero === 'arch' ? 'arch' : 'round';
        card(ctx, M.hero || M.thumb, 200, 130 + (1 - ap) * -90, 680, 680, { shape, r: 60, zoom: zoomFor(S, pr(lt, 0, k.dur)), alpha: ap, panX: S.v.panX, sweep: pr(lt, 1.3, 1.0) });
        logo(ctx, B, W / 2, 900, 70, main, ease.out(a(0.3, 0.5)));
        const hook = h0.lines.slice(0, 3);
        hook.forEach((s, i) => reveal(ctx, s, W / 2, 1030 + i * 106, { f: F.sans, w: 900, s: 96, c: main, ls: 2, max: 920 }, a(0.35 + i * 0.12, 0.7)));
        const ay = 1030 + hook.length * 106 + 40;
        reveal(ctx, h0.accent, W / 2, ay, { f: F.serif, w: 600, it: true, s: 150, c: accent, max: 940 }, a(0.75, 0.8));
        txt(ctx, h0.urdu, W / 2, ay + 120, { f: F.urdu, w: 600, s: 48, c: sub, alpha: ease.out(a(1.2, 0.6)), dir: 'rtl', max: 940 });
      },
      // Full-bleed product photo with the name over a dark fade.
      reveal(ctx, k) {
        const { W, H, lt } = k, a = (d, l = 0.6) => pr(lt, d, l);
        // Photo fills the top, fading into a solid dark band that carries the name.
        const ph = 1400;
        bgDark(ctx, W, H, k.t);
        if (M.hero) cover(ctx, M.hero.el, 0, 0, W, ph, zoomFor(S, pr(lt, 0, k.dur), 0.12), (M.hero.fx ?? 0.5) + (S.v.panX || 0) * 0.04, M.hero.fy ?? 0.5);
        const g = ctx.createLinearGradient(0, ph * 0.55, 0, ph);
        g.addColorStop(0, 'rgba(0,0,0,0)'); g.addColorStop(1, C.ink);
        ctx.fillStyle = g; ctx.fillRect(0, 0, W, ph);
        ctx.fillStyle = C.ink; ctx.fillRect(0, ph - 1, W, H - ph + 1);
        const top = ctx.createLinearGradient(0, 0, 0, 380);
        top.addColorStop(0, 'rgba(0,0,0,.45)'); top.addColorStop(1, 'rgba(0,0,0,0)');
        ctx.fillStyle = top; ctx.fillRect(0, 0, W, 380);
        sweepLight(ctx, 0, 0, W, ph, pr(lt, 1.0, 1.2));
        logo(ctx, B, W / 2, 190, 84, '#fff', ease.out(a(0, 0.5)));
        txt(ctx, t.intro, W / 2, 1230, { f: F.body, w: 600, s: 38, ls: 14, c: C.lav, alpha: ease.out(a(0.2, 0.5)) });
        reveal(ctx, t.productLine1, W / 2, 1390, { f: F.serif, w: 600, it: true, s: 160, c: '#fff', max: 960 }, a(0.3, 0.7));
        reveal(ctx, t.productLine2, W / 2, 1510, { f: F.sans, w: 900, s: 104, ls: 8, c: '#fff', max: 960 }, a(0.45, 0.7));
        chipRow(ctx, t.introChips, W / 2, 1570, 72, 30, lt, 1.0, 0.15);
      },
      // Benefits first, photo below.
      benefits(ctx, k) {
        const { W, lt } = k, a = (d, l = 0.6) => pr(lt, d, l);
        bgLight(ctx, W, k.H, k.t);
        reveal(ctx, t.benefitsTitle, W / 2, 300, { f: F.serif, w: 600, it: true, s: 104, c: C.purple, max: 940 }, a(0.1, 0.7));
        lines(t.benefits).slice(0, 4).forEach((b, i) => benefitChip(ctx, b, 130, 370 + i * 118, 820, 96, a(0.4 + i * 0.4, 0.55)));
        const ap = ease.out(a(0.9, 0.7)), wf = S.v.wide === 'full' ? 'round' : (S.v.wide || 'round');
        card(ctx, M.wide, 90, 900 + (1 - ap) * 100, 900, 640, { shape: wf, r: 40, tilt: -0.035, zoom: zoomFor(S, pr(lt, 0, k.dur), 0.1), alpha: ap, panX: S.v.panX });
      },
      // Price front and centre.
      offer(ctx, k) {
        const { W, lt } = k, a = (d, l = 0.6) => pr(lt, d, l);
        bgLight(ctx, W, k.H, k.t);
        logo(ctx, B, W / 2, 190, 80, C.purple, ease.out(a(0, 0.5)));
        const ap = ease.out(a(0, 0.7));
        card(ctx, M.square, 290, 270 + (1 - ap) * 60, 500, 500, { shape: 'circle', zoom: lerp(1.14, 1, ease.out(pr(lt, 0, 1.6))), alpha: ap });
        if (t.price) {
          const pp = a(0.35, 0.6);
          ctx.save(); popScale(ctx, W / 2, 960, pp, 0.5); ctx.globalAlpha *= clamp(pp * 2);
          txt(ctx, 'ONLY', W / 2, 870, { f: F.body, w: 600, s: 34, ls: 10, c: C.violet });
          txt(ctx, (t.currency || 'Rs.') + ' ' + t.price, W / 2, 1010, { f: F.sans, w: 900, s: 170, c: C.purple, max: 940 });
          ctx.restore();
        }
        reveal(ctx, t.offerTitle, W / 2, 1120, { f: F.sans, w: 900, s: 84, ls: 2, c: C.deep, max: 920 }, a(0.6, 0.6));
        reveal(ctx, t.offerSub, W / 2, 1195, { f: F.serif, w: 600, it: true, s: 66, c: C.violet, max: 920 }, a(0.75, 0.6));
        txt(ctx, t.offerUrdu, W / 2, 1275, { f: F.urdu, w: 600, s: 44, c: C.deep, alpha: ease.out(a(0.95, 0.6)), dir: 'rtl', max: 940 });
        chipRow(ctx, t.offerChips, W / 2, 1305, 66, 28, lt, 1.1, 0.1);
        ctaButton(ctx, 190, 1400, 700, 112, a(1.35, 0.6), lt - 1.9, t.cta);
        txt(ctx, t.url, W / 2, 1575, { f: F.body, w: 600, s: 46, ls: 2, c: C.purple, alpha: ease.out(a(1.6, 0.6)), max: 940 });
      },
    };

    return {
      hook(ctx, k) {
        if (useB('hook')) return layoutB.hook(ctx, k);
        const { W, lt } = k, a = (d, l = 0.6) => pr(lt, d, l);
        const dark = S.v.darkHook, h0 = hookText(S);
        const main = dark ? '#fff' : C.purple, accent = dark ? C.lav : C.violet, sub = dark ? C.lav : C.deep;
        (dark ? bgDark : bgLight)(ctx, W, k.H, k.t);
        logo(ctx, B, W / 2, 250, 92, main, ease.out(a(0, 0.5)));
        const hook = h0.lines.slice(0, 3), top = 700 + (3 - hook.length) * 70;
        hook.forEach((s, i) => reveal(ctx, s, W / 2, top + i * 142, { f: F.sans, w: 900, s: 120, c: main, ls: 2, max: 930 }, a(0.12 + i * 0.14, 0.7)));
        const ay = top + hook.length * 142 + 70;
        reveal(ctx, h0.accent, W / 2, ay, { f: F.serif, w: 600, it: true, s: 210, c: accent, max: 960 }, a(0.6, 0.8));
        const uw = 560 * ease.out(a(1.0, 0.6));
        if (uw > 1 && h0.accent) { ctx.strokeStyle = accent; ctx.lineWidth = 8; ctx.lineCap = 'round'; ctx.beginPath(); ctx.moveTo(W / 2 - uw / 2, ay + 54); ctx.lineTo(W / 2 + uw / 2, ay + 54); ctx.stroke(); }
        txt(ctx, h0.urdu, W / 2, ay + 204, { f: F.urdu, w: 600, s: 56, c: sub, alpha: ease.out(a(1.2, 0.6)), dir: 'rtl', max: 940 });
        const pp = ease.out(a(0.9, 0.7));
        if (pp > 0 && M.thumb) {
          const r = 150, cx = W / 2, cy = 1640 + (1 - pp) * 120 + Math.sin(lt * 2.2) * 8;
          ctx.save(); ctx.globalAlpha *= pp;
          ctx.shadowColor = 'rgba(0,0,0,.3)'; ctx.shadowBlur = 50; ctx.shadowOffsetY = 20;
          ctx.beginPath(); ctx.arc(cx, cy, r, 0, Math.PI * 2); ctx.fillStyle = '#fff'; ctx.fill(); ctx.shadowColor = 'transparent';
          ctx.beginPath(); ctx.arc(cx, cy, r - 8, 0, Math.PI * 2); ctx.clip();
          cover(ctx, M.thumb.el, cx - r, cy - r, r * 2, r * 2, 1.25, M.thumb.fx, M.thumb.fy);
          ctx.restore();
        }
      },
      reveal(ctx, k) {
        if (useB('reveal')) return layoutB.reveal(ctx, k);
        const { W, lt } = k, a = (d, l = 0.6) => pr(lt, d, l);
        bgLight(ctx, W, k.H, k.t);
        glow(ctx, W / 2, 1110, 640, 'rgba(255,255,255,1)', 0.9);
        txt(ctx, t.intro, W / 2, 290, { f: F.body, w: 600, s: 38, ls: 14, c: C.violet, alpha: ease.out(a(0, 0.5)) });
        reveal(ctx, t.productLine1, W / 2, 455, { f: F.serif, w: 600, it: true, s: 170, c: C.purple, max: 960 }, a(0.1, 0.7));
        reveal(ctx, t.productLine2, W / 2, 580, { f: F.sans, w: 900, s: 112, ls: 8, c: C.purple, max: 960 }, a(0.25, 0.7));
        const ap = ease.out(a(0.2, 0.9)), x = 170, y = 660 + (1 - ap) * 160, w = 740, h = 925;
        heroFrame(ctx, S, x, y, w, h, { zoom: zoomFor(S, pr(lt, 0.2, k.dur)), alpha: ap, sweep: pr(lt, 1.4, 1.0) });
        chipRow(ctx, t.introChips, W / 2, 1548, 76, 32, lt, 1.2, 0.15);
      },
      benefits(ctx, k) {
        if (useB('benefits')) return layoutB.benefits(ctx, k);
        const { W, lt } = k, a = (d, l = 0.6) => pr(lt, d, l);
        bgLight(ctx, W, k.H, k.t);
        const ap = ease.out(a(0, 0.7)), wf = S.v.wide || 'round', z = zoomFor(S, pr(lt, 0, k.dur), 0.1);
        if (wf === 'full') card(ctx, M.wide, 0, 0, W, 960, { shape: 'full', zoom: z, alpha: ap, panX: S.v.panX });
        else card(ctx, M.wide, 90, 230 + (1 - ap) * 80, 900, 675, { shape: wf, r: 40, tilt: 0.04, zoom: z, alpha: ap, panX: S.v.panX });
        reveal(ctx, t.benefitsTitle, W / 2, 1030, { f: F.serif, w: 600, it: true, s: 104, c: C.purple, max: 940 }, a(0.3, 0.7));
        lines(t.benefits).slice(0, 4).forEach((b, i) => benefitChip(ctx, b, 130, 1092 + i * 120, 820, 98, a(0.65 + i * 0.42, 0.55)));
      },
      ingredients(ctx, k) {
        const { W, lt } = k, a = (d, l = 0.6) => pr(lt, d, l);
        bgDark(ctx, W, k.H, k.t);
        if (t.countNumber) {
          const n = Math.round(t.countNumber * ease.out(pr(lt, 0.1, 0.9)));
          ctx.save(); popScale(ctx, W / 2, 660, a(0, 0.6), 0.7); ctx.globalAlpha *= clamp(a(0, 0.3) * 2);
          txt(ctx, n + '+', W / 2, 790, { f: F.sans, w: 900, s: 380, c: '#fff', shadow: 'rgba(0,0,0,.3)' });
          ctx.restore();
        }
        reveal(ctx, t.countLabel, W / 2, 905, { f: F.sans, w: 800, s: 62, ls: 4, c: C.lav, max: 920 }, a(0.4, 0.7));
        txt(ctx, t.countUrdu, W / 2, 1012, { f: F.urdu, w: 600, s: 48, c: '#fff', alpha: 0.92 * ease.out(a(0.65, 0.6)), dir: 'rtl', max: 940 });
        tagCloud(ctx, t.tags, 90, 900, 1090, 80, 38, lt, 0.8, 0.06);
        txt(ctx, t.freeFromLine, W / 2, 1540, { f: F.body, w: 600, s: 30, ls: 3, c: C.lav, alpha: ease.out(a(1.7, 0.5)), max: 940 });
      },
      offer(ctx, k) {
        if (useB('offer')) return layoutB.offer(ctx, k);
        const { W, lt } = k, a = (d, l = 0.6) => pr(lt, d, l);
        bgLight(ctx, W, k.H, k.t);
        logo(ctx, B, W / 2, 200, 84, C.purple, ease.out(a(0, 0.5)));
        const ap = ease.out(a(0, 0.7));
        card(ctx, M.square, 250, 290 + (1 - ap) * 70, 640, 630, { r: 44, zoom: lerp(1.14, 1, ease.out(pr(lt, 0, 1.6))), alpha: ap, sweep: pr(lt, 1.7, 1.0) });
        priceBadge(ctx, t, 255, 860, 150, a(0.35, 0.6), k.t, S.v.badge);
        reveal(ctx, t.offerTitle, W / 2, 1075, { f: F.sans, w: 900, s: 96, ls: 2, c: C.purple, max: 920 }, a(0.6, 0.6));
        reveal(ctx, t.offerSub, W / 2, 1160, { f: F.serif, w: 600, it: true, s: 78, c: C.violet, max: 920 }, a(0.75, 0.6));
        txt(ctx, t.offerUrdu, W / 2, 1250, { f: F.urdu, w: 600, s: 46, c: C.deep, alpha: ease.out(a(0.95, 0.6)), dir: 'rtl', max: 940 });
        chipRow(ctx, t.offerChips, W / 2, 1288, 70, 30, lt, 1.1, 0.1);
        ctaButton(ctx, 190, 1392, 700, 118, a(1.35, 0.6), lt - 1.9, t.cta);
        txt(ctx, t.url, W / 2, 1580, { f: F.body, w: 600, s: 48, ls: 2, c: C.purple, alpha: ease.out(a(1.6, 0.6)), max: 940 });
      },
      meet(ctx, k) {
        const { W, H, lt } = k, a = (d, l = 0.6) => pr(lt, d, l);
        cover(ctx, M.clipOpen.el, 0, 0, W, H, lerp(1.1, 1.0, ease.out(pr(lt, 0, k.dur))), M.clipOpen.fx, M.clipOpen.fy);
        grade(ctx, W, H);
        logo(ctx, B, W / 2, 190, 84, '#fff', ease.out(a(0, 0.5)));
        reveal(ctx, t.meetTop, W / 2, 1190, { f: F.sans, w: 800, s: 66, ls: 12, c: '#fff', max: 940 }, a(0.3, 0.6));
        reveal(ctx, t.meetBig, W / 2, 1425, { f: F.serif, w: 600, it: true, s: 240, c: '#fff', max: 960 }, a(0.45, 0.8));
        txt(ctx, t.meetSub, W / 2, 1515, { f: F.body, w: 500, s: 38, c: C.lav, alpha: ease.out(a(0.95, 0.6)), max: 940 });
      },
      inside(ctx, k) {
        const { W, lt } = k, a = (d, l = 0.6) => pr(lt, d, l);
        bgLight(ctx, W, k.H, k.t);
        reveal(ctx, t.insideTitle1, W / 2, 300, { f: F.sans, w: 900, s: 104, ls: 4, c: C.purple, max: 940 }, a(0, 0.6));
        reveal(ctx, t.insideTitle2, W / 2, 445, { f: F.serif, w: 600, it: true, s: 160, c: C.violet, max: 940 }, a(0.12, 0.7));
        (t.inside || []).slice(0, 6).forEach(([name, benefit], i) => {
          const x = [100, 560][i % 2], y = 530 + Math.floor(i / 2) * 285, w = 420, h = 255, p = a(0.35 + i * 0.28, 0.5);
          if (p <= 0 || !name) return;
          ctx.save(); ctx.globalAlpha *= clamp(p * 2); ctx.translate(0, (1 - ease.out(p)) * 60);
          ctx.shadowColor = 'rgba(0,0,0,.16)'; ctx.shadowBlur = 40; ctx.shadowOffsetY = 14;
          rrPath(ctx, x, y, w, h, 32); ctx.fillStyle = '#fff'; ctx.fill(); ctx.shadowColor = 'transparent';
          leafDot(ctx, x + 64, y + 68, 34, LEAVES[i % LEAVES.length]);
          txt(ctx, name, x + 116, y + 86, { f: F.sans, w: 800, s: 50, c: C.purple, a: 'left', max: w - 130 });
          txt(ctx, benefit, x + 36, y + 182, { f: F.body, w: 500, s: 36, c: C.muted, a: 'left', max: w - 60 });
          ctx.restore();
        });
        txt(ctx, t.insideMore, W / 2, 1440, { f: F.body, w: 600, s: 42, c: C.violet, alpha: ease.out(a(2.2, 0.5)), max: 940 });
        txt(ctx, t.insideMoreList, W / 2, 1500, { f: F.body, w: 500, s: 30, c: C.muted, alpha: ease.out(a(2.4, 0.5)), max: 920 });
      },
      nothing(ctx, k) {
        const { W, lt } = k, a = (d, l = 0.6) => pr(lt, d, l);
        bgDark(ctx, W, k.H, k.t);
        reveal(ctx, t.nothingTitle1, W / 2, 420, { f: F.sans, w: 900, s: 124, ls: 6, c: '#fff', max: 940 }, a(0, 0.6));
        reveal(ctx, t.nothingTitle2, W / 2, 565, { f: F.serif, w: 600, it: true, s: 120, c: C.lav, max: 940 }, a(0.15, 0.7));
        lines(t.nothing).slice(0, 4).forEach((label, i) => {
          const y = 790 + i * 150, p = a(0.5 + i * 0.3, 0.45);
          if (p <= 0) return;
          ctx.save(); ctx.globalAlpha *= clamp(p * 2);
          ctx.save(); popScale(ctx, 250, y - 22, p, 0.3); crossIcon(ctx, 250, y - 22, 42); ctx.restore();
          txt(ctx, label, 322, y, { f: F.sans, w: 700, s: 64, c: '#fff', a: 'left', max: 660 });
          const sw = Math.min(660, measure(ctx, label, { f: F.sans, w: 700, s: 64 })) * ease.out(a(0.75 + i * 0.3, 0.35));
          if (sw > 1) { ctx.strokeStyle = C.red; ctx.lineWidth = 8; ctx.lineCap = 'round'; ctx.beginPath(); ctx.moveTo(322, y - 22); ctx.lineTo(322 + sw, y - 22); ctx.stroke(); }
          ctx.restore();
        });
        stamp(ctx, t, W / 2, 1440, 150, a(2.2, 0.5));
      },
      howto(ctx, k) {
        const { W, lt } = k, a = (d, l = 0.6) => pr(lt, d, l);
        bgLight(ctx, W, k.H, k.t);
        const ap = ease.out(a(0, 0.6));
        card(ctx, M.clipHowto || M.wide, 90, 200 + (1 - ap) * 70, 900, 660, { r: 40, zoom: 1.05, alpha: ap });
        reveal(ctx, t.howTitle, W / 2, 1000, { f: F.serif, w: 600, it: true, s: 116, c: C.purple, max: 940 }, a(0.25, 0.7));
        (t.steps || []).slice(0, 3).forEach(([title, sub], i) => {
          const y = 1070 + i * 150, p = a(0.55 + i * 0.5, 0.5);
          if (p <= 0 || !title) return;
          ctx.save(); ctx.globalAlpha *= clamp(p * 2); ctx.translate((1 - ease.out(p)) * -60, 0);
          ctx.beginPath(); ctx.arc(170, y + 50, 48, 0, Math.PI * 2); ctx.fillStyle = C.purple; ctx.fill();
          txt(ctx, String(i + 1), 170, y + 68, { f: F.sans, w: 800, s: 52, c: '#fff' });
          txt(ctx, title, 250, y + 44, { f: F.sans, w: 800, s: 50, c: C.ink, a: 'left', max: 720 });
          txt(ctx, sub, 250, y + 96, { f: F.body, w: 500, s: 36, c: C.muted, a: 'left', max: 720 });
          ctx.restore();
        });
        txt(ctx, t.howNote, W / 2, 1560, { f: F.body, w: 600, s: 40, c: C.violet, alpha: ease.out(a(2.2, 0.5)), max: 940 });
      },
    };
  }

  // ===================== 16:9 (1920x1080) =====================
  function landscapeScenes(S) {
    const t = S.t, M = S.media, B = S.brand;
    return {
      hook(ctx, k) {
        const { W, H, lt } = k, a = (d, l = 0.6) => pr(lt, d, l);
        const dark = S.v.darkHook, h0 = hookText(S);
        const main = dark ? '#fff' : C.purple, accent = dark ? C.lav : C.violet, sub = dark ? C.lav : C.deep;
        (dark ? bgDark : bgLight)(ctx, W, H, k.t);
        // Mirror: photo on the left, text on the right.
        const tx = S.v.mirror ? 980 : 140, px = S.v.mirror ? 160 : 1184;
        logo(ctx, B, tx, 140, 76, main, ease.out(a(0, 0.5)), 'left');
        const hook = h0.lines.slice(0, 3);
        hook.forEach((s, i) => reveal(ctx, s, tx, 370 + i * 110, { f: F.sans, w: 900, s: 104, ls: 2, c: main, a: 'left', max: 860 }, a(0.12 + i * 0.14, 0.7)));
        const ay = 370 + hook.length * 110 + 70;
        reveal(ctx, h0.accent, tx, ay, { f: F.serif, w: 600, it: true, s: 170, c: accent, a: 'left', max: 880 }, a(0.6, 0.8));
        const uw = 500 * ease.out(a(1.0, 0.6));
        if (uw > 1 && h0.accent) { ctx.strokeStyle = accent; ctx.lineWidth = 7; ctx.lineCap = 'round'; ctx.beginPath(); ctx.moveTo(tx + 6, ay + 45); ctx.lineTo(tx + 6 + uw, ay + 45); ctx.stroke(); }
        txt(ctx, h0.urdu, tx + 6, Math.min(ay + 160, 1010), { f: F.urdu, w: 600, s: 48, c: sub, a: 'left', alpha: ease.out(a(1.2, 0.6)), max: 860 });
        const ap = ease.out(a(0.25, 0.9));
        if (!dark) glow(ctx, px + 300, 560, 560, 'rgba(255,255,255,1)', 0.9);
        heroFrame(ctx, S, px, 154 + (1 - ap) * 120, 640, 800, { zoom: zoomFor(S, pr(lt, 0.25, k.dur)), alpha: ap, sweep: pr(lt, 1.6, 1.0) });
      },
      reveal(ctx, k) {
        const { W, H, lt } = k, a = (d, l = 0.6) => pr(lt, d, l);
        const bg = M.clipWide || M.wide || M.hero;
        // Photo on one side, name on a dark panel on the other, so the text never sits on the product.
        const m = S.v.mirror, tx = m ? 1010 : 140, max = m ? 790 : 880, pw = 1050, px = m ? 0 : W - pw;
        ctx.fillStyle = C.ink; ctx.fillRect(0, 0, W, H);
        glow(ctx, m ? W - 420 : 420, H * 0.55, 720, C.glowDark, 0.35);
        cover(ctx, bg.el, px, 0, pw, H, lerp(1.08, 1, ease.out(pr(lt, 0, k.dur))), bg.fx, bg.fy);
        const g = m ? ctx.createLinearGradient(pw, 0, pw - 460, 0) : ctx.createLinearGradient(px, 0, px + 460, 0);
        g.addColorStop(0, C.ink); g.addColorStop(1, 'rgba(0,0,0,0)');
        ctx.fillStyle = g; ctx.fillRect(px, 0, pw, H);
        logo(ctx, B, tx, 140, 70, '#fff', ease.out(a(0, 0.5)), 'left');
        txt(ctx, t.intro, tx + 6, 390, { f: F.body, w: 600, s: 34, ls: 12, c: C.lav, a: 'left', alpha: ease.out(a(0.1, 0.5)) });
        reveal(ctx, t.productLine1, tx, 560, { f: F.serif, w: 600, it: true, s: 180, c: '#fff', a: 'left', max }, a(0.2, 0.7));
        reveal(ctx, t.productLine2, tx + 6, 690, { f: F.sans, w: 900, s: 124, ls: 8, c: '#fff', a: 'left', max }, a(0.35, 0.7));
        txt(ctx, (t.introChips || []).filter(Boolean).join('  ·  '), tx + 8, 775, { f: F.body, w: 500, s: 36, c: C.lav, a: 'left', alpha: ease.out(a(0.9, 0.6)), max });
      },
      benefits(ctx, k) {
        const { W, H, lt } = k, a = (d, l = 0.6) => pr(lt, d, l);
        bgLight(ctx, W, H, k.t);
        const ap = ease.out(a(0, 0.7)), wf = S.v.wide || 'round', z = zoomFor(S, pr(lt, 0, k.dur), 0.1);
        const m = S.v.mirror, cx = m ? 120 : 1100;
        if (wf === 'full') card(ctx, M.wide, m ? 900 : 0, 0, 1020, H, { shape: 'full', zoom: z, alpha: ap, panX: S.v.panX });
        else card(ctx, M.wide, (m ? 920 : 120) + (1 - ap) * (m ? 60 : -60), 170, 880, 660, { shape: wf, r: 40, tilt: m ? 0.035 : -0.035, zoom: z, alpha: ap, panX: S.v.panX });
        reveal(ctx, t.benefitsTitle, cx, 300, { f: F.serif, w: 600, it: true, s: 96, c: C.purple, a: 'left', max: 720 }, a(0.3, 0.7));
        lines(t.benefits).slice(0, 4).forEach((b, i) => benefitChip(ctx, b, cx, 360 + i * 122, 700, 96, a(0.65 + i * 0.42, 0.55)));
      },
      ingredients(ctx, k) {
        const { W, H, lt } = k, a = (d, l = 0.6) => pr(lt, d, l);
        bgDark(ctx, W, H, k.t);
        if (t.countNumber) {
          const n = Math.round(t.countNumber * ease.out(pr(lt, 0.1, 0.9)));
          ctx.save(); popScale(ctx, 520, 480, a(0, 0.6), 0.7); ctx.globalAlpha *= clamp(a(0, 0.3) * 2);
          txt(ctx, n + '+', 520, 600, { f: F.sans, w: 900, s: 340, c: '#fff', shadow: 'rgba(0,0,0,.3)' });
          ctx.restore();
        }
        reveal(ctx, t.countLabel, 520, 705, { f: F.sans, w: 800, s: 54, ls: 4, c: C.lav, max: 820 }, a(0.4, 0.7));
        txt(ctx, t.countUrdu, 520, 805, { f: F.urdu, w: 600, s: 44, c: '#fff', alpha: 0.92 * ease.out(a(0.65, 0.6)), dir: 'rtl', max: 820 });
        tagCloud(ctx, t.tags, 1010, 800, 300, 84, 38, lt, 0.8, 0.06);
        txt(ctx, t.freeFromLine, W / 2, 990, { f: F.body, w: 600, s: 28, ls: 3, c: C.lav, alpha: ease.out(a(1.6, 0.5)), max: 1700 });
      },
      offer(ctx, k) {
        const { W, H, lt } = k, a = (d, l = 0.6) => pr(lt, d, l);
        bgLight(ctx, W, H, k.t);
        const ap = ease.out(a(0, 0.7)), m = S.v.mirror;
        card(ctx, M.square, m ? 1060 : 200, 160 + (1 - ap) * 60, 660, 650, { r: 44, zoom: lerp(1.14, 1, ease.out(pr(lt, 0, 1.6))), alpha: ap, sweep: pr(lt, 1.7, 1.0) });
        priceBadge(ctx, t, m ? 1695 : 225, 760, 140, a(0.35, 0.6), k.t, S.v.badge);
        const cx = m ? 560 : 1360;
        logo(ctx, B, cx, 175, 70, C.purple, ease.out(a(0, 0.5)));
        reveal(ctx, t.offerTitle, cx, 360, { f: F.sans, w: 900, s: 100, ls: 2, c: C.purple, max: 940 }, a(0.6, 0.6));
        reveal(ctx, t.offerSub, cx, 450, { f: F.serif, w: 600, it: true, s: 80, c: C.violet, max: 940 }, a(0.75, 0.6));
        txt(ctx, t.offerUrdu, cx, 540, { f: F.urdu, w: 600, s: 44, c: C.deep, alpha: ease.out(a(0.95, 0.6)), dir: 'rtl', max: 940 });
        chipRow(ctx, t.offerChips, cx, 588, 66, 28, lt, 1.1, 0.1);
        ctaButton(ctx, cx - 320, 700, 640, 112, a(1.35, 0.6), lt - 1.9, t.cta);
        txt(ctx, t.url, cx, 900, { f: F.body, w: 600, s: 46, ls: 2, c: C.purple, alpha: ease.out(a(1.6, 0.6)), max: 940 });
      },
    };
  }

  // [scene name, base seconds, voice key, clip role]
  const PLANS = {
    motion: [['hook', 2.8], ['reveal', 3.0], ['benefits', 4.4], ['ingredients', 2.8], ['offer', 3.6]],
    footage: [['meet', 3.4, 'meet', 'clipOpen'], ['inside', 4.4], ['nothing', 3.8], ['howto', 4.4, 'howto', 'clipHowto'], ['offer', 4.0]],
    landscape: [['hook', 3.2], ['reveal', 3.6, 'reveal', 'clipWide'], ['benefits', 4.0], ['ingredients', 3.0], ['offer', 4.2]],
  };

  function available(format, name, S) {
    const t = S.t, M = S.media;
    if (name === 'benefits') return lines(t.benefits).length >= 2 && !!M.wide;
    if (name === 'ingredients') return !!t.countNumber || lines(t.tags).length >= 3;
    if (name === 'inside') return (t.inside || []).filter((r) => r && r[0]).length >= 2;
    if (name === 'nothing') return lines(t.nothing).length >= 2;
    if (name === 'howto') return (t.steps || []).filter((r) => r && r[0]).length >= 2;
    if (name === 'meet') return !!M.clipOpen;
    return true;
  }

  function build(format, S) {
    const isWide = format === 'landscape';
    const W = isWide ? 1920 : 1080, H = isWide ? 1080 : 1920;
    S = { ...S, v: S.v || variation(1, 'elegant') };
    const draw = isWide ? landscapeScenes(S) : portraitScenes(S);
    // Some looks swap the two middle scenes so the story order changes too.
    const plan = PLANS[format].map((p) => p.slice());
    if (S.v.swapMiddle) {
      const [a, b] = format === 'footage' ? ['inside', 'nothing'] : ['benefits', 'ingredients'];
      const i = plan.findIndex((p) => p[0] === a), j = plan.findIndex((p) => p[0] === b);
      if (i > -1 && j > -1) [plan[i], plan[j]] = [plan[j], plan[i]];
    }
    let at = 0;
    const scenes = [], voices = [], pop = [];
    plan.forEach(([name, base, voiceKey, clipRole]) => {
      if (!available(format, name, S)) return;
      const key = voiceKey || name;
      const vd = S.voiceDur[key] || 0;
      const lead = scenes.length ? 0.3 : 0.15;
      const len = Math.max(base * (S.v.pace || 1), vd ? vd + lead + 0.5 : 0);
      const clip = clipRole && S.media[clipRole] && S.media[clipRole].el.tagName === 'VIDEO' ? S.media[clipRole] : null;
      scenes.push({ name, from: at, to: at + len, draw: draw[name], clip });
      if (vd) voices.push({ key, at: at + lead });
      if (name === 'benefits') lines(S.t.benefits).slice(0, 4).forEach((_, i) => pop.push(at + 0.65 + i * 0.42));
      if (name === 'inside') (S.t.inside || []).slice(0, 6).forEach((_, i) => pop.push(at + 0.35 + i * 0.28));
      if (name === 'nothing') lines(S.t.nothing).slice(0, 4).forEach((_, i) => pop.push(at + 0.5 + i * 0.3));
      at += len;
    });
    const offer = scenes[scenes.length - 1];
    return {
      format, w: W, h: H, duration: at, scenes, voices, v: S.v,
      palette: S.hue ? paletteFor(S.hue, S.v.accent) : null,
      cues: {
        style: S.v.music,
        drop: scenes[1] ? scenes[1].from : 0,
        whoosh: scenes.slice(1).map((s) => s.from),
        hit: [offer.from + 0.35],
        pop: pop.concat([1.1, 1.2, 1.3, 1.35].map((d) => offer.from + d)),
      },
    };
  }

  window.AdTemplates = { build, PLANS, available, variation, LOOKS, FONT_PAIRS };
})();
