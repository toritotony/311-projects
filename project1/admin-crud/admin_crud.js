function toggleFields() {
    var opSel = document.getElementById('op');
    if (!opSel) return;
    var isDelete = opSel.value === 'delete';

    document.querySelectorAll('.field').forEach(function (el) {
      var isPk = el.getAttribute('data-pk') === '1';
      var show = isDelete ? isPk : true;
      el.style.display = show ? '' : 'none';

      // prevent non-PK values from posting during delete
      var ctl = el.querySelector('input,select,textarea');
      if (ctl) ctl.disabled = !show;
    });

    var submit = document.getElementById('submitBtn');
    if (submit) submit.textContent = isDelete ? 'Delete' : 'Submit';
  }

document.addEventListener('DOMContentLoaded', toggleFields);
