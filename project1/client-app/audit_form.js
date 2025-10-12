document.addEventListener('DOMContentLoaded', () => {
  /* -------- Notes (dynamic) -------- */
  const notesList = document.getElementById('notesList');
  const addNoteBtn = document.getElementById('addNoteBtn');

  function addNote(initialText = '') {
    const idx = notesList.querySelectorAll('.note-row').length;
    const row = document.createElement('div');
    row.className = 'row note-row';
    row.innerHTML = `
      <div class="col">
        <label for="note_${idx}">Note ${idx + 1}</label>
        <textarea id="note_${idx}" name="note_text[]" rows="2">${initialText}</textarea>
      </div>
      <div class="col" style="align-self:end">
        <button type="button" class="btn" data-action="remove-note">Remove</button>
      </div>
    `;
    notesList.appendChild(row);
  }

  addNoteBtn?.addEventListener('click', () => addNote());

  notesList?.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-action="remove-note"]');
    if (!btn) return;
    const row = btn.closest('.note-row');
    if (!row) return;
    // keep at least one note row
    if (notesList.querySelectorAll('.note-row').length > 1) {
      row.remove();
      // re-label remaining
      [...notesList.querySelectorAll('.note-row')].forEach((r, i) => {
        const lab = r.querySelector('label');
        const area = r.querySelector('textarea');
        if (lab) lab.textContent = `Note ${i + 1}`;
        if (area) area.id = `note_${i}`;
      });
    }
  });

  /* -------- Auditors (dynamic, >=1) -------- */
  const auditorsList = document.getElementById('auditorsList');
  const addAudBtn = document.getElementById('addAuditorBtn');

  function templateAuditorSelect(idx) {
    // clone the first select’s options to keep them in sync
    const first = auditorsList.querySelector('select');
    const opts = first ? first.innerHTML : '<option value="">-- Select auditor --</option>';
    return `
      <div class="row auditor-row">
        <div class="col">
          <label for="aud_${idx}">Auditor ${idx + 1}</label>
          <select id="aud_${idx}" name="auditor_id[]" required>
            ${opts}
          </select>
        </div>
        <div class="col" style="align-self:end">
          <button type="button" class="btn" data-action="remove-auditor">Remove</button>
        </div>
      </div>
    `;
  }

  function addAuditor() {
    const idx = auditorsList.querySelectorAll('.auditor-row').length;
    const wrap = document.createElement('div');
    wrap.innerHTML = templateAuditorSelect(idx);
    auditorsList.appendChild(wrap.firstElementChild);
  }

  addAudBtn?.addEventListener('click', addAuditor);

  auditorsList?.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-action="remove-auditor"]');
    if (!btn) return;
    const rows = auditorsList.querySelectorAll('.auditor-row');
    if (rows.length <= 1) return; // keep at least one
    btn.closest('.auditor-row')?.remove();
    // re-label remaining
    [...auditorsList.querySelectorAll('.auditor-row')].forEach((r, i) => {
      const lab = r.querySelector('label');
      const sel = r.querySelector('select');
      if (lab) lab.textContent = `Auditor ${i + 1}`;
      if (sel) sel.id = `aud_${i}`;
    });
  });
});
