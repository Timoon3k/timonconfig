(() => {
  'use strict';
  const dialog = document.querySelector('#tc-config-content-dialog');
  if (!dialog || typeof dialog.showModal !== 'function') return;
  const title = dialog.querySelector('h2');
  const body = dialog.querySelector('.tc-content-body');
  const status = dialog.querySelector('.tc-content-status');
  const close = dialog.querySelector('.tc-content-close');
  const copy = dialog.querySelector('.tc-content-copy');
  const discordHosts = new Set(['discord.com', 'www.discord.com', 'ptb.discord.com', 'canary.discord.com', 'discordapp.com', 'www.discordapp.com', 'ptb.discordapp.com', 'canary.discordapp.com', 'discord.gg', 'www.discord.gg']);
  const addressStart = String.raw`(?:https?://|(?:(?:www|ptb|canary)\.)?discord(?:app)?\.com/|(?:www\.)?discord\.gg/)`;
  const discordPattern = new RegExp(`${addressStart}(?:(?![,;]${addressStart})[^\\s<>"'\`\\u2018-\\u201f\\u00ab\\u00bb])+`, 'gi');
  const discordURL = value => {
    try {
      const url = new URL(/^https?:\/\//i.test(value) ? value : `https://${value}`);
      return ['http:', 'https:'].includes(url.protocol) && discordHosts.has(url.hostname) && !url.username && !url.password ? url.href : null;
    } catch { return null; }
  };
  const openDiscord = link => { link.target = '_blank'; link.rel = 'noopener noreferrer'; };
  const linkDiscordText = () => {
    body.querySelectorAll('a[href]').forEach(link => { if (discordURL(link.getAttribute('href'))) openDiscord(link); });
    // Work on text nodes only: preserve safe Guide markup and literal Values/code syntax.
    const walker = document.createTreeWalker(body, NodeFilter.SHOW_TEXT, {
      acceptNode: node => node.parentElement.closest('a, script, style, textarea') ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT,
    });
    const nodes = [];
    while (walker.nextNode()) nodes.push(walker.currentNode);
    for (const node of nodes) {
      const text = node.textContent;
      const fragment = document.createDocumentFragment();
      let cursor = 0;
      for (const match of text.matchAll(discordPattern)) {
        if (match.index && /[\p{L}\p{N}_@./:\\-]/u.test(text[match.index - 1])) continue;
        const candidate = match[0];
        const counts = { '(': 0, ')': 0, '[': 0, ']': 0, '{': 0, '}': 0 };
        for (const character of candidate) { if (Object.hasOwn(counts, character)) counts[character]++; }
        let end = candidate.length;
        // Keep sentence punctuation and unmatched closing brackets outside the destination.
        while (end) {
          const last = candidate[end - 1], opening = { ')': '(', ']': '[', '}': '{' }[last];
          if (/[.,!?;:]/.test(last)) { end--; continue; }
          if (!opening || counts[last] <= counts[opening]) break;
          counts[last]--; end--;
        }
        const label = candidate.slice(0, end);
        const href = discordURL(label);
        if (!href) continue;
        const link = document.createElement('a');
        link.href = href; link.textContent = label; openDiscord(link);
        fragment.append(text.slice(cursor, match.index), link);
        cursor = match.index + label.length;
      }
      if (cursor) { fragment.append(text.slice(cursor)); node.replaceWith(fragment); }
    }
  };
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
      linkDiscordText();
      body.querySelectorAll('img').forEach(img => { img.loading = 'lazy'; });
      copyText = payload.data.text; copy.disabled = !copyText; status.textContent = '';
    } catch (error) { if (error.name !== 'AbortError' && current === generation) status.textContent = error.message || 'Unable to load content. Please try again.'; }
  });
  copy.addEventListener('click', async () => {
    try { await navigator.clipboard.writeText(copyText); status.textContent = 'Copied to clipboard.'; }
    catch { status.textContent = 'Copy is unavailable. Select the text and copy it manually.'; body.focus(); }
  });
})();
