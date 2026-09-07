<?php
define('BASE_URL', '../..');
require_once BASE_URL . '/config/db.php';
$page_title = 'My Requests';
require_once BASE_URL . '/includes/header.php';

// Cancel
if (isset($_POST['cancel_id'])) {
    $cid = (int)$_POST['cancel_id'];
    $uid = (int)$current_user['user_id'];
    mysqli_query($conn, "UPDATE stock_requests SET status='cancelled' WHERE request_id=$cid AND requester_id=$uid AND status='pending'");
    audit_log($conn, $uid, 'REQUEST_CANCEL', "Cancelled request ID $cid");
}

$uid  = (int)$current_user['user_id'];
$reqs = mysqli_query($conn,
    "SELECT r.*, d.dept_name FROM stock_requests r
     JOIN departments d ON r.dept_id=d.dept_id
     WHERE r.requester_id=$uid ORDER BY r.created_at DESC"
);
?>

<div class="page-header">
  <div><h1>My Stock Requests</h1><p>View and track all stock requests you have submitted.</p></div>
  <a href="submit.php" class="btn btn-primary"><i class="fa fa-plus"></i> New Request</a>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Ref Number</th><th>Department</th><th>Purpose</th><th>Status</th><th>Date</th><th>Action</th></tr>
      </thead>
      <tbody>
      <?php if (mysqli_num_rows($reqs) === 0): ?>
        <tr><td colspan="6"><div class="empty-state"><i class="fa fa-list"></i><p>You have not submitted any requests yet.</p></div></td></tr>
      <?php else: while ($r = mysqli_fetch_assoc($reqs)): ?>
        <tr>
          <td><strong><?= htmlspecialchars($r['ref_number']) ?></strong></td>
          <td><?= htmlspecialchars($r['dept_name']) ?></td>
          <td><?= htmlspecialchars(substr($r['purpose'] ?? '—', 0, 60)) ?></td>
          <td><span class="badge badge-<?= $r['status'] ?>"><?= $r['status'] ?></span></td>
          <td><?= date('d M Y', strtotime($r['created_at'])) ?></td>
          <td>
            <?php if ($r['status'] === 'pending'): ?>
            <form method="POST" onsubmit="return confirm('Cancel this request?')" style="display:inline">
              <input type="hidden" name="cancel_id" value="<?= $r['request_id'] ?>">
              <button class="btn btn-sm btn-danger"><i class="fa fa-times"></i> Cancel</button>
            </form>
            <?php else: ?>
            <span style="color:var(--text-muted);font-size:12px">—</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endwhile; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once BASE_URL . '/includes/footer.php'; ?>
