/* DriveGo London · gallery (photos and videos) */

// One entry per item, shown in this order.
// Photos: `image` is the file in assets/img/gallery/.
// Videos: add `type: 'video'` with `src` (a file uploaded to assets/videos/)
// or `youtube` (a YouTube id), plus `image` as the cover.
const ITEMS = [
  { image: '20.webp', topic: 'Our school', alt: 'The blue DriveGo London training car with a Student Driver sign in downtown London' },
  { image: '37.webp', topic: 'Our school', alt: 'Steven, founder and former DriveTest examiner, next to the training car' },
  { image: '73.webp', topic: 'Our school', alt: 'Announcement: the first Hispanic driving school in London approved by the MTO' },
  { image: '61.webp', topic: 'Our school', alt: 'BDE certified complete course with 30 hours online and 10 hours in the car' },
  { image: '31.webp', topic: 'Our school', alt: 'Certified instructor London badge' },
  { image: '45.webp', topic: 'Students who passed', alt: 'Student who passed his G2 holding his licence' },
  { image: '49.webp', topic: 'Students who passed', alt: 'Student who passed his G2 next to the training car' },
  { image: '51.webp', topic: 'Students who passed', alt: 'Student who passed her G2 next to the training car' },
  { image: '53.webp', topic: 'Students who passed', alt: 'Student who passed her G2 on the first try' },
  { image: '57.webp', topic: 'Students who passed', alt: 'Student who passed his G2 driving exam in London' },
  { image: '65.webp', topic: 'Students who passed', alt: 'Student who passed her G2 holding a Canadian flag card' },
  { image: '09.webp', topic: 'Students who passed', alt: 'Student holding his licence next to the training car' },
  { image: '23.webp', topic: 'Students who passed', alt: 'Student holding her licence in the driver seat' },
  { image: '25.webp', topic: 'Students who passed', alt: 'Student with his licence beside the Student Driver car' },
  { image: '27.webp', topic: 'Students who passed', alt: 'Student holding her licence outside the DriveTest centre' },
  { image: '29.webp', topic: 'Students who passed', alt: 'Student showing her new licence in a parking lot' },
  { image: '33.webp', topic: 'Students who passed', alt: 'Student holding his licence next to the blue training car' },
  { image: '35.webp', topic: 'Students who passed', alt: 'Student with his licence outside the DriveTest centre' },
  { image: '39.webp', topic: 'Students who passed', alt: 'Student holding her test result outside' },
  { image: '41.webp', topic: 'Students who passed', alt: 'Student smiling with her licence outside the DriveTest centre' },
  { image: '43.webp', topic: 'Students who passed', alt: 'Student holding his licence outside the DriveTest centre' },
  { image: '47.webp', topic: 'Students who passed', alt: 'Student holding his licence in front of the training car' },
  { image: '55.webp', topic: 'Students who passed', alt: 'Student in sunglasses holding her licence beside the training car' },
  { image: '59.webp', topic: 'Students who passed', alt: 'Student holding her new licence outside the DriveTest centre' },
  { image: '63.webp', topic: 'Students who passed', alt: 'Student in a black hoodie holding his new licence' },
  { image: '67.webp', topic: 'Students who passed', alt: 'Student giving a thumbs up with his licence on a snowy day' },
  { image: '69.webp', topic: 'Students who passed', alt: 'Student holding her licence in a parking lot' },
  { image: '71.webp', topic: 'Students who passed', alt: 'Student laughing as she shows her new licence' },
  { image: '75.webp', topic: 'Students who passed', alt: 'Student in sunglasses holding her new licence' },
];

const BASE = 'assets/img/gallery/';

const gallery = document.querySelector('[data-gallery]');
const filters = document.querySelector('[data-filters]');
const empty = document.querySelector('[data-gallery-empty]');
const viewer = document.querySelector('[data-player]');
const viewerFrame = document.querySelector('[data-player-frame]');
const viewerTitle = document.querySelector('[data-player-title]');

const escapeHtml = (text) => String(text).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const isVideo = (item) => item.type === 'video';
const isReady = (item) => !isVideo(item) || Boolean(item.src || item.youtube);

function renderFilters() {
  const topics = ['All', ...new Set(ITEMS.map((item) => item.topic))];
  if (ITEMS.some(isVideo) && !topics.includes('Videos')) topics.push('Videos');
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

function matches(item, topic) {
  if (topic === 'All') return true;
  if (topic === 'Videos') return isVideo(item);
  return item.topic === topic;
}

function renderGallery(topic = 'All') {
  const list = ITEMS.map((item, index) => ({ item, index })).filter(({ item }) => matches(item, topic));
  empty.hidden = list.length > 0;
  gallery.innerHTML = list
    .map(({ item, index }) => {
      const ready = isReady(item);
      const badge = isVideo(item)
        ? `<span class="tile__badge">${ready ? '<i class="ph-fill ph-play" aria-hidden="true"></i>' : 'Coming soon'}</span>`
        : '';
      const label = isVideo(item) ? `Play video: ${item.alt}` : `Open photo: ${item.alt}`;
      const inner = `<img src="${BASE}${escapeHtml(item.image)}" alt="${escapeHtml(item.alt)}" loading="lazy">${badge}`;
      return ready
        ? `<button type="button" class="tile${isVideo(item) ? ' tile--video' : ''}" data-open="${index}" aria-label="${escapeHtml(label)}">${inner}</button>`
        : `<div class="tile tile--video tile--soon">${inner}</div>`;
    })
    .join('');
}

function openViewer(item) {
  viewerTitle.textContent = item.alt;
  if (!isVideo(item)) {
    viewerFrame.className = 'player__frame player__frame--photo';
    viewerFrame.innerHTML = `<img src="${BASE}${escapeHtml(item.image)}" alt="${escapeHtml(item.alt)}">`;
  } else {
    viewerFrame.className = 'player__frame';
    viewerFrame.innerHTML = item.src
      ? `<video src="${escapeHtml(item.src)}" poster="${BASE}${escapeHtml(item.image)}" controls autoplay playsinline></video>`
      : `<iframe src="https://www.youtube-nocookie.com/embed/${encodeURIComponent(item.youtube)}?autoplay=1" title="${escapeHtml(item.alt)}" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen></iframe>`;
  }
  viewer.showModal();
}

if (gallery && filters) {
  renderFilters();
  renderGallery();

  gallery.addEventListener('click', (e) => {
    const tile = e.target.closest('[data-open]');
    if (tile) openViewer(ITEMS[Number(tile.dataset.open)]);
  });

  document.querySelector('[data-player-close]').addEventListener('click', () => viewer.close());
  viewer.addEventListener('click', (e) => {
    if (e.target === viewer) viewer.close();
  });
  // Stop any video when the viewer closes (Escape, button or backdrop)
  viewer.addEventListener('close', () => {
    viewerFrame.innerHTML = '';
  });
}
