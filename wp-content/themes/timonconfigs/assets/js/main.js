(() => {
  const menuToggle = document.querySelector('[data-menu-toggle]');
  const menu = document.querySelector('[data-menu]');

  if (menuToggle && menu) {
    menuToggle.addEventListener('click', () => {
      const expanded = menuToggle.getAttribute('aria-expanded') === 'true';
      menuToggle.setAttribute('aria-expanded', String(!expanded));
      menu.classList.toggle('is-open', !expanded);
    });
  }

  const modal = document.querySelector('[data-video-modal]');
  const player = modal?.querySelector('[data-video-player]');
  const title = modal?.querySelector('[data-video-modal-title]');
  const close = modal?.querySelector('[data-video-close]');
  let trigger = null;

  const closeVideo = () => {
    if (!modal || !player) return;
    player.pause();
    player.removeAttribute('src');
    player.removeAttribute('poster');
    player.load();
    modal.close();
    trigger?.focus();
  };

  document.querySelectorAll('[data-video-open]').forEach((button) => {
    button.addEventListener('click', () => {
      if (!modal || !player || !button.dataset.videoSrc) return;
      trigger = button;
      player.src = button.dataset.videoSrc;
      player.poster = button.dataset.videoPoster || '';
      if (title) title.textContent = button.dataset.videoTitle || 'Apex Legends demo';
      modal.showModal();
      player.play().catch(() => {});
    });
  });

  close?.addEventListener('click', closeVideo);
  modal?.addEventListener('cancel', (event) => {
    event.preventDefault();
    closeVideo();
  });
  modal?.addEventListener('click', (event) => {
    if (event.target === modal) closeVideo();
  });
})();

