function toggleFieldsets() {
  const op = document.getElementById('op').value;
  const entity = document.getElementById('entity').value;

  const blocks = document.querySelectorAll('[data-block]');
  blocks.forEach(b => b.style.display = 'none');

  if (op === 'create') {
    const el = document.querySelector(`[data-block="${entity}-all"]`);
    if (el) el.style.display = 'block';
  } else if (op === 'update') {
    const pk = document.querySelector(`[data-block="${entity}-pk"]`);
    const editable = document.querySelector(`[data-block="${entity}-editable"]`);
    if (pk) pk.style.display = 'block';
    if (editable) editable.style.display = 'block';
  } else if (op === 'delete') {
    const pk = document.querySelector(`[data-block="${entity}-pk"]`);
    if (pk) pk.style.display = 'block';
  } else if (op === 'read') {
    const pkOpt = document.querySelector(`[data-block="${entity}-pk-optional"]`);
    if (pkOpt) pkOpt.style.display = 'block';
  }
}

document.addEventListener('DOMContentLoaded', toggleFieldsets);
