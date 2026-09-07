<?php
define('BASE_URL', '../..');
require_once BASE_URL . '/config/db.php';
$page_title = 'Approve Requests';
require_once BASE_URL . '/includes/header.php';

$msg = $err = '';

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id = (int)$_POST['request_id'];
    $decision   = sanitise($conn, $_POST['decision']);
    $comments   = sanitise($conn, $_POST['comments'] ?? '');
    $approver   = (int)$current_user['user_id'];

    if (in_array($decision, ['approved','rejected','returned'])) {
        // Update request status
        $new_status = $decision === 'approved' ? 'approved' : ($decision === 'rejected' ? 'rejected' : 'pending');
        mysqli_query($conn, "UPDATE stock_requests SET status='$new_status' WHERE request_id=$request_id");

        // Record approval
        mysqli_query($conn,
            "INSERT INTO approvals (request_id,approver_id,decision,comments)
             VALUES ($request_id,$approver,'$decision','$comments')"
        );

        audit_log($conn, $approver, 'REQUEST_' . strtoupper($decision), "Request ID $request_id: $decision");
        $msg = "Request has been <strong>$decision</strong> successfully.";
    }
}

// Fetch pending requests
$pending = mysqli_query($conn,
    "SELECT r.*, u.full_name, d.dept_name
     FROM stock_requests r
     JOIN users u ON r.requester_id=u.user_id
     JOIN departments d ON r.dept_id=d.dept_id
     WHERE r.status='pending'
     ORDER BY r.created_at ASC"
);
?>

<div class="page-header">
  <div><h1>Approve Stock Requests</h1><p>Review and action pending stock requests from staff.</p></div>
</div>

<?php if ($msg): ?><div class="alert alert-success"><i class="fa fa-check-circle"></i> <?= $msg ?></div><?php endif; ?>

<?php if (mysqli_num_rows($pending) === 0): ?>
<div class="card"><div class="card-body">
  <div class="empty-state"><i class="fa fa-check-double"></i><p>No pending requests. All caught up!</p></div>
</div></div>
<?php else: while ($r = mysqli_fetch_assoc($pending)):
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
    <span class="badge badge-pending">Pending</span>
  </div>
  <div class="card-body">
    <?php if ($r['purpose']): ?>
    <p style="margin-bottom:12px;color:var(--text-muted);font-size:13px">
      <strong>Purpose:</strong> <?= htmlspecialchars($r['purpose']) ?>
    </p>
    <?php endif; ?>

    <table style="margin-bottom:16px">
      <thead><tr><th>Item</th><th>Qty Requested</th><th>Available</th><th>UoM</th></tr></thead>
      <tbody>
      <?php while ($item = mysqli_fetch_assoc($items)): $low = $item['qty_on_hand'] < $item['qty_requested']; ?>
        <tr>
          <td><?= htmlspecialchars($item['item_name']) ?></td>
          <td><?= $item['qty_requested'] ?></td>
          <td style="<?= $low?'color:var(--danger);font-weight:700':'' ?>"><?= $item['qty_on_hand'] ?> <?= $low?'⚠':'✓' ?></td>
          <td><?= htmlspecialchars($item['unit_of_measure']) ?></td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>

    <form method="POST">
      <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
      <div class="form-group">
        <label>Comments (optional)</label>
        <textarea name="comments" class="form-control" rows="2" placeholder="Add a note for the requester..."></textarea>
      </div>
      <div style="display:flex;gap:8px">
        <button type="submit" name="decision" value="approved" class="btn btn-success">
          <i class="fa fa-check"></i> Approve
        </button>
        <button type="submit" name="decision" value="returned" class="btn btn-warning"
                onclick="return confirm('Return this request for revision?')">
          <i class="fa fa-undo"></i> Return
        </button>
        <button type="submit" name="decision" value="rejected" class="btn btn-danger"
                onclick="return confirm('Reject this request?')">
          <i class="fa fa-times"></i> Reject
        </button>
      </div>
    </form>
  </div>
</div>
<?php endwhile; endif; ?>

<?php require_once BASE_URL . '/includes/footer.php'; ?>
