(function () {
  function addParticipantRow() {
    const wrap = document.getElementById('participants');
    const options = document.getElementById('auditor_options').innerHTML;

    const row = document.createElement('div');
    row.className = 'row';
    row.innerHTML = `
      <div class="col">
        <label>Auditor</label>
        <select name="auditors[]">${options}</select>
      </div>
      <div class="col">
        <label>Role (optional)</label>
        <input type="text" name="roles[]" placeholder="e.g., Project Manager"/>
      </div>`;
    wrap.appendChild(row);
  }

  document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('add_participant');
    if (btn) btn.addEventListener('click', addParticipantRow);
  });
})();
