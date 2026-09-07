<?php
define('BASE_URL', '../..');
require_once BASE_URL . '/config/db.php';
$page_title = 'Issue Stock';
require_once BASE_URL . '/includes/header.php';

$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id = (int)$_POST['request_id'];
    $issued_by  = (int)$current_user['user_id'];
    $notes      = sanitise($conn, $_POST['notes'] ?? '');

    // Get request items
    $req_items = mysqli_query($conn,
        "SELECT ri.stock_id, ri.qty_requested, si.qty_on_hand, si.item_name
         FROM stock_request_items ri
         JOIN stock_items si ON ri.stock_id=si.stock_id
         WHERE ri.request_id=$request_id"
    );

    $can_issue = true;
    $items_arr = [];
    while ($ri = mysqli_fetch_assoc($req_items)) {
        if ($ri['qty_on_hand'] < $ri['qty_requested']) {
            $err = "Insufficient stock for: <strong>{$ri['item_name']}</strong>. Available: {$ri['qty_on_hand']}.";
            $can_issue = false; break;
        }
        $items_arr[] = $ri;
    }

    if ($can_issue && !empty($items_arr)) {
        // Create issuance record
        mysqli_query($conn,
            "INSERT INTO stock_issuances (request_id,issued_by,issued_date,notes)
             VALUES ($request_id,$issued_by,CURDATE(),'$notes')"
        );
        $issuance_id = mysqli_insert_id($conn);

        foreach ($items_arr as $ri) {
            $sid = (int)$ri['stock_id'];
            $qty = (int)$ri['qty_requested'];
            // Issuance item
            mysqli_query($conn, "INSERT INTO stock_issuance_items (issuance_id,stock_id,qty_issued) VALUES ($issuance_id,$sid,$qty)");
            // Deduct from stock
            mysqli_query($conn, "UPDATE stock_items SET qty_on_hand=qty_on_hand-$qty WHERE stock_id=$sid");
        }

        // Update request status
        mysqli_query($conn, "UPDATE stock_requests SET status='issued' WHERE request_id=$request_id");
        audit_log($conn, $issued_by, 'STOCK_ISSUE', "Issued stock for request ID $request_id, issuance ID $issuance_id");
        $msg = "Stock issued successfully. Issuance ID: <strong>ISS-$issuance_id</strong>.";
    }
}

// Approved requests ready to issue
$approved = mysqli_query($conn,
    "SELECT r.*, u.full_name, d.dept_name
     FROM stock_requests r
     JOIN users u ON r.requester_id=u.user_id
     JOIN departments d ON r.dept_id=d.dept_id
     WHERE r.status='approved'
     ORDER BY r.created_at ASC"
);
?>

<div class="page-header">
  <div><h1>Issue Stock</h1><p>Process approved stock requests and issue items from the store.</p></div>
</div>

<?php if ($msg): ?><div class="alert alert-success"><i class="fa fa-check-circle"></i> <?= $msg ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger"><i class="fa fa-times-circle"></i> <?= $err ?></div><?php endif; ?>

<?php if (mysqli_num_rows($approved) === 0): ?>
<div class="card"><div class="card-body">
  <div class="empty-state"><i class="fa fa-hand-holding"></i><p>No approved requests pending issuance.</p></div>
</div></div>
<?php else: while ($r = mysqli_fetch_assoc($approved)):
    $items = mysqli_query($conn,
        "SELECT ri.qty_requested, si.item_name, si.unit_of_measure, si.qty_on_hand
         FROM stock_request_items ri
         JOIN stock_items si ON ri.stock_id=si.stock_id
         WHERE ri.request_id={$r['request_id']}"
    );
?>
<div class="card" style="margin-bottom:16px">
  <div class="card-header">
    <div>
      <strong><?= htmlspecialchars($r['ref_number']) ?></strong>
      <span style="margin-left:10px;font-size:12px;color:var(--text-muted)">
        <?= htmlspecialchars($r['full_name']) ?> · <?= htmlspecialchars($r['dept_name']) ?> · <?= date('d M Y', strtotime($r['created_at'])) ?>
      </span>
    </div>
    <span class="badge badge-approved">Approved</span>
  </div>
  <div class="card-body">
    <table style="margin-bottom:16px">
      <thead><tr><th>Item</th><th>Qty Requested</th><th>Available</th><th>UoM</th></tr></thead>
      <tbody>
      <?php while ($item = mysqli_fetch_assoc($items)):
          $insufficient = $item['qty_on_hand'] < $item['qty_requested']; ?>
        <tr>
          <td><?= htmlspecialchars($item['item_name']) ?></td>
          <td><?= $item['qty_requested'] ?></td>
          <td style="<?= $insufficient?'color:var(--danger);font-weight:700':'' ?>">
            <?= $item['qty_on_hand'] ?> <?= $insufficient?'⚠ Insufficient':'' ?>
          </td>
          <td><?= htmlspecialchars($item['unit_of_measure']) ?></td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
    <form method="POST">
      <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
      <div class="form-group">
        <label>Notes (optional)</label>
        <input type="text" name="notes" class="form-control" placeholder="e.g. Partial issue — remaining to follow">
      </div>
      <button type="submit" class="btn btn-success" onclick="return confirm('Confirm stock issuance for this request?')">
        <i class="fa fa-hand-holding"></i> Issue Stock
      </button>
    </form>
  </div>
</div>
<?php endwhile; endif; ?>

<?php require_once BASE_URL . '/includes/footer.php'; ?>
