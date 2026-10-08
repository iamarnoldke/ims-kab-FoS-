<?php

// IMS — Database Configuration

$DB_HOST = "localhost";
$DB_PORT = 4306;
$DB_USER = "root";
$DB_PASS = "";
$DB_NAME = "ims_db";

$conn = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);

if (!$conn) {
    die(json_encode([
        'error' => true,
        'message' => 'Database connection failed: ' . mysqli_connect_error()
    ]));
}

mysqli_set_charset($conn, 'utf8mb4');

// ── Helpers ──────────────────────────────────────────────────

/**
 * Sanitise a value for safe use in queries.
 */
function sanitise($conn, $value) {
    return mysqli_real_escape_string($conn, trim($value));
}

/**
 * Log an action to the audit trail.
 */
function audit_log($conn, $user_id, $action_type, $details) {
    $user_id     = (int)$user_id;
    $action_type = sanitise($conn, $action_type);
    $details     = sanitise($conn, $details);
    $ip          = sanitise($conn, $_SERVER['REMOTE_ADDR'] ?? 'unknown');
    mysqli_query($conn,
        "INSERT INTO audit_log (user_id, action_type, details, ip_address)
        VALUES ($user_id, '$action_type', '$details', '$ip')"
    );
}

/**
 * Generate a unique reference number.
 * e.g.  REQ-20240825-001
 */
function generate_ref($conn, $prefix) {
    $date = date('Ymd');
    $prefix = strtoupper(sanitise($conn, $prefix));
    $result = mysqli_query($conn,
        "SELECT COUNT(*) AS cnt FROM stock_requests
         WHERE ref_number LIKE '{$prefix}-{$date}-%'"
    );
    $row = mysqli_fetch_assoc($result);
    $seq = str_pad($row['cnt'] + 1, 3, '0', STR_PAD_LEFT);
    return "{$prefix}-{$date}-{$seq}";
}

/**
 * Generate a GRN number.
 */
function generate_grn($conn) {
    $date = date('Ymd');
    $result = mysqli_query($conn,
        "SELECT COUNT(*) AS cnt FROM goods_received
         WHERE grn_number LIKE 'GRN-{$date}-%'"
    );
    $row = mysqli_fetch_assoc($result);
    $seq = str_pad($row['cnt'] + 1, 3, '0', STR_PAD_LEFT);
    return "GRN-{$date}-{$seq}";
}

/**
 * Check if stock item is at or below reorder point and
 * return true if a low-stock alert should be shown.
 */
function is_low_stock($conn, $stock_id) {
    $stock_id = (int)$stock_id;
    $r = mysqli_query($conn,
        "SELECT qty_on_hand, reorder_point FROM stock_items WHERE stock_id = $stock_id"
    );
    $row = mysqli_fetch_assoc($r);
    return $row && $row['qty_on_hand'] <= $row['reorder_point'];
}
