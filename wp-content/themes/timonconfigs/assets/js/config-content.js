(() => {
  'use strict';
  const dialog = document.querySelector('#tc-config-content-dialog');
  if (!dialog || typeof dialog.showModal !== 'function') return;
  const title = dialog.querySelector('h2');
  const body = dialog.querySelector('.tc-content-body');
  const status = dialog.querySelector('.tc-content-status');
  const close = dialog.querySelector('.tc-content-close');
  const copy = dialog.querySelector('.tc-content-copy');
  let opener, controller, copyText = '', generation = 0;
  const reset = () => { controller?.abort(); generation++; body.replaceChildren(); copyText = ''; copy.disabled = true; status.textContent = ''; };
  dialog.addEventListener('close', () => { reset(); opener?.focus(); });
  close.addEventListener('click', () => dialog.close());
  dialog.addEventListener('click', event => { if (event.target === dialog) { const rect = dialog.getBoundingClientRect(); if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) dialog.close(); } });
  dialog.addEventListener('keydown', event => {
    if (event.key !== 'Tab') return;
    const focusable = [...dialog.querySelectorAll('button:not(:disabled), a[href], [tabindex="0"]')].filter(el => el.getClientRects().length);
    const first = focusable[0], last = focusable.at(-1);
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
  });
  document.addEventListener('click', async event => {
    const trigger = event.target.closest('[data-tc-content][data-config-id]');
    if (!trigger) return;
    reset(); opener = trigger; controller = new AbortController(); const current = generation;
    title.textContent = trigger.dataset.tcContent === 'guide' ? 'Guide' : 'Values';
    status.textContent = 'Checking access…'; dialog.showModal(); close.focus();
    try {
      const endpoint = new URL(dialog.dataset.endpoint, location.href);
      endpoint.searchParams.set('action', 'tc_content_nonce');
      const nonceResponse = await fetch(endpoint, { credentials: 'same-origin', cache: 'no-store', signal: controller.signal });
      const nonceData = await nonceResponse.json();
      if (!nonceResponse.ok || !nonceData.success || !nonceData.data.nonce) throw new Error('Unable to verify your session. Reload and try again.');
      const response = await fetch(dialog.dataset.endpoint, { method: 'POST', credentials: 'same-origin', cache: 'no-store', signal: controller.signal, body: new URLSearchParams({ action: 'tc_config_content', config_id: trigger.dataset.configId, kind: trigger.dataset.tcContent, _wpnonce: nonceData.data.nonce }) });
      const payload = await response.json();
      if (current !== generation) return;
      if (!response.ok || !payload.success) throw new Error(payload.data?.message || 'This content is not currently available.');
      title.textContent = payload.data.title;
      // HTML comes only from the server's strict guide allowlist or escaped plain values.
      body.innerHTML = payload.data.html;
      body.querySelectorAll('img').forEach(img => { img.loading = 'lazy'; });
      copyText = payload.data.text; copy.disabled = !copyText; status.textContent = '';
    } catch (error) { if (error.name !== 'AbortError' && current === generation) status.textContent = error.message || 'Unable to load content. Please try again.'; }
  });
  copy.addEventListener('click', async () => {
    try { await navigator.clipboard.writeText(copyText); status.textContent = 'Copied to clipboard.'; }
    catch { status.textContent = 'Copy is unavailable. Select the text and copy it manually.'; body.focus(); }
  });
})();
