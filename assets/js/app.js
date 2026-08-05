const noteList = document.getElementById('note-list');
const categoryCloud = document.getElementById('category-cloud');
const themeToggle = document.getElementById('theme-toggle');

const titleField = document.getElementById('title');
const categoryField = document.getElementById('category');
const noteField = document.getElementById('note');
const previewPane = document.getElementById('preview');
const saveButton = document.getElementById('save');
const deleteButton = document.getElementById('delete');
const tabWrite = document.getElementById('tab-write');
const tabPreview = document.getElementById('tab-preview');

const emptyState = document.getElementById('viewer-empty');
const viewerBody = document.getElementById('viewer-body');
const viewerTitle = document.getElementById('viewer-title');
const viewerDates = document.getElementById('viewer-dates');
const viewerContent = document.getElementById('viewer-content');

const isWritePage = !!noteField;
const isViewPage = !!viewerContent;

let allNotes = [];
let activeCategory = null;
let currentNoteId = null;

function applyTheme(theme) {
  document.documentElement.setAttribute('data-theme', theme);
  themeToggle.textContent = theme === 'dark' ? '◑' : '◐';
  localStorage.setItem('lannotate-theme', theme);
  if (theme === 'dark') {
    document.body.style.backgroundColor = '';
  } else if (window.__randomBgColor) {
    document.body.style.backgroundColor = window.__randomBgColor;
  }
}

applyTheme(localStorage.getItem('lannotate-theme') || 'light');
themeToggle.addEventListener('click', () => {
  const next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
  applyTheme(next);
});

async function lanNotate() {
  try {
    const res = await fetch('api.php?action=loadnotes');
    const notes = await res.json();
    allNotes = notes;
    applyCategoryFilter();
  } catch (err) {
    console.error('Failed to load notes:', err);
  }
}

function applyCategoryFilter() {
  const filtered = activeCategory
    ? allNotes.filter(n => n.category === activeCategory)
    : allNotes;
  renderNoteList(filtered);
}

function formatDate(iso) {
  if (!iso) return '';
  const d = new Date(iso);
  return d.toLocaleDateString(undefined, {
    year: 'numeric', month: 'short', day: 'numeric',
    hour: '2-digit', minute: '2-digit'
  });
}

function capitalize(str) {
  return str ? str.charAt(0).toUpperCase() + str.slice(1) : str;
}

function shuffle(arr) {
  const a = arr.slice();
  for (let i = a.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [a[i], a[j]] = [a[j], a[i]];
  }
  return a;
}

function randomColor() {
  const hue = Math.floor(Math.random() * 360);
  return `hsl(${hue}, 70%, 85%)`;
}

async function loadCategoryCloud() {
  try {
    const res = await fetch('api.php?action=loadcategories');
    const categories = await res.json();
    renderCategoryCloud(categories);
  } catch (err) {
    console.error('Failed to load categories:', err);
  }
}

function renderCategoryCloud(categories) {
  categoryCloud.innerHTML = '';
  if (categories.length === 0) {
    categoryCloud.innerHTML = '<li class="empty-nav">No categories yet.</li>';
    return;
  }
  categories.forEach(cat => {
    const li = document.createElement('li');
    const btn = document.createElement('button');
    btn.className = 'category-pill';
    btn.textContent = capitalize(cat);
    if (cat === activeCategory) btn.classList.add('active');
    btn.addEventListener('click', () => {
      activeCategory = (activeCategory === cat) ? null : cat;
      document.querySelectorAll('.category-pill').forEach(b => b.classList.remove('active'));
      if (activeCategory) btn.classList.add('active');
      applyCategoryFilter();
    });
    li.appendChild(btn);
    categoryCloud.appendChild(li);
  });
}

function renderNoteList(notes) {
  noteList.innerHTML = '';
  if (notes.length === 0) {
    noteList.innerHTML = '<li class="empty-nav">No notes yet.</li>';
    return;
  }
  notes.forEach(n => {
    const li = document.createElement('li');
    const btn = document.createElement('button');
    btn.className = 'note-item';
    btn.dataset.id = n.link;
    btn.innerHTML = `
      <span class="note-title">${escapeHtml(n.notename)}</span>
      <span class="note-meta">
        ${n.category ? `<span class="note-category">${escapeHtml(capitalize(n.category))}</span>` : ''}
        <span class="note-date">${formatDate(n.updated)}</span>
      </span>
    `;
    btn.addEventListener('click', () => {
      if (isWritePage) editNote(n.link, btn);
      else if (isViewPage) viewNote(n.link, btn);
    });
    li.appendChild(btn);
    noteList.appendChild(li);
  });
}


async function loadArchive() {

    const res = await fetch('api.php?action=loadnotes');
    const notes = await res.json();
    let listing = document.getElementById('archive-list');
    notes.forEach(n => {
    const li = document.createElement('li');
    const btn = document.createElement('button');
    btn.className = 'note-item';
    btn.dataset.id = n.link;
    btn.innerHTML = `
      <span class="note-title">${escapeHtml(n.notename)}</span>
      <span class="note-meta">
        ${n.category ? `<span class="note-category">${escapeHtml(capitalize(n.category))}</span>` : ''}
        <span class="note-date">${formatDate(n.updated)}</span>
        <span><a href="#" onclick="editNote(${n.link, n.link}); document.getElementById('archive-list').style.display = 'none';">edit</a></span>
        <span><a href="#" onclick="viewNote(${n.link, n.link}); document.getElementById('archive-list').style.display = 'none';">view</a></span>
      </span>
    `;
    li.appendChild(btn);
    listing.appendChild(li);
  });
  listing.style.display = 'block';
}


if (isWritePage) {
  const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

  // Load categories for the dropdown
  (async function loadCategories() {
    try {
      const res = await fetch('api.php?action=loadcategories');
      const categories = await res.json();
      const previousValue = categoryField.value;
      categories.forEach(cat => {
        const opt = document.createElement('option');
        opt.value = cat;
        opt.textContent = capitalize(cat);
        categoryField.appendChild(opt);
      });
      categoryField.value = previousValue;
    } catch (err) {
      console.error('Failed to load categories:', err);
    }
  })();

  window.editNote = async function editNote(noteId, buttonEl) {
    try {
      const res = await fetch('api.php?action=loadnote&id=' + encodeURIComponent(noteId));
      const data = await res.json();
      loadNote(data);
      currentNoteId = noteId;
      document.querySelectorAll('.note-item').forEach(b => b.classList.remove('active'));
      if (buttonEl) buttonEl.classList.add('active');
    } catch (err) {
      console.error('Failed to load note:', err);
    }
  };

  function loadNote(data) {
    titleField.value = data.title || '';
    categoryField.value = data.category || '';
    noteField.value = data.note || '';
    renderPreview();
    showWriteTab();
  }

  saveButton.addEventListener('click', () => {
    saveNote(titleField.value, noteField.value, categoryField.value);
  });

  async function saveNote(title, note, category) {
    try {
      const res = await fetch('api.php?action=savenote', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: currentNoteId, title, note, category, csrf: csrfToken })
      });
      const result = await res.json();
      currentNoteId = result.id;
      lanNotate();
    } catch (err) {
      console.error('Failed to save note:', err);
    }
  }

  deleteButton.addEventListener('click', () => {
    if (!currentNoteId) return; // nothing to delete yet
    if (!confirm('Delete this note? This cannot be undone.')) return;
    deleteNote(currentNoteId);
  });

  async function deleteNote(id) {
    try {
      const res = await fetch('api.php?action=deletenote', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, csrf: csrfToken })
      });
      const result = await res.json();
      if (result.success) {
        currentNoteId = null;
        titleField.value = '';
        categoryField.value = '';
        noteField.value = '';
        renderPreview();
        lanNotate();
      }
    } catch (err) {
      console.error('Failed to delete note:', err);
    }
  }

  function renderPreview() {
    previewPane.innerHTML = markup(noteField.value);
  }

  function showWriteTab() {
    noteField.classList.remove('hidden');
    previewPane.classList.remove('visible');
    tabWrite.classList.add('active');
    tabPreview.classList.remove('active');
  }

  function showPreviewTab() {
    renderPreview();
    noteField.classList.add('hidden');
    previewPane.classList.add('visible');
    tabPreview.classList.add('active');
    tabWrite.classList.remove('active');
  }

  tabWrite.addEventListener('click', showWriteTab);
  tabPreview.addEventListener('click', showPreviewTab);
}

if (isViewPage) {
  window.viewNote = async function viewNote(noteId, buttonEl) {
    try {
      const res = await fetch('api.php?action=loadnote&id=' + encodeURIComponent(noteId));
      const data = await res.json();

      document.querySelectorAll('.note-item').forEach(b => b.classList.remove('active'));
      if (buttonEl) buttonEl.classList.add('active');

      viewerTitle.textContent = data.title || '(untitled)';

      let dateLine = '';
      if (data.category) dateLine += capitalize(data.category);
      if (data.created) dateLine += (dateLine ? '  ·  ' : '') + 'Created ' + formatDate(data.created);
      if (data.updated) dateLine += (dateLine ? '  ·  ' : '') + 'Updated ' + formatDate(data.updated);
      viewerDates.textContent = dateLine;

      viewerContent.innerHTML = markup(data.note);

      emptyState.style.display = 'none';
      viewerBody.style.display = 'block';
    } catch (err) {
      console.error('Failed to load note:', err);
    }
  };
}


const settingsModal = document.getElementById('modal-settings');
const settingsBackdrop = document.getElementById('modal-backdrop');
 
function showSettings() {
  settingsModal.style.display = 'block';
  if (settingsBackdrop) settingsBackdrop.classList.add('visible');
}
 
function hideSettings() {
  settingsModal.style.display = 'none';
  if (settingsBackdrop) settingsBackdrop.classList.remove('visible');
}
 
if (settingsBackdrop) {
  settingsBackdrop.addEventListener('click', hideSettings);
}

function getFontStyleTag() {
  let tag = document.getElementById('user-font-style');
  if (!tag) {
    tag = document.createElement('style');
    tag.id = 'user-font-style';
    document.head.appendChild(tag);
  }
  return tag;
}

function applyFontSettings(font, size) {
  const tag = getFontStyleTag();
  const rules = [];
  if (font) rules.push(`* { font-family: ${font} !important; }`);
  if (size) rules.push(`#viewer-content p, #note { font-size: ${size} !important; }`);
  tag.textContent = rules.join('\n');
}

document.getElementById('save-settings').addEventListener('click', () => {
  const font = document.getElementById('font').value;
  const fontSize = document.getElementById('font-size').value;

  applyFontSettings(font, fontSize);

  localStorage.setItem('font', font);
  localStorage.setItem('font-size', fontSize);

  hideSettings();
});

applyFontSettings(localStorage.getItem('font'), localStorage.getItem('font-size'));

// --- Init ---
lanNotate();
loadCategoryCloud();
