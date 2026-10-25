/* Ad Studio — royalty-free music bed + sound effects + voice-over mix (offline WebAudio).
   renderSoundtrack(duration, cues, voices, opts) -> AudioBuffer (48 kHz stereo)
   voices = [{ buffer: AudioBuffer, at: seconds }]  — music ducks under each voice line. */
(function () {
  'use strict';

  const SR = 48000;
  const midi = (n) => 440 * Math.pow(2, (n - 69) / 12);

  function noiseBuffer(ctx, seconds) {
    const buf = ctx.createBuffer(1, Math.ceil(SR * seconds), SR);
    const d = buf.getChannelData(0); let seed = 1234567;
    for (let i = 0; i < d.length; i++) { seed = (seed * 16807) % 2147483647; d[i] = (seed / 2147483647) * 2 - 1; }
    return buf;
  }
  function impulse(ctx, seconds, decay) {
    const len = Math.ceil(SR * seconds), buf = ctx.createBuffer(2, len, SR);
    for (let ch = 0; ch < 2; ch++) {
      const d = buf.getChannelData(ch); let seed = 99 + ch * 77;
      for (let i = 0; i < len; i++) { seed = (seed * 16807) % 2147483647; d[i] = ((seed / 2147483647) * 2 - 1) * Math.pow(1 - i / len, decay); }
    }
    return buf;
  }

  async function decodeVoice(url) {
    const res = await fetch(url, { credentials: 'same-origin' });
    if (!res.ok) throw new Error('Voice file missing: ' + url);
    const data = await res.arrayBuffer();
    const ac = new (window.AudioContext || window.webkitAudioContext)();
    try { return await ac.decodeAudioData(data); } finally { ac.close(); }
  }

  async function renderSoundtrack(duration, cues, voices = [], opts = {}) {
    cues = Object.assign({ whoosh: [], pop: [], hit: [], drop: 0 }, cues || {});
    const musicOn = opts.music !== false;
    const volume = opts.volume ?? 0.8;
    const ctx = new OfflineAudioContext(2, Math.ceil(SR * duration), SR);

    const limiter = ctx.createDynamicsCompressor();
    limiter.threshold.value = -10; limiter.ratio.value = 6; limiter.attack.value = 0.005; limiter.release.value = 0.2;
    limiter.connect(ctx.destination);

    // Music bus: fades in/out, and ducks under voice.
    const music = ctx.createGain();
    music.gain.setValueAtTime(0, 0);
    music.gain.linearRampToValueAtTime(volume, 0.5);
    const duckTo = volume * 0.32;
    const fadeAt = Math.max(0.6, duration - 1.4);
    let last = 0.5;
    [...voices].sort((a, b) => a.at - b.at).forEach(({ buffer, at }) => {
      const end = Math.min(at + buffer.duration, duration);
      if (at - 0.25 < last || at >= fadeAt) return; // overlapping or inside the final fade
      music.gain.setValueAtTime(volume, at - 0.25);
      music.gain.linearRampToValueAtTime(duckTo, at);
      music.gain.setValueAtTime(duckTo, end);
      if (end + 0.35 < fadeAt) {
        music.gain.linearRampToValueAtTime(volume, end + 0.35);
        last = end + 0.35;
      } else {
        last = end;
      }
    });
    // Final fade starts from wherever the music level is (ducked or full).
    if (last < fadeAt) music.gain.setValueAtTime(volume, fadeAt);
    music.gain.linearRampToValueAtTime(0, duration);
    music.connect(limiter);

    // Voice bus with a touch of presence EQ.
    const voiceBus = ctx.createGain(); voiceBus.gain.value = 1.15;
    const presence = ctx.createBiquadFilter(); presence.type = 'peaking'; presence.frequency.value = 3000; presence.gain.value = 3; presence.Q.value = 0.8;
    const lowcut = ctx.createBiquadFilter(); lowcut.type = 'highpass'; lowcut.frequency.value = 80;
    voiceBus.connect(lowcut).connect(presence).connect(limiter);
    voices.forEach(({ buffer, at }) => {
      const s = ctx.createBufferSource(); s.buffer = buffer; s.connect(voiceBus); s.start(Math.max(0, at));
    });

    if (musicOn) buildMusic(ctx, music, duration, cues);
    return ctx.startRendering();
  }

  function buildMusic(ctx, master, duration, cues) {
    const reverb = ctx.createConvolver(); reverb.buffer = impulse(ctx, 2.4, 2.6);
    const wet = ctx.createGain(); wet.gain.value = 0.28; reverb.connect(wet).connect(master);
    const noise = noiseBuffer(ctx, 2);
    const bpm = 100, beat = 60 / bpm, bar = beat * 4;
    const prog = [[57, 60, 64], [53, 57, 60], [48, 52, 55], [55, 59, 62]];
    const bass = [45, 41, 48, 43];

    function pad(notes, start, len) {
      const lp = ctx.createBiquadFilter(); lp.type = 'lowpass'; lp.frequency.value = 1100; lp.Q.value = 0.4;
      const g = ctx.createGain();
      g.gain.setValueAtTime(0, start); g.gain.linearRampToValueAtTime(0.05, start + 0.35);
      g.gain.setValueAtTime(0.05, start + len - 0.2); g.gain.linearRampToValueAtTime(0, start + len + 0.4);
      lp.connect(g); g.connect(master); g.connect(reverb);
      notes.forEach((n) => [-7, 7].forEach((cents) => {
        const o = ctx.createOscillator(); o.type = 'sawtooth'; o.frequency.value = midi(n); o.detune.value = cents;
        o.connect(lp); o.start(start); o.stop(start + len + 0.5);
      }));
    }
    function tone(type, freq, start, len, gain, dest, glideTo) {
      const o = ctx.createOscillator(); o.type = type; o.frequency.setValueAtTime(freq, start);
      if (glideTo) o.frequency.exponentialRampToValueAtTime(glideTo, start + len * 0.6);
      const g = ctx.createGain();
      g.gain.setValueAtTime(0, start); g.gain.linearRampToValueAtTime(gain, start + 0.008); g.gain.exponentialRampToValueAtTime(0.0008, start + len);
      o.connect(g); (dest || [master]).forEach((d) => g.connect(d)); o.start(start); o.stop(start + len + 0.05);
    }
    function noiseHit(start, len, gain, type, freq, q, dest) {
      const s = ctx.createBufferSource(); s.buffer = noise;
      const f = ctx.createBiquadFilter(); f.type = type; f.frequency.value = freq; f.Q.value = q || 0.7;
      const g = ctx.createGain(); g.gain.setValueAtTime(gain, start); g.gain.exponentialRampToValueAtTime(0.0008, start + len);
      s.connect(f).connect(g); (dest || [master]).forEach((d) => g.connect(d)); s.start(start, (start * 7.3) % 1.5); s.stop(start + len + 0.05);
    }

    for (let b = 0, t = 0; t < duration; b++, t = b * bar) {
      const chord = prog[b % 4];
      pad(chord, t, bar);
      [0, 2].forEach((q) => tone('sine', midi(bass[b % 4]), t + q * beat, beat * 1.6, 0.22));
      if (b >= 1) {
        const arp = [chord[0] + 12, chord[1] + 12, chord[2] + 12, chord[1] + 24];
        for (let i = 0; i < 8; i++) tone('triangle', midi(arp[i % 4]), t + i * beat / 2, 0.28, 0.045, [master, reverb]);
      }
      for (let q = 0; q < 4; q++) {
        const bt = t + q * beat;
        if (bt < cues.drop || bt > duration - 0.8) continue;
        tone('sine', 140, bt, 0.32, 0.55, [master], 42);
        if (q % 2 === 1) noiseHit(bt, 0.16, 0.12, 'bandpass', 1900, 0.8, [master, reverb]);
        noiseHit(bt + beat / 2, 0.05, 0.05, 'highpass', 8000);
      }
    }
    cues.whoosh.forEach((at) => {
      const start = Math.max(0, at - 0.38);
      const s = ctx.createBufferSource(); s.buffer = noise;
      const f = ctx.createBiquadFilter(); f.type = 'bandpass'; f.Q.value = 1.2;
      f.frequency.setValueAtTime(350, start); f.frequency.exponentialRampToValueAtTime(5200, start + 0.55);
      const g = ctx.createGain();
      g.gain.setValueAtTime(0.0001, start); g.gain.exponentialRampToValueAtTime(0.32, start + 0.36); g.gain.exponentialRampToValueAtTime(0.0008, start + 0.7);
      s.connect(f).connect(g); g.connect(master); g.connect(reverb); s.start(start); s.stop(start + 0.75);
    });
    cues.pop.forEach((at) => tone('sine', 700, at, 0.16, 0.16, [master, reverb], 1400));
    cues.hit.forEach((at) => {
      tone('sine', 90, at, 1.2, 0.5, [master], 38);
      [76, 79, 83, 88].forEach((n, i) => tone('triangle', midi(n), at + i * 0.04, 1.4, 0.05, [master, reverb]));
    });
  }

  window.AdAudio = { renderSoundtrack, decodeVoice };
})();
