/* Drive Go London · video gallery */

// Add one entry per video. Use `src` for a video file uploaded to the site
// (e.g. 'assets/videos/parallel-parking.mp4') or `youtube` for a YouTube id.
// While both are empty the card shows "Coming soon" and does not open.
const VIDEOS = [
  { title: 'G2 test route walkthrough in London', topic: 'Road test tips', poster: 'assets/img/incar.webp', length: '', src: '', youtube: '' },
  { title: 'Parallel parking in five steps', topic: 'Parking', poster: 'assets/img/roadtest.webp', length: '', src: '', youtube: '' },
  { title: 'Merging onto the 401 without stress', topic: 'Highway', poster: 'assets/img/hero-wide.webp', length: '', src: '', youtube: '' },
  { title: 'What examiners check on your full G test', topic: 'Road test tips', poster: 'assets/img/refresher.webp', length: '', src: '', youtube: '' },
  { title: 'Three point turn on a narrow street', topic: 'Parking', poster: 'assets/img/incar.webp', length: '', src: '', youtube: '' },
  { title: 'Student passes G2 on the first try', topic: 'Student wins', poster: 'assets/img/roadtest.webp', length: '', src: '', youtube: '' },
];

const gallery = document.querySelector('[data-gallery]');
const filters = document.querySelector('[data-filters]');
const empty = document.querySelector('[data-gallery-empty]');
const player = document.querySelector('[data-player]');
const playerFrame = document.querySelector('[data-player-frame]');
const playerTitle = document.querySelector('[data-player-title]');

const escapeHtml = (text) => text.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const isReady = (video) => Boolean(video.src || video.youtube);

function renderFilters() {
  const topics = ['All', ...new Set(VIDEOS.map((v) => v.topic))];
  filters.innerHTML = topics
    .map((topic, i) => `<button type="button" role="tab" class="filters__btn" aria-selected="${i === 0}" data-topic-filter="${escapeHtml(topic)}">${escapeHtml(topic)}</button>`)
    .join('');
  filters.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-topic-filter]');
    if (!btn) return;
    filters.querySelectorAll('[data-topic-filter]').forEach((b) => b.setAttribute('aria-selected', String(b === btn)));
    renderGallery(btn.dataset.topicFilter);
  });
}

function renderGallery(topic = 'All') {
  const list = VIDEOS.map((video, index) => ({ video, index })).filter(({ video }) => topic === 'All' || video.topic === topic);
  empty.hidden = list.length > 0;
  gallery.innerHTML = list
    .map(({ video, index }) => {
      const ready = isReady(video);
      const tag = ready ? 'button type="button"' : 'div';
      const close = ready ? 'button' : 'div';
      return `
        <article class="clip${ready ? '' : ' clip--soon'}">
          <${tag} class="clip__media" ${ready ? `data-play="${index}" aria-label="Play ${escapeHtml(video.title)}"` : ''}>
            <img src="${escapeHtml(video.poster)}" alt="" loading="lazy">
            <span class="clip__badge">${ready ? '<i class="ph-fill ph-play" aria-hidden="true"></i>' : 'Coming soon'}</span>
            ${video.length ? `<span class="clip__length">${escapeHtml(video.length)}</span>` : ''}
          </${close}>
          <div class="clip__body">
            <p class="clip__topic">${escapeHtml(video.topic)}</p>
            <h3 class="clip__title">${escapeHtml(video.title)}</h3>
          </div>
        </article>`;
    })
    .join('');
}

function openPlayer(video) {
  playerTitle.textContent = video.title;
  playerFrame.innerHTML = video.src
    ? `<video src="${escapeHtml(video.src)}" poster="${escapeHtml(video.poster)}" controls autoplay playsinline></video>`
    : `<iframe src="https://www.youtube-nocookie.com/embed/${encodeURIComponent(video.youtube)}?autoplay=1" title="${escapeHtml(video.title)}" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen></iframe>`;
  player.showModal();
}

function closePlayer() {
  player.close();
}

if (gallery && filters) {
  renderFilters();
  renderGallery();

  gallery.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-play]');
    if (btn) openPlayer(VIDEOS[Number(btn.dataset.play)]);
  });

  document.querySelector('[data-player-close]').addEventListener('click', closePlayer);
  player.addEventListener('click', (e) => {
    if (e.target === player) closePlayer();
  });
  // Stop playback when the dialog closes (Escape, button or backdrop)
  player.addEventListener('close', () => {
    playerFrame.innerHTML = '';
  });
}
