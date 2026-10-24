/* Smooth server role-verification cooldown; authorization remains entirely server-side. */
(() => {
  'use strict';
  const deadlines = new WeakMap();
  document.querySelectorAll('form.tc-owner-form[data-wait-seconds]').forEach(form => {
    const seconds = Math.max(0, Math.min(10, Number(form.dataset.waitSeconds) || 0));
    deadlines.set(form, performance.now() + seconds * 1000);
  });
  let pending = null;
  document.addEventListener('submit', event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !deadlines.has(form)) return;
    if (pending) { event.preventDefault(); return; }
    const remaining = deadlines.get(form) - performance.now();
    if (remaining <= 0) return;
    event.preventDefault();
    pending = form;
    const submitter = event.submitter;
    const controls = [...document.querySelectorAll('.tc-owner-form button[type="submit"]')];
    const previous = controls.map(button => button.disabled);
    controls.forEach(button => { button.disabled = true; });
    form.setAttribute('aria-busy', 'true');
    const status = document.createElement('p');
    status.setAttribute('role', 'status');
    status.className = 'tc-owner-upload-hint';
    form.append(status);
    const update = () => { status.textContent = `Checking access… submitting in ${Math.max(1, Math.ceil((deadlines.get(form) - performance.now()) / 1000))}s`; };
    update();
    const interval = setInterval(update, 500);
    setTimeout(() => {
      clearInterval(interval);
      controls.forEach((button, index) => { button.disabled = previous[index]; });
      status.remove(); form.removeAttribute('aria-busy'); pending = null;
      if (!form.isConnected) return;
      if (submitter instanceof HTMLButtonElement || submitter instanceof HTMLInputElement) form.requestSubmit(submitter);
      else form.requestSubmit();
    }, remaining + 100);
  });
})();
