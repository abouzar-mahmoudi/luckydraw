/* قرعه‌کشی — public registration page (/signup/CODE) */
(function () {
  'use strict';
  const U = window.LDU; const LD = window.LD;
  if (!U || !window.LD_SIGNUP) return;
  const { $, T: tr, api, toast, fmt } = U;
  let signup = window.LD_SIGNUP;
  const form = $('#signupForm'); const done = $('#signupDone'); const closed = $('#signupClosed');
  const nameIn = $('#signupName'); const codeIn = $('#signupCode'); const submit = $('#signupSubmit');
  const until = $('#signupUntil'); const countEl = $('#signupCount');
  let serverOffset = 0;
  const now = () => Date.now() + serverOffset;

  function renderMeta() {
    if (until) until.textContent = U.fmtTime(signup.expires_at);
    if (countEl) {
      countEl.innerHTML = '';
      const parts = tr('registered_count', '\u0000').split('\u0000');
      const b = document.createElement('b'); b.className = 'num'; b.textContent = fmt(signup.total || 0);
      countEl.append(parts[0] || '', b, parts[1] || '');
    }
  }
  function showClosed() {
    if (form) form.hidden = true;
    if (done) done.hidden = true;
    if (closed) closed.hidden = false;
  }
  function checkExpiry() {
    if (signup.expires_at - now() <= 0) showClosed();
  }
  renderMeta();

  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const name = nameIn ? nameIn.value.trim() : '';
      const code = codeIn ? codeIn.value.trim() : '';
      if (nameIn && !name) { nameIn.focus(); nameIn.classList.add('invalid', 'shake'); setTimeout(() => nameIn.classList.remove('shake'), 500); return; }
      if (codeIn && !code) { codeIn.focus(); codeIn.classList.add('invalid', 'shake'); setTimeout(() => codeIn.classList.remove('shake'), 500); return; }
      submit.disabled = true; submit.querySelector('i').className = 'fa-solid fa-spinner spin';
      submit.querySelector('span').textContent = tr('reg_submitting');
      try {
        const r = await api('signup_register', { code: signup.id, name, code_value: code });
        serverOffset = r.server_time - Date.now();
        signup = r.signup; renderMeta();
        form.hidden = true; done.hidden = false;
        $('#signupDoneMsg').textContent = r.entry.status === 'approved' ? tr('done_approved') : tr('done_pending');
        const label = [r.entry.name, r.entry.code ? '(' + r.entry.code + ')' : ''].filter(Boolean).join(' ');
        $('#signupEntry').textContent = label;
        try { sessionStorage.setItem('ld.signup.' + signup.id, JSON.stringify(r.entry)); } catch (err) { /* ignore */ }
        U.confettiBurst && U.confettiBurst();
      } catch (err) {
        toast(err.message, 'err', 5000);
        if (err.code === 'signup_closed' || err.code === 'not_found') showClosed();
        if (err.code === 'duplicate') { (codeIn || nameIn).classList.add('invalid'); (codeIn || nameIn).select(); }
        submit.disabled = false; submit.querySelector('i').className = 'fa-solid fa-user-plus';
        submit.querySelector('span').textContent = tr('submit');
      }
    });
    [nameIn, codeIn].forEach((i) => i && i.addEventListener('input', () => i.classList.remove('invalid')));
  }

  // restore "already registered" state after a reload on this device
  try {
    const prev = sessionStorage.getItem('ld.signup.' + signup.id);
    if (prev && form && done) {
      const entry = JSON.parse(prev);
      form.hidden = true; done.hidden = false;
      $('#signupDoneMsg').textContent = entry.status === 'approved' ? tr('done_approved') : tr('done_pending');
      $('#signupEntry').textContent = [entry.name, entry.code ? '(' + entry.code + ')' : ''].filter(Boolean).join(' ');
    }
  } catch (err) { /* ignore */ }

  // light polling: keeps the counter fresh and notices a closed form
  async function poll() {
    try {
      const r = await api('signup', { code: signup.id });
      serverOffset = r.server_time - Date.now();
      signup = r.signup; renderMeta();
      if (!signup.open) showClosed();
    } catch (err) {
      if (err.code === 'not_found') { signup.expires_at = 0; showClosed(); }
    }
  }
  setInterval(poll, 15000);
  setInterval(checkExpiry, 1000);
  (async () => { try { const info = await api('info'); serverOffset = info.server_time - Date.now(); } catch (e) { /* ignore */ } checkExpiry(); })();
})();
