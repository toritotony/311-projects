<?php
/* audit_form.php — Waste Audit entry (self-posting, no sessions/cURL)
 * Uses hum_conn_no_login(); inserts: audit → categories → comments → participants (all in one txn)
 * Changes requested:
 *  - No total_weight field in the form; server computes total from entered category weights
 *  - Dynamic notes (array), only non-empty get saved
 *  - Dynamic auditors (>=1), chosen from auditors table; create participants only
 *  - Toolbar link to CRUD page
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/hum_conn_no_login.php';

/* ====== CONFIG: adjust table names/columns here if your schema differs ====== */
$TBL = [
  'locations'      => 'locations',          // LOCATION_ID, NAME
  'waste'          => 'waste',              // WASTE_ID, CATEGORY, PARENT_WASTE_ID
  'auditors'       => 'auditors',           // AUDITOR_ID, FNAME, LNAME, AFFILIATION
  'audit'          => 'audits',             // AUDIT_ID, LOCATION_ID, AUDITED_AT, NUM_BAGS, CONTAMINATION_FLAG
  'item'           => 'audit_item',         // AUDIT_ITEM_ID, AUDIT_ID, WASTE_ID, ABS_MASS, REL_MASS, REL_VOLUME
  'notes'          => 'notes',              // NOTE_ID, AUDIT_ID, NOTE_TEXT, CREATED_AT
  'participants'   => 'audit_participant',  // AUDIT_ID, AUDITOR_ID, ROLE_LABEL
];


$CRUD_URL = 'https://nrs-projects.humboldt.edu/~aw399/data311-project1-webapp/admin_crud_form/admin_crud.php'; // link to your CRUD page

/* ====== DB helpers ====== */
function db() {
  $c = hum_conn_no_login();
  if (!$c) { $e = oci_error(); die('DB connect failed: '.($e['message']??'unknown')); }
  return $c;
}
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function qall($conn, $sql, $bind = []) {
  $st = oci_parse($conn, $sql);
  foreach ($bind as $k=>$v) oci_bind_by_name($st, $k, $bind[$k]);
  if (!oci_execute($st, OCI_DEFAULT)) { $e = oci_error($st); throw new RuntimeException($e['message']); }
  $rows = [];
  while ($r = oci_fetch_assoc($st)) $rows[] = $r;
  oci_free_statement($st);
  return $rows;
}

function gen_id($len = 6) {
  $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
  $out = '';
  for ($i=0; $i<$len; $i++) $out .= $chars[random_int(0, strlen($chars)-1)];
  return $out;
}

/* ====== Load reference data for GET & sticky POST ====== */
$conn = db();
$locations = qall($conn, "SELECT location_id, name FROM {$TBL['locations']} ORDER BY name");
$wasteCats = qall($conn, "SELECT waste_id, category FROM {$TBL['waste']} WHERE parent_waste_id IS NULL ORDER BY category");
// If you want ALL categories (not just roots), use: WHERE 1=1
$auditorOpts = qall(
  $conn,
  "SELECT auditor_id,
          TRIM(COALESCE(fname,'') || ' ' || COALESCE(lname,'')) AS fullname
   FROM {$TBL['auditors']}
   ORDER BY fname, lname"
);

oci_close($conn);

/* ====== POST handler ====== */
$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $conn = db();
  try {
    // Required core fields
    $location_id  = $_POST['location_id'] ?? null;
    $audit_date   = $_POST['audit_date']  ?? null; // YYYY-MM-DD
    $audit_time   = $_POST['audit_time']  ?? null; // HH:MM
    $bags         = $_POST['bags_audited'] ?? null;

    $contam = !empty($_POST['contamination_flag']) ? 1 : 0;

    // compute total weight from category weights
    $cat_name   = $_POST['cat_name']   ?? [];
    $cat_weight = $_POST['cat_weight'] ?? [];
    $cat_volume = $_POST['cat_volume'] ?? [];

    $total_weight = 0.0;
    foreach ($cat_weight as $wid => $wstr) {
      $w = trim($wstr);
      if ($w === '') continue;
      $total_weight += (float)$w;
    }

    // date+time -> one Oracle DATE (audited_at)
    $dt = trim(($audit_date ?? '').' '.($audit_time ?? '')); // "YYYY-MM-DD HH:MM"

    if (!$location_id) throw new RuntimeException('Please select a location.');
    if (!$audit_date)  throw new RuntimeException('Please provide a date.');
    if (!$audit_time)  throw new RuntimeException('Please provide a time.');

    // Dynamic notes (array of strings)
    $notes = $_POST['note_text'] ?? []; // e.g., note_text[0], note_text[1], ...

    // Dynamic auditors (array of auditor_id); require >=1 valid selection
    $auditors_selected = array_filter(array_map('trim', $_POST['auditor_id'] ?? []), fn($v)=>$v !== '');
    if (count($auditors_selected) < 1) {
      throw new RuntimeException('Please select at least one auditor.');
    }

    // Insert audit (RETURNING AUDIT_ID)
    $audit_id = gen_id(6);
    $sqlAudit = "INSERT INTO {$TBL['audit']}
                (audit_id, location_id, audited_at, num_bags, contamination_flag)
                VALUES (:p_aid, :p_loc, TO_DATE(:p_dt,'YYYY-MM-DD HH24:MI'), :p_bags, :p_cf)";
    $st = oci_parse($conn, $sqlAudit);
    oci_bind_by_name($st, ':p_aid',  $audit_id);
    oci_bind_by_name($st, ':p_loc',  $location_id);
    $dt = trim(($audit_date ?? '').' '.($audit_time ?? ''));
    oci_bind_by_name($st, ':p_dt',   $dt);
    $bags_i = ($bags === '' ? null : (int)$bags);
    oci_bind_by_name($st, ':p_bags', $bags_i);
    $contam = !empty($_POST['contamination_flag']) ? 1 : 0;
    oci_bind_by_name($st, ':p_cf',   $contam);

    if (!oci_execute($st, OCI_NO_AUTO_COMMIT)) { $e = oci_error($st); throw new RuntimeException($e['message']); }
    oci_free_statement($st);

    // Insert line items (skip rows with no weight and no volume)
    // compute $total_weight once before this block
    $total_weight = 0.0;
    foreach (($_POST['cat_weight'] ?? []) as $wstr) {
      $w = trim((string)$wstr);
      if ($w !== '') $total_weight += (float)$w;
    }

    $sti = oci_parse($conn,
      "INSERT INTO {$TBL['item']}
      (audit_item_id, audit_id, waste_id, abs_mass, rel_mass, rel_volume)
      VALUES (:p_iid, :p_aid, :p_wid, :p_abs, :p_rmass, :p_rvol)"
    );

    foreach (($_POST['cat_name'] ?? []) as $wid => $label) {
      $w = trim($_POST['cat_weight'][$wid] ?? '');
      $v = trim($_POST['cat_volume'][$wid] ?? '');
      if ($w === '' && $v === '') continue;

      $abs   = ($w === '' ? null : (float)$w);
      $rvol  = ($v === '' ? null : (float)$v);
      $rmass = (is_null($abs) || $total_weight <= 0) ? null : round(($abs / $total_weight) * 100, 2);

      $iid = gen_id(8);

      oci_bind_by_name($sti, ':p_iid',   $iid);
      oci_bind_by_name($sti, ':p_aid',   $audit_id);
      oci_bind_by_name($sti, ':p_wid',   $wid);
      oci_bind_by_name($sti, ':p_abs',   $abs);
      oci_bind_by_name($sti, ':p_rmass', $rmass);
      oci_bind_by_name($sti, ':p_rvol',  $rvol);

      if (!oci_execute($sti, OCI_NO_AUTO_COMMIT)) { $e = oci_error($sti); throw new RuntimeException($e['message']); }
    }
    oci_free_statement($sti);

    // Insert notes (only non-empty) -> store as 'Other' comment type, into comments + comments_other
    $notes = $_POST['note_text'] ?? [];
    foreach ($notes as $txt) {
      $txt = trim($txt ?? '');
      if ($txt === '') continue;

      $nid = gen_id(6);
      $stn = oci_parse($conn,
        "INSERT INTO {$TBL['notes']}
        (note_id, audit_id, note_text, created_at)
        VALUES (:nid, :aid, :txt, SYSDATE)"
      );
      oci_bind_by_name($stn, ':nid', $nid);
      oci_bind_by_name($stn, ':aid', $audit_id);
      oci_bind_by_name($stn, ':txt', $txt);
      if (!oci_execute($stn, OCI_NO_AUTO_COMMIT)) { $e = oci_error($stn); throw new RuntimeException($e['message']); }
      oci_free_statement($stn);
    }


    // Insert participants (selected auditors)
    $stp = oci_parse($conn,
      "INSERT INTO {$TBL['participants']}
      (audit_id, auditor_id, role_label)
      VALUES (:p_aid, :p_uid, :p_rlabel)"
    );
    $rlabel = 'Auditor';

    foreach (array_filter(array_map('trim', $_POST['auditor_id'] ?? []), fn($v)=>$v!=='') as $aud_id) {
      oci_bind_by_name($stp, ':p_aid',    $audit_id);
      oci_bind_by_name($stp, ':p_uid',    $aud_id);
      oci_bind_by_name($stp, ':p_rlabel', $rlabel);

      if (!oci_execute($stp, OCI_NO_AUTO_COMMIT)) { $e = oci_error($stp); throw new RuntimeException($e['message']); }
    }
    oci_free_statement($stp);

    oci_commit($conn);
    oci_close($conn);
    $msg = 'Saved audit #'.h($audit_id).' successfully.';
    $_POST = []; // clear form

  } catch (Throwable $t) {
    if ($conn) @oci_rollback($conn);
    if ($conn) @oci_close($conn);
    $err = $t->getMessage();
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Waste Audit Entry</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="audit_form.css">
</head>
<body>
<div class="container">
  <h1>Waste Audit Entry</h1>

  <div class="toolbar">
    <a class="btn btn-accent" href="<?=h($CRUD_URL)?>">⚙️ Open CRUD (manage locations / auditors / categories)</a>
  </div>

  <?php if($msg): ?><div class="alert ok"><?=h($msg)?></div><?php endif; ?>
  <?php if($err): ?><div class="alert bad"><?=h($err)?></div><?php endif; ?>

  <form method="post" action="<?=h($_SERVER['PHP_SELF'])?>" novalidate>
    <fieldset class="card">
      <legend>Audit Details</legend>

      <div class="row">
        <div class="col">
          <label for="location_id">Location *</label>
          <select id="location_id" name="location_id" required>
            <option value="">-- Select --</option>
            <?php foreach ($locations as $loc): ?>
              <option value="<?=h($loc['LOCATION_ID'])?>" <?=(!empty($_POST['location_id']) && $_POST['location_id']==$loc['LOCATION_ID'])?'selected':''?>>
                <?=h($loc['NAME'])?> (<?=h($loc['LOCATION_ID'])?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col">
          <label for="audit_date">Date *</label>
          <input type="date" id="audit_date" name="audit_date" value="<?=h($_POST['audit_date'] ?? '')?>" required>
        </div>
        <div class="col">
          <label for="audit_time">Time *</label>
          <input type="time" id="audit_time" name="audit_time" value="<?=h($_POST['audit_time'] ?? '')?>" required>
        </div>
        <div class="col">
          <label for="bags_audited">Number of bags</label>
          <input type="number" id="bags_audited" name="bags_audited" value="<?=h($_POST['bags_audited'] ?? '')?>">
        </div>
        <div class="col chk">
          <input type="checkbox" id="contamination_flag" name="contamination_flag"
                <?= !empty($_POST['contamination_flag']) ? 'checked' : '' ?>>
          <label for="contamination_flag" style="margin:0">Contamination observed?</label>
        </div>
      </div>
    </fieldset>

    <fieldset class="card">
      <legend>Categories</legend>
      <p class="hint">Enter weight (lbs) and/or volume (%) per category. Total weight is calculated automatically from the weights you enter.</p>
      <?php foreach ($wasteCats as $w): $wid=$w['WASTE_ID']; $label=$w['CATEGORY']; ?>
        <div class="row">
          <div class="col">
            <label><?=h($label)?></label>
            <input type="hidden" name="cat_name[<?=h($wid)?>]" value="<?=h($label)?>">
          </div>
          <div class="col">
            <label for="cw_<?=$wid?>">Weight (lbs)</label>
            <input type="number" step="0.01" id="cw_<?=$wid?>" name="cat_weight[<?=h($wid)?>]" value="<?=h($_POST['cat_weight'][$wid] ?? '')?>">
          </div>
          <div class="col">
            <label for="cv_<?=$wid?>">Volume (%)</label>
            <input type="number" step="0.01" id="cv_<?=$wid?>" name="cat_volume[<?=h($wid)?>]" value="<?=h($_POST['cat_volume'][$wid] ?? '')?>">
          </div>
        </div>
      <?php endforeach; ?>
    </fieldset>

    <fieldset class="card" id="notesFieldset">
      <legend>Notes</legend>
      <div id="notesList">
        <?php
        $notesSticky = $_POST['note_text'] ?? ['']; // start with 1 blank
        foreach ($notesSticky as $i => $val): ?>
          <div class="row note-row">
            <div class="col">
              <label for="note_<?=$i?>">Note <?=($i+1)?></label>
              <textarea id="note_<?=$i?>" name="note_text[]" rows="2"><?=h($val)?></textarea>
            </div>
            <div class="col" style="align-self:end">
              <button type="button" class="btn" data-action="remove-note">Remove</button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="btn" id="addNoteBtn">+ Add note</button>
      <p class="hint">Only non-empty notes are saved.</p>
    </fieldset>

    <fieldset class="card" id="auditorsFieldset">
      <legend>Auditors *</legend>
      <div id="auditorsList">
        <?php
        // sticky selections (ensure at least one control)
        $audSticky = $_POST['auditor_id'] ?? [''];
        foreach ($audSticky as $i => $sel): ?>
          <div class="row auditor-row">
            <div class="col">
              <label for="aud_<?=$i?>">Auditor <?=($i+1)?></label>
              <select id="aud_<?=$i?>" name="auditor_id[]" required>
                <option value="">-- Select auditor --</option>
                <?php foreach ($auditorOpts as $ao):
                  $id = $ao['AUDITOR_ID'];
                  $label = trim($ao['FULLNAME'] ?? '');
                ?>
                  <option value="<?=h($id)?>" <?=($sel!=='' && $sel==$id)?'selected':''?>><?=h($label)?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col" style="align-self:end">
              <button type="button" class="btn" data-action="remove-auditor">Remove</button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="btn" id="addAuditorBtn">+ Add auditor</button>
      <p class="hint">Choose at least one auditor. Add more if multiple people participated.</p>
    </fieldset>

    <div class="actions">
      <button type="submit" class="primary">Save Audit</button>
      <button type="reset">Clear</button>
    </div>
  </form>
</div>

<script src="audit_form.js" defer></script>
</body>
</html>
