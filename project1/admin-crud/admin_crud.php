<?php
/**
 * admin_crud.php — self-posting CRUD (no sessions, no cURL)
 * - Uses hum_conn_no_login() for Oracle connection
 * - Pattern: oci_parse → oci_bind_by_name → oci_execute → oci_commit
 * - Entities: locations, auditors, waste
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ---- DB connection helper ----
require_once __DIR__ . '/hum_conn_no_login.php';
function db() {
    $c = hum_conn_no_login();
    if (!$c) { $e = oci_error(); die("DB connect failed: ".($e['message']??'unknown')); }
    return $c;
}

// ---- small view helpers ----
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function sel($v,$x){ return ($v===$x)?' selected':''; }

// ---- entity field specs (simple) ----
$ENTITIES = [
    'locations' => [
        'pk' => 'location_id',
        'fields' => [
            'location_id' => ['label'=>'ID (leave blank to auto)', 'type'=>'text'],
            'name'        => ['label'=>'Name', 'type'=>'text', 'required'=>true],
            'floor_num'   => ['label'=>'Floor #', 'type'=>'number'],
            'loc_type'    => ['label'=>'Type', 'type'=>'text'],
        ],
        'columns' => 'location_id, name, floor_num, loc_type',
        'table'   => 'locations',
    ],
    'auditors' => [
        'pk' => 'auditor_id',
        'fields' => [
            'auditor_id' => ['label'=>'ID (leave blank to auto)', 'type'=>'text'],
            'fname'      => ['label'=>'First name', 'type'=>'text', 'required'=>true],
            'lname'      => ['label'=>'Last name', 'type'=>'text', 'required'=>true],
            'affiliation'=> ['label'=>'Affiliation', 'type'=>'text'],
        ],
        'columns' => 'auditor_id, fname, lname, affiliation',
        'table'   => 'auditors',
    ],
    'waste' => [
        'pk' => 'waste_id',
        'fields' => [
            'waste_id'        => ['label'=>'ID (leave blank to auto)', 'type'=>'text'],
            'category'        => ['label'=>'Category', 'type'=>'text', 'required'=>true],
            'parent_waste_id' => ['label'=>'Parent Waste ID (optional)', 'type'=>'text'],
        ],
        'columns' => 'waste_id, category, parent_waste_id',
        'table'   => 'waste',
    ],
];

// ---- read current row helper ----
function fetch_by_id($conn, $table, $pkcol, $id){
    $sql = "SELECT * FROM {$table} WHERE {$pkcol} = :id";
    $st = oci_parse($conn, $sql);
    oci_bind_by_name($st, ":id", $id);
    oci_execute($st, OCI_DEFAULT);
    $row = oci_fetch_assoc($st) ?: null;
    oci_free_statement($st);
    return $row;
}

// ---- handle POST ops ----
$op = $_POST['op'] ?? 'create';
$entity = $_POST['entity'] ?? 'locations';
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($ENTITIES[$entity])) {
    $spec = $ENTITIES[$entity];
    $pk   = $spec['pk'];
    $tbl  = $spec['table'];
    $conn = db();

    try {
        if ($op === 'create') {
            // collect inputs
            // --- CREATE ---
            $cols = [];
            $bind = [];
            $enforceRequired = in_array($op, ['create','update'], true);  // <-- fixed name

            // collect NON-PK fields only
            foreach ($spec['fields'] as $col => $meta) {
                if ($col === $pk) continue;
                $v = $_POST[$col] ?? null;
                if ($v === '') $v = null;

                if ($enforceRequired && !empty($meta['required']) && $v === null) {
                    throw new RuntimeException("Missing required field: {$meta['label']}");
                }
                $cols[] = $col;
                $bind[":$col"] = $v;
            }

            // pk input (nullable -> trigger fills it)
            $pk_in = $_POST[$pk] ?? null;
            if ($pk_in === '') $pk_in = null;

            // build column/value lists
            $cols_sql = implode(', ', $cols);
            $vals_sql = implode(', ', array_map(fn($c) => ":$c", $cols));

            // only include PK in the INSERT when explicitly provided
            if ($pk_in !== null) {
                $cols_sql = $cols_sql ? "$cols_sql, $pk" : $pk;
                $vals_sql = $vals_sql ? "$vals_sql, :$pk" : ":$pk";
            }

            // final SQL (RETURNING works in both cases)
            $sql = "INSERT INTO {$tbl} ($cols_sql) VALUES ($vals_sql)
                    RETURNING {$pk} INTO :out_id";

            $st = oci_parse($conn, $sql);

            // bind non-pk values
            foreach ($bind as $k => $v) {
                oci_bind_by_name($st, $k, $bind[$k]);
            }

            // bind pk only if provided
            if ($pk_in !== null) {
                oci_bind_by_name($st, ":$pk", $pk_in);
            }

            // get the generated/echoed pk back
            $out_id = null;
            oci_bind_by_name($st, ":out_id", $out_id, 64);

            if (!oci_execute($st, OCI_NO_AUTO_COMMIT)) {
                $e = oci_error($st);
                throw new RuntimeException($e['message'] ?? 'Insert failed');
            }
            oci_commit($conn);
            oci_free_statement($st);

            $msg = "Created in {$tbl} with {$pk} = ".h($out_id ?? $pk_in ?? '(unknown)');

        }
        elseif ($op === 'update') {
            $id = $_POST[$pk] ?? '';
            if ($id === '') throw new RuntimeException("{$pk} is required for update.");

            $sets = [];
            $bind = [":id" => $id];
            foreach ($spec['fields'] as $col => $meta) {
                if ($col === $pk) continue; // don't update PK
                $v = $_POST[$col] ?? null;
                if ($v === '') $v = null;
                if ($v !== null || array_key_exists($col, $_POST)) {
                    $sets[] = "{$col} = :{$col}";
                    $bind[":$col"] = $v;
                }
            }
            if (!$sets) throw new RuntimeException("Nothing to update.");

            $sql = "UPDATE {$tbl} SET ".implode(', ', $sets)." WHERE {$pk} = :id";
            $st = oci_parse($conn, $sql);
            foreach ($bind as $k=>$v) { oci_bind_by_name($st, $k, $bind[$k]); }

            if (!oci_execute($st, OCI_NO_AUTO_COMMIT)) {
                $e = oci_error($st); throw new RuntimeException($e['message'] ?? 'Update failed');
            }
            oci_commit($conn);
            oci_free_statement($st);

            $msg = "Updated {$tbl} row with {$pk} = ".h($id);
        }
        elseif ($op === 'delete') {
            $id = $_POST[$pk] ?? '';
            if ($id === '') throw new RuntimeException("{$pk} is required for delete.");

            $sql = "DELETE FROM {$tbl} WHERE {$pk} = :id";
            $st = oci_parse($conn, $sql);
            oci_bind_by_name($st, ":id", $id);

            if (!oci_execute($st, OCI_NO_AUTO_COMMIT)) {
                $e = oci_error($st); throw new RuntimeException($e['message'] ?? 'Delete failed');
            }
            oci_commit($conn);
            oci_free_statement($st);

            $msg = "Deleted from {$tbl} where {$pk} = ".h($id);
        }
        elseif ($op === 'read') {
            // nothing to do; listing happens below
            $msg = "Listed rows for {$tbl}.";
        }
        else {
            $err = "Unknown operation.";
        }
    } catch (Throwable $t) {
        if ($conn) @oci_rollback($conn);
        $err = $t->getMessage();
    } finally {
        if ($conn) @oci_close($conn);
    }
}

// ---- fetch table rows for display (simple) ----
function fetch_all($entity, $spec){
    $conn = db();
    $st = oci_parse($conn, "SELECT {$spec['columns']} FROM {$spec['table']} ORDER BY 1");
    oci_execute($st, OCI_DEFAULT);
    $rows = [];
    while ($row = oci_fetch_assoc($st)) $rows[] = $row;
    oci_free_statement($st);
    oci_close($conn);
    return $rows;
}

$currSpec = $ENTITIES[$entity] ?? $ENTITIES['locations'];
$rows = fetch_all($entity, $currSpec);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="admin_crud.css" />
    <script src="admin_crud.js" defer></script>
    <title>Admin CRUD</title>
</head>
<body>
<h1>Admin CRUD (self-posting, no sessions)</h1>
<p class="hint">Pick an entity, fill fields, and click an action. Inserts use <code>RETURNING</code> so trigger-generated IDs come back.</p>

<?php if($msg): ?><div class="flash ok"><?=h($msg)?></div><?php endif; ?>
<?php if($err): ?><div class="flash err"><?=h($err)?></div><?php endif; ?>

<form method="post" action="<?=h($_SERVER['PHP_SELF'])?>">
    <fieldset class="row">
        <div>
            <label for="entity">Entity</label>
            <select id="entity" name="entity" onchange="this.form.submit()">
                <?php foreach($ENTITIES as $k=>$v): ?>
                    <option value="<?=h($k)?>"<?=sel($entity,$k)?>><?=h($k)?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="op">Operation</label>
            <select id="op" name="op" onchange="toggleFields()">
                <option value="create"<?=sel($op,'create')?>>Create</option>
                <option value="update"<?=sel($op,'update')?>>Update</option>
                <option value="delete"<?=sel($op,'delete')?>>Delete</option>
            </select>
        </div>
    </fieldset>

    <fieldset>
        <?php foreach($currSpec['fields'] as $name=>$meta): ?>
        <?php $isPk = ($name === $currSpec['pk']) ? 1 : 0; ?>
        <div class="field" data-pk="<?= $isPk ?>">
            <label for="<?=h($name)?>"><?=h($meta['label'])?><?=!empty($meta['required'])?' *':''?></label>
            <?php if(($meta['type'] ?? '') === 'number'): ?>
            <input type="number" step="1" id="<?=h($name)?>" name="<?=h($name)?>" value="<?=h($_POST[$name]??'')?>"/>
            <?php else: ?>
            <input type="text" id="<?=h($name)?>" name="<?=h($name)?>" value="<?=h($_POST[$name]??'')?>"/>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <div class="hint">For <strong>Create</strong>, leave the ID blank to let the trigger assign one. For <strong>Update/Delete</strong>, provide the ID.</div>
    </fieldset>

    <div class="actions">
        <button type="submit" id="submitBtn" class="primary">Submit</button>
        <button type="button" class="primary"
                onclick="for(const el of this.form.querySelectorAll('input[type=text],input[type=number],input[type=email],input[type=date],input[type=time],textarea')) el.value='';">
            Clear fields
        </button>
    </div>

</form>

<h2>Rows: <?=h($entity)?></h2>
<table>
    <thead>
    <tr>
        <?php
        $cols = array_map('trim', explode(',', $currSpec['columns']));
        foreach ($cols as $c) echo '<th>'.h($c).'</th>';
        ?>
    </tr>
    </thead>
    <tbody>
    <?php if(!$rows): ?>
        <tr><td colspan="<?=count($cols)?>"><em>No rows</em></td></tr>
    <?php else: foreach($rows as $r): ?>
        <tr>
            <?php foreach ($cols as $c): $c = trim($c); ?>
                <td><?=h($r[strtoupper($c)] ?? '')?></td>
            <?php endforeach; ?>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>

<footer style="margin-top:2rem;color:#666">
    <hr/>
    <div>Built by Thursday Thoroughbreads></div>
</footer>

</body>
</html>

