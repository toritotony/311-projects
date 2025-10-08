<?php
/**
 * parser.php
 * A single endpoint for CRUD on locations, auditors, and waste (categories).
 * DB: Oracle (OCI8)
 *
 * Call with either application/json (recommended) or form-encoded.
 * Params:
 *   entity: locations | auditors | waste
 *   op:     create | read | update | delete
 *   data:   object with fields (for JSON) OR individual fields as POST vars
 *
 * Examples (JSON body):
 *   { "entity":"locations", "op":"create",
 *     "data": { "location_id":"ALDER", "name":"Alder Residence Hall", "floor_num": null, "loc_type":"Residence Hall" } }
 *
 *   { "entity":"auditors", "op":"update",
 *     "data": { "auditor_id":"AU0001", "fname":"Alex", "lname":"Kim", "affiliation":"Student" } }
 *
 *   { "entity":"waste", "op":"delete", "data": { "waste_id":"WA0003" } }
 *
 * Read:
 *   { "entity":"locations", "op":"read" }              // list all
 *   { "entity":"locations", "op":"read", "data": { "location_id":"ALDER" } } // by PK
 */

// ---------- CONFIG ----------
header('Content-Type: application/json; charset=utf-8');

// If serving from a different domain, enable CORS appropriately:
// header('Access-Control-Allow-Origin: https://your-admin-app');
// header('Access-Control-Allow-Headers: Content-Type, Authorization');
// header('Access-Control-Allow-Methods: POST, OPTIONS');
// if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ---------- HELPERS ----------

function respond($ok, $payload = null, $http=200) {
    http_response_code($http);
    echo json_encode([
        'ok' => $ok ? true : false,
        'data' => $ok ? $payload : null,
        'error' => $ok ? null : $payload
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Fetch JSON or form input, normalize to ['entity','op','data'] */
function get_input(): array {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $raw = file_get_contents('php://input');

    if (stripos($contentType, 'application/json') !== false && $raw) {
        $j = json_decode($raw, true);
        if (!is_array($j)) respond(false, 'Invalid JSON.', 400);
        $entity = $j['entity'] ?? null;
        $op     = $j['op'] ?? null;
        $data   = $j['data'] ?? [];
    } else {
        // Allow form posts / querystring for convenience
        $entity = $_REQUEST['entity'] ?? null;
        $op     = $_REQUEST['op'] ?? null;
        // collect all request fields under 'data' except entity/op
        $data = $_REQUEST;
        unset($data['entity'], $data['op']);
    }
    if (!$entity || !$op) respond(false, 'Missing "entity" or "op".', 400);
    if (!is_array($data)) $data = [];
    return [$entity, strtolower($op), $data];
}

/** Oracle connection */
function db_connect() {
    $conn = oci_connect();
    if (!$conn) {
        $e = oci_error();
        respond(false, 'DB connect failed: '.($e['message'] ?? 'unknown'), 500);
    }
    return $conn;
}

/** Execute SELECT; returns rows as assoc arrays */
function db_select($conn, string $sql, array $binds = []): array {
    $stid = oci_parse($conn, $sql);
    if (!$stid) {
        $e = oci_error($conn);
        respond(false, 'Parse failed: '.($e['message'] ?? 'unknown'), 500);
    }
    foreach ($binds as $k => $v) {
        // Use explicit null binding for nullable cols
        if ($v === null) {
            oci_bind_by_name($stid, $k, $v, -1, SQLT_CHR);
        } else {
            oci_bind_by_name($stid, $k, $v);
        }
    }
    if (!oci_execute($stid)) {
        $e = oci_error($stid);
        respond(false, 'Query failed: '.($e['message'] ?? 'unknown'), 500);
    }
    $rows = [];
    oci_fetch_all($stid, $out, 0, -1, OCI_FETCHSTATEMENT_BY_ROW + OCI_ASSOC);
    if ($out) $rows = $out;
    oci_free_statement($stid);
    return $rows;
}

/** Execute DML (INSERT/UPDATE/DELETE) */
function db_dml($conn, string $sql, array $binds = []) {
    $stid = oci_parse($conn, $sql);
    if (!$stid) {
        $e = oci_error($conn);
        respond(false, 'Parse failed: '.($e['message'] ?? 'unknown'), 500);
    }
    foreach ($binds as $k => $v) {
        if ($v === null) {
            oci_bind_by_name($stid, $k, $v, -1, SQLT_CHR);
        } else {
            oci_bind_by_name($stid, $k, $v);
        }
    }
    if (!oci_execute($stid, OCI_NO_AUTO_COMMIT)) {
        $e = oci_error($stid);
        // Attempt to rollback on failure
        @oci_rollback($conn);
        respond(false, 'DML failed: '.($e['message'] ?? 'unknown'), 400);
    }
    oci_commit($conn);
    oci_free_statement($stid);
}

/** Generate a fixed-length uppercase ID (fallback if you don’t supply your own IDs) */
function gen_id(int $len = 6): string {
    $pool = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $s = '';
    for ($i=0; $i<$len; $i++) $s .= $pool[random_int(0, strlen($pool)-1)];
    return $s;
}

// Whitelists (reflect your schema)
$SCHEMAS = [
    'locations' => [
        'pk' => 'location_id',
        'cols' => ['location_id','name','floor_num','loc_type']
    ],
    'auditors' => [
        'pk' => 'auditor_id',
        'cols' => ['auditor_id','fname','lname','affiliation']
    ],
    'waste' => [
        'pk' => 'waste_id',
        'cols' => ['waste_id','category','parent_waste_id']
    ]
];

// ---------- MAIN ----------
[$entity, $op, $data] = get_input();
$entity = strtolower($entity);

if (!isset($SCHEMAS[$entity])) respond(false, 'Unsupported entity.', 400);
$pk = $SCHEMAS[$entity]['pk'];
$cols = $SCHEMAS[$entity]['cols'];

$conn = db_connect($DB_USER, $DB_PASS, $DB_CONN);

try {
    switch ($op) {
        case 'read':
            // Optional by PK, else list all
            if (!empty($data[$pk])) {
                $sql = "SELECT ".implode(',', $cols)." FROM {$entity} WHERE {$pk} = :id";
                $rows = db_select($conn, $sql, [':id' => $data[$pk]]);
            } else {
                $sql = "SELECT ".implode(',', $cols)." FROM {$entity} ORDER BY {$pk}";
                $rows = db_select($conn, $sql);
            }
            respond(true, $rows);

        case 'create': {
            // Prepare values; allow client-provided PK; else generate
            $payload = [];
            foreach ($cols as $c) {
                if ($c === $pk) {
                    $payload[$c] = $data[$c] ?? gen_id(($entity === 'waste') ? 6 : 6);
                } else {
                    // Accept missing optional fields as NULL
                    $payload[$c] = array_key_exists($c, $data) ? ($data[$c] !== '' ? $data[$c] : null) : null;
                }
            }

            // Basic validation (you can expand)
            if ($entity === 'locations') {
                if (!$payload['name']) respond(false, 'name is required for locations', 400);
            }
            if ($entity === 'auditors') {
                if (!$payload['fname'] || !$payload['lname']) respond(false, 'fname and lname are required for auditors', 400);
            }
            if ($entity === 'waste') {
                if (!$payload['category']) respond(false, 'category is required for waste', 400);
            }

            // Build INSERT
            $colList = implode(',', $cols);
            $bindList = implode(',', array_map(fn($c) => ":$c", $cols));
            $sql = "INSERT INTO {$entity} ({$colList}) VALUES ({$bindList})";
            $binds = [];
            foreach ($cols as $c) $binds[":$c"] = $payload[$c];

            db_dml($conn, $sql, $binds);
            respond(true, ['message' => 'created', 'id' => $payload[$pk]]);
        }

        case 'update': {
            // Require PK
            if (empty($data[$pk])) respond(false, "Missing {$pk} for update.", 400);

            // Determine which updatable cols are present
            $setParts = [];
            $binds = [":pk" => $data[$pk]];
            foreach ($cols as $c) {
                if ($c === $pk) continue;
                if (array_key_exists($c, $data)) {
                    // allow empty string → NULL
                    $setParts[] = "{$c} = :{$c}";
                    $binds[":{$c}"] = ($data[$c] !== '' ? $data[$c] : null);
                }
            }
            if (!$setParts) respond(false, 'No updatable fields provided.', 400);

            $sql = "UPDATE {$entity} SET ".implode(', ', $setParts)." WHERE {$pk} = :pk";
            db_dml($conn, $sql, $binds);
            respond(true, ['message' => 'updated', 'id' => $data[$pk]]);
        }

        case 'delete': {
            if (empty($data[$pk])) respond(false, "Missing {$pk} for delete.", 400);

            // Attempt delete; FK constraints may block if referenced elsewhere
            $sql = "DELETE FROM {$entity} WHERE {$pk} = :pk";
            db_dml($conn, $sql, [':pk' => $data[$pk]]);
            respond(true, ['message' => 'deleted', 'id' => $data[$pk]]);
        }

        default:
            respond(false, 'Unsupported op.', 400);
    }
} finally {
    if ($conn) @oci_close($conn);
}
