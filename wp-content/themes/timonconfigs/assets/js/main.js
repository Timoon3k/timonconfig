(() => {
  const menuToggle = document.querySelector('[data-menu-toggle]');
  const menu = document.querySelector('[data-menu]');
  if (menuToggle && menu) {
    const setMenu = (open) => {
      menuToggle.setAttribute('aria-expanded', String(open));
      menu.classList.toggle('is-open', open);
    };
    menuToggle.addEventListener('click', () => setMenu(menuToggle.getAttribute('aria-expanded') !== 'true'));
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && menu.classList.contains('is-open')) {
        setMenu(false);
        menuToggle.focus();
      }
    });
    menu.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setMenu(false)));
  }

  const players = document.querySelectorAll('[data-inline-video] video');
  document.querySelectorAll('[data-inline-video]').forEach((card) => {
    const player = card.querySelector('video');
    const button = card.querySelector('[data-video-play]');
    if (!player || !button) return;
    player.controls = false;
    player.tabIndex = -1;
    button.addEventListener('click', async () => {
      if (!player.getAttribute('src') && player.dataset.videoSrc) {
        player.src = player.dataset.videoSrc;
      }
      if (player.error) player.load();
      player.controls = true;
      player.tabIndex = 0;
      card.classList.add('is-started');
      button.hidden = true;
      try {
        await player.play();
      } catch {
        // Native controls remain available when autoplay is blocked.
        player.focus();
      }
    });
    player.addEventListener('play', () => {
      players.forEach((other) => { if (other !== player) other.pause(); });
    });
    player.addEventListener('error', () => {
      button.hidden = false;
      card.classList.remove('is-started');
      const label = button.querySelector('.tc-inline-video__label');
      if (label) label.textContent = 'VIDEO UNAVAILABLE · RETRY';
    });
  });

  document.querySelectorAll('[data-review-slider]').forEach((slider) => {
    const track = slider.querySelector('[data-review-track]');
    const previous = slider.querySelector('[data-review-prev]');
    const next = slider.querySelector('[data-review-next]');
    if (!track || !previous || !next) return;
    const move = (direction) => track.scrollBy({ left: direction * Math.max(track.clientWidth * 0.82, 280), behavior: 'smooth' });
    previous.addEventListener('click', () => move(-1));
    next.addEventListener('click', () => move(1));
  });
})();
