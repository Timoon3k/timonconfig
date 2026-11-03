/* A static illustration remains useful when motion or JavaScript is unavailable. */
(() => {
  'use strict';
  const reduced = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
  document.querySelectorAll('[data-hero-atmosphere]').forEach(hero => {
    const button = hero.querySelector('[data-hero-motion-toggle]');
    if (!button || hero.dataset.motionEnabled !== '1') return;
    const label = button.querySelector('[data-motion-label]');
    let pausedByVisitor = false;
    let visible = true;
    const update = () => {
      const prefersStill = Boolean(reduced && reduced.matches);
      const paused = pausedByVisitor || prefersStill || document.hidden || !visible;
      hero.dataset.motionState = paused ? 'paused' : 'running';
      button.hidden = prefersStill;
      button.setAttribute('aria-pressed', String(paused));
      button.setAttribute('aria-label', paused ? 'Play background animation' : 'Pause background animation');
      if (label) label.textContent = paused ? 'Play animation' : 'Pause animation';
    };
    button.addEventListener('click', () => { pausedByVisitor = !pausedByVisitor; update(); });
    document.addEventListener('visibilitychange', update);
    if (reduced) {
      if (reduced.addEventListener) reduced.addEventListener('change', update);
      else if (reduced.addListener) reduced.addListener(update);
    }
    if ('IntersectionObserver' in window) {
      const observer = new IntersectionObserver(entries => {
        visible = entries.some(entry => entry.isIntersecting);
        update();
      }, { threshold: 0 });
      observer.observe(hero);
    }
    update();
  });
})();
