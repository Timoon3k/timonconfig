/* Keep selected private files in memory during verification failures; access stays server-side. */
(() => {
  'use strict';
  if (!window.fetch || !window.FormData) return;
  const deadlines = new WeakMap();
  document.querySelectorAll('form.tc-owner-form[data-wait-seconds]').forEach(form => {
    const seconds = Math.max(0, Math.min(10, Number(form.dataset.waitSeconds) || 0));
    deadlines.set(form, performance.now() + seconds * 1000);
  });
  let pending = null;
  const redirect = value => {
    if (typeof value !== 'string') return false;
    const destination = new URL(value, location.href);
    if (destination.origin !== location.origin) return false;
    location.assign(destination.href);
    return true;
  };
  document.addEventListener('submit', async event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !deadlines.has(form)) return;
    event.preventDefault();
    if (pending) return;
    pending = form;
    const submitter = event.submitter;
    const controls = [...document.querySelectorAll('.tc-owner-form button[type="submit"]')];
    const previous = controls.map(button => button.disabled);
    controls.forEach(button => { button.disabled = true; });
    form.setAttribute('aria-busy', 'true');
    let status = form.querySelector('[data-owner-result]');
    if (!status) {
      status = document.createElement('p');
      status.dataset.ownerResult = '';
      form.append(status);
    }
    status.setAttribute('role', 'status');
    status.className = 'tc-owner-upload-hint';
    try {
      const remaining = deadlines.get(form) - performance.now();
      if (remaining > 0) {
        const update = () => { status.textContent = `Checking access… submitting in ${Math.max(1, Math.ceil((deadlines.get(form) - performance.now()) / 1000))}s`; };
        update();
        const interval = setInterval(update, 500);
        try { await new Promise(resolve => setTimeout(resolve, remaining + 100)); }
        finally { clearInterval(interval); }
      }
      if (!form.isConnected) return;
      const data = new FormData(form);
      // Disabled submit buttons are omitted by FormData, but publish/draft is a deliberate choice.
      if (submitter && submitter.name) data.append(submitter.name, submitter.value);
      status.textContent = 'Checking access and saving configuration…';
      // A hidden name=action field shadows HTMLFormElement.action; read the attribute.
      const endpoint = new URL(form.getAttribute('action') || location.href, location.href);
      if (endpoint.origin !== location.origin) throw new Error('Invalid form endpoint');
      const response = await fetch(endpoint.href, {
        method: 'POST', body: data, credentials: 'same-origin', cache: 'no-store',
        headers: { Accept: 'application/json' }
      });
      const result = await response.json();
      if (!result || typeof result.success !== 'boolean' || !result.data || typeof result.data !== 'object') throw new Error('Invalid response');
      if (result.success && response.ok && redirect(result.data.redirect)) return;
      // Only a server-confirmed recoverable draft may navigate after an upload error.
      if (!result.success && result.data.code === 'tc_owner_draft_saved' && redirect(result.data.redirect)) return;
      const wait = Math.max(0, Math.min(10, Number(result.data.retry_after) || 0));
      document.querySelectorAll('form.tc-owner-form[data-wait-seconds]').forEach(item => deadlines.set(item, performance.now() + wait * 1000));
      status.className = 'tc-owner-feedback tc-owner-feedback--error';
      status.setAttribute('role', 'alert');
      status.textContent = typeof result.data.message === 'string' ? result.data.message : 'Saving failed. Your entries and selected files are still here.';
      if (wait) status.textContent += ` Your entries and selected files are still here. Wait ${wait} seconds, then try again.`;
    } catch {
      status.className = 'tc-owner-feedback tc-owner-feedback--error';
      status.setAttribute('role', 'alert');
      status.textContent = 'The connection was interrupted. Your entries and selected files are still here. Check Existing configurations before trying again, because the server may have saved your request.';
    } finally {
      controls.forEach((button, index) => { button.disabled = previous[index]; });
      form.removeAttribute('aria-busy');
      pending = null;
    }
  });
})();
