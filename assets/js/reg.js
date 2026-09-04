/* =========================================================================
   قرعه‌کشی — registration ("ثبت‌نام جهت قرعه‌کشی") host controller.
   Lives inside the settings panel of the list tools (pick / wheel / teams):
   create a public signup link, moderate entries, import approved names into
   the participant list (#listInput).
   ========================================================================= */
(function () {
  'use strict';
  const U = window.LDU; const LD = window.LD;
  if (!U || !LD || !LD.tool) return;
  const box = document.getElementById('regBox');
  const listInput = document.getElementById('listInput');
  if (!box || !listInput) return;
  const { $, $$, fmt, toast, api, store } = U; const tr = U.T;
  const tool = LD.tool;
  const q = (sel) => $(sel, box);

  const els = {
    toggle: q('#regToggle'), body: q('#regBody'), badge: q('#regBadge'), badgeCount: q('#regBadgeCount'),
    stepCreate: q('[data-step="create"]'), stepReady: q('[data-step="ready"]'),
    fields: q('#regFields'), auto: q('#regAuto'), ttl: q('#regTtlOptions'), codeMode: q('#regCodeMode'),
    customWrap: q('#regCustomWrap'), customCode: q('#regCustomCode'), customPrefix: q('#regCustomPrefix'), create: q('#regCreate'),
    state: q('#regState'), stateText: q('#regStateText'), expires: q('#regExpires'), url: q('#regUrl'), copy: q('#regCopy'),
    qr: q('#regQr'), open: q('#regOpen'), autoLive: q('#regAutoLive'), extendMinutes: q('#regExtendMinutes'), extend: q('#regExtend'),
    filter: q('#regFilter'), entries: q('#regEntries'), bulk: q('#regBulk'), approveAll: q('#regApproveAll'), rejectAll: q('#regRejectAll'),
    importBtn: q('#regImport'), importCount: q('#regImportCount'), close: q('#regClose'), end: q('#regEnd'),
  };

  let signup = null;                      // host view from the server
  let session = store.get('signup.' + tool, null); // {code, token}
  let pollTimer = null; let tickTimer = null;
  let filter = 'all';
  let seenTotal = null;
  let serverOffset = 0;
  const now = () => Date.now() + serverOffset;

  /* ---------------- helpers ---------------- */
  function ttlLabel(m) {
    if (m % 1440 === 0) return tr('reg_days', fmt(m / 1440));
    return tr('reg_hours', fmt(m / 60));
  }
  function urlFor(code) {
    return window.location.origin + (LD.signupUrl || (LD.base + '/?s={code}')).replace('{code}', code);
  }
  function normalizeCodeInput(v) {
    return U.toLatinDigits(String(v || '')).toUpperCase().replace(/[\s_]+/g, '-').replace(/[^A-Z0-9-]/g, '').replace(/-{2,}/g, '-').slice(0, (LD.codeRules && LD.codeRules.max) || 16);
  }
  function entryLabel(e) {
    if (e.name && e.code) return `${e.name} (${e.code})`;
    return e.name || e.code || tr('reg_no_name');
  }
  function setOpen(open) {
    els.toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    els.body.hidden = !open;
    box.classList.toggle('open', open);
    store.set('signupOpen.' + tool, open);
  }
  els.toggle.addEventListener('click', () => setOpen(els.body.hidden));

  /* ---------------- create step ---------------- */
  let fields = store.get('signupFields.' + tool, 'name');
  $$('button', els.fields).forEach((b) => {
    b.classList.toggle('active', b.dataset.fields === fields);
    b.addEventListener('click', () => { fields = b.dataset.fields; store.set('signupFields.' + tool, fields); $$('button', els.fields).forEach((x) => x.classList.toggle('active', x === b)); });
  });
  els.auto.checked = !!store.get('signupAuto.' + tool, false);
  els.auto.addEventListener('change', () => store.set('signupAuto.' + tool, els.auto.checked));

  const ttlOptions = LD.signupTtlOptions || [60, 180, 360, 720, 1440, 2880, 4320, 10080];
  let ttl = store.get('signupTtl', 1440);
  if (!ttlOptions.includes(ttl)) ttl = 1440;
  ttlOptions.forEach((m) => {
    const c = document.createElement('button'); c.type = 'button'; c.className = 'chip' + (m === ttl ? ' active' : ''); c.textContent = ttlLabel(m);
    c.addEventListener('click', () => { ttl = m; store.set('signupTtl', m); $$('.chip', els.ttl).forEach((x) => x.classList.toggle('active', x === c)); });
    els.ttl.appendChild(c);
    const o = document.createElement('option'); o.value = m; o.textContent = ttlLabel(m); if (m === 1440) o.selected = true; els.extendMinutes.appendChild(o);
  });

  let codeMode = 'auto';
  const setMode = (m) => {
    codeMode = m === 'custom' ? 'custom' : 'auto';
    $$('button', els.codeMode).forEach((b) => b.classList.toggle('active', b.dataset.mode === codeMode));
    els.customWrap.hidden = codeMode !== 'custom';
    if (codeMode === 'custom') setTimeout(() => els.customCode.focus(), 30);
  };
  $$('button', els.codeMode).forEach((b) => b.addEventListener('click', () => setMode(b.dataset.mode)));
  els.customPrefix.textContent = urlFor('').replace(/^https?:\/\//, '');
  els.customCode.addEventListener('input', () => {
    const v = normalizeCodeInput(els.customCode.value);
    if (v !== els.customCode.value) els.customCode.value = v;
    els.customCode.classList.remove('invalid');
  });

  els.create.addEventListener('click', async () => {
    const b = els.create; b.disabled = true; b.querySelector('i').className = 'fa-solid fa-spinner spin';
    try {
      const shareTitle = store.get('shareTitle.' + tool, '');
      const payload = { tool, title: shareTitle, fields, auto: els.auto.checked, ttl };
      if (codeMode === 'custom') {
        const c = normalizeCodeInput(els.customCode.value).replace(/^-+|-+$/g, '');
        const rules = LD.codeRules || { min: 4, max: 16 };
        if (c.length < rules.min || c.length > rules.max) {
          els.customCode.classList.add('invalid', 'shake'); setTimeout(() => els.customCode.classList.remove('shake'), 500); els.customCode.focus();
          throw Object.assign(new Error(tr('custom_code_len', fmt(rules.min), fmt(rules.max))), { code: 'invalid' });
        }
        payload.code = c;
      }
      const r = await api('signup_create', payload);
      serverOffset = r.server_time - Date.now();
      session = { code: r.signup.id, token: r.token };
      store.set('signup.' + tool, session);
      applySignup(r.signup);
      showReady();
      toast(tr('reg_created'), 'ok');
    } catch (err) {
      toast(err.message, 'err', 5000);
      if (err.code === 'code_taken' || err.code === 'invalid') { els.customCode.classList.add('invalid'); setMode('custom'); els.customCode.select(); }
    } finally {
      b.disabled = false; b.querySelector('i').className = 'fa-solid fa-link';
    }
  });

  /* ---------------- ready step ---------------- */
  function showReady() {
    els.stepCreate.hidden = true; els.stepReady.hidden = false;
    const url = urlFor(session.code);
    els.url.value = url; els.open.href = url;
    U.renderQr(els.qr, url, 150);
    startPolling();
    setOpen(true);
  }
  function showCreate() {
    els.stepCreate.hidden = false; els.stepReady.hidden = true;
    els.badge.hidden = true;
  }

  function counts(s) {
    const c = { total: 0, pending: 0, approved: 0, rejected: 0 };
    (s.entries || []).forEach((e) => { c.total++; c[e.status] = (c[e.status] || 0) + 1; });
    return c;
  }

  function applySignup(s) {
    const prevTotal = signup ? signup.entries.length : null;
    signup = s;
    const c = counts(s);
    // header badge (pending count when moderating, else total)
    const badgeN = s.auto ? c.total : c.pending;
    els.badge.hidden = false;
    els.badgeCount.textContent = fmt(badgeN);
    els.badge.classList.toggle('attention', !s.auto && c.pending > 0);
    els.badge.classList.toggle('closed', !s.open);
    // state / expiry
    els.state.classList.toggle('closed', !s.open);
    els.stateText.textContent = s.open ? tr('reg_status_open') : tr('reg_status_closed');
    els.close.querySelector('span').textContent = s.open ? tr('reg_close') : tr('reg_reopen');
    els.close.querySelector('i').className = s.open ? 'fa-solid fa-lock' : 'fa-solid fa-lock-open';
    els.expires.textContent = U.fmtTime(s.expires_at);
    els.autoLive.checked = !!s.auto;
    // counters
    $$('[data-count]', els.filter).forEach((b) => { b.textContent = fmt(c[b.dataset.count] || 0); });
    els.bulk.hidden = c.pending === 0;
    els.importCount.textContent = fmt(c.approved);
    els.importBtn.disabled = c.approved === 0;
    renderEntries();
    if (seenTotal !== null && prevTotal !== null && c.total > seenTotal && !els.body.hidden) {
      toast(tr('reg_new', fmt(c.total - seenTotal)), 'info', 2500);
    }
    seenTotal = c.total;
  }

  function renderEntries() {
    const root = els.entries; root.innerHTML = '';
    const list = (signup.entries || []).filter((e) => filter === 'all' || e.status === filter).slice().reverse();
    if (!list.length) {
      const li = document.createElement('li'); li.className = 'empty'; li.textContent = tr('reg_empty'); root.appendChild(li); return;
    }
    list.forEach((e) => {
      const li = document.createElement('li'); li.className = 'reg-entry ' + e.status; li.dataset.id = e.id;
      const main = document.createElement('div'); main.className = 'reg-entry-main';
      const name = document.createElement('span'); name.className = 'reg-entry-name'; name.textContent = e.name || (e.code ? '' : tr('reg_no_name'));
      const code = document.createElement('span'); code.className = 'reg-entry-code num'; code.dir = 'ltr'; code.textContent = e.code || '';
      const time = document.createElement('small'); time.className = 'reg-entry-time num'; time.textContent = U.fmtTime(e.at);
      main.append(name, code, time);
      const acts = document.createElement('div'); acts.className = 'reg-entry-actions';
      const mk = (op, icon, title, cls) => {
        const b = document.createElement('button'); b.type = 'button'; b.className = 'icon-mini ' + cls; b.title = title; b.innerHTML = `<i class="fa-solid ${icon}"></i>`;
        b.addEventListener('click', () => moderate(op, e.id));
        return b;
      };
      if (e.status !== 'approved') acts.appendChild(mk('approve', 'fa-check', tr('reg_approve'), 'ok'));
      if (e.status !== 'rejected') acts.appendChild(mk('reject', 'fa-xmark', tr('reg_reject'), 'warn'));
      acts.appendChild(mk('delete', 'fa-trash', tr('reg_delete'), 'danger'));
      const st = document.createElement('span'); st.className = 'reg-entry-status'; st.textContent = tr('reg_' + e.status);
      li.append(main, st, acts);
      root.appendChild(li);
    });
  }

  $$('button', els.filter).forEach((b) => b.addEventListener('click', () => {
    filter = b.dataset.filter; $$('button', els.filter).forEach((x) => x.classList.toggle('active', x === b)); renderEntries();
  }));

  async function hostCall(action, data) {
    try {
      const r = await api(action, Object.assign({ code: session.code, token: session.token }, data || {}));
      serverOffset = r.server_time - Date.now();
      if (r.ended) return r;
      applySignup(r.signup);
      return r;
    } catch (err) {
      if (err.code === 'not_found' || err.code === 'forbidden') { drop(tr('reg_expired')); return null; }
      toast(err.message, 'err', 4000);
      return null;
    }
  }
  const moderate = (op, entry) => hostCall('signup_moderate', { op, entry });
  els.approveAll.addEventListener('click', () => moderate('approve', '*'));
  els.rejectAll.addEventListener('click', () => moderate('reject', '*'));
  els.autoLive.addEventListener('change', () => hostCall('signup_set', { auto: els.autoLive.checked }));
  els.close.addEventListener('click', () => hostCall('signup_set', { open: !signup.open }));
  els.extend.addEventListener('click', async () => { if (await hostCall('signup_extend', { minutes: parseInt(els.extendMinutes.value, 10) })) toast(tr('extended'), 'ok'); });
  els.copy.addEventListener('click', async () => { toast((await U.copyText(els.url.value)) ? tr('link_copied') : tr('link_copy_failed'), 'ok'); els.url.select(); });
  els.end.addEventListener('click', async () => {
    if (!confirm(tr('reg_confirm_end'))) return;
    try { await api('signup_end', { code: session.code, token: session.token }); } catch (err) { /* ignore */ }
    drop(tr('reg_ended'));
  });

  /* import approved entries into the participant list (dedupe, keep weights of existing names) */
  els.importBtn.addEventListener('click', () => {
    const approved = (signup.entries || []).filter((e) => e.status === 'approved').map(entryLabel);
    const current = U.parseList(listInput.value);
    const have = new Set(current.map((it) => it.n.toLowerCase()));
    let added = 0;
    approved.forEach((n) => { const k = n.toLowerCase(); if (!have.has(k)) { have.add(k); current.push({ n, w: 1 }); added++; } });
    if (!added) { toast(tr('reg_import_none'), 'info'); return; }
    listInput.value = U.listToText(current);
    listInput.dispatchEvent(new Event('input', { bubbles: true }));
    toast(tr('reg_imported', fmt(added)), 'ok');
    listInput.scrollTop = listInput.scrollHeight;
  });

  /* ---------------- polling ---------------- */
  function startPolling() {
    stopPolling();
    const poll = async () => {
      if (!session) return;
      try {
        const r = await api('signup', { code: session.code, token: session.token, v: signup ? signup.version : -1 }, 'POST'); // POST: keep the token out of server logs
        serverOffset = r.server_time - Date.now();
        if (r.changed !== false && r.signup) applySignup(r.signup);
        else if (signup && r.expires_at) { signup.expires_at = r.expires_at; els.expires.textContent = U.fmtTime(r.expires_at); }
      } catch (err) { if (err.code === 'not_found') drop(tr('reg_expired')); }
    };
    pollTimer = setInterval(poll, document.hidden ? 15000 : 5000);
    tickTimer = setInterval(() => { if (signup && signup.expires_at - now() <= 0) drop(tr('reg_expired')); }, 1000);
    poll();
  }
  function stopPolling() { clearInterval(pollTimer); clearInterval(tickTimer); pollTimer = tickTimer = null; }
  document.addEventListener('visibilitychange', () => { if (session && pollTimer) startPolling(); });

  function drop(msg) {
    session = null; signup = null; seenTotal = null; store.del('signup.' + tool);
    stopPolling();
    showCreate();
    if (msg) toast(msg, 'info', 4000);
  }

  /* ---------------- resume after reload ---------------- */
  setOpen(!!store.get('signupOpen.' + tool, false));
  if (session && session.code && session.token) {
    (async () => {
      try {
        const r = await api('signup', { code: session.code, token: session.token, v: -1 }, 'POST');
        serverOffset = r.server_time - Date.now();
        if (!r.signup || !r.signup.entries) throw Object.assign(new Error('forbidden'), { code: 'forbidden' });
        applySignup(r.signup);
        seenTotal = r.signup.entries.length;
        showReady();
        if (!store.get('signupOpen.' + tool, true)) setOpen(false);
      } catch (e) { drop(); }
    })();
  }
})();
