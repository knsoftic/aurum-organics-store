/* Ad Studio — automatic media analysis (runs in the browser, no API).
   - Finds the product in each photo (difference from the background colour) -> crop focus point.
   - Measures each clip's orientation, length and amount of motion.
   - Extracts the dominant brand hue from the photos -> full video colour palette.
   - Assigns the best photo / clip to every role the templates use. */
(function () {
  'use strict';
  const E = window.AdEngine;

  function rgbToHsl(r, g, b) {
    r /= 255; g /= 255; b /= 255;
    const max = Math.max(r, g, b), min = Math.min(r, g, b), l = (max + min) / 2;
    if (max === min) return [0, 0, l];
    const d = max - min, s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
    let h = max === r ? (g - b) / d + (g < b ? 6 : 0) : max === g ? (b - r) / d + 2 : (r - g) / d + 4;
    return [h * 60, s, l];
  }
  const hsl = (h, s, l, a) => (a === undefined ? `hsl(${h.toFixed(1)} ${(s * 100).toFixed(1)}% ${(l * 100).toFixed(1)}%)` : `hsl(${h.toFixed(1)} ${(s * 100).toFixed(1)}% ${(l * 100).toFixed(1)}% / ${a})`);

  function hexToHs(hex) {
    const m = /^#?([0-9a-f]{6})$/i.exec(hex || '');
    if (!m) return null;
    const n = parseInt(m[1], 16);
    const [h, s] = rgbToHsl((n >> 16) & 255, (n >> 8) & 255, n & 255);
    return { h, s };
  }

  // Pixel statistics for one drawable (image or video frame).
  function pixelStats(src) {
    const w = src.videoWidth || src.naturalWidth, h = src.videoHeight || src.naturalHeight;
    const scale = 160 / Math.max(w, h);
    const cw = Math.max(8, Math.round(w * scale)), ch = Math.max(8, Math.round(h * scale));
    const c = document.createElement('canvas'); c.width = cw; c.height = ch;
    const ctx = c.getContext('2d', { willReadFrequently: true });
    ctx.drawImage(src, 0, 0, cw, ch);
    const d = ctx.getImageData(0, 0, cw, ch).data;

    // Background = median colour of the outer border.
    const border = [];
    const ring = Math.max(2, Math.round(Math.min(cw, ch) * 0.06));
    for (let y = 0; y < ch; y++) for (let x = 0; x < cw; x++) {
      if (x < ring || y < ring || x >= cw - ring || y >= ch - ring) { const i = (y * cw + x) * 4; border.push([d[i], d[i + 1], d[i + 2]]); }
    }
    const med = (k) => border.map((p) => p[k]).sort((a, b) => a - b)[border.length >> 1];
    const bg = [med(0), med(1), med(2)];

    let sx = 0, sy = 0, n = 0, minX = cw, minY = ch, maxX = 0, maxY = 0;
    const hues = new Float32Array(36);
    let gray = 0;
    for (let y = 0; y < ch; y++) for (let x = 0; x < cw; x++) {
      const i = (y * cw + x) * 4, r = d[i], g = d[i + 1], b = d[i + 2];
      const dist = Math.hypot(r - bg[0], g - bg[1], b - bg[2]);
      if (dist > 48) { sx += x; sy += y; n++; if (x < minX) minX = x; if (y < minY) minY = y; if (x > maxX) maxX = x; if (y > maxY) maxY = y; }
      const [hh, ss, ll] = rgbToHsl(r, g, b);
      if (ss > 0.22 && ll > 0.12 && ll < 0.88) hues[Math.floor(hh / 10) % 36] += ss;
      gray += (r + g + b) / 3;
    }
    const fx = n > 30 ? sx / n / cw : 0.5, fy = n > 30 ? sy / n / ch : 0.5;
    return {
      w, h, aspect: w / h, fx: E.clamp(fx, 0.2, 0.8), fy: E.clamp(fy, 0.25, 0.75),
      bbox: n > 30 ? [minX / cw, minY / ch, maxX / cw, maxY / ch] : [0, 0, 1, 1],
      hues: Array.from(hues), brightness: gray / (cw * ch) / 255, gray: getGray(d, cw, ch),
    };
  }
  function getGray(d, cw, ch) {
    const g = new Float32Array(cw * ch);
    for (let i = 0; i < g.length; i++) g[i] = (d[i * 4] + d[i * 4 + 1] + d[i * 4 + 2]) / 3;
    return g;
  }

  async function analyzeImage(url) {
    const img = await E.loadImage(url);
    const s = pixelStats(img); delete s.gray;
    return s;
  }

  async function analyzeVideo(url) {
    const v = await E.loadVideo(url);
    const times = [0.12, 0.38, 0.62, 0.86].map((f) => f * v.duration);
    let prev = null, motion = 0, stats = null;
    for (const t of times) {
      await E.seek(v, t);
      const s = pixelStats(v);
      if (prev && prev.length === s.gray.length) {
        let diff = 0; for (let i = 0; i < s.gray.length; i++) diff += Math.abs(s.gray[i] - prev[i]);
        motion += diff / s.gray.length;
      }
      prev = s.gray; stats = stats || s;
    }
    await E.seek(v, 0.3);
    return { w: v.videoWidth, h: v.videoHeight, aspect: v.videoWidth / v.videoHeight, duration: v.duration, motion: motion / 3, fx: stats.fx, fy: stats.fy, hues: stats.hues };
  }

  // Full palette from a single hue/saturation.
  function paletteFrom(h, s) {
    // Yellow is far brighter than other hues at the same lightness, so its accent is darkened to stay readable.
    s = E.clamp(s, 0, 0.85);
    return {
      ink: hsl(h, s * 0.6, 0.11), deep: hsl(h, s * 0.9, 0.17), purple: hsl(h, s, 0.27), royal: hsl(h, s, 0.38),
      violet: hsl(h, Math.min(1, s + 0.1), h > 45 && h < 80 ? 0.42 : 0.53), lav: hsl(h, s * 0.7, 0.84), lavLight: hsl(h, s * 0.6, 0.95),
      muted: hsl(h, 0.1, 0.45), bg1: hsl(h, s * 0.6, 0.97), bg2: hsl(h, s * 0.55, 0.86),
      dark1: hsl(h, s * 0.8, 0.25), dark2: hsl(h, s * 0.9, 0.09),
      glow: hsl(h, s, 0.62, 0.55), glowDark: hsl(h, s, 0.66, 0.55),
      leaf: '#4f9a58', petal: hsl(h, s, 0.6),
    };
  }

  function dominantHue(results) {
    const bins = new Float32Array(36);
    results.forEach((r) => r.hues && r.hues.forEach((v, i) => { bins[i] += v; }));
    let best = 0; for (let i = 1; i < 36; i++) if (bins[i] > bins[best]) best = i;
    const total = bins.reduce((a, b) => a + b, 0);
    if (total < 1) return null;
    // Weighted hue average across the winning bin and its neighbours.
    let sum = 0, wsum = 0;
    for (let k = -1; k <= 1; k++) {
      const i = (best + k + 36) % 36;
      const wrap = k === -1 && best === 0 ? -360 : k === 1 && best === 35 ? 360 : 0;
      sum += (i * 10 + 5 + wrap) * bins[i]; wsum += bins[i];
    }
    const share = bins[best] / total;
    return { h: ((sum / wsum) + 360) % 360, s: E.clamp(0.45 + share * 0.6, 0.45, 0.8) };
  }

  function score(item, target) {
    return -Math.abs(Math.log(item.aspect / target)) + 0.18 * Math.log(Math.min(item.w, item.h) / 600 + 0.01);
  }
  function pick(entries, target, used) {
    const sorted = [...entries].sort((a, b) => score(b[1], target) - score(a[1], target));
    return (sorted.find(([p]) => !used.has(p)) || sorted[0] || [null])[0];
  }

  function assignRoles(images, videos) {
    const roles = {};
    const imgs = Object.entries(images), used = new Set();
    roles.hero = pick(imgs, 0.8, used); used.add(roles.hero);
    roles.wide = pick(imgs, 1.33, used); used.add(roles.wide);
    roles.square = pick(imgs, 1.0, used); used.add(roles.square);
    roles.thumb = pick(imgs, 1.0, used);
    const vids = Object.entries(videos).sort((a, b) => b[1].motion - a[1].motion);
    const portrait = vids.filter(([, v]) => v.aspect < 1), landscape = vids.filter(([, v]) => v.aspect >= 1);
    roles.clipOpen = (portrait[0] || vids[0] || [null])[0];
    roles.clipHowto = (portrait[1] || portrait[0] || vids[1] || vids[0] || [null])[0];
    roles.clipWide = (landscape[0] || vids[0] || [null])[0];
    return roles;
  }

  async function run(media, urlOf, onProgress) {
    const images = {}, videos = {};
    const imgList = media.images.filter((m) => m.enabled), vidList = media.videos.filter((m) => m.enabled);
    const total = imgList.length + vidList.length; let done = 0;
    for (const m of imgList) {
      try { images[m.path] = await analyzeImage(urlOf(m.path)); } catch (e) { console.warn(e); }
      onProgress(++done / total, 'Photos ' + done + '/' + total);
    }
    for (const m of vidList) {
      try { videos[m.path] = await analyzeVideo(urlOf(m.path)); } catch (e) { console.warn(e); }
      onProgress(++done / total, 'Clips ' + done + '/' + total);
    }
    const dom = dominantHue([...Object.values(images), ...Object.values(videos)]);
    Object.values(images).forEach((i) => delete i.hues);
    Object.values(videos).forEach((v) => delete v.hues);
    return { images, videos, roles: assignRoles(images, videos), hue: dom, at: new Date().toISOString() };
  }

  window.AdAnalyze = { run, paletteFrom, hexToHs, assignRoles };
})();
