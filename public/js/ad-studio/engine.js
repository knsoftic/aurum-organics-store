/* Ad Studio — motion-graphics engine.
   Deterministic canvas renderer -> H.264/AAC MP4 via WebCodecs + mp4-muxer (no server, no API). */
(function () {
  'use strict';

  const FPS = 30;
  const F = { sans: 'Montserrat', serif: 'Cormorant Garamond', body: 'Jost', urdu: 'Noto Nastaliq Urdu' };

  // Palette is replaced per project (see setPalette / Analyze.paletteFrom).
  const C = {
    ink: '#1d0d36', deep: '#2a1050', purple: '#3b1a6b', royal: '#5a2ea6', violet: '#7c4dc8',
    lav: '#d8c5f0', lavLight: '#f3ecfc', muted: '#6e6580', red: '#e0393e',
    bg1: '#f6f0fd', bg2: '#dcc8f3', dark1: '#36156a', dark2: '#14062b', glow: 'rgba(146,98,214,.55)', glowDark: 'rgba(160,110,235,.55)',
    leaf: '#4f9a58', petal: '#8a5cd0',
  };
  function setPalette(p) { Object.assign(C, p || {}); }

  // Look of the current video (see AdTemplates.variation): transitions, text motion, particles…
  const V = { transition: 'sweep', text: 'rise', particles: 'bokeh', enhance: true };
  function setVariation(v) { Object.assign(V, { transition: 'sweep', text: 'rise', particles: 'bokeh', enhance: true }, v || {}); }

  // ---------- Math / easing ----------
  const clamp = (v, a = 0, b = 1) => Math.min(b, Math.max(a, v));
  const lerp = (a, b, t) => a + (b - a) * t;
  const ease = {
    out: (t) => 1 - Math.pow(1 - t, 3),
    inOut: (t) => (t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2),
    back: (t) => { const c1 = 1.9, c3 = c1 + 1; return 1 + c3 * Math.pow(t - 1, 3) + c1 * Math.pow(t - 1, 2); },
  };
  const pr = (t, start, len) => clamp((t - start) / len);
  function rng(seed) { return () => { seed |= 0; seed = (seed + 0x6D2B79F5) | 0; let t = Math.imul(seed ^ (seed >>> 15), 1 | seed); t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t; return ((t ^ (t >>> 14)) >>> 0) / 4294967296; }; }

  // ---------- Assets (by URL) ----------
  const cache = new Map();
  function loadImage(url) {
    if (cache.has(url)) return cache.get(url);
    const p = new Promise((res, rej) => { const i = new Image(); i.crossOrigin = 'anonymous'; i.onload = () => res(i); i.onerror = () => rej(new Error('Image failed: ' + url)); i.src = url; });
    cache.set(url, p); return p;
  }
  function loadVideo(url) {
    if (cache.has(url)) return cache.get(url);
    const p = new Promise((res, rej) => {
      const v = document.createElement('video');
      v.crossOrigin = 'anonymous'; v.muted = true; v.playsInline = true; v.preload = 'auto'; v.src = url;
      v.addEventListener('loadeddata', () => res(v), { once: true });
      v.onerror = () => rej(new Error('Video failed: ' + url));
    });
    cache.set(url, p); return p;
  }
  async function seek(v, time) {
    time = Math.max(0, Math.min(time, v.duration - 0.05));
    if (Math.abs(v.currentTime - time) < 0.002) return;
    await new Promise((r) => { v.addEventListener('seeked', r, { once: true }); v.currentTime = time; });
  }

  // ---------- Drawing helpers ----------
  function font(o) { return `${o.it ? 'italic ' : ''}${o.w || 400} ${o.s}px "${o.f || F.sans}"`; }

  function txt(ctx, s, x, y, o) {
    if (!s) return;
    ctx.save();
    ctx.font = font(o);
    ctx.fillStyle = o.c || C.purple;
    ctx.textAlign = o.a || 'center';
    ctx.textBaseline = o.base || 'alphabetic';
    ctx.globalAlpha *= o.alpha ?? 1;
    ctx.letterSpacing = (o.ls || 0) + 'px';
    if (o.dir) ctx.direction = o.dir;
    if (o.shadow) { ctx.shadowColor = o.shadow; ctx.shadowBlur = 30; ctx.shadowOffsetY = 8; }
    if (o.max) { const w = ctx.measureText(s).width; if (w > o.max) ctx.font = font({ ...o, s: o.s * o.max / w }); }
    ctx.fillText(s, x, y);
    ctx.restore();
  }
  function measure(ctx, s, o) { ctx.save(); ctx.font = font(o); ctx.letterSpacing = (o.ls || 0) + 'px'; const w = ctx.measureText(s || '').width; ctx.restore(); return w; }

  // Animated text entrance; style depends on the video's look (V.text).
  function reveal(ctx, s, x, y, o, p) {
    if (!s) return;
    p = clamp(p);
    const e = ease.out(p), alpha = (o.alpha ?? 1) * clamp(p * 2.5);
    ctx.save();
    if (V.text === 'slide') {
      txt(ctx, s, x - (1 - e) * o.s * 1.4, y, { ...o, alpha });
    } else if (V.text === 'pop') {
      const k = lerp(0.55, 1, ease.back(p));
      ctx.translate(x, y - o.s * 0.35); ctx.scale(k, k); ctx.translate(-x, -(y - o.s * 0.35));
      txt(ctx, s, x, y, { ...o, alpha });
    } else if (V.text === 'type' && o.dir !== 'rtl') {
      // Lay out the full line first so the text does not shift while it types out.
      let style = o, full = measure(ctx, s, o);
      if (o.max && full > o.max) { style = { ...o, s: o.s * o.max / full }; full = o.max; }
      const chars = Array.from(String(s)), part = chars.slice(0, Math.ceil(chars.length * clamp(p * 1.4))).join('');
      const align = o.a || 'center', left = align === 'center' ? x - full / 2 : align === 'right' ? x - full : x;
      txt(ctx, part, left, y, { ...style, a: 'left', max: undefined, alpha: Math.min(1, (o.alpha ?? 1) * clamp(p * 6)) });
    } else {
      ctx.beginPath(); ctx.rect(0, y - o.s * 1.1, ctx.canvas.width, o.s * 1.45); ctx.clip();
      txt(ctx, s, x, y + (1 - e) * o.s * 1.25, { ...o, alpha });
    }
    ctx.restore();
  }

  function rrPath(ctx, x, y, w, h, r) {
    r = Math.min(r, w / 2, h / 2);
    ctx.beginPath();
    ctx.moveTo(x + r, y); ctx.arcTo(x + w, y, x + w, y + h, r); ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r); ctx.arcTo(x, y, x + w, y, r); ctx.closePath();
  }
  function archPath(ctx, x, y, w, h, r = 24) {
    ctx.beginPath();
    ctx.moveTo(x, y + w / 2); ctx.arc(x + w / 2, y + w / 2, w / 2, Math.PI, 0);
    ctx.lineTo(x + w, y + h - r); ctx.arcTo(x + w, y + h, x + w - r, y + h, r);
    ctx.lineTo(x + r, y + h); ctx.arcTo(x, y + h, x, y + h - r, r); ctx.closePath();
  }

  // Cover-crop with zoom; (fx, fy) is the subject's position found by the analyser.
  function cover(ctx, src, x, y, w, h, zoom = 1, fx = 0.5, fy = 0.5) {
    if (!src) return;
    const sw = src.videoWidth || src.naturalWidth, sh = src.videoHeight || src.naturalHeight;
    if (!sw || !sh) return;
    const scale = Math.max(w / sw, h / sh) * zoom;
    const cw = w / scale, ch = h / scale;
    const sx = clamp(fx * sw - cw / 2, 0, sw - cw), sy = clamp(fy * sh - ch / 2, 0, sh - ch);
    ctx.save();
    ctx.imageSmoothingQuality = 'high';
    if (V.enhance) ctx.filter = 'contrast(1.06) saturate(1.1) brightness(1.02)';
    ctx.drawImage(src, sx, sy, cw, ch, x, y, w, h);
    ctx.restore();
  }

  // Photo/clip in a frame. Shapes: round, arch, circle, tilt (polaroid), full (soft-faded edge).
  function card(ctx, media, x, y, w, h, o = {}) {
    if (!media || !media.el) return;
    const { r = 36, zoom = 1, alpha = 1, shadow = true } = o;
    let shape = o.shape || 'round';
    ctx.save();
    ctx.globalAlpha *= alpha;
    if (shape === 'circle') { const d = Math.min(w, h); x += (w - d) / 2; y += (h - d) / 2; w = h = d; }
    if (shape === 'tilt') {
      ctx.translate(x + w / 2, y + h / 2); ctx.rotate(o.tilt ?? -0.06); ctx.translate(-(x + w / 2), -(y + h / 2));
      const b = Math.min(w, h) * 0.035;
      ctx.save(); ctx.shadowColor = 'rgba(0,0,0,.3)'; ctx.shadowBlur = 60; ctx.shadowOffsetY = 30;
      rrPath(ctx, x - b, y - b, w + b * 2, h + b * 3.2, 18); ctx.fillStyle = '#fff'; ctx.fill(); ctx.restore();
      shape = 'round';
    }
    const path = () => {
      if (shape === 'arch') archPath(ctx, x, y, w, h);
      else if (shape === 'circle') { ctx.beginPath(); ctx.arc(x + w / 2, y + h / 2, w / 2, 0, Math.PI * 2); }
      else rrPath(ctx, x, y, w, h, shape === 'full' ? 0 : (o.shape === 'tilt' ? 10 : r));
    };
    if (shadow && shape !== 'full' && o.shape !== 'tilt') {
      ctx.save(); ctx.shadowColor = 'rgba(0,0,0,.28)'; ctx.shadowBlur = 70; ctx.shadowOffsetY = 34;
      path(); ctx.fillStyle = C.lavLight; ctx.fill(); ctx.restore();
    }
    path(); ctx.clip();
    cover(ctx, media.el, x, y, w, h, zoom, (media.fx ?? 0.5) + (o.panX || 0) * 0.06, media.fy ?? 0.5);
    if (shape === 'full') {
      const g = ctx.createLinearGradient(0, y + h * 0.62, 0, y + h);
      g.addColorStop(0, 'rgba(255,255,255,0)'); g.addColorStop(1, C.bg2);
      ctx.fillStyle = g; ctx.fillRect(x, y, w, h);
    }
    if (o.sweep !== undefined) sweepLight(ctx, x, y, w, h, o.sweep);
    ctx.restore();
    if (shape === 'circle' && alpha > 0) {
      ctx.save(); ctx.globalAlpha *= alpha; ctx.strokeStyle = C.violet; ctx.lineWidth = 4;
      ctx.beginPath(); ctx.arc(x + w / 2, y + h / 2, w / 2 + 18, 0, Math.PI * 2); ctx.stroke(); ctx.restore();
    }
  }

  function sweepLight(ctx, x, y, w, h, p) {
    if (p <= 0 || p >= 1) return;
    const cx = lerp(x - w * 0.6, x + w * 1.6, ease.inOut(p));
    const g = ctx.createLinearGradient(cx - 180, y, cx + 180, y + 120);
    g.addColorStop(0, 'rgba(255,255,255,0)'); g.addColorStop(0.5, 'rgba(255,255,255,.45)'); g.addColorStop(1, 'rgba(255,255,255,0)');
    ctx.save(); ctx.globalCompositeOperation = 'lighter'; ctx.fillStyle = g; ctx.fillRect(x, y, w, h); ctx.restore();
  }

  function glow(ctx, x, y, r, color, alpha) {
    const g = ctx.createRadialGradient(x, y, 0, x, y, r);
    g.addColorStop(0, color); g.addColorStop(1, 'rgba(255,255,255,0)');
    ctx.save(); ctx.globalAlpha *= alpha; ctx.fillStyle = g; ctx.fillRect(x - r, y - r, r * 2, r * 2); ctx.restore();
  }

  function pill(ctx, x, y, w, h, fill, stroke) {
    rrPath(ctx, x, y, w, h, h / 2);
    if (fill) { ctx.fillStyle = fill; ctx.fill(); }
    if (stroke) { ctx.strokeStyle = stroke; ctx.lineWidth = 2; ctx.stroke(); }
  }

  function checkIcon(ctx, cx, cy, r, bg, fg = '#fff') {
    ctx.beginPath(); ctx.arc(cx, cy, r, 0, Math.PI * 2); ctx.fillStyle = bg || C.purple; ctx.fill();
    ctx.beginPath(); ctx.moveTo(cx - r * 0.42, cy + r * 0.02); ctx.lineTo(cx - r * 0.1, cy + r * 0.34); ctx.lineTo(cx + r * 0.46, cy - r * 0.3);
    ctx.strokeStyle = fg; ctx.lineWidth = r * 0.18; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.stroke();
  }
  function crossIcon(ctx, cx, cy, r) {
    ctx.beginPath(); ctx.arc(cx, cy, r, 0, Math.PI * 2); ctx.fillStyle = C.red; ctx.fill();
    ctx.beginPath(); const k = r * 0.36;
    ctx.moveTo(cx - k, cy - k); ctx.lineTo(cx + k, cy + k); ctx.moveTo(cx + k, cy - k); ctx.lineTo(cx - k, cy + k);
    ctx.strokeStyle = '#fff'; ctx.lineWidth = r * 0.18; ctx.lineCap = 'round'; ctx.stroke();
  }

  // Brand lock-up: optional round mark + name.
  function logo(ctx, brand, x, y, size, color, alpha = 1, align = 'center') {
    const o = { f: F.sans, w: 800, s: size * 0.42, ls: size * 0.09, c: color, a: 'left', base: 'middle' };
    const tw = measure(ctx, brand.name, o);
    const markW = brand.mark ? size + size * 0.28 : 0;
    const left = align === 'center' ? x - (markW + tw) / 2 : x;
    ctx.save(); ctx.globalAlpha *= alpha;
    if (brand.mark) ctx.drawImage(brand.mark, left, y - size / 2, size, size);
    txt(ctx, brand.name, left + markW, y + 2, o);
    ctx.restore();
  }

  // ---------- Backgrounds ----------
  const PARTS = (() => { const r = rng(7); return Array.from({ length: 34 }, () => ({ x: r(), y: r(), s: r(), sp: 0.4 + r() * 0.8, ph: r() * 6.28, kind: r() })); })();

  function bgLight(ctx, W, H, t) {
    const g = ctx.createLinearGradient(0, 0, W * 0.3, H);
    g.addColorStop(0, C.bg1); g.addColorStop(1, C.bg2);
    ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
    glow(ctx, W * (0.25 + 0.08 * Math.sin(t * 0.5)), H * 0.22, Math.max(W, H) * 0.55, 'rgba(255,255,255,.95)', 0.9);
    glow(ctx, W * (0.85 + 0.05 * Math.cos(t * 0.4)), H * 0.78, Math.max(W, H) * 0.5, C.glow, 0.55);
    particles(ctx, W, H, t, false);
  }
  function bgDark(ctx, W, H, t) {
    const g = ctx.createLinearGradient(0, 0, W * 0.4, H);
    g.addColorStop(0, C.dark1); g.addColorStop(1, C.dark2);
    ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
    glow(ctx, W * 0.5, H * 0.38, Math.max(W, H) * 0.55, C.glowDark, 0.55 + 0.1 * Math.sin(t * 1.3));
    particles(ctx, W, H, t, true);
  }
  function particles(ctx, W, H, t, dark) {
    const unit = Math.min(W, H);
    if (V.particles === 'none') return;
    if (V.particles === 'sparkle') {
      PARTS.forEach((p) => {
        const tw = 0.5 + 0.5 * Math.sin(t * (2 + p.sp * 3) + p.ph * 3);
        const x = (p.x + Math.sin(t * 0.2 + p.ph) * 0.01) * W, y = ((p.y - t * 0.012 * p.sp) % 1 + 1) % 1 * H;
        const s = (6 + p.s * 14) * unit / 1080 * (0.4 + tw * 0.6);
        ctx.save(); ctx.globalAlpha = (dark ? 0.7 : 0.55) * tw; ctx.fillStyle = dark ? '#fff' : C.violet;
        ctx.translate(x, y); ctx.rotate(p.ph);
        ctx.beginPath();
        for (let k = 0; k < 8; k++) { const rr = k % 2 ? s * 0.28 : s; const a = k * Math.PI / 4; ctx.lineTo(Math.cos(a) * rr, Math.sin(a) * rr); }
        ctx.closePath(); ctx.fill(); ctx.restore();
      });
      return;
    }
    const onlyNature = V.particles === 'leaves';
    PARTS.forEach((p, i) => {
      if (onlyNature && p.kind < 0.62) return;
      const y = ((p.y - t * 0.035 * p.sp) % 1 + 1) % 1;
      const x = p.x + Math.sin(t * 0.8 * p.sp + p.ph) * 0.02;
      const px = x * W, py = y * H;
      ctx.save();
      if (p.kind < 0.62) {
        ctx.globalAlpha = (dark ? 0.18 : 0.45) * (0.4 + p.s * 0.6);
        ctx.fillStyle = dark ? C.lav : '#ffffff';
        ctx.beginPath(); ctx.arc(px, py, (4 + p.s * 18) * unit / 1080, 0, Math.PI * 2); ctx.fill();
      } else if (p.kind < 0.82) {
        const s = (14 + p.s * 22) * unit / 1080;
        ctx.translate(px, py); ctx.rotate(p.ph + t * 0.3 * (i % 2 ? 1 : -1));
        ctx.globalAlpha = dark ? 0.22 : 0.35; ctx.fillStyle = C.leaf;
        ctx.beginPath(); ctx.ellipse(0, 0, s, s * 0.42, 0, 0, Math.PI * 2); ctx.fill();
      } else {
        const s = (6 + p.s * 10) * unit / 1080;
        ctx.translate(px, py); ctx.rotate(p.ph + t);
        ctx.globalAlpha = dark ? 0.35 : 0.5; ctx.fillStyle = dark ? C.lav : C.petal;
        ctx.beginPath(); ctx.ellipse(0, 0, s, s * 0.55, 0, 0, Math.PI * 2); ctx.fill();
      }
      ctx.restore();
    });
  }

  // Scene-cut transitions; each fully covers the frame exactly at the cut.
  function transition(ctx, W, H, t, at) {
    const u = pr(t, at - 0.32, 0.64);
    if (u <= 0 || u >= 1) return;
    if (V.transition === 'iris') {
      const k = u < 0.5 ? ease.inOut(u * 2) : 1 - ease.inOut((u - 0.5) * 2);
      const R = Math.hypot(W, H) / 2 * k;
      ctx.save();
      ctx.fillStyle = C.violet; ctx.beginPath(); ctx.arc(W / 2, H / 2, R * 1.08 + 1, 0, Math.PI * 2); ctx.fill();
      ctx.fillStyle = C.deep; ctx.beginPath(); ctx.arc(W / 2, H / 2, R, 0, Math.PI * 2); ctx.fill();
      ctx.restore();
      return;
    }
    if (V.transition === 'slide') {
      const k = u < 0.5 ? ease.inOut(u * 2) : 1 - ease.inOut((u - 0.5) * 2);
      const n = 4, colW = W / n;
      ctx.save();
      for (let i = 0; i < n; i++) {
        const delay = i * 0.06, kk = clamp((k - delay) / (1 - 0.06 * (n - 1)));
        const hh = H * ease.out(kk) * 1.02;
        ctx.fillStyle = i % 2 ? C.deep : C.purple;
        if (u < 0.5) ctx.fillRect(i * colW - 1, 0, colW + 2, hh); else ctx.fillRect(i * colW - 1, H - hh, colW + 2, hh);
      }
      ctx.restore();
      return;
    }
    if (V.transition === 'flash') {
      const k = 1 - Math.abs(u - 0.5) * 2;
      ctx.save();
      ctx.globalAlpha = ease.inOut(clamp(k * 1.25)); ctx.fillStyle = '#ffffff'; ctx.fillRect(0, 0, W, H);
      ctx.globalAlpha = ease.inOut(clamp(k * 1.6 - 0.6)) * 0.35; ctx.fillStyle = C.lav; ctx.fillRect(0, 0, W, H);
      ctx.restore();
      return;
    }
    const skew = Math.min(W, H) * 0.25;
    [[C.violet, 0.07], [C.deep, 0]].forEach(([color, lead]) => {
      const x = lerp(-W * 2.3, W * 2.3, ease.inOut(clamp(u + lead)));
      ctx.save(); ctx.fillStyle = color; ctx.beginPath();
      ctx.moveTo(x - W * 0.6 + skew, 0); ctx.lineTo(x + W * 1.6 + skew, 0);
      ctx.lineTo(x + W * 1.6 - skew, H); ctx.lineTo(x - W * 0.6 - skew, H); ctx.closePath(); ctx.fill();
      ctx.restore();
    });
  }

  // ---------- Runner ----------
  async function drawFrame(ctx, comp, t, seekVideos = true) {
    if (comp.v) setVariation(comp.v);
    const scene = comp.scenes.find((s) => t >= s.from && t < s.to) || comp.scenes[comp.scenes.length - 1];
    const lt = t - scene.from;
    if (seekVideos && scene.clip && scene.clip.el) {
      const v = scene.clip.el; const d = Math.max(0.2, v.duration - 0.1);
      await seek(v, ((scene.clip.start || 0) + lt) % d);
    }
    ctx.save();
    scene.draw(ctx, { W: comp.w, H: comp.h, t, lt, dur: scene.to - scene.from });
    ctx.restore();
    comp.scenes.forEach((s, i) => { if (i > 0) transition(ctx, comp.w, comp.h, t, s.from); });
  }

  async function renderMp4(canvas, comp, audioBuffer, onProgress) {
    const ctx = canvas.getContext('2d');
    canvas.width = comp.w; canvas.height = comp.h;
    const frames = Math.round(comp.duration * FPS);
    const { Muxer, ArrayBufferTarget } = window.Mp4Muxer;
    const muxer = new Muxer({
      target: new ArrayBufferTarget(),
      video: { codec: 'avc', width: comp.w, height: comp.h, frameRate: FPS },
      audio: { codec: 'aac', numberOfChannels: 2, sampleRate: 48000 },
      fastStart: 'in-memory',
      firstTimestampBehavior: 'offset',
    });

    let failure = null;
    const venc = new VideoEncoder({ output: (c, m) => muxer.addVideoChunk(c, m), error: (e) => { failure = e; } });
    const config = { codec: 'avc1.640033', width: comp.w, height: comp.h, bitrate: 10_000_000, framerate: FPS, avc: { format: 'avc' } };
    const support = await VideoEncoder.isConfigSupported(config);
    if (!support.supported) throw new Error('This browser cannot encode H.264 video. Please use Google Chrome or Microsoft Edge on a computer.');
    venc.configure(config);

    for (let i = 0; i < frames; i++) {
      if (failure) throw failure;
      await drawFrame(ctx, comp, i / FPS, true);
      const frame = new VideoFrame(canvas, { timestamp: Math.round(i * 1e6 / FPS), duration: Math.round(1e6 / FPS) });
      venc.encode(frame, { keyFrame: i % (FPS * 2) === 0 });
      frame.close();
      while (venc.encodeQueueSize > 8) await new Promise((r) => venc.addEventListener('dequeue', r, { once: true }));
      if (i % 15 === 0) onProgress(i / frames * 0.9, `Frames ${i}/${frames}`);
    }
    await venc.flush();

    onProgress(0.92, 'Audio…');
    const aenc = new AudioEncoder({ output: (c, m) => muxer.addAudioChunk(c, m), error: (e) => { failure = e; } });
    aenc.configure({ codec: 'mp4a.40.2', sampleRate: 48000, numberOfChannels: 2, bitrate: 192000 });
    const L = audioBuffer.getChannelData(0), R = audioBuffer.getChannelData(audioBuffer.numberOfChannels > 1 ? 1 : 0), block = 4800;
    for (let s = 0; s < L.length; s += block) {
      const n = Math.min(block, L.length - s);
      const data = new Float32Array(n * 2);
      data.set(L.subarray(s, s + n), 0); data.set(R.subarray(s, s + n), n);
      const ad = new AudioData({ format: 'f32-planar', sampleRate: 48000, numberOfFrames: n, numberOfChannels: 2, timestamp: Math.round(s / 48000 * 1e6), data });
      aenc.encode(ad); ad.close();
    }
    await aenc.flush();
    if (failure) throw failure;

    muxer.finalize();
    return muxer.target.buffer;
  }

  window.AdEngine = {
    FPS, C, F, V, rng, setPalette, setVariation, clamp, lerp, ease, pr, txt, reveal, measure, rrPath, archPath, cover, card, sweepLight, glow,
    pill, checkIcon, crossIcon, logo, bgLight, bgDark, loadImage, loadVideo, seek, drawFrame, renderMp4,
  };
})();
