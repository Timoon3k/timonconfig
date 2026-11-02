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
    try {
      const destination = new URL(value, location.href);
      if (destination.origin !== location.origin || destination.username || destination.password) return false;
      location.assign(destination.href);
      return true;
    } catch { return false; }
  };
  const retained = 'Your entries and selected files are still here.';
  const uncertain = 'Check Existing configurations before trying again, because the server may have saved your request.';
  const cooldown = wait => {
    document.querySelectorAll('form.tc-owner-form[data-wait-seconds]').forEach(item => deadlines.set(item, performance.now() + wait * 1000));
  };
  const retryDelay = response => {
    const value = response.headers.get('Retry-After');
    if (!value) return 0;
    const seconds = /^\d+$/.test(value.trim()) ? Number(value) : Math.ceil((Date.parse(value) - Date.now()) / 1000);
    return Number.isFinite(seconds) ? Math.max(0, Math.min(300, seconds)) : 0;
  };
  const transportMessage = response => {
    const code = `HTTP ${response.status}`;
    if (response.status === 413) return `The server rejected this upload because it exceeds its request size limit (${code}). ${retained} Try fewer or smaller files, or ask the administrator to check the hosting upload limits.`;
    if (response.status === 401 || response.status === 403) return `The server refused the save request (${code}). Your session may have expired, or a hosting security rule may have blocked the upload. ${retained} Sign in again in another tab if needed.`;
    if (response.status === 429) return `The server is limiting save requests (${code}). ${retained}`;
    if (response.status === 400) return `The server rejected an incomplete or invalid save request (${code}). ${retained} Ask the administrator to check the upload endpoint and hosting request limits.`;
    if (response.status === 404) return `The save endpoint could not be found (${code}). ${retained} Ask the administrator to check the website installation.`;
    if (response.status >= 500) return `The server could not confirm whether the configuration was saved (${code}). ${retained} ${uncertain}`;
    return `The server returned an unexpected save response (${code}). ${retained} ${uncertain}`;
  };
  const uploadLimitMessage = (form, data) => {
    const limit = key => {
      const value = Number(form.dataset[key]);
      return Number.isFinite(value) && value > 0 ? value : 0;
    };
    const perFile = limit('uploadMaxBytes');
    const post = limit('postMaxBytes');
    const maximumFiles = limit('uploadMaxFiles');
    const sizeLabel = bytes => bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MiB` : `${Math.ceil(bytes / 1024)} KiB`;
    const byteLength = value => new Blob([value]).size;
    let files = 0;
    let bytes = 4096;
    for (const [key, value] of data.entries()) {
      // Only byte lengths are checked. Script/backup contents are never read or decoded.
      bytes += 4096 + byteLength(key);
      if (value instanceof Blob) {
        bytes += value.size;
        if (!value.size && !value.name) continue;
        files++;
        if (perFile && value.size > perFile) return `The selected file "${String(value.name || 'upload').slice(0, 120)}" exceeds the ${sizeLabel(perFile)} per-file upload limit. ${retained} Choose a smaller file or ask the administrator to check the hosting upload limit.`;
      } else {
        bytes += byteLength(String(value));
      }
    }
    if (maximumFiles && files > maximumFiles) return `This request contains ${files} selected files; the server accepts at most ${Math.floor(maximumFiles)} per save. ${retained} Select fewer files and try again.`;
    if (post && bytes > post) return `The selected files and form data exceed the estimated ${sizeLabel(post)} request limit, including upload overhead. ${retained} Try fewer or smaller files, or ask the administrator to check the hosting request limit.`;
    return '';
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
    const showError = message => {
      status.className = 'tc-owner-feedback tc-owner-feedback--error';
      status.setAttribute('role', 'alert');
      status.textContent = message;
    };
    let response = null;
    try {
      const makeData = () => {
        const data = new FormData(form);
        // Disabled submit buttons are omitted, but publish/draft is a deliberate choice.
        if (submitter && submitter.name) data.append(submitter.name, submitter.value);
        return data;
      };
      let data = makeData();
      const tooLarge = uploadLimitMessage(form, data);
      if (tooLarge) { showError(tooLarge); return; }
      const remaining = deadlines.get(form) - performance.now();
      if (remaining > 0) {
        const update = () => { status.textContent = `Checking access… submitting in ${Math.max(1, Math.ceil((deadlines.get(form) - performance.now()) / 1000))}s`; };
        update();
        const interval = setInterval(update, 500);
        try { await new Promise(resolve => setTimeout(resolve, remaining + 100)); }
        finally { clearInterval(interval); }
        // The user may have edited the form during the verification cooldown.
        data = makeData();
        const changedLimit = uploadLimitMessage(form, data);
        if (changedLimit) { showError(changedLimit); return; }
      }
      if (!form.isConnected) return;
      status.textContent = 'Checking access and saving configuration…';
      // A hidden name=action field shadows HTMLFormElement.action; read the attribute.
      const endpoint = new URL(form.getAttribute('action') || location.href, location.href);
      if (endpoint.origin !== location.origin || endpoint.username || endpoint.password) throw new Error('Invalid form endpoint');
      endpoint.searchParams.set('tc_owner_json', '1');
      if (/\/wp-admin\/admin-post\.php$/.test(endpoint.pathname) && data.get('action') === 'tc_owner_config') {
        // Keep legacy dispatch available even if PHP discards an oversized multipart body.
        endpoint.searchParams.set('action', 'tc_owner_config');
      }
      response = await fetch(endpoint.href, {
        method: 'POST', body: data, credentials: 'same-origin', cache: 'no-store',
        headers: { Accept: 'application/json' }
      });
      // HTML login/security/error pages are not network failures and must never be rendered.
      if (response.redirected) {
        showError(`The save request was redirected instead of confirmed (HTTP ${response.status}). ${retained} Your session may have expired. Sign in again in another tab, then check Existing configurations before trying again.`);
        return;
      }
      let result = null;
      try { result = JSON.parse(await response.text()); } catch { /* Handle the HTTP result below. */ }
      if (!result || typeof result.success !== 'boolean' || !result.data || typeof result.data !== 'object' || Array.isArray(result.data)) {
        const wait = response.status === 429 ? retryDelay(response) : 0;
        if (wait) cooldown(wait);
        showError(transportMessage(response) + (wait ? ` Wait ${wait} second${wait === 1 ? '' : 's'} before trying again.` : ''));
        return;
      }
      if (result.success && response.ok && redirect(result.data.redirect)) return;
      // Only a server-confirmed recoverable draft may navigate after an upload error.
      if (!result.success && result.data.code === 'tc_owner_draft_saved' && redirect(result.data.redirect)) return;
      if (result.success) { showError(transportMessage(response)); return; }
      const wait = Math.max(0, Math.min(10, Number(result.data.retry_after) || 0), response.status === 429 ? retryDelay(response) : 0);
      cooldown(wait);
      showError(typeof result.data.message === 'string' && result.data.message.trim() ? result.data.message : transportMessage(response));
      if (wait) status.textContent += ` ${retained} Wait ${wait} second${wait === 1 ? '' : 's'}, then try again.`;
    } catch {
      showError(response ? transportMessage(response) : `The connection was interrupted. ${retained} ${uncertain}`);
    } finally {
      controls.forEach((button, index) => { button.disabled = previous[index]; });
      form.removeAttribute('aria-busy');
      pending = null;
    }
  });
})();
