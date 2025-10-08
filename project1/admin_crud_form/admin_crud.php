<?php
// admin_crud.php — single-file postback admin for parser.php (CSS/JS split)

session_start();
header('Content-Type: text/html; charset=utf-8');

// ===== CONFIG =====
$PARSER_URL = './parser.php'; // adjust if parser.php lives elsewhere

// ===== CSRF =====
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$CSRF = $_SESSION['csrf'];

// ===== Form schema (labels/help) =====
$SCHEMAS = [
    'locations' => [
        'pk'   => 'location_id',
        'cols' => [
            'location_id' => ['label' => 'Location ID', 'hint' => 'e.g., ALDER (6 chars)'],
            'name'        => ['label' => 'Name',        'hint' => 'e.g., Alder Residence Hall'],
            'floor_num'   => ['label' => 'Floor #',     'hint' => 'Optional (NUMBER)'],
            'loc_type'    => ['label' => 'Type',        'hint' => 'e.g., Residence Hall, Academic'],
        ],
    ],
    'auditors' => [
        'pk'   => 'auditor_id',
        'cols' => [
            'auditor_id'  => ['label' => 'Auditor ID', 'hint' => 'e.g., AU0001 (6 chars)'],
            'fname'       => ['label' => 'First Name', 'hint' => 'Required'],
            'lname'       => ['label' => 'Last Name',  'hint' => 'Required'],
            'affiliation' => ['label' => 'Affiliation','hint' => 'e.g., Student, Staff'],
        ],
    ],
    'waste' => [
        'pk'   => 'waste_id',
        'cols' => [
            'waste_id'        => ['label' => 'Waste ID',        'hint' => 'e.g., WA0001 (6 chars)'],
            'category'        => ['label' => 'Category',        'hint' => 'Required'],
            'parent_waste_id' => ['label' => 'Parent Waste ID', 'hint' => 'Optional (FK to waste_id)'],
        ],
    ],
];

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function post($k, $d=''){ return $_POST[$k] ?? $d; }

$entity = post('entity', 'locations');
$op     = post('op', 'read'); // create|read|update|delete
$result = null;
$error  = null;

// ===== Handle submit (POST + CSRF) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_submit'])) {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $schema = $SCHEMAS[$entity] ?? null;
        if (!$schema) {
            $error = 'Unsupported entity.';
        } else {
            $pk = $schema['pk'];
            $cols = array_keys($schema['cols']);

            $data = [];
            if ($op === 'create') {
                foreach ($cols as $c) $data[$c] = trim((string)post($c, ''));
            } elseif ($op === 'update') {
                $data[$pk] = trim((string)post($pk, ''));
                foreach ($cols as $c) {
                    if ($c === $pk) continue;
                    if (isset($_POST[$c])) $data[$c] = trim((string)post($c, ''));
                }
            } elseif ($op === 'delete') {
                $data[$pk] = trim((string)post($pk, ''));
            } elseif ($op === 'read') {
                if (isset($_POST[$pk])) {
                    $val = trim((string)post($pk, ''));
                    if ($val !== '') $data[$pk] = $val;
                }
            } else {
                $error = 'Unsupported operation.';
            }

            if (!$error) {
                $payload = json_encode([
                    'entity' => $entity,
                    'op'     => $op,
                    'data'   => $data
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                $ch = curl_init($PARSER_URL);
                curl_setopt_array($ch, [
                    CURLOPT_POST           => true,
                    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                    CURLOPT_POSTFIELDS     => $payload,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 20,
                ]);
                $resp = curl_exec($ch);
                $curlErr = curl_error($ch);
                $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($resp === false) {
                    $error = 'Request failed: ' . $curlErr;
                } else {
                    $json = json_decode($resp, true);
                    if (!is_array($json)) {
                        $error = "Bad response (HTTP {$httpCode}): " . h($resp);
                    } else {
                        if (!empty($json['ok'])) {
                            $result = $json['data'];
                        } else {
                            $error = $json['error'] ?? ("Operation failed (HTTP {$httpCode}).");
                        }
                    }
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Admin CRUD</title>
<link rel="stylesheet" href="admin_crud.css" />
</head>
<body>
<div class="container">
  <div class="card">
    <h1>Admin CRUD</h1>
    <form method="post" action="" onsubmit="return true;">
      <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
      <div class="row">
        <div class="col">
          <label for="entity">Entity</label>
          <select name="entity" id="entity" onchange="this.form.submit()">
            <?php foreach (array_keys($SCHEMAS) as $e): ?>
              <option value="<?=h($e)?>" <?= $entity===$e?'selected':''?>><?=h($e)?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col">
          <label for="op">Operation</label>
          <select name="op" id="op" onchange="toggleFieldsets()">
            <?php foreach (['create','read','update','delete'] as $o): ?>
              <option value="<?=h($o)?>" <?= $op===$o?'selected':''?>><?=h($o)?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <hr />

      <?php
      foreach ($SCHEMAS as $ename => $s) {
          $pk = $s['pk'];
          $cols = $s['cols'];
          // PK only (required)
          echo '<fieldset data-block="'.h($ename).'-pk" style="display:none">';
          echo '<legend>'.h(strtoupper($ename)).' — Primary Key</legend>';
          echo '<div class="row"><div class="col">';
          echo '<label>'.h($cols[$pk]['label']).'</label>';
          echo '<input type="text" name="'.h($pk).'" value="'.h(post($pk,'')).'" />';
          echo '<div class="hint">'.h($cols[$pk]['hint']).'</div>';
          echo '</div></div>';
          echo '</fieldset>';

          // PK optional (for read)
          echo '<fieldset data-block="'.h($ename).'-pk-optional" style="display:none">';
          echo '<legend>'.h(strtoupper($ename)).' — Optional Filter by PK</legend>';
          echo '<div class="row"><div class="col">';
          echo '<label>'.h($cols[$pk]['label']).' <span class="small">(optional)</span></label>';
          echo '<input type="text" name="'.h($pk).'" value="'.h(post($pk,'')).'" />';
          echo '<div class="hint">Leave blank to list all.</div>';
          echo '</div></div>';
          echo '</fieldset>';

          // All fields (for create)
          echo '<fieldset data-block="'.h($ename).'-all" style="display:none">';
          echo '<legend>'.h(strtoupper($ename)).' — Create Fields</legend>';
          echo '<div class="row">';
          foreach ($cols as $c => $meta) {
              echo '<div class="col">';
              echo '<label>'.h($meta['label']).'</label>';
              echo '<input type="text" name="'.h($c).'" value="'.h(post($c,'')).'"/>';
              echo '<div class="hint">'.h($meta['hint']).'</div>';
              echo '</div>';
          }
          echo '</div>';
          echo '</fieldset>';

          // Editable (non-PK) fields (for update)
          echo '<fieldset data-block="'.h($ename).'-editable" style="display:none">';
          echo '<legend>'.h(strtoupper($ename)).' — Update Fields</legend>';
          echo '<div class="row">';
          foreach ($cols as $c => $meta) {
              if ($c === $pk) continue;
              echo '<div class="col">';
              echo '<label>'.h($meta['label']).' <span class="small">(optional)</span></label>';
              echo '<input type="text" name="'.h($c).'" value="'.h(post($c,'')).'"/>';
              echo '<div class="hint">'.h($meta['hint']).'</div>';
              echo '</div>';
          }
          echo '</div>';
          echo '</fieldset>';
      }
      ?>

      <div class="actions">
        <button type="submit" class="primary" name="do_submit" value="1">Run</button>
        <button type="button" onclick="toggleFieldsets()">Refresh Fields</button>
      </div>
    </form>

    <?php if ($error): ?>
      <div class="alert bad" role="alert">
        <strong>Error:</strong> <pre><?=h($error)?></pre>
      </div>
    <?php endif; ?>

    <?php if ($result !== null): ?>
      <div class="alert ok" role="status">
        <strong>Result</strong>
        <pre><?=
h(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
?></pre>
      </div>
    <?php endif; ?>
  </div>

  <p class="small" style="margin:.75rem 0 0 .25rem;">
    Tip: For <em>delete</em>, only the primary key is required. For <em>read</em>, leave PK blank to list all.
  </p>
</div>
<script src="admin_crud.js"></script>
<script>toggleFieldsets();</script>
</body>
</html>
