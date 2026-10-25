/* Ad Studio — admin UI: media & analysis, script, Urdu voice, preview, render & upload. */
(function () {
  'use strict';
  const S = window.STUDIO, E = window.AdEngine, AN = window.AdAnalyze, T = window.AdTemplates, AU = window.AdAudio;
  const state = { title: S.title, config: S.config, renders: S.renders || [] };
  const cfg = () => state.config;
  const $ = (sel, el = document) => el.querySelector(sel);
  const panel = (name) => $(`[data-panel="${name}"]`);
  const csrf = $('meta[name=csrf-token]').content;
  const url = (p) => (!p ? null : /^https?:/.test(p) ? p : S.uploads + '/' + String(p).replace(/^\//, ''));
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const base = (p) => String(p).split('/').pop();

  // ---------- Config defaults ----------
  (function normalise() {
    const c = cfg();
    c.media = c.media || {}; c.media.images = c.media.images || []; c.media.videos = c.media.videos || [];
    c.palette = c.palette || { mode: 'auto', primary: null };
    c.formats = c.formats || ['motion', 'footage', 'landscape'];
    c.music = c.music || { enabled: true, volume: 0.8 };
    c.voice = c.voice || {}; c.voice.lines = c.voice.lines || {}; c.voice.clips = Array.isArray(c.voice.clips) ? {} : (c.voice.clips || {});
    c.voice.mode = c.voice.mode || 'none'; c.voice.voice = c.voice.voice || Object.keys(S.voices)[0]; c.voice.rate = c.voice.rate || 0;
    c.text = c.text || {};
  })();

  // ---------- Server ----------
  async function api(endpoint, opts = {}) {
    const headers = { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' };
    if (opts.json) headers['Content-Type'] = 'application/json';
    const res = await fetch(endpoint, { method: opts.method || 'POST', credentials: 'same-origin', headers, body: opts.json ? JSON.stringify(opts.json) : opts.body });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.message || (data.errors && Object.values(data.errors)[0][0]) || `Request failed (${res.status})`);
    return data;
  }

  let saveTimer = null;
  function dirty(rebuild = true) {
    setSave('dirty'); clearTimeout(saveTimer); saveTimer = setTimeout(save, 900);
    if (rebuild) schedulePreview();
  }
  async function save() {
    clearTimeout(saveTimer); saveTimer = null;
    try { await api(S.routes.update, { method: 'PUT', json: { title: state.title, config: state.config } }); setSave('saved'); } catch (e) { setSave('error', e.message); }
  }
  // Leaving the page with an unsaved change: send it immediately.
  window.addEventListener('pagehide', () => {
    if (!saveTimer) return;
    fetch(S.routes.update, {
      method: 'PUT', keepalive: true, credentials: 'same-origin',
      headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({ title: state.title, config: state.config }),
    });
  });
  function setSave(s, msg) {
    const el = $('#saveState'); if (!el) return;
    el.className = 'studio-save ' + (s === 'saved' ? '' : s);
    el.textContent = s === 'saved' ? 'All changes saved' : s === 'dirty' ? 'Saving…' : 'Not saved: ' + msg;
  }
  $('#projectTitle').addEventListener('input', (e) => { state.title = e.target.value || 'Untitled ad'; dirty(false); });

  // ---------- Tabs ----------
  document.querySelectorAll('[data-step]').forEach((b) => b.addEventListener('click', () => {
    document.querySelectorAll('[data-step]').forEach((x) => x.classList.toggle('active', x === b));
    document.querySelectorAll('[data-panel]').forEach((p) => { p.hidden = p.dataset.panel !== b.dataset.step; });
    ({ media: renderMedia, script: renderScript, voice: renderVoice, render: renderRenderPanel })[b.dataset.step]();
  }));

  // ---------- Palette ----------
  function fromHex(hex) { const hs = AN.hexToHs(hex) || AN.hexToHs('#5a2ea6'); return AN.paletteFrom(hs.h, hs.s); }
  function currentPalette() {
    const c = cfg(), A = c.analysis;
    if (c.palette.mode === 'custom') return fromHex(c.palette.primary || S.storeColor);
    if (c.palette.mode === 'auto' && A && A.hue) return AN.paletteFrom(A.hue.h, A.hue.s);
    return fromHex(S.storeColor);
  }

  // ================= 1 · MEDIA & ANALYSIS =================
  const ROLE_NAMES = { hero: 'Hero', wide: 'Benefits', square: 'Offer', thumb: 'Hook', clipOpen: 'Opener', clipHowto: 'How-to', clipWide: 'Landscape' };

  function renderMedia() {
    const c = cfg(), A = c.analysis, roles = (A && A.roles) || {};
    const badges = (p) => Object.entries(roles).filter(([, v]) => v === p).map(([r]) => `<span>${ROLE_NAMES[r]}</span>`).join('');
    const item = (m, i, type) => {
      const a = A && (type === 'images' ? A.images : A.videos)[m.path];
      const focus = a && type === 'images' ? `<i class="focus" style="left:${(a.fx * 100).toFixed(0)}%;top:${(a.fy * 100).toFixed(0)}%"></i>` : '';
      const meta = a && type === 'videos' ? `<span class="meta">${a.duration.toFixed(0)}s · ${a.aspect < 1 ? '9:16' : '16:9'}</span>` : '';
      const el = type === 'images' ? `<img src="${esc(url(m.path))}" loading="lazy" alt="">` : `<video src="${esc(url(m.path))}#t=0.5" muted preload="metadata"></video>`;
      return `<div class="media-item ${m.enabled ? '' : 'off'}">${el}${focus}${meta}<label><input type="checkbox" data-toggle="${type}:${i}" ${m.enabled ? 'checked' : ''}> Use</label><div class="roles">${badges(m.path)}</div></div>`;
    };
    const pal = currentPalette();
    panel('media').innerHTML = `
      <h4>Photos (${c.media.images.length})</h4>
      <div class="media-grid">${c.media.images.map((m, i) => item(m, i, 'images')).join('')}
        <label class="upload-tile"><input type="file" accept="image/jpeg,image/png,image/webp,video/mp4,video/webm,video/quicktime" multiple hidden data-upload>+ Add photos or videos</label></div>
      <h4>Videos (${c.media.videos.length})</h4>
      <div class="media-grid">${c.media.videos.map((m, i) => item(m, i, 'videos')).join('') || '<p class="muted" style="grid-column:1/-1;margin:0;font-size:13px">No clips yet — the footage reel needs at least one product video.</p>'}</div>
      <h4>Automatic analysis</h4>
      <div class="flex wrap" style="gap:12px">
        <button type="button" class="btn btn-primary btn-sm" data-analyze>${A ? 'Re-analyse media' : 'Analyse media'}</button>
        <span class="muted" style="font-size:12.5px">${A ? 'Last analysed ' + new Date(A.at).toLocaleString() + ' · gold dot = detected product position' : 'Finds the product in every photo, picks the best shot for each scene and builds the colour palette.'}</span>
      </div>
      <div class="progress" style="margin:12px 0" hidden data-analyze-progress><span></span></div>
      <h4>Colours</h4>
      <div class="flex wrap" style="gap:12px">
        <select class="select" data-palette-mode style="width:auto">
          <option value="auto">From product photos (auto)</option><option value="store">Website theme</option><option value="custom">Custom colour</option>
        </select>
        <input type="color" data-palette-color value="${esc(c.palette.primary || S.storeColor)}" ${c.palette.mode === 'custom' ? '' : 'hidden'}>
        <div class="swatches">${['deep', 'purple', 'royal', 'violet', 'lav', 'bg1'].map((k) => `<span style="background:${pal[k]}" title="${k}"></span>`).join('')}</div>
      </div>
      ${A ? roleSelects(A) : ''}`;
    $('[data-palette-mode]', panel('media')).value = c.palette.mode;
  }

  function roleSelects(A) {
    const c = cfg();
    const imgs = c.media.images.filter((m) => m.enabled && A.images[m.path]);
    const vids = c.media.videos.filter((m) => m.enabled && A.videos[m.path]);
    const sel = (role, list, allowNone) => `<div><label>${ROLE_NAMES[role]}</label><select class="select" data-role="${role}">
      ${allowNone ? '<option value="">— none —</option>' : ''}${list.map((m) => `<option value="${esc(m.path)}" ${A.roles[role] === m.path ? 'selected' : ''}>${esc(base(m.path))}</option>`).join('')}</select></div>`;
    return `<h4>Which media goes where</h4><div class="role-grid">
      ${['hero', 'wide', 'square', 'thumb'].map((r) => sel(r, imgs, false)).join('')}
      ${vids.length ? ['clipOpen', 'clipHowto', 'clipWide'].map((r) => sel(r, vids, true)).join('') : ''}</div>`;
  }

  panel('media').addEventListener('change', async (e) => {
    const c = cfg(), el = e.target;
    if (el.dataset.toggle) {
      const [type, i] = el.dataset.toggle.split(':');
      c.media[type][i].enabled = el.checked;
      if (c.analysis) reassignRoles();
      renderMedia(); dirty();
    } else if (el.matches('[data-upload]')) {
      await uploadMedia(el.files);
    } else if (el.matches('[data-palette-mode]')) {
      c.palette.mode = el.value; renderMedia(); dirty();
    } else if (el.matches('[data-role]')) {
      c.analysis.roles[el.dataset.role] = el.value || null; renderMedia(); dirty();
    }
  });
  panel('media').addEventListener('input', (e) => {
    if (e.target.matches('[data-palette-color]')) { cfg().palette.primary = e.target.value; dirty(); clearTimeout(renderMedia.t); renderMedia.t = setTimeout(renderMedia, 300); }
  });
  panel('media').addEventListener('click', (e) => { if (e.target.closest('[data-analyze]')) runAnalysis(); });

  function reassignRoles() {
    const c = cfg(), A = c.analysis;
    const pickEnabled = (type, all) => Object.fromEntries(Object.entries(all).filter(([p]) => c.media[type].some((m) => m.path === p && m.enabled)));
    A.roles = AN.assignRoles(pickEnabled('images', A.images), pickEnabled('videos', A.videos));
  }

  async function uploadMedia(files) {
    if (!files || !files.length) return;
    const fd = new FormData(); [...files].forEach((f) => fd.append('files[]', f));
    toast('Uploading ' + files.length + ' file(s)…');
    try {
      const res = await api(S.routes.media, { body: fd });
      cfg().media = res.media;
      renderMedia();
      await runAnalysis();
    } catch (e) { toast(e.message, true); }
  }

  async function runAnalysis() {
    const c = cfg();
    if (!c.media.images.some((m) => m.enabled)) { toast('Add or enable at least one photo first.', true); return; }
    const bar = $('[data-analyze-progress]', panel('media'));
    if (bar) { bar.hidden = false; bar.firstElementChild.style.width = '0%'; }
    busy(true, 'Analysing media…');
    try {
      c.analysis = await AN.run(c.media, url, (p, label) => { if (bar) bar.firstElementChild.style.width = Math.round(p * 100) + '%'; busy(true, 'Analysing ' + label); });
      renderMedia(); dirty();
    } catch (e) { toast('Analysis failed: ' + e.message, true); } finally { busy(false); }
  }

  // ================= 2 · SCRIPT =================
  const SCRIPT = [
    ['Brand & price', [['brand', 'Brand name'], ['url', 'Website (end screen)'], ['currency', 'Currency'], ['price', 'Price'], ['size', 'Size'], ['cta', 'Button text']]],
    ['Hook (first 3 seconds)', [['hook', 'Big lines (one per line)', 'lines'], ['hookAccent', 'Accent words'], ['hookUrdu', 'Urdu line on screen', 'urdu']]],
    ['Product intro', [['intro', 'Small heading'], ['productLine1', 'Name — line 1 (italic)'], ['productLine2', 'Name — line 2 (bold)'], ['introChips', 'Small tags (one per line)', 'lines']]],
    ['Benefits', [['benefitsTitle', 'Title'], ['benefits', 'Benefits (max 4, one per line)', 'lines']]],
    ['Ingredients', [['countNumber', 'Counter number (0 = hide)', 'number'], ['countLabel', 'Counter label'], ['countUrdu', 'Urdu line', 'urdu'], ['tags', 'Ingredient tags (one per line)', 'lines'], ['freeFromLine', 'Bottom line']]],
    ['Price & offer', [['offerTitle', 'Big line'], ['offerSub', 'Second line'], ['offerUrdu', 'Urdu line', 'urdu'], ['offerChips', 'Tags (one per line)', 'lines']]],
    ['Footage reel — opener', [['meetTop', 'Small line'], ['meetBig', 'Big word'], ['meetSub', 'Sub line']]],
    ['Footage reel — what’s inside', [['insideTitle1', 'Title 1'], ['insideTitle2', 'Title 2'], ['inside', 'Ingredient | benefit (one per line, max 6)', 'pairs'], ['insideMore', 'More line'], ['insideMoreList', 'More list']]],
    ['Footage reel — free from', [['nothingTitle1', 'Title 1'], ['nothingTitle2', 'Title 2'], ['nothing', 'Items (one per line, max 4)', 'lines'], ['stamp1', 'Stamp big'], ['stamp2', 'Stamp small']]],
    ['Footage reel — how to use', [['howTitle', 'Title'], ['steps', 'Step | detail (one per line, max 3)', 'pairs'], ['howNote', 'Note']]],
  ];
  function fieldValue(v, type) {
    if (type === 'lines') return (v || []).join('\n');
    if (type === 'pairs') return (v || []).map((r) => (r || []).join(' | ')).join('\n');
    return v ?? '';
  }
  function renderScript() {
    const t = cfg().text;
    panel('script').innerHTML = `
      <div class="flex between wrap" style="margin-bottom:14px">
        <p class="muted" style="margin:0;font-size:13px">Written automatically from the product's details. Edit anything — the preview updates live.</p>
        <button type="button" class="btn btn-light btn-sm" data-regenerate>↻ Rewrite from product data</button>
      </div>
      ${SCRIPT.map(([title, fields]) => `<div class="script-group"><strong>${title}</strong><div class="form-grid">
        ${fields.map(([key, label, type]) => {
          const wide = type === 'lines' || type === 'pairs' || type === 'urdu';
          const v = esc(fieldValue(t[key], type));
          const input = type === 'lines' || type === 'pairs'
            ? `<textarea class="textarea" style="min-height:${type === 'pairs' ? 120 : 84}px" data-field="${key}" data-type="${type}">${v}</textarea>`
            : `<input class="input ${type === 'urdu' ? 'urdu' : ''}" ${type === 'number' ? 'type="number" min="0" max="99"' : ''} data-field="${key}" data-type="${type || 'text'}" value="${v}">`;
          return `<div class="field ${wide ? 'full' : ''}"><label>${label}</label>${input}</div>`;
        }).join('')}</div></div>`).join('')}`;
  }
  panel('script').addEventListener('input', (e) => {
    const el = e.target; if (!el.dataset.field) return;
    const t = cfg().text, type = el.dataset.type, v = el.value;
    t[el.dataset.field] = type === 'lines' ? v.split('\n').map((s) => s.trim()).filter(Boolean)
      : type === 'pairs' ? v.split('\n').map((s) => s.split('|').map((x) => x.trim())).filter((r) => r[0])
        : type === 'number' ? (parseInt(v, 10) || 0) : v;
    dirty();
  });
  panel('script').addEventListener('click', async (e) => {
    if (!e.target.closest('[data-regenerate]')) return;
    if (!confirm('Replace all text and Urdu voice lines with a fresh version from the product data?')) return;
    try { const res = await api(S.routes.regenerate); state.config.text = res.config.text; state.config.voice.lines = res.config.voice.lines; renderScript(); schedulePreview(); toast('Script rewritten.'); } catch (err) { toast(err.message, true); }
  });

  // ================= 3 · VOICE =================
  const FORMAT_KEYS = { motion: ['hook', 'reveal', 'benefits', 'ingredients', 'offer'], landscape: ['hook', 'reveal', 'benefits', 'ingredients', 'offer'], footage: ['meet', 'inside', 'nothing', 'howto', 'offer'] };
  const buffers = new Map();
  let recorder = null;

  function voiceKeys() {
    const wanted = new Set(cfg().formats.flatMap((f) => FORMAT_KEYS[f] || []));
    return Object.keys(S.voiceKeys).filter((k) => wanted.has(k));
  }
  async function voiceBuffer(path) {
    if (!buffers.has(path)) buffers.set(path, AU.decodeVoice(url(path)).catch((e) => { buffers.delete(path); throw e; }));
    return buffers.get(path);
  }

  function renderVoice() {
    const v = cfg().voice;
    const modes = [['none', 'No voice — music only'], ['tts', 'AI Urdu voice (Azure)' + (S.ttsReady ? '' : ' — not connected')], ['record', 'Record my own voice'], ['upload', 'Upload audio files']];
    panel('voice').innerHTML = `
      <div class="form-grid" style="margin-bottom:14px">
        <div class="field"><label>Voice-over</label><select class="select" data-vmode>${modes.map(([k, l]) => `<option value="${k}" ${v.mode === k ? 'selected' : ''} ${k === 'tts' && !S.ttsReady ? 'disabled' : ''}>${l}</option>`).join('')}</select></div>
        ${v.mode === 'tts' ? `<div class="field"><label>Voice</label><select class="select" data-vvoice>${Object.entries(S.voices).map(([k, l]) => `<option value="${k}" ${v.voice === k ? 'selected' : ''}>${l}</option>`).join('')}</select></div>
        <div class="field"><label>Speed: <span data-vrate-label>${v.rate > 0 ? '+' : ''}${v.rate}%</span></label><input type="range" min="-20" max="20" step="5" value="${v.rate}" data-vrate style="width:100%;accent-color:var(--gold)"></div>
        <div class="field" style="align-self:end"><button type="button" class="btn btn-gold btn-sm" data-vall>Generate all lines</button></div>` : ''}
      </div>
      ${!S.ttsReady ? `<div class="alert alert-info" style="font-size:12.5px">Realistic AI Urdu voices need a free <strong>Azure Speech</strong> key — add it in <a href="${S.settingsUrl}" style="text-decoration:underline">Settings → Integrations</a>. Without it, record your own voice (each line is shown like a teleprompter) or upload audio.</div>` : ''}
      ${v.mode === 'none' ? '<p class="muted" style="font-size:13px">Videos will have background music only. Choose a voice option above to add an Urdu voice-over.</p>' : ''}
      ${voiceKeys().map((key) => {
        const clip = v.clips[key];
        const recording = recorder && recorder.key === key;
        const actions = [];
        if (v.mode === 'tts') actions.push(`<button type="button" class="btn btn-primary btn-sm" data-vgen="${key}">${clip ? 'Regenerate' : 'Generate voice'}</button>`);
        if (v.mode === 'record') actions.push(recording ? `<button type="button" class="btn btn-danger btn-sm" data-vstop="${key}"><span class="rec-dot"></span> Stop</button>` : `<button type="button" class="btn btn-primary btn-sm" data-vrec="${key}">● Record</button>`);
        if (v.mode === 'upload' || v.mode === 'record') actions.push(`<button type="button" class="btn btn-light btn-sm" data-vup="${key}">Upload file</button>`);
        if (clip) actions.push(`<audio controls preload="metadata" src="${esc(url(clip.path))}"></audio><span class="muted" style="font-size:12px" data-vdur="${key}"></span><button type="button" class="icon-action danger" title="Delete" data-vdel="${key}">✕</button>`);
        return `<div class="voice-row"><div class="head"><strong>${S.voiceKeys[key]}</strong>${clip ? `<span class="badge b-green">${clip.source === 'tts' ? 'AI voice' : clip.source === 'record' ? 'Recorded' : 'Uploaded'}</span>` : '<span class="badge b-gray">No audio</span>'}</div>
          <textarea class="textarea urdu" data-vline="${key}">${esc(v.lines[key] || '')}</textarea>
          ${v.mode !== 'none' ? `<div class="actions">${actions.join('')}</div>` : ''}</div>`;
      }).join('')}`;
    voiceKeys().forEach(async (key) => {
      const clip = v.clips[key]; if (!clip) return;
      try { const b = await voiceBuffer(clip.path); const el = $(`[data-vdur="${key}"]`, panel('voice')); if (el) el.textContent = b.duration.toFixed(1) + 's'; } catch (e) { /* ignore */ }
    });
  }

  panel('voice').addEventListener('change', (e) => {
    const v = cfg().voice, el = e.target;
    if (el.matches('[data-vmode]')) { v.mode = el.value; renderVoice(); dirty(); }
    if (el.matches('[data-vvoice]')) { v.voice = el.value; dirty(false); }
  });
  panel('voice').addEventListener('input', (e) => {
    const v = cfg().voice, el = e.target;
    if (el.matches('[data-vline]')) { v.lines[el.dataset.vline] = el.value; dirty(false); }
    if (el.matches('[data-vrate]')) { v.rate = parseInt(el.value, 10); $('[data-vrate-label]', panel('voice')).textContent = (v.rate > 0 ? '+' : '') + v.rate + '%'; dirty(false); }
  });
  panel('voice').addEventListener('click', async (e) => {
    const b = e.target.closest('button'); if (!b) return;
    const d = b.dataset;
    try {
      if (d.vgen) await generateVoice(d.vgen, b);
      if (d.vall) { for (const key of voiceKeys()) await generateVoice(key, b); toast('All voice lines generated.'); }
      if (d.vrec) await startRecording(d.vrec);
      if (d.vstop) recorder && recorder.mr.stop();
      if (d.vup) { const input = $('#voiceFile'); input.onchange = () => input.files[0] && uploadVoice(d.vup, input.files[0], 'upload', input.files[0].name).finally(() => { input.value = ''; }); input.click(); }
      if (d.vdel) { await api(S.routes.voiceDelete, { method: 'DELETE', json: { key: d.vdel } }); delete cfg().voice.clips[d.vdel]; renderVoice(); schedulePreview(); }
    } catch (err) { toast(err.message, true); renderVoice(); }
  });

  async function generateVoice(key, btn) {
    const v = cfg().voice, text = (v.lines[key] || '').trim();
    if (!text) return;
    if (btn) { btn.disabled = true; btn.textContent = 'Generating…'; }
    const res = await api(S.routes.tts, { json: { key, text, voice: v.voice, rate: v.rate } });
    v.clips[key] = { path: res.path, source: 'tts' };
    renderVoice(); schedulePreview();
  }

  async function startRecording(key) {
    if (!navigator.mediaDevices || !window.MediaRecorder) throw new Error('Recording is not supported in this browser.');
    const stream = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true } });
    const type = MediaRecorder.isTypeSupported('audio/webm;codecs=opus') ? 'audio/webm;codecs=opus' : '';
    const mr = new MediaRecorder(stream, type ? { mimeType: type, audioBitsPerSecond: 128000 } : undefined);
    const chunks = [];
    mr.ondataavailable = (ev) => ev.data.size && chunks.push(ev.data);
    mr.onstop = async () => {
      stream.getTracks().forEach((t) => t.stop());
      recorder = null;
      const blob = new Blob(chunks, { type: 'audio/webm' });
      if (blob.size < 2000) { toast('Recording was too short.', true); renderVoice(); return; }
      await uploadVoice(key, blob, 'record', 'voice.webm');
    };
    recorder = { mr, key };
    mr.start();
    renderVoice();
  }

  async function uploadVoice(key, blob, source, name) {
    const fd = new FormData(); fd.append('key', key); fd.append('source', source); fd.append('audio', blob, name);
    try {
      const res = await api(S.routes.voice, { body: fd });
      cfg().voice.clips[key] = { path: res.path, source };
      toast('Voice saved.');
    } catch (e) { toast(e.message, true); }
    renderVoice(); schedulePreview();
  }

  // ================= 4 · RENDER =================
  const hasVideos = () => cfg().media.videos.some((m) => m.enabled);
  function renderRenderPanel() {
    const c = cfg(), v = c.voice, keys = voiceKeys();
    const ready = keys.filter((k) => v.clips[k]).length;
    panel('render').innerHTML = `
      <h4>Formats</h4>
      ${Object.entries(S.formats).map(([k, f]) => {
        const disabled = k === 'footage' && !hasVideos();
        return `<label class="format-check ${disabled ? 'disabled' : ''}"><input type="checkbox" data-format="${k}" ${c.formats.includes(k) && !disabled ? 'checked' : ''} ${disabled ? 'disabled' : ''}>
          <span><strong>${f.label}</strong><small>${f.ratio} · ${f.w}×${f.h}${disabled ? ' · needs at least one product video' : k === 'landscape' ? ' · Facebook / Instagram feed, YouTube' : ' · TikTok, Reels, Stories'}</small></span></label>`;
      }).join('')}
      <h4>Sound</h4>
      <div class="flex wrap" style="gap:18px">
        <label class="switch"><input type="checkbox" data-music ${c.music.enabled ? 'checked' : ''}><span class="track"></span> Background music</label>
        <label class="flex" style="font-size:13px">Music volume <input type="range" min="0.2" max="1" step="0.05" value="${c.music.volume}" data-volume style="accent-color:var(--gold)"></label>
      </div>
      <p class="muted" style="font-size:13px">Voice-over: ${v.mode === 'none' ? 'off (music only)' : `${ready}/${keys.length} lines ready` + (ready < keys.length ? ' — scenes without audio stay silent' : '')}.</p>
      <button type="button" class="btn btn-gold" data-render style="margin-top:6px">▶ Render selected videos</button>
      <div style="margin-top:14px" data-render-status hidden>
        <div class="progress"><span></span></div>
        <p class="muted" style="font-size:12.5px;margin:8px 0 0" data-render-text></p>
      </div>
      <p class="muted" style="font-size:12px;margin-top:14px">Rendering happens in this browser (Chrome or Edge on a computer works best) — keep this tab open until it finishes. Each video takes about 20–60 seconds.</p>`;
  }
  panel('render').addEventListener('change', (e) => {
    const c = cfg(), el = e.target;
    if (el.dataset.format) { c.formats = Object.keys(S.formats).filter((k) => $(`[data-format="${k}"]`, panel('render'))?.checked); dirty(false); fillPreviewSelect(); }
    if (el.matches('[data-music]')) { c.music.enabled = el.checked; dirty(false); }
    if (el.matches('[data-volume]')) { c.music.volume = parseFloat(el.value); dirty(false); }
  });
  panel('render').addEventListener('click', (e) => { if (e.target.closest('[data-render]')) renderAll(e.target.closest('[data-render]')); });

  async function renderAll(btn) {
    const c = cfg();
    const formats = c.formats.filter((f) => f !== 'footage' || hasVideos());
    if (!formats.length) { toast('Choose at least one format.', true); return; }
    if (!c.analysis) await runAnalysis();
    const box = $('[data-render-status]', panel('render')), bar = box.querySelector('span'), text = $('[data-render-text]', panel('render'));
    box.hidden = false; btn.disabled = true; playing = false;
    try {
      const spec = await buildSpec();
      for (let i = 0; i < formats.length; i++) {
        const f = formats[i], label = S.formats[f].label, step = (p, msg) => { bar.style.width = Math.round(((i + p) / formats.length) * 100) + '%'; text.textContent = `${label} (${i + 1}/${formats.length}) — ${msg}`; };
        const comp = T.build(f, spec);
        step(0.01, 'Mixing music and voice…');
        const voices = comp.voices.filter((v) => spec.buffers[v.key]).map((v) => ({ buffer: spec.buffers[v.key], at: v.at }));
        const audio = await AU.renderSoundtrack(comp.duration, comp.cues, voices, { music: c.music.enabled, volume: c.music.volume });
        const mp4 = await E.renderMp4($('#renderCanvas'), comp, audio, (p, m) => step(p * 0.9, m));
        step(0.92, 'Uploading…');
        const render = await uploadRender(mp4, f, comp.duration, voices.length > 0, (p) => step(0.92 + p * 0.08, 'Uploading ' + Math.round(p * 100) + '%'));
        state.renders.unshift(render); renderList();
      }
      bar.style.width = '100%'; text.textContent = 'Done! Your videos are ready in "Rendered videos".';
      toast('Videos rendered.');
    } catch (err) {
      console.error(err); text.textContent = 'Error: ' + err.message; toast(err.message, true);
    } finally { btn.disabled = false; }
  }

  async function uploadRender(buffer, format, duration, hasVoice, onProgress) {
    const id = Array.from(crypto.getRandomValues(new Uint8Array(16)), (b) => b.toString(16).padStart(2, '0')).join('');
    const size = 4 * 1024 * 1024, total = Math.ceil(buffer.byteLength / size);
    let last;
    for (let i = 0; i < total; i++) {
      const fd = new FormData();
      fd.append('upload_id', id); fd.append('index', i); fd.append('total', total); fd.append('format', format);
      fd.append('duration', duration.toFixed(2)); fd.append('has_voice', hasVoice ? 1 : 0);
      fd.append('chunk', new Blob([buffer.slice(i * size, (i + 1) * size)], { type: 'application/octet-stream' }), 'chunk.bin');
      last = await api(S.routes.chunk, { body: fd });
      onProgress((i + 1) / total);
    }
    if (!last.done) throw new Error('Upload did not complete.');
    return { ...last.render, voice: hasVoice, date: 'just now' };
  }

  function renderList() {
    const box = $('#renderList');
    box.innerHTML = state.renders.length ? state.renders.map((r) => `
      <div class="render-item">
        <video src="${esc(r.url)}#t=1" preload="metadata" muted data-open="${esc(r.url)}"></video>
        <div><div class="t">${esc(r.label)}</div>
          <div class="s">${Number(r.duration).toFixed(1)}s · ${(r.size / 1048576).toFixed(1)} MB${r.voice ? ' · Urdu voice' : ''} · ${esc(r.date || '')}</div>
          <div class="a"><a class="btn btn-primary btn-sm" href="${esc(r.url)}" download="${esc(r.download)}">Download</a>
            <button type="button" class="btn btn-light btn-sm" data-open="${esc(r.url)}">Play</button>
            <button type="button" class="icon-action danger" data-delete="${esc(r.delete)}" title="Delete">✕</button></div></div>
      </div>`).join('') : '<p class="muted" style="margin:0;font-size:13px">No videos yet — go to step 4 and render.</p>';
  }
  $('#renderList').addEventListener('click', async (e) => {
    const open = e.target.closest('[data-open]'), del = e.target.closest('[data-delete]');
    if (open) window.open(open.dataset.open, '_blank');
    if (del && confirm('Delete this video?')) {
      try { await api(del.dataset.delete, { method: 'DELETE' }); state.renders = state.renders.filter((r) => r.delete !== del.dataset.delete); renderList(); } catch (err) { toast(err.message, true); }
    }
  });

  // ================= Spec + preview =================
  async function buildSpec() {
    const c = cfg(), A = c.analysis || { images: {}, videos: {}, roles: {} };
    E.setPalette(currentPalette());
    const brand = { name: c.text.brand || '', mark: S.brandMark ? await E.loadImage(S.brandMark).catch(() => null) : null };
    const media = {};
    for (const role of ['hero', 'wide', 'square', 'thumb']) {
      const p = A.roles[role] || (c.media.images.find((m) => m.enabled) || {}).path;
      if (!p) continue;
      const info = A.images[p] || {};
      media[role] = { el: await E.loadImage(url(p)), fx: info.fx ?? 0.5, fy: info.fy ?? 0.5 };
    }
    for (const role of ['clipOpen', 'clipHowto', 'clipWide']) {
      const p = A.roles[role];
      if (!p || !c.media.videos.some((m) => m.path === p && m.enabled)) continue;
      const info = A.videos[p] || {};
      media[role] = { el: await E.loadVideo(url(p)), fx: info.fx ?? 0.5, fy: info.fy ?? 0.45, start: Math.min(0.4, (info.duration || 1) * 0.05) };
    }
    const voiceDur = {}, bufs = {};
    if (c.voice.mode !== 'none') {
      for (const [key, clip] of Object.entries(c.voice.clips || {})) {
        try { const b = await voiceBuffer(clip.path); bufs[key] = b; voiceDur[key] = b.duration; } catch (e) { console.warn(e); }
      }
    }
    return { brand, t: c.text, media, voiceDur, buffers: bufs };
  }

  const pCanvas = $('#previewCanvas'), pCtx = pCanvas.getContext('2d');
  let previewComp = null, previewTimer, playing = false;

  function fillPreviewSelect() {
    const sel = $('#previewFormat'), cur = sel.value;
    const list = Object.keys(S.formats).filter((k) => k !== 'footage' || hasVideos());
    sel.innerHTML = list.map((k) => `<option value="${k}">${S.formats[k].label}</option>`).join('');
    sel.value = list.includes(cur) ? cur : list[0];
  }
  function schedulePreview() { clearTimeout(previewTimer); previewTimer = setTimeout(rebuildPreview, 350); }
  async function rebuildPreview() {
    if (!cfg().media.images.some((m) => m.enabled)) { busy(true, 'Add at least one photo to preview'); return; }
    try {
      const spec = await buildSpec();
      previewComp = T.build($('#previewFormat').value, spec);
      pCanvas.width = previewComp.w; pCanvas.height = previewComp.h;
      const slider = $('#previewTime'); slider.max = previewComp.duration.toFixed(2);
      await drawPreview(Math.min(parseFloat(slider.value) || 0, previewComp.duration - 0.05));
      busy(false);
    } catch (e) { console.error(e); busy(true, 'Preview error: ' + e.message); }
  }
  async function drawPreview(t) {
    if (!previewComp) return;
    await E.drawFrame(pCtx, previewComp, t, true);
    $('#previewClock').textContent = t.toFixed(1) + 's / ' + previewComp.duration.toFixed(1) + 's';
  }
  $('#previewFormat').addEventListener('change', rebuildPreview);
  $('#previewTime').addEventListener('input', (e) => { playing = false; drawPreview(parseFloat(e.target.value)); });
  $('#previewPlay').addEventListener('click', async () => {
    if (playing) { playing = false; return; }
    if (!previewComp) return;
    playing = true;
    const slider = $('#previewTime');
    let t = parseFloat(slider.value) || 0; if (t >= previewComp.duration - 0.1) t = 0;
    while (playing && t < previewComp.duration) {
      const start = performance.now();
      await drawPreview(t); slider.value = t;
      await new Promise((r) => requestAnimationFrame(r));
      t += Math.max(1 / 30, (performance.now() - start) / 1000);
    }
    playing = false;
  });

  // ---------- helpers ----------
  function busy(on, label) { const el = $('#previewBusy'); el.classList.toggle('show', !!on); if (label) el.textContent = label; }
  function toast(msg, error) {
    let el = $('#studioToast');
    if (!el) { el = document.createElement('div'); el.id = 'studioToast'; el.style.cssText = 'position:fixed;left:50%;bottom:24px;transform:translateX(-50%);z-index:300;padding:12px 20px;border-radius:30px;color:#fff;font-size:13.5px;box-shadow:0 10px 30px rgba(0,0,0,.3);transition:opacity .3s;max-width:90vw'; document.body.appendChild(el); }
    el.style.background = error ? '#b4423f' : '#22103f'; el.textContent = msg; el.style.opacity = 1;
    clearTimeout(toast.t); toast.t = setTimeout(() => { el.style.opacity = 0; }, error ? 6000 : 3000);
  }

  // ---------- Boot ----------
  (async function boot() {
    renderMedia(); renderScript(); renderList(); fillPreviewSelect();
    busy(true, 'Loading fonts…');
    await Promise.all(['900 100px Montserrat', '800 100px Montserrat', '700 100px Montserrat', 'italic 600 100px "Cormorant Garamond"', '500 40px Jost', '600 40px Jost', '600 40px "Noto Nastaliq Urdu"']
      .map((f) => document.fonts.load(f, 'Aa مضبوط').catch(() => null)));
    const c = cfg();
    const known = c.analysis ? new Set([...Object.keys(c.analysis.images || {}), ...Object.keys(c.analysis.videos || {})]) : new Set();
    const stale = [...c.media.images, ...c.media.videos].some((m) => m.enabled && !known.has(m.path));
    if (stale && c.media.images.length) await runAnalysis();
    await rebuildPreview();
    window.__studioReady = true;
  })();
})();
