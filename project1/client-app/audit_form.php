<?php
// audit_form.php — Create ONE audit + items + participants + notes (postback)

session_start();
header('Content-Type: text/html; charset=utf-8');

// ===== DB CONFIG =====
$DB_USER = getenv('DB_USER') ?: 'YOUR_USER';
$DB_PASS = getenv('DB_PASS') ?: 'YOUR_PASSWORD';
$DB_CONN = getenv('DB_CONN') ?: 'HOST:1521/ORCLPDB1';

// ===== CSRF =====
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf'];

// ===== Helpers =====
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function db_connect($u,$p,$c){
  $conn = @oci_connect($u,$p,$c,'AL32UTF8');
  if(!$conn){ $e=oci_error(); die("<pre>DB connect failed: ".h($e['message']??'unknown')."</pre>"); }
  return $conn;
}
function qall($c,$sql,$binds=[]){
  $s=oci_parse($c,$sql); if(!$s){$e=oci_error($c); throw new Exception($e['message']);}
  foreach($binds as $k=>&$v){ oci_bind_by_name($s,$k,$v); }
  if(!oci_execute($s)){ $e=oci_error($s); throw new Exception($e['message']);}
  $rows=[]; oci_fetch_all($s,$rows,0,-1,OCI_FETCHSTATEMENT_BY_ROW+OCI_ASSOC);
  oci_free_statement($s); return $rows;
}
function qexec($c,$sql,$binds=[]){
  $s=oci_parse($c,$sql); if(!$s){$e=oci_error($c); throw new Exception($e['message']);}
  foreach($binds as $k=>&$v){
    if($v===null) oci_bind_by_name($s,$k,$v,-1,SQLT_CHR); else oci_bind_by_name($s,$k,$v);
  }
  if(!oci_execute($s, OCI_NO_AUTO_COMMIT)){ $e=oci_error($s); throw new Exception($e['message']);}
  oci_free_statement($s);
}
function gen_id($len=6){
  $pool='ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'; $s='';
  for($i=0;$i<$len;$i++) $s.=$pool[random_int(0,strlen($pool)-1)];
  return $s;
}

// ===== Fetch dynamic lists =====
$conn = db_connect($DB_USER,$DB_PASS,$DB_CONN);
$locations   = qall($conn, "SELECT location_id, name FROM locations ORDER BY name");
$auditors    = qall($conn, "SELECT auditor_id, fname, lname FROM auditors ORDER BY lname, fname");
$waste_types = qall($conn, "SELECT waste_id, category FROM waste WHERE waste_id IS NOT NULL ORDER BY category");

$ok_msg = null; $err_msg = null;

// ===== Handle POST =====
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['do_submit'])){
  try{
    if(!hash_equals($_SESSION['csrf']??'', $_POST['csrf']??'')) throw new Exception('Invalid CSRF token.');

    $audit_id           = gen_id(6);
    $location_id        = trim($_POST['location_id'] ?? '');
    $audited_at         = trim($_POST['audited_at'] ?? '');
    $num_bags           = trim($_POST['num_bags'] ?? '');
    $contamination_flag = isset($_POST['contamination_flag']) ? 1 : 0;

    if($location_id==='') throw new Exception('Location is required.');
    if($audited_at==='')  throw new Exception('Audit date is required.');
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$audited_at)) throw new Exception('Date must be YYYY-MM-DD.');
    if($num_bags==='')    throw new Exception('Number of bags is required.');

    $aud_sel = $_POST['auditors'] ?? [];
    $roles   = $_POST['roles'] ?? [];
    if(!is_array($aud_sel) || !is_array($roles) || count($aud_sel)!==count($roles))
      throw new Exception('Participants arrays are misaligned.');

    $notes_lines = preg_split("/\r\n|\n|\r/", (string)($_POST['notes'] ?? ''));

    // Gather item fields for each waste_id present in DB
    $items = [];
    foreach($waste_types as $w){
      $wid = $w['WASTE_ID'];
      $abs  = trim($_POST["abs_$wid"]  ?? '0');  $abs  = ($abs==='')? null : (float)$abs;
      $relm = trim($_POST["relm_$wid"] ?? '0');  $relm = ($relm==='')? null : (float)$relm;
      $relv = trim($_POST["relv_$wid"] ?? '0');  $relv = ($relv==='')? null : (float)$relv;
      $items[] = ['waste_id'=>$wid,'abs_mass'=>$abs,'rel_mass'=>$relm,'rel_volume'=>$relv];
    }

    // Insert: audits
    qexec($conn,
      "INSERT INTO audits (audit_id, location_id, audited_at, num_bags, contamination_flag)
       VALUES (:AID, :LID, TO_DATE(:ADT,'YYYY-MM-DD'), :NBG, :CF)",
      [':AID'=>$audit_id, ':LID'=>$location_id, ':ADT'=>$audited_at, ':NBG'=>$num_bags, ':CF'=>$contamination_flag]
    );

    // Insert: participants
    for($i=0;$i<count($aud_sel);$i++){
      $aid = trim($aud_sel[$i]); if($aid==='') continue;
      $role= trim($roles[$i]??''); $role = $role!=='' ? $role : null;
      qexec($conn,
        "INSERT INTO audit_participant (audit_id, auditor_id, role_label)
         VALUES (:AID, :PID, :ROL)",
        [':AID'=>$audit_id, ':PID'=>$aid, ':ROL'=>$role]
      );
    }

    // Insert: notes
    foreach($notes_lines as $line){
      $t = trim($line); if($t==='') continue;
      $nid = gen_id(6);
      qexec($conn,
        "INSERT INTO notes (note_id, audit_id, note_text, created_at)
         VALUES (:NID, :AID, :TXT, TO_DATE(:ADT,'YYYY-MM-DD'))",
        [':NID'=>$nid, ':AID'=>$audit_id, ':TXT'=>$t, ':ADT'=>$audited_at]
      );
    }

    // Insert: items (exactly one per waste)
    foreach($items as $it){
      $iid = sprintf("IT%06d", random_int(0,999999)); // 8-ish total length
      qexec($conn,
        "INSERT INTO audit_item (audit_item_id, audit_id, waste_id, abs_mass, rel_mass, rel_volume)
         VALUES (:IID, :AID, :WID, :ABS, :RMS, :RVL)",
        [':IID'=>$iid, ':AID'=>$audit_id, ':WID'=>$it['waste_id'], ':ABS'=>$it['abs_mass'], ':RMS'=>$it['rel_mass'], ':RVL'=>$it['rel_volume']]
      );
    }

    oci_commit($conn);
    $ok_msg = "Audit {$audit_id} created.";
  } catch(Throwable $ex){
    @oci_rollback($conn);
    $err_msg = $ex->getMessage();
  }
}

// Refresh lists (keeps it dynamic after POST)
$locations   = qall($conn, "SELECT location_id, name FROM locations ORDER BY name");
$auditors    = qall($conn, "SELECT auditor_id, fname, lname FROM auditors ORDER BY lname, fname");
$waste_types = qall($conn, "SELECT waste_id, category FROM waste WHERE waste_id IS NOT NULL ORDER BY category");
@oci_close($conn);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>Create Audit</title>
<link rel="stylesheet" href="audit_form.css" />
</head>
<body>
<div class="container">
  <div class="card">
    <h1>Create Audit</h1>

    <?php if($ok_msg): ?><div class="alert ok"><strong>Success:</strong> <?=h($ok_msg)?></div><?php endif; ?>
    <?php if($err_msg): ?><div class="alert bad"><strong>Error:</strong> <?=h($err_msg)?></div><?php endif; ?>

    <form method="post" action="">
      <input type="hidden" name="csrf" value="<?=h($CSRF)?>"/>

      <fieldset>
        <legend>Audit Info</legend>
        <div class="row">
          <div class="col">
            <label>Location</label>
            <select name="location_id" required>
              <option value="">— select —</option>
              <?php foreach($locations as $r): ?>
                <option value="<?=h($r['LOCATION_ID'])?>"><?=h($r['NAME'])?> (<?=h($r['LOCATION_ID'])?>)</option>
              <?php endforeach; ?>
            </select>
            <small class="hint">From <code>locations</code>.</small>
          </div>
          <div class="col">
            <label>Audit Date</label>
            <input type="date" name="audited_at" required />
          </div>
          <div class="col">
            <label># of Bags</label>
            <input type="number" name="num_bags" min="0" step="1" required />
          </div>
          <div class="col chk">
            <label><input type="checkbox" name="contamination_flag" value="1" /> Contamination observed</label>
          </div>
        </div>
      </fieldset>

      <fieldset>
        <legend>Audit Items (per Waste Category)</legend>
        <small class="hint">Rows mirror <code>waste</code> table.</small>
        <table class="items">
          <thead>
            <tr>
              <th>Waste Category</th>
              <th>Abs Mass (lbs)</th>
              <th>Rel Mass (0–1)</th>
              <th>Rel Volume (0–1)</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($waste_types as $w): $wid=$w['WASTE_ID']; ?>
              <tr>
                <td><?=h($w['CATEGORY'])?> <small>(<?=h($wid)?>)</small></td>
                <td><input type="number" name="abs_<?=h($wid)?>"  step="0.01"  min="0"   placeholder="e.g., 10.41"></td>
                <td><input type="number" name="relm_<?=h($wid)?>" step="0.0001" min="0" max="1" placeholder="e.g., 0.26"></td>
                <td><input type="number" name="relv_<?=h($wid)?>" step="0.0001" min="0" max="1" placeholder="e.g., 0.30"></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </fieldset>

      <fieldset>
        <legend>Participants</legend>
        <div id="participants">
          <div class="row">
            <div class="col">
              <label>Auditor</label>
              <select name="auditors[]">
                <option value="">— select —</option>
                <?php foreach($auditors as $a): ?>
                  <option value="<?=h($a['AUDITOR_ID'])?>"><?=h($a['LNAME'].', '.$a['FNAME'])?> (<?=h($a['AUDITOR_ID'])?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col">
              <label>Role (optional)</label>
              <input type="text" name="roles[]" placeholder="e.g., Project Manager"/>
            </div>
          </div>
        </div>
        <button type="button" class="btn" id="add_participant">+ Add another participant</button>

        <!-- hidden select options for cloning -->
        <select id="auditor_options" style="display:none">
          <option value="">— select —</option>
          <?php foreach($auditors as $a): ?>
            <option value="<?=h($a['AUDITOR_ID'])?>"><?=h($a['LNAME'].', '.$a['FNAME'])?> (<?=h($a['AUDITOR_ID'])?>)</option>
          <?php endforeach; ?>
        </select>
      </fieldset>

      <fieldset>
        <legend>Notes</legend>
        <label>One note per line (optional)</label>
        <textarea name="notes" rows="4" placeholder="Signage and removal of bins&#10;..."></textarea>
      </fieldset>

      <div class="actions">
        <button class="primary" type="submit" name="do_submit" value="1">Create Audit</button>
        <button type="reset">Reset</button>
      </div>
    </form>
  </div>
</div>

<script src="audit_form.js"></script>
</body>
</html>
