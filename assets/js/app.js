/* =========================================================================
   قرعه‌کشی — shared utilities (theme, digits, sound, api, toasts, modal, qr)
   ========================================================================= */
(function () {
  'use strict';

  const LD = window.LD || {};
  const $ = (sel, root) => (root || document).querySelector(sel);
  const $$ = (sel, root) => Array.from((root || document).querySelectorAll(sel));

  /* ---------------- storage helpers ---------------- */
  const store = {
    get(k, d) { try { const v = localStorage.getItem('ld.' + k); return v === null ? d : JSON.parse(v); } catch (e) { return d; } },
    set(k, v) { try { localStorage.setItem('ld.' + k, JSON.stringify(v)); } catch (e) { /* ignore */ } },
    del(k) { try { localStorage.removeItem('ld.' + k); } catch (e) { /* ignore */ } },
  };

  /* ---------------- i18n ---------------- */
  const LDC = window.LD || {};
  const LANG = LDC.lang || 'fa';
  const I18N = LDC.i18n || {};
  /** Translate a UI string exported from app/lang/<lang>.php; {0},{1}… are replaced by args. */
  function T(key) {
    let s = Object.prototype.hasOwnProperty.call(I18N, key) ? I18N[key] : key;
    for (let i = 1; i < arguments.length; i++) s = s.split('{' + (i - 1) + '}').join(String(arguments[i]));
    return s;
  }
  const SEP = T('sep');

  /* ---------------- digits (Persian / Latin) ---------------- */
  const FA = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
  function digitsMode() { return document.documentElement.getAttribute('data-digits') || (LANG === 'fa' ? 'fa' : 'en'); }
  function fmt(n) {
    const s = String(n);
    if (digitsMode() !== 'fa') return s;
    return s.replace(/[0-9]/g, (d) => FA[+d]);
  }
  function fmtNum(n) {
    if (typeof n !== 'number') n = Number(n) || 0;
    return fmt(n.toLocaleString('en-US'));
  }
  function pad2(n) { return String(n).padStart(2, '0'); }
  function fmtClock(ms) {
    if (ms < 0) ms = 0;
    const s = Math.floor(ms / 1000);
    const m = Math.floor(s / 60);
    const h = Math.floor(m / 60);
    const out = h > 0 ? `${h}:${pad2(m % 60)}:${pad2(s % 60)}` : `${pad2(m)}:${pad2(s % 60)}`;
    return fmt(out);
  }
  function fmtTime(ts) {
    const d = new Date(ts);
    return fmt(`${pad2(d.getHours())}:${pad2(d.getMinutes())}`);
  }
  /** Convert Persian/Arabic digits in user input to Latin */
  function toLatinDigits(s) {
    return String(s).replace(/[۰-۹]/g, (d) => String(FA.indexOf(d))).replace(/[٠-٩]/g, (d) => String('٠١٢٣٤٥٦٧٨٩'.indexOf(d)));
  }
  /** Re-render every element marked with data-fmt after a digit-mode switch */
  function refreshDigits() {
    $$('[data-raw]').forEach((el) => { el.textContent = fmt(el.getAttribute('data-raw')); });
    document.dispatchEvent(new CustomEvent('ld:digits'));
  }

  /* ---------------- theme ---------------- */
  const themeBtn = $('#themeToggle');
  function applyTheme(t, save) {
    document.documentElement.setAttribute('data-theme', t);
    if (save) store.set('theme', t);
    const meta = $('meta[name="theme-color"]');
    if (meta) meta.setAttribute('content', t === 'light' ? '#f3f5fb' : '#0b0f1a');
    if (themeBtn) themeBtn.querySelector('i').className = t === 'light' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
    document.dispatchEvent(new CustomEvent('ld:theme'));
  }
  applyTheme(document.documentElement.getAttribute('data-theme') || 'dark', false);
  if (themeBtn) {
    themeBtn.addEventListener('click', () => {
      const cur = document.documentElement.getAttribute('data-theme') || 'dark';
      applyTheme(cur === 'dark' ? 'light' : 'dark', true);
    });
  }
  const digitsBtn = $('#digitsToggle');
  if (digitsBtn) {
    digitsBtn.addEventListener('click', () => {
      const cur = digitsMode();
      const next = cur === 'fa' ? 'en' : 'fa';
      document.documentElement.setAttribute('data-digits', next);
      store.set('digits.' + LANG, next); // remembered per language
      refreshDigits();
    });
  }

  /* ---------------- sound (Web Audio, no files) ---------------- */
  const sound = {
    enabled: store.get('sound', true),
    ctx: null,
    ensure() {
      if (!this.enabled) return null;
      try {
        if (!this.ctx) this.ctx = new (window.AudioContext || window.webkitAudioContext)();
        if (this.ctx.state === 'suspended') this.ctx.resume();
        return this.ctx;
      } catch (e) { return null; }
    },
    tone(freq, dur, type, vol, when) {
      const ctx = this.ensure(); if (!ctx) return;
      const t0 = ctx.currentTime + (when || 0);
      const o = ctx.createOscillator(); const g = ctx.createGain();
      o.type = type || 'sine'; o.frequency.setValueAtTime(freq, t0);
      g.gain.setValueAtTime(0.0001, t0);
      g.gain.exponentialRampToValueAtTime(vol || 0.2, t0 + 0.01);
      g.gain.exponentialRampToValueAtTime(0.0001, t0 + dur);
      o.connect(g).connect(ctx.destination); o.start(t0); o.stop(t0 + dur + 0.02);
    },
    tick() {
      const ctx = this.ensure(); if (!ctx) return;
      const t0 = ctx.currentTime;
      const o = ctx.createOscillator(); const g = ctx.createGain();
      o.type = 'square'; o.frequency.setValueAtTime(1800, t0); o.frequency.exponentialRampToValueAtTime(600, t0 + 0.03);
      g.gain.setValueAtTime(0.12, t0); g.gain.exponentialRampToValueAtTime(0.0001, t0 + 0.05);
      o.connect(g).connect(ctx.destination); o.start(t0); o.stop(t0 + 0.06);
    },
    blip(i) { this.tone(500 + (i % 6) * 90, 0.06, 'triangle', 0.08); },
    win() {
      const notes = [523.25, 659.25, 783.99, 1046.5, 1318.5];
      notes.forEach((f, i) => this.tone(f, 0.35, 'triangle', 0.18, i * 0.09));
      this.tone(1568, 0.7, 'sine', 0.12, notes.length * 0.09);
    },
    coin() {
      const ctx = this.ensure(); if (!ctx) return;
      for (let i = 0; i < 3; i++) this.tone(2400 + i * 300, 0.25, 'sine', 0.06, i * 0.05);
    },
    land() { this.tone(180, 0.15, 'sine', 0.25); this.tone(90, 0.25, 'triangle', 0.2, 0.02); },
    toggle() {
      this.enabled = !this.enabled; store.set('sound', this.enabled); this.render();
      if (this.enabled) this.blip(1);
    },
    render() {
      const b = $('#soundToggle'); if (!b) return;
      b.classList.toggle('off', !this.enabled);
      b.querySelector('i').className = this.enabled ? 'fa-solid fa-volume-high' : 'fa-solid fa-volume-xmark';
    },
  };
  sound.render();
  const soundBtn = $('#soundToggle');
  if (soundBtn) soundBtn.addEventListener('click', () => sound.toggle());
  // unlock audio on first interaction (browser autoplay policy)
  ['pointerdown', 'keydown'].forEach((ev) => document.addEventListener(ev, () => sound.ensure(), { once: true, passive: true }));

  /* ---------------- api ---------------- */
  async function api(action, data, method) {
    const m = method || (data ? 'POST' : 'GET');
    let url = LD.api + '?a=' + encodeURIComponent(action);
    const opt = { method: m, headers: { 'Accept': 'application/json' }, cache: 'no-store' };
    if (m === 'GET' && data) {
      url += '&' + new URLSearchParams(data).toString();
    } else if (data) {
      opt.headers['Content-Type'] = 'application/json';
      opt.body = JSON.stringify(data);
    }
    let res;
    try {
      res = await fetch(url, opt);
    } catch (e) {
      const err = new Error(T('network')); err.code = 'network'; throw err;
    }
    let json = null;
    try { json = await res.json(); } catch (e) { /* not json */ }
    if (!res.ok || !json || json.ok === false) {
      const err = new Error((json && json.message) || T('server_error', res.status));
      err.code = (json && json.error) || ('http_' + res.status);
      err.status = res.status;
      throw err;
    }
    return json;
  }

  /* ---------------- toasts ---------------- */
  function toast(msg, type, ms) {
    const root = $('#toasts'); if (!root) return;
    const el = document.createElement('div');
    el.className = 'toast ' + (type || 'info');
    const icon = type === 'ok' ? 'fa-circle-check' : type === 'err' ? 'fa-circle-exclamation' : 'fa-circle-info';
    el.innerHTML = `<i class="fa-solid ${icon}"></i><span></span>`;
    el.querySelector('span').textContent = msg;
    root.appendChild(el);
    setTimeout(() => { el.style.opacity = '0'; el.style.transition = 'opacity .3s'; setTimeout(() => el.remove(), 320); }, ms || 2600);
  }

  /* ---------------- modal ---------------- */
  function openModal(node, opts) {
    const root = $('#modalRoot');
    const back = document.createElement('div');
    back.className = 'modal-backdrop';
    back.appendChild(node);
    root.appendChild(back);
    const close = () => { back.remove(); document.removeEventListener('keydown', onKey); if (opts && opts.onClose) opts.onClose(); };
    const onKey = (e) => { if (e.key === 'Escape') close(); };
    document.addEventListener('keydown', onKey);
    back.addEventListener('click', (e) => { if (e.target === back && !(opts && opts.sticky)) close(); });
    $$('[data-close]', node).forEach((b) => b.addEventListener('click', close));
    return { close, el: back };
  }

  /* ---------------- clipboard ---------------- */
  async function copyText(text) {
    try {
      if (navigator.clipboard && window.isSecureContext) { await navigator.clipboard.writeText(text); return true; }
    } catch (e) { /* fall through */ }
    // fallback works on http:// LAN addresses
    const ta = document.createElement('textarea');
    ta.value = text; ta.setAttribute('readonly', ''); ta.style.position = 'fixed'; ta.style.opacity = '0';
    document.body.appendChild(ta); ta.select();
    let ok = false;
    try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
    ta.remove();
    return ok;
  }

  /* ---------------- QR (offline, via bundled qrcode-generator) ---------------- */
  function renderQr(container, text, size) {
    container.innerHTML = '';
    if (typeof window.qrcode !== 'function') return;
    try {
      const qr = window.qrcode(0, 'M');
      qr.addData(text);
      qr.make();
      const n = qr.getModuleCount();
      const px = size || 200;
      const canvas = document.createElement('canvas');
      canvas.width = px; canvas.height = px;
      const ctx = canvas.getContext('2d');
      ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, px, px);
      const cell = px / (n + 2);
      ctx.fillStyle = '#0b0f1a';
      for (let r = 0; r < n; r++) for (let c = 0; c < n; c++) if (qr.isDark(r, c)) ctx.fillRect(Math.floor((c + 1) * cell), Math.floor((r + 1) * cell), Math.ceil(cell), Math.ceil(cell));
      container.appendChild(canvas);
    } catch (e) { /* too long / unsupported */ }
  }

  /* ---------------- confetti helpers ---------------- */
  function confettiBurst(strength) {
    if (typeof window.confetti !== 'function') return;
    const s = strength || 1;
    const colors = ['#7c5cff', '#4f9dff', '#27d1c4', '#ffcc4d', '#ff5c8a', '#ffffff'];
    const fire = (ratio, opts) => window.confetti(Object.assign({ origin: { y: 0.6 }, colors, zIndex: 1000 }, opts, { particleCount: Math.floor(220 * s * ratio) }));
    fire(0.25, { spread: 26, startVelocity: 55 });
    fire(0.2, { spread: 60 });
    fire(0.35, { spread: 100, decay: 0.91, scalar: 0.9 });
    fire(0.1, { spread: 120, startVelocity: 25, decay: 0.92, scalar: 1.2 });
    fire(0.1, { spread: 120, startVelocity: 45 });
  }
  function confettiSides(durationMs) {
    if (typeof window.confetti !== 'function') return;
    const end = Date.now() + (durationMs || 1800);
    const colors = ['#ffcc4d', '#ff5c8a', '#7c5cff', '#4f9dff'];
    (function frame() {
      window.confetti({ particleCount: 3, angle: 60, spread: 55, origin: { x: 0 }, colors, zIndex: 1000 });
      window.confetti({ particleCount: 3, angle: 120, spread: 55, origin: { x: 1 }, colors, zIndex: 1000 });
      if (Date.now() < end) requestAnimationFrame(frame);
    })();
  }

  /* ---------------- fullscreen ---------------- */
  function setupFullscreen(btn, panel) {
    if (!btn || !panel) return;
    let exitBtn = panel.querySelector('.exit-fs');
    if (!exitBtn) {
      exitBtn = document.createElement('button');
      exitBtn.type = 'button'; exitBtn.className = 'btn btn-ghost exit-fs';
      exitBtn.innerHTML = '<i class="fa-solid fa-compress"></i><span></span>'; exitBtn.querySelector('span').textContent = T('exit');
      panel.prepend(exitBtn);
    }
    const isFs = () => document.fullscreenElement === panel || panel.classList.contains('fs');
    const enter = () => {
      if (panel.requestFullscreen) panel.requestFullscreen().catch(() => panel.classList.add('fs'));
      else panel.classList.add('fs');
      setTimeout(() => window.dispatchEvent(new Event('resize')), 200);
    };
    const exit = () => {
      if (document.fullscreenElement) document.exitFullscreen().catch(() => {});
      panel.classList.remove('fs');
      setTimeout(() => window.dispatchEvent(new Event('resize')), 200);
    };
    btn.addEventListener('click', () => (isFs() ? exit() : enter()));
    exitBtn.addEventListener('click', exit);
    document.addEventListener('fullscreenchange', () => setTimeout(() => window.dispatchEvent(new Event('resize')), 100));
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && panel.classList.contains('fs')) exit(); });
  }

  /* ---------------- join form (home / expired page) ---------------- */
  const joinForm = $('#joinForm');
  if (joinForm) {
    const input = $('#joinCode', joinForm);
    const rules = LD.codeRules || { min: 4, max: 16 };
    const clean = (v) => toLatinDigits(String(v || '')).toUpperCase().replace(/[\s_]+/g, '-').replace(/[^A-Z0-9-]/g, '').replace(/-{2,}/g, '-').slice(0, rules.max);
    input.addEventListener('input', () => { const v = clean(input.value); if (v !== input.value) input.value = v; });
    input.addEventListener('paste', (e) => {
      // allow pasting a full live link: .../live/CODE or ...?r=CODE
      const t = (e.clipboardData || window.clipboardData); const txt = t ? t.getData('text') : '';
      const m = /(?:\/live\/|\/l\/|[?&]r=)([A-Za-z0-9-]{1,64})/.exec(txt);
      if (m) { e.preventDefault(); input.value = clean(m[1]); }
    });
    joinForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const code = clean(input.value).replace(/^-+|-+$/g, '');
      if (code.length < rules.min || code.length > rules.max) {
        input.classList.remove('shake'); void input.offsetWidth; input.classList.add('shake');
        toast(T('code_len', fmt(rules.min), fmt(rules.max)), 'err'); return;
      }
      window.location.href = LD.liveUrl.replace('{code}', encodeURIComponent(code));
    });
  }

  /* ---------------- list parsing (shared by pick / wheel) ---------------- */
  /**
   * Normalise a weight: decimals allowed (0.25, 0.5, 2.5 …), clamped to 0.01–100,
   * rounded to two decimals. Mirrors Draw::weight() on the server.
   */
  function parseWeight(raw) {
    const v = parseFloat(toLatinDigits(String(raw == null ? '' : raw)).replace(/[٫,]/g, '.'));
    if (!Number.isFinite(v)) return 1;
    return Math.round(Math.max(0.01, Math.min(100, v)) * 100) / 100;
  }
  /** Weight for display: "×3", "×0.5" — nothing for the default weight 1. */
  function fmtWeight(w) {
    w = parseWeight(w);
    return w === 1 ? '' : '×' + fmt(String(w));
  }
  /** "علی*3" / "Sara*0.5" → {n, w}; splits on newline, comma, Persian comma, semicolon */
  function parseList(text) {
    const out = [];
    const parts = String(text || '').split(/[\n,،;؛]+/);
    for (let p of parts) {
      p = p.replace(/\s+/g, ' ').trim();
      if (!p) continue;
      let w = 1;
      const m = p.match(/^(.*?)\s*[*×x]\s*([0-9۰-۹]{0,3}(?:[.٫][0-9۰-۹]{1,2})?)$/u);
      if (m && m[1].trim() && m[2] !== '') { p = m[1].trim(); w = parseWeight(m[2]); }
      if (p.length > 80) p = p.slice(0, 80);
      out.push({ n: p, w });
      if (out.length >= (LD.maxItems || 500)) break;
    }
    return out;
  }
  function listToText(items) {
    return items.map((it) => (parseWeight(it.w) !== 1 ? `${it.n}*${parseWeight(it.w)}` : it.n)).join('\n');
  }

  /* ---------------- history rendering ---------------- */
  function renderHistory(listEl, history, tool) {
    if (!listEl) return;
    listEl.innerHTML = '';
    if (!history || !history.length) {
      const li = document.createElement('li'); li.className = 'empty'; li.textContent = T('history_empty'); listEl.appendChild(li); return;
    }
    history.forEach((h, i) => {
      const li = document.createElement('li');
      const idx = document.createElement('span'); idx.className = 'idx num'; idx.textContent = fmt(history.length - i);
      const txt = document.createElement('span'); txt.className = 'txt';
      txt.textContent = historyText(h, tool);
      txt.title = txt.textContent;
      const time = document.createElement('span'); time.className = 'time num'; time.textContent = fmtTime(h.at);
      li.append(idx, txt, time);
      listEl.appendChild(li);
    });
  }
  function historyText(h, tool) {
    if (tool === 'coin' && Array.isArray(h.items)) {
      const labels = h.labels || [T('heads'), T('tails')];
      return h.items.map((s) => labels[s]).join(SEP);
    }
    if (tool === 'number' && Array.isArray(h.items)) return h.items.map((n) => fmtNum(n)).join(SEP);
    if (tool === 'teams' && Array.isArray(h.items)) return h.items.join(' | ');
    if (Array.isArray(h.items)) return h.items.join(SEP);
    return h.text || '';
  }
  function historyToCsv(history, tool) {
    const rows = [['#', T('csv_time'), T('csv_result')]];
    history.slice().reverse().forEach((h, i) => {
      const d = new Date(h.at);
      rows.push([i + 1, d.toLocaleString(LDC.locale || (LANG === 'fa' ? 'fa-IR' : 'en-US')), historyText(h, tool)]);
    });
    // Cells starting with = + - @ (or tab/CR) are neutralised so a participant
    // named "=HYPERLINK(...)" cannot become a formula when opened in Excel.
    const cell = (c) => {
      let s = String(c);
      if (/^[=+\-@\t\r]/.test(s)) s = "'" + s;
      return '"' + s.replace(/"/g, '""') + '"';
    };
    return '\uFEFF' + rows.map((r) => r.map(cell).join(',')).join('\r\n');
  }
  function download(name, content, type) {
    const blob = new Blob([content], { type: type || 'text/plain;charset=utf-8' });
    const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = name; a.click();
    setTimeout(() => URL.revokeObjectURL(a.href), 1000);
  }

  /* ---------------- secure random (used in local mode only as fallback) ---------------- */
  function randInt(min, max) {
    const range = max - min + 1;
    if (range <= 0) return min;
    const arr = new Uint32Array(1);
    const limit = Math.floor(0x100000000 / range) * range;
    let x;
    do { crypto.getRandomValues(arr); x = arr[0]; } while (x >= limit);
    return min + (x % range);
  }
  function uid(len) {
    const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    let s = ''; for (let i = 0; i < (len || 12); i++) s += chars[randInt(0, chars.length - 1)];
    return s;
  }

  window.LDU = { $, $$, T, SEP, LANG, store, fmt, fmtNum, fmtClock, fmtTime, toLatinDigits, refreshDigits, sound, api, toast, openModal, copyText, renderQr, confettiBurst, confettiSides, setupFullscreen, parseList, parseWeight, fmtWeight, listToText, renderHistory, historyText, historyToCsv, download, randInt, uid };
})();
