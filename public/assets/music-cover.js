'use strict';
document.addEventListener('click', event => {
  const button = event.target.closest('[data-audio-url]');
  const cover = document.getElementById('library-preview-cover');
  if (!button || !cover) return;
  const url = new URL(button.dataset.audioUrl, location.origin);
  if (url.origin !== location.origin) return;
  const params = new URLSearchParams({id: url.searchParams.get('id') || '', version: url.searchParams.get('version') || ''});
  cover.src = '/music-library-cover.php?' + params;
});
