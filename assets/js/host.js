/* =========================================================================
   قرعه‌کشی — host page controller (settings → state → draw → live sharing)
   ========================================================================= */
(function () {
  'use strict';
  const U = window.LDU; const T = window.LDTools; const LD = window.LD;
  const { $, $$, fmt, fmtClock, toast, api, store, sound } = U; const tr = U.T; const SEP = U.SEP;

  const tool = LD.tool;
  const impl = T[tool];
  const stage = $('#stage');
  if (!impl || !stage) return;
  impl.init(stage);

  const goBtn = $('#goBtn');
  const resetBtn = $('#resetBtn');
  const historyList = $('#historyList');
  const settings = $('#settingsPanel');

  let busy = false;
  let history = store.get('history.' + tool, []);
  let live = store.get('room.' + tool, null); // {code, token, expires_at, created_at}
  let pushTimer = null;
  let pollTimer = null;
  let tickTimer = null;
  let serverOffset = 0; // server_time - Date.now()

  /* ------------------------------------------------------------------ */
  /* settings <-> state                                                  */
  /* ------------------------------------------------------------------ */
  const el = {
    coinHeads: $('#coinHeads'), coinTails: $('#coinTails'), coinCount: $('#coinCount'),
    numMin: $('#numMin'), numMax: $('#numMax'), numCount: $('#numCount'), numUnique: $('#numUnique'), numSort: $('#numSort'),
    list: $('#listInput'), listCount: $('#listCount'), pickCount: $('#pickCount'), remove: $('#removeWinner'),
    wheelDuration: $('#wheelDuration'), wheelDurationOut: $('#wheelDurationOut'),
    teamsBy: $('#teamsBy'), teamsN: $('#teamsN'), teamsNames: $('#teamsNames'), teamsNLabel: $('#teamsNLabel'),
  };

  function intVal(input, def) { const v = parseInt(U.toLatinDigits(input.value), 10); return Number.isFinite(v) ? v : def; }

  function readState() {
    switch (tool) {
      case 'coin':
        return { heads: el.coinHeads.value.trim() || tr('heads'), tails: el.coinTails.value.trim() || tr('tails'), count: clampInt(intVal(el.coinCount, 1), 1, 10) };
      case 'number': {
        let min = intVal(el.numMin, 1), max = intVal(el.numMax, 100);
        return { min, max, count: clampInt(intVal(el.numCount, 1), 1, 100), unique: el.numUnique.checked, sort: el.numSort.checked };
      }
      case 'pick':
        return { items: U.parseList(el.list.value), count: clampInt(intVal(el.pickCount, 1), 1, 100), remove: el.remove.checked };
      case 'wheel':
        return { items: U.parseList(el.list.value), remove: el.remove.checked, duration: clampInt(intVal(el.wheelDuration, 7), 3, 15) };
      case 'teams': {
        const by = (el.teamsBy.querySelector('.active') || {}).dataset ? el.teamsBy.querySelector('.active').dataset.by : 'groups';
        return { items: U.parseList(el.list.value).map((it) => ({ n: it.n, w: 1 })), by, n: clampInt(intVal(el.teamsN, 2), 1, 100), names: el.teamsNames.value.split(/[\n,،;؛]+/).map((x) => x.trim()).filter(Boolean).slice(0, 50) };
      }
    }
    return {};
  }
  function writeState(s) {
    if (!s) return;
    switch (tool) {
      case 'coin': el.coinHeads.value = s.heads || tr('heads'); el.coinTails.value = s.tails || tr('tails'); el.coinCount.value = s.count || 1; break;
      case 'number': el.numMin.value = s.min; el.numMax.value = s.max; el.numCount.value = s.count || 1; el.numUnique.checked = !!s.unique; el.numSort.checked = !!s.sort; break;
      case 'pick': el.list.value = U.listToText(s.items || []); el.pickCount.value = s.count || 1; el.remove.checked = !!s.remove; break;
      case 'wheel': el.list.value = U.listToText(s.items || []); el.remove.checked = !!s.remove; el.wheelDuration.value = s.duration || 7; el.wheelDurationOut.textContent = fmt(s.duration || 7); break;
      case 'teams':
        el.list.value = U.listToText(s.items || []); el.teamsN.value = s.n || 2; el.teamsNames.value = (s.names || []).join(SEP);
        $$('button', el.teamsBy).forEach((b) => b.classList.toggle('active', b.dataset.by === (s.by || 'groups')));
        break;
    }
  }
  function clampInt(v, a, b) { return Math.max(a, Math.min(b, v)); }

  /**
   * Merge the server's post-draw state into the form without clobbering
   * settings the user changed during the animation.
   * - lists: the names removed by the draw are removed from the *current* list
   *   (so names typed meanwhile are kept, and a toggle flipped mid-draw sticks);
   * - everything else is left exactly as it is on screen.
   */
  function applyNextState(before, next) {
    if (!next || !el.list) return;
    const beforeItems = before.items || [];
    const nextItems = next.items || [];
    if (nextItems.length === beforeItems.length) return; // nothing was removed
    // multiset of names that survived the draw
    const keep = new Map();
    nextItems.forEach((it) => keep.set(it.n, (keep.get(it.n) || 0) + 1));
    const removed = new Map();
    beforeItems.forEach((it) => {
      const k = keep.get(it.n) || 0;
      if (k > 0) keep.set(it.n, k - 1); else removed.set(it.n, (removed.get(it.n) || 0) + 1);
    });
    const current = U.parseList(el.list.value);
    const out = current.filter((it) => {
      const r = removed.get(it.n) || 0;
      if (r > 0) { removed.set(it.n, r - 1); return false; }
      return true;
    });
    el.list.value = U.listToText(out);
  }

  function onSettingsChanged(rerender) {
    const s = readState();
    store.set('state.' + tool, s);
    if (el.listCount) el.listCount.textContent = fmt((s.items || []).length);
    if (el.wheelDurationOut) el.wheelDurationOut.textContent = fmt(s.duration);
    if (el.teamsNLabel) el.teamsNLabel.textContent = s.by === 'size' ? tr('teams_n_size') : tr('teams_n_groups');
    if (rerender !== false && !busy) impl.render(s);
    if (live) schedulePush(s);
  }

  // restore saved settings
  const saved = store.get('state.' + tool, null);
  if (saved) writeState(saved);
  onSettingsChanged();

  // wire inputs
  $$('input, textarea, select', settings).forEach((inp) => {
    if (inp.closest('#regBox')) return; // registration controls have their own controller (reg.js)
    const evt = inp.tagName === 'TEXTAREA' || inp.type === 'text' || inp.type === 'number' || inp.type === 'range' ? 'input' : 'change';
    inp.addEventListener(evt, () => { inp.dataset.seen = inp.value; onSettingsChanged(true); });
    // number inputs: 'change' fires on blur; only act on it when 'input' did not already
    // see this value (otherwise the stage would re-render — and shift — right under a click)
    if (inp.type === 'number') inp.addEventListener('change', () => { if (inp.dataset.seen !== inp.value) { inp.dataset.seen = inp.value; onSettingsChanged(true); } });
  });
  $$('.stepper button', settings).forEach((b) => b.addEventListener('click', () => {
    const input = b.parentElement.querySelector('input');
    const step = parseInt(b.dataset.step, 10);
    const min = parseInt(input.min || '-999999', 10), max = parseInt(input.max || '999999', 10);
    input.value = clampInt((parseInt(U.toLatinDigits(input.value), 10) || 0) + step, min, max);
    onSettingsChanged(true);
  }));
  if (el.teamsBy) $$('button', el.teamsBy).forEach((b) => b.addEventListener('click', () => {
    $$('button', el.teamsBy).forEach((x) => x.classList.toggle('active', x === b)); onSettingsChanged(true);
  }));
  $$('.quick-ranges .chip', settings).forEach((c) => c.addEventListener('click', () => {
    const [a, b] = c.dataset.range.split(',');
    el.numMin.value = a; el.numMax.value = b; onSettingsChanged(true);
  }));

  // list tools
  if (el.list) {
    const setList = (items) => { el.list.value = U.listToText(items); onSettingsChanged(true); };
    $('#listDedupe').addEventListener('click', () => {
      const seen = new Set(); const out = [];
      U.parseList(el.list.value).forEach((it) => { const k = it.n.toLowerCase(); if (!seen.has(k)) { seen.add(k); out.push(it); } });
      setList(out); toast(tr('dedupe_done'), 'ok');
    });
    $('#listShuffle').addEventListener('click', () => {
      const a = U.parseList(el.list.value);
      for (let i = a.length - 1; i > 0; i--) { const j = U.randInt(0, i); [a[i], a[j]] = [a[j], a[i]]; }
      setList(a);
    });
    $('#listSort').addEventListener('click', () => setList(U.parseList(el.list.value).sort((x, y) => x.n.localeCompare(y.n, LD.locale || U.LANG))));
    $('#listNumbers').addEventListener('click', () => {
      const n = prompt(tr('numbers_prompt'), '20');
      if (n === null) return;
      const k = clampInt(parseInt(U.toLatinDigits(n), 10) || 0, 1, LD.maxItems || 500);
      setList(Array.from({ length: k }, (_, i) => ({ n: String(i + 1), w: 1 })));
    });
    $('#listSample').addEventListener('click', () => setList(U.parseList(tr('sample_names')).map((it) => ({ n: it.n, w: 1 }))));
    $('#listClear').addEventListener('click', () => { if (!el.list.value.trim() || confirm(tr('confirm_clear_list'))) setList([]); });
    // paste from Excel: tabs → newline
    el.list.addEventListener('paste', (e) => {
      const text = (e.clipboardData || window.clipboardData).getData('text');
      if (text && /\t/.test(text)) { e.preventDefault(); const start = el.list.selectionStart; const v = el.list.value; el.list.value = v.slice(0, start) + text.replace(/\t+/g, '\n') + v.slice(el.list.selectionEnd); onSettingsChanged(true); }
    });
  }

  /* ------------------------------------------------------------------ */
  /* history                                                             */
  /* ------------------------------------------------------------------ */
  function renderHistory() { U.renderHistory(historyList, history, tool); }
  renderHistory();
  document.addEventListener('ld:digits', renderHistory);
  function addHistory(h) { history.unshift(h); if (history.length > 200) history.length = 200; store.set('history.' + tool, history); renderHistory(); }
  $('#historyClear').addEventListener('click', async () => {
    if (!history.length) return;
    if (!confirm(tr('confirm_clear_history'))) return;
    history = []; store.set('history.' + tool, history); renderHistory();
    if (live) { try { await api('clear', { code: live.code, token: live.token }); } catch (e) { /* ignore */ } }
  });
  $('#historyCopy').addEventListener('click', async () => {
    if (!history.length) return toast(tr('history_is_empty'), 'info');
    const txt = history.slice().reverse().map((h, i) => `${i + 1}. ${U.historyText(h, tool)}`).join('\n');
    toast((await U.copyText(txt)) ? tr('copied') : tr('copy_failed'), 'ok');
  });
  $('#historyDownload').addEventListener('click', () => {
    if (!history.length) return toast(tr('history_is_empty'), 'info');
    U.download(`luckydraw-${tool}-${new Date().toISOString().slice(0, 10)}.csv`, U.historyToCsv(history, tool), 'text/csv;charset=utf-8');
  });

  /* ------------------------------------------------------------------ */
  /* draw                                                                */
  /* ------------------------------------------------------------------ */
  function setBusy(b) {
    busy = b;
    goBtn.disabled = b;
    if (impl.hub) impl.hub.disabled = b || (impl.items || []).length < 2;
    goBtn.querySelector('i').classList.toggle('spin', b && tool !== 'wheel');
    settings.classList.toggle('busy', b);
  }
  function validate(s) {
    if (tool === 'wheel' && (s.items || []).length < 2) { toast(tr('need_two_options'), 'err'); el.list.focus(); return false; }
    if (tool === 'pick' && !(s.items || []).length) { toast(tr('list_empty'), 'err'); el.list.focus(); return false; }
    if (tool === 'teams' && (s.items || []).length < 2) { toast(tr('need_two_people'), 'err'); el.list.focus(); return false; }
    if (tool === 'number') {
      if (s.min > s.max) { toast(tr('min_gt_max'), 'info'); [el.numMin.value, el.numMax.value] = [s.max, s.min]; onSettingsChanged(false); }
      if (s.unique && s.count > Math.abs(s.max - s.min) + 1) { toast(tr('unique_too_many'), 'err'); return false; }
    }
    return true;
  }
  async function go() {
    if (busy) return;
    const state = readState();
    if (!validate(state)) return;
    setBusy(true);
    sound.ensure();
    let event;
    try {
      if (live) {
        const r = await api('draw', { code: live.code, token: live.token, state });
        event = r.event;
        syncOffset(r.server_time);
        applyRoomMeta(r.room);
      } else {
        const r = await api('roll', { tool, state });
        event = r.event;
      }
    } catch (e) {
      if (live && e.code === 'not_found') { dropLive(tr('live_expired_local')); }
      else if (e.code === 'network' || (e.status && e.status >= 500)) toast(tr('server_down_local'), 'info');
      else { toast(e.message, 'err'); setBusy(false); return; }
      try { event = localRoll(state); } catch (err) { toast(err.message, 'err'); setBusy(false); return; }
    }
    try {
      const summary = await impl.play(event);
      addHistory(summary.history);
      // Apply the post-draw state (e.g. winner removed). Only the *list* is taken
      // from the server result; every other control keeps whatever the user set
      // while the animation was running (toggles, counts, duration …).
      if (event.next) { applyNextState(state, event.next); onSettingsChanged(false); }
    } catch (e) {
      console.error(e); toast(tr('render_error'), 'err');
    }
    setBusy(false);
    if (tool === 'wheel' && impl.hub) impl.hub.disabled = (readState().items || []).length < 2;
  }
  goBtn.addEventListener('click', go);
  if (impl.hub) impl.hub.addEventListener('click', go);
  resetBtn.addEventListener('click', () => { if (!busy) { impl.render(readState()); T.banner(null); } });
  document.addEventListener('keydown', (e) => {
    if (e.code === 'Space' && !/^(INPUT|TEXTAREA|SELECT|BUTTON)$/.test(document.activeElement.tagName)) { e.preventDefault(); go(); }
  });

  /** Client-side fallback with crypto RNG (only used if the server is unreachable). */
  function localRoll(s) {
    const now = Date.now();
    const ev = { id: 'local-' + U.uid(6), at: now, tool, state: s, result: null, next: s };
    const weighted = (items, count) => {
      // decimal weights → integer "tickets" (1/100 unit) so the crypto RNG stays integer-based
      const tickets = items.map((it) => Math.max(1, Math.round(U.parseWeight(it.w == null ? 1 : it.w) * 100)));
      const idx = items.map((_, i) => i); const chosen = [];
      while (chosen.length < count && idx.length) {
        const total = idx.reduce((a, i) => a + tickets[i], 0);
        let r = U.randInt(1, total), pos = 0;
        for (let p = 0; p < idx.length; p++) { r -= tickets[idx[p]]; if (r <= 0) { pos = p; break; } }
        chosen.push(idx[pos]); idx.splice(pos, 1);
      }
      return chosen;
    };
    if (tool === 'coin') ev.result = { sides: Array.from({ length: s.count }, () => U.randInt(0, 1)), flips: U.randInt(7, 11), duration: 2600 };
    else if (tool === 'number') {
      const nums = []; const seen = new Set();
      while (nums.length < s.count) { const n = U.randInt(s.min, s.max); if (s.unique) { if (seen.has(n)) continue; seen.add(n); } nums.push(n); }
      if (s.sort) nums.sort((a, b) => a - b);
      ev.result = { numbers: nums, duration: 2200 + Math.min(6, s.count) * 350 };
    } else if (tool === 'pick') {
      if (!s.items.length) throw new Error(tr('empty_list'));
      const idx = weighted(s.items, Math.min(s.count, s.items.length));
      ev.result = { indexes: idx, picked: idx.map((i) => s.items[i]), duration: 2600 + Math.min(10, idx.length) * 700 };
      if (s.remove) ev.next = Object.assign({}, s, { items: s.items.filter((_, i) => !idx.includes(i)) });
    } else if (tool === 'wheel') {
      if (s.items.length < 2) throw new Error(tr('min_two_options'));
      const i = weighted(s.items, 1)[0];
      ev.result = { index: i, winner: s.items[i], turns: U.randInt(5, 8), offset: U.randInt(120, 880) / 1000, duration: s.duration * 1000 };
      if (s.remove) ev.next = Object.assign({}, s, { items: s.items.filter((_, k) => k !== i) });
    } else if (tool === 'teams') {
      if (s.items.length < 2) throw new Error(tr('min_two_people'));
      const n = impl.groupCount(s, s.items.length);
      const idx = s.items.map((_, i) => i);
      for (let i = idx.length - 1; i > 0; i--) { const j = U.randInt(0, i); [idx[i], idx[j]] = [idx[j], idx[i]]; }
      const groups = Array.from({ length: n }, () => []);
      idx.forEach((i, k) => groups[k % n].push(i));
      ev.result = { groups, duration: 1200 + Math.min(s.items.length, 40) * 110 };
    }
    return ev;
  }

  /* ------------------------------------------------------------------ */
  /* live sharing                                                        */
  /* ------------------------------------------------------------------ */
  const pill = $('#livePill'); const pillCode = $('#liveCode'); const pillTimer = $('#liveTimer'); const pillViewers = $('#liveViewers');
  const shareBtn = $('#shareBtn');
  let modal = null; let modalEls = null; let roomMeta = null; let serverInfo = null;

  function syncOffset(serverTime) { if (serverTime) serverOffset = serverTime - Date.now(); }
  function now() { return Date.now() + serverOffset; }

  function liveUrlFor(code, host) {
    const path = LD.liveUrl.replace('{code}', code);
    const origin = host ? `${window.location.protocol}//${host}` : window.location.origin;
    return origin + path;
  }
  function isLocalHost(h) { return /^(localhost|127\.|0\.0\.0\.0|\[::1\])/.test(h); }
  /** Live-code input filter: Latin digits, upper-case A–Z/0–9 and dashes (spaces → dash). */
  function normalizeCodeInput(v) {
    return U.toLatinDigits(String(v || '')).toUpperCase().replace(/[\s_]+/g, '-').replace(/[^A-Z0-9-]/g, '').replace(/-{2,}/g, '-').slice(0, (LD.codeRules && LD.codeRules.max) || 16);
  }

  function schedulePush(state) {
    clearTimeout(pushTimer);
    pushTimer = setTimeout(async () => {
      if (!live) return;
      try { const r = await api('state', { code: live.code, token: live.token, state }); applyRoomMeta(r.room); }
      catch (e) { if (e.code === 'not_found' || e.code === 'forbidden') dropLive(tr('live_ended')); }
    }, 500);
  }

  function applyRoomMeta(room) {
    if (!room) return;
    roomMeta = room;
    live.expires_at = room.expires_at; live.created_at = room.created_at; live.max_expires_at = room.max_expires_at;
    store.set('room.' + tool, live);
    if (pillViewers) pillViewers.textContent = fmt(room.viewers || 0);
    if (modalEls) {
      modalEls.viewers.textContent = fmt(room.viewers || 0);
      modalEls.expires.textContent = U.fmtTime(room.expires_at);
    }
    tick();
  }

  function setLive(room, token) {
    live = { code: room.id, token, expires_at: room.expires_at, created_at: room.created_at, max_expires_at: room.max_expires_at };
    store.set('room.' + tool, live);
    roomMeta = room;
    pill.hidden = false; pill.classList.remove('dead', 'warn');
    pillCode.textContent = room.id;
    shareBtn.querySelector('span').textContent = tr('live_link');
    startPolling();
    tick();
  }
  function dropLive(msg) {
    live = null; roomMeta = null; store.del('room.' + tool);
    stopPolling();
    pill.hidden = true;
    shareBtn.querySelector('span').textContent = tr('create_live');
    if (modal) { modal.close(); modal = null; }
    if (msg) toast(msg, 'info', 4000);
  }
  function tick() {
    if (!live) return;
    const left = live.expires_at - now();
    pillTimer.textContent = fmtClock(left);
    pill.classList.toggle('warn', left < 90000 && left > 0);
    if (modalEls) modalEls.expires.textContent = U.fmtTime(live.expires_at) + ' (' + fmtClock(left) + ')';
    if (left <= 0) { dropLive(tr('live_time_over')); }
  }
  function startPolling() {
    stopPolling();
    tickTimer = setInterval(tick, 1000);
    const poll = async () => {
      if (!live) return;
      try {
        const r = await api('room', { code: live.code, v: -1 });
        syncOffset(r.server_time);
        applyRoomMeta(r.room);
      } catch (e) { if (e.code === 'not_found') dropLive(tr('live_expired')); }
    };
    pollTimer = setInterval(poll, 4000);
    poll();
  }
  function stopPolling() { clearInterval(pollTimer); clearInterval(tickTimer); pollTimer = tickTimer = null; }

  async function openShare() {
    const tpl = $('#shareTemplate');
    const node = tpl.content.firstElementChild.cloneNode(true);
    modal = U.openModal(node, { onClose: () => { modal = null; modalEls = null; } });
    const stepCreate = $('[data-step="create"]', node); const stepReady = $('[data-step="ready"]', node);
    modalEls = {
      code: $('#shareCode', node), url: $('#shareUrl', node), qr: $('#shareQr', node), expires: $('#shareExpires', node), viewers: $('#shareViewers', node),
      hostNote: $('#shareHostNote', node), hostSelect: $('#shareHostSelect', node), open: $('#shareOpen', node),
    };
    // TTL chips
    const ttlBox = $('#ttlOptions', node); let ttl = store.get('ttl', 10);
    LD.ttlOptions.forEach((m) => {
      const c = document.createElement('button'); c.type = 'button'; c.className = 'chip' + (m === ttl ? ' active' : ''); c.textContent = tr('minutes', fmt(m));
      c.addEventListener('click', () => { ttl = m; store.set('ttl', m); $$('.chip', ttlBox).forEach((x) => x.classList.toggle('active', x === c)); });
      ttlBox.appendChild(c);
    });
    const extendSel = $('#extendMinutes', node);
    LD.ttlOptions.forEach((m) => { const o = document.createElement('option'); o.value = m; o.textContent = tr('minutes', fmt(m)); if (m === 10) o.selected = true; extendSel.appendChild(o); });

    // custom code (auto / custom)
    const modeBox = $('#shareCodeMode', node); const customWrap = $('#shareCustomWrap', node); const customInput = $('#shareCustomCode', node);
    const customPrefix = $('#shareCustomPrefix', node);
    let codeMode = store.get('shareCodeMode', 'auto');
    const setMode = (m) => {
      codeMode = m === 'custom' ? 'custom' : 'auto'; store.set('shareCodeMode', codeMode);
      $$('button', modeBox).forEach((b) => b.classList.toggle('active', b.dataset.mode === codeMode));
      customWrap.hidden = codeMode !== 'custom';
      if (codeMode === 'custom') setTimeout(() => customInput.focus(), 30);
    };
    $$('button', modeBox).forEach((b) => b.addEventListener('click', () => setMode(b.dataset.mode)));
    customPrefix.textContent = liveUrlFor('', window.location.host).replace(/^https?:\/\//, '');
    customInput.value = store.get('shareCustomCode.' + tool, '');
    customInput.addEventListener('input', () => {
      const v = normalizeCodeInput(customInput.value);
      if (v !== customInput.value) customInput.value = v;
      store.set('shareCustomCode.' + tool, v);
      customInput.classList.remove('invalid');
    });
    setMode(codeMode);

    const showReady = () => {
      stepCreate.hidden = true; stepReady.hidden = false;
      modalEls.code.textContent = live.code;
      const note = $('#shareCodeNote', node); if (note) note.hidden = !(roomMeta && roomMeta.custom);
      modalEls.code.classList.toggle('long', live.code.length > 8);
      const hosts = [];
      const cur = window.location.host;
      if (!isLocalHost(cur)) hosts.push(cur);
      if (serverInfo && serverInfo.lan_ip) {
        const port = window.location.port ? ':' + window.location.port : '';
        const lan = serverInfo.lan_ip + port;
        if (!hosts.includes(lan)) hosts.push(lan);
      }
      if (!hosts.length) hosts.push(cur);
      modalEls.hostSelect.innerHTML = '';
      hosts.forEach((h) => { const o = document.createElement('option'); o.value = h; o.textContent = h; modalEls.hostSelect.appendChild(o); });
      const preferred = store.get('shareHost', null);
      if (preferred && hosts.includes(preferred)) modalEls.hostSelect.value = preferred;
      else if (isLocalHost(cur) && hosts.length) modalEls.hostSelect.value = hosts[0];
      modalEls.hostNote.hidden = hosts.length < 2 && !isLocalHost(cur);
      if (isLocalHost(cur) && hosts.length === 1 && isLocalHost(hosts[0])) {
        modalEls.hostNote.hidden = false;
        modalEls.hostNote.querySelector('span').textContent = tr('localhost_note');
      }
      const applyHost = () => {
        const url = liveUrlFor(live.code, modalEls.hostSelect.value);
        modalEls.url.value = url; modalEls.open.href = url;
        U.renderQr(modalEls.qr, url, 200);
        store.set('shareHost', modalEls.hostSelect.value);
      };
      modalEls.hostSelect.addEventListener('change', applyHost);
      applyHost();
      applyRoomMeta(roomMeta || { expires_at: live.expires_at, created_at: live.created_at, viewers: 0 });
    };

    $('#shareCreate', node).addEventListener('click', async (e) => {
      const b = e.currentTarget; b.disabled = true; b.querySelector('i').className = 'fa-solid fa-spinner spin';
      try {
        if (!serverInfo) { try { serverInfo = await api('info'); syncOffset(serverInfo.server_time); } catch (err) { serverInfo = {}; } }
        if (serverInfo.store_error) throw new Error(tr('storage_error', serverInfo.store_error));
        const state = readState();
        const payload = { tool, state, ttl, title: $('#shareTitle', node).value.trim() };
        if (codeMode === 'custom') {
          const c = normalizeCodeInput(customInput.value).replace(/^-+|-+$/g, '');
          const rules = LD.codeRules || { min: 4, max: 16 };
          if (c.length < rules.min || c.length > rules.max) {
            customInput.classList.add('invalid', 'shake'); setTimeout(() => customInput.classList.remove('shake'), 500); customInput.focus();
            throw Object.assign(new Error(tr('custom_code_len', fmt(rules.min), fmt(rules.max))), { code: 'invalid' });
          }
          payload.code = c;
        }
        const r = await api('create', payload);
        syncOffset(r.server_time);
        setLive(r.room, r.token);
        showReady();
        toast(tr('live_created'), 'ok');
      } catch (err) {
        toast(err.message, 'err', 5000);
        if (err.code === 'code_taken' || err.code === 'invalid') { customInput.classList.add('invalid'); setMode('custom'); customInput.select(); }
        b.disabled = false; b.querySelector('i').className = 'fa-solid fa-link';
      }
    });
    $('#shareCopy', node).addEventListener('click', async () => {
      toast((await U.copyText(modalEls.url.value)) ? tr('link_copied') : tr('link_copy_failed'), 'ok');
      modalEls.url.select();
    });
    $('#shareExtend', node).addEventListener('click', async () => {
      if (!live) return;
      try {
        const r = await api('extend', { code: live.code, token: live.token, minutes: parseInt(extendSel.value, 10) });
        syncOffset(r.server_time); applyRoomMeta(r.room); pill.classList.remove('warn');
        toast(tr('extended'), 'ok');
      } catch (err) { toast(err.message, 'err'); if (err.code === 'not_found') dropLive(); }
    });
    $('#shareEnd', node).addEventListener('click', async () => {
      if (!live || !confirm(tr('confirm_end'))) return;
      try { await api('end', { code: live.code, token: live.token }); } catch (err) { /* ignore */ }
      dropLive(tr('live_finished'));
    });

    if (live) {
      if (!serverInfo) { try { serverInfo = await api('info'); syncOffset(serverInfo.server_time); } catch (err) { serverInfo = {}; } }
      showReady();
    } else {
      $('#shareTitle', node).value = store.get('shareTitle.' + tool, '');
      $('#shareTitle', node).addEventListener('input', (e) => store.set('shareTitle.' + tool, e.target.value));
      setTimeout(() => $('#shareTitle', node).focus(), 50);
    }
  }
  shareBtn.addEventListener('click', openShare);
  pill.addEventListener('click', openShare);

  // resume a live session after reload
  if (live) {
    (async () => {
      try {
        const r = await api('room', { code: live.code, v: -1 });
        syncOffset(r.server_time);
        if (r.room.expires_at <= now()) throw Object.assign(new Error('expired'), { code: 'not_found' });
        setLive(r.room, live.token);
        // server state is what viewers currently see — adopt it
        writeState(r.room.state); onSettingsChanged(true);
        toast(tr('live_still_active', r.room.id), 'ok');
      } catch (e) { dropLive(); }
    })();
  }

  U.setupFullscreen($('#fullscreenBtn'), $('#stagePanel'));
  window.addEventListener('beforeunload', () => { clearTimeout(pushTimer); });
})();
