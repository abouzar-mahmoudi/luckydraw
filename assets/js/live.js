/* =========================================================================
   قرعه‌کشی — live viewer page (read-only, polls the room and replays events)
   ========================================================================= */
(function () {
  'use strict';
  const U = window.LDU; const T = window.LDTools; const LD = window.LD;
  const { $, fmt, fmtClock, toast, api, store } = U; const tr = U.T;

  let room = window.LD_ROOM;
  if (!room) return; // expired page

  const tool = room.tool;
  const impl = T[tool];
  const stage = $('#stage');
  impl.init(stage);

  const status = $('#liveStatus'); const timer = $('#liveTimer'); const viewers = $('#liveViewers');
  const list = $('#liveList'); const count = $('#liveCount'); const historyList = $('#historyList'); const title = $('#liveTitle');
  const pill = $('.live-pill');

  let vid = store.get('vid', null); if (!vid) { vid = U.uid(16); store.set('vid', vid); }
  let serverOffset = 0; const now = () => Date.now() + serverOffset;
  let version = -1; let playing = false; let lastEventId = null; let pending = null; let failures = 0; let ended = false;

  function setStatus(text, cls) { status.className = 'live-status ' + (cls || ''); status.innerHTML = text; }

  function renderList(state) {
    if (!list) return;
    list.innerHTML = '';
    const items = state.items || [];
    if (count) count.textContent = fmt(items.length);
    if (tool === 'coin') { list.innerHTML = `<span class="tag">${esc(state.heads)}</span><span class="tag">${esc(state.tails)}</span>`; if (count) count.textContent = fmt(2); return; }
    if (tool === 'number') { list.innerHTML = `<span class="tag num">${esc(tr('range', fmt(state.min), fmt(state.max)))}</span><span class="tag num">${esc(tr('n_numbers', fmt(state.count)))}</span>`; if (count) count.textContent = ''; return; }
    items.slice(0, 300).forEach((it) => {
      const t = document.createElement('span'); t.className = 'tag'; t.textContent = it.n;
      if (U.fmtWeight(it.w)) { const s = document.createElement('small'); s.textContent = U.fmtWeight(it.w); t.appendChild(s); }
      list.appendChild(t);
    });
    if (items.length > 300) { const m = document.createElement('span'); m.className = 'tag'; m.textContent = `+${fmt(items.length - 300)}`; list.appendChild(m); }
  }
  function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

  function renderHistory() { U.renderHistory(historyList, room.history, tool); }
  document.addEventListener('ld:digits', renderHistory);

  async function applyRoom(r, initial) {
    const prevState = room ? JSON.stringify(room.state) : null;
    room = r;
    version = r.version;
    if (title && r.title) title.textContent = r.title;
    renderList(r.state);
    renderHistory();
    if (viewers) viewers.textContent = fmt(Math.max(1, r.viewers || 1));
    const ev = r.event;
    if (ev && ev.id !== lastEventId) {
      lastEventId = ev.id;
      const age = Math.max(0, now() - ev.at);
      const total = (ev.result && ev.result.duration) || 3000;
      // late joiners fast-forward into the animation; very old events are shown silently
      await playEvent(ev, age, age > total + 1500);
    } else if (!playing && (initial || JSON.stringify(r.state) !== prevState)) {
      // settings changed without a new draw (host edited the list) → refresh the stage
      impl.render(r.state);
      if (initial) setStatus('<i class="fa-solid fa-satellite-dish fa-fade"></i> ' + esc(tr('waiting_host')));
    }
  }

  async function playEvent(ev, skipMs, quiet) {
    if (playing) { pending = ev; return; }
    playing = true;
    if (!quiet) setStatus('<i class="fa-solid fa-bolt"></i> ' + esc(tr('running')), 'ok');
    try { await impl.play(ev, { skipMs, quiet }); }
    catch (e) { console.error(e); }
    playing = false;
    setStatus('<i class="fa-solid fa-satellite-dish fa-fade"></i> ' + esc(tr('waiting_host')));
    if (pending) { const p = pending; pending = null; await playEvent(p, Math.max(0, now() - p.at), false); }
  }

  function tick() {
    if (ended) return;
    const left = room.expires_at - now();
    timer.textContent = fmtClock(left);
    if (pill) pill.classList.toggle('warn', left < 90000 && left > 0);
    if (left <= 0) expire();
  }
  function expire() {
    if (ended) return;
    ended = true; clearInterval(pollTimer); clearInterval(tickTimer);
    if (pill) { pill.classList.remove('warn'); pill.classList.add('dead'); }
    timer.textContent = fmtClock(0);
    setStatus('<i class="fa-solid fa-hourglass-end"></i> ' + esc(tr('live_over')), 'err');
    const box = document.createElement('div');
    box.className = 'expired'; box.style.padding = '20px 0 0';
    box.innerHTML = `<p class="muted">${esc(tr('live_over_desc'))}</p><a class="btn btn-primary" href="${esc(LD.base)}/"><i class="fa-solid fa-house"></i><span>${esc(tr('home'))}</span></a>`;
    $('#stagePanel').appendChild(box);
  }

  async function poll() {
    if (ended) return;
    try {
      const r = await api('room', { code: room.id, v: version, vid });
      failures = 0;
      serverOffset = r.server_time - Date.now();
      if (r.changed) await applyRoom(r.room, false);
      else { room.expires_at = r.expires_at; if (viewers) viewers.textContent = fmt(Math.max(1, r.viewers || 1)); }
    } catch (e) {
      if (e.code === 'not_found') { expire(); return; }
      failures++;
      if (failures === 3) setStatus('<i class="fa-solid fa-triangle-exclamation"></i> ' + esc(tr('conn_lost')), 'err');
    }
  }

  const pollTimer = setInterval(poll, 1000);
  const tickTimer = setInterval(tick, 1000);
  U.setupFullscreen($('#fullscreenBtn'), $('#stagePanel'));

  // initial render
  (async () => {
    try { const info = await api('info'); serverOffset = info.server_time - Date.now(); } catch (e) { /* ignore */ }
    await applyRoom(room, true);
    tick();
  })();
})();
