// Filename: collide-audio-library.js
// Revision : 1.0.0
// Description : Collide-only admin picker for assigning existing uploaded walk-up audio files.
// Author : Jason Lamb (with help from ChatGPT)
// Created Date : 2026-09-14
// Modified Date : 2026-09-14
// Changelog :
// 1.0.0 Add reusable uploaded-song picker to each Collide team admin card

const collideAudioLibrary = {
  files: null,
  loading: null,
  queued: false
};

function collideAudioEscapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function collideAudioFileSize(bytes) {
  const size = Number(bytes || 0);
  if (!Number.isFinite(size) || size <= 0) return '';
  if (size < 1024 * 1024) return `${Math.max(1, Math.round(size / 1024))} KB`;
  return `${(size / 1024 / 1024).toFixed(1)} MB`;
}

function collideAudioFileLabel(file) {
  const name = String(file?.label || file?.file || '').trim();
  const size = collideAudioFileSize(file?.size_bytes);
  return size ? `${name} (${size})` : name;
}

async function collideFetchAudioLibrary() {
  if (Array.isArray(collideAudioLibrary.files)) {
    return collideAudioLibrary.files;
  }

  if (collideAudioLibrary.loading) {
    return collideAudioLibrary.loading;
  }

  collideAudioLibrary.loading = fetch('team-meta.php?action=audio-library', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: '{}',
    cache: 'no-store'
  })
    .then(async (response) => {
      const payload = await response.json().catch(() => ({}));
      if (!response.ok) {
        throw new Error(payload.error || 'Unable to load uploaded walk-up songs.');
      }
      collideAudioLibrary.files = Array.isArray(payload.files) ? payload.files : [];
      return collideAudioLibrary.files;
    })
    .finally(() => {
      collideAudioLibrary.loading = null;
    });

  return collideAudioLibrary.loading;
}

function collideRenderAudioPicker(select, files) {
  if (!select) return;

  if (!Array.isArray(files) || files.length === 0) {
    select.innerHTML = '<option value="">No uploaded songs available yet</option>';
    select.disabled = true;
    return;
  }

  select.disabled = false;
  select.innerHTML = [
    '<option value="">No change / keep current song</option>',
    ...files.map((file) => {
      const name = String(file?.file || '').trim();
      if (name === '') return '';
      return `<option value="${collideAudioEscapeHtml(name)}">${collideAudioEscapeHtml(collideAudioFileLabel(file))}</option>`;
    })
  ].join('');
}

async function collideHydrateAudioPicker(select) {
  if (!select || select.dataset.collideAudioHydrated === 'true') return;
  select.dataset.collideAudioHydrated = 'true';

  try {
    const files = await collideFetchAudioLibrary();
    collideRenderAudioPicker(select, files);
  } catch (error) {
    select.innerHTML = `<option value="">${collideAudioEscapeHtml(error.message)}</option>`;
    select.disabled = true;
  }
}

function collideInsertAudioPickers() {
  document.querySelectorAll('.collide-meta-form').forEach((form) => {
    if (form.querySelector('[data-collide-existing-song-picker]')) return;

    const fileInput = form.querySelector('input[type="file"][name="walkup_audio"]');
    const fileField = fileInput?.closest('.collide-meta-field');
    if (!fileField) return;

    const picker = document.createElement('label');
    picker.className = 'collide-meta-field collide-existing-song-field';
    picker.innerHTML = `
      <span>Existing uploaded song</span>
      <select name="existing_walkup_file" data-collide-existing-song-picker disabled>
        <option value="">Loading uploaded songs...</option>
      </select>
      <small class="collide-meta-help">Choose one already uploaded, or upload a new file below.</small>
    `;

    fileField.insertAdjacentElement('beforebegin', picker);
    collideHydrateAudioPicker(picker.querySelector('select'));
  });
}

function collideScheduleAudioPickerInsert() {
  if (collideAudioLibrary.queued) return;
  collideAudioLibrary.queued = true;

  window.requestAnimationFrame(() => {
    collideAudioLibrary.queued = false;
    collideInsertAudioPickers();
  });
}

document.addEventListener('submit', (event) => {
  if (!event.target.closest('.collide-meta-form')) return;

  // A save may upload a new reusable audio file, so force the next rendered
  // picker to reload the file list from the server.
  collideAudioLibrary.files = null;
  collideAudioLibrary.loading = null;
}, true);

const collideAudioLibraryObserver = new MutationObserver(collideScheduleAudioPickerInsert);
collideAudioLibraryObserver.observe(document.querySelector('#app') || document.body, {
  childList: true,
  subtree: true
});

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', collideScheduleAudioPickerInsert, { once: true });
} else {
  collideScheduleAudioPickerInsert();
}
