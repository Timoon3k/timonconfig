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

  const pauseOtherVideos = (active) => {
    document.querySelectorAll('[data-video-player]').forEach((other) => { if (other !== active) other.pause(); });
    document.querySelectorAll('[data-youtube-player]').forEach((iframe) => {
      if (iframe !== active && iframe.contentWindow) {
        iframe.contentWindow.postMessage(JSON.stringify({ event: 'command', func: 'pauseVideo', args: [] }), 'https://www.youtube-nocookie.com');
      }
    });
  };
  let modalIndex = 0;
  const openVideoModal = (player, card, trigger, onClose) => {
    if (typeof HTMLDialogElement === 'undefined' || !HTMLDialogElement.prototype.showModal) return false;
    const dialog = document.createElement('dialog');
    dialog.className = 'tc-video-modal';
    const headingId = `tc-video-dialog-${++modalIndex}`;
    dialog.setAttribute('aria-labelledby', headingId);
    const bar = document.createElement('div');
    bar.className = 'tc-video-modal__bar';
    const heading = document.createElement('h2');
    heading.id = headingId;
    heading.textContent = card.dataset.videoTitle || 'Apex Legends video';
    const close = document.createElement('button');
    close.type = 'button';
    close.setAttribute('aria-label', 'Close video');
    close.textContent = '×';
    bar.append(heading, close);
    dialog.append(bar, player);
    document.body.append(dialog);
    close.addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
    dialog.addEventListener('close', () => {
      onClose();
      dialog.remove();
      trigger.focus();
    }, { once: true });
    dialog.showModal();
    close.focus();
    return true;
  };

  document.querySelectorAll('[data-inline-video]').forEach((card) => {
    const player = card.querySelector('video');
    const button = card.querySelector('[data-video-play]');
    if (!button) return;
    if (card.dataset.videoKind === 'youtube') {
      const videoId = card.dataset.youtubeId || '';
      if (!/^[A-Za-z0-9_-]{11}$/.test(videoId)) return;
      button.addEventListener('click', () => {
        const iframe = document.createElement('iframe');
        iframe.title = card.dataset.videoTitle || 'Apex Legends video';
        iframe.dataset.youtubePlayer = '';
        iframe.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
        iframe.allowFullscreen = true;
        iframe.referrerPolicy = 'strict-origin-when-cross-origin';
        iframe.src = `https://www.youtube-nocookie.com/embed/${videoId}?autoplay=1&enablejsapi=1&rel=0`;
        pauseOtherVideos(iframe);
        card.classList.add('is-started');
        button.hidden = true;
        const reset = () => {
          iframe.remove();
          card.classList.remove('is-started');
          button.hidden = false;
        };
        iframe.addEventListener('error', () => {
          reset();
          const label = button.querySelector('.tc-inline-video__label');
          if (label) label.textContent = 'VIDEO UNAVAILABLE · RETRY';
        });
        if (card.dataset.videoMode !== 'modal' || !openVideoModal(iframe, card, button, reset)) {
          card.append(iframe);
          iframe.focus();
        }
      });
      return;
    }
    if (!player) return;
    player.controls = false;
    player.tabIndex = -1;
    button.addEventListener('click', async () => {
      if (!player.getAttribute('src') && player.dataset.videoSrc) {
        player.src = player.dataset.videoSrc;
      }
      player.querySelectorAll('[data-video-track-src]').forEach((track) => {
        if (!track.getAttribute('src')) track.src = track.dataset.videoTrackSrc;
      });
      if (player.error) player.load();
      player.controls = true;
      player.tabIndex = 0;
      card.classList.add('is-started');
      button.hidden = true;
      pauseOtherVideos(player);
      if (card.dataset.videoMode === 'modal') {
        openVideoModal(player, card, button, () => {
          player.pause();
          player.controls = false;
          player.tabIndex = -1;
          card.prepend(player);
          card.classList.remove('is-started');
          button.hidden = false;
        });
      }
      try {
        await player.play();
      } catch {
        // Native controls remain available when autoplay is blocked.
        player.focus();
      }
    });
    player.addEventListener('play', () => pauseOtherVideos(player));
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
    const move = (direction) => track.scrollBy({ left: direction * Math.max(track.clientWidth * 0.82, 280), behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
    previous.addEventListener('click', () => move(-1));
    next.addEventListener('click', () => move(1));
  });
})();
