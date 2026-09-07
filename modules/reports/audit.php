<?php
define('BASE_URL', '../..');
require_once BASE_URL . '/config/db.php';
$page_title = 'Audit Trail';
require_once BASE_URL . '/includes/header.php';

$logs = mysqli_query($conn,
    "SELECT al.*, u.full_name FROM audit_log al
     LEFT JOIN users u ON al.user_id=u.user_id
     ORDER BY al.created_at DESC LIMIT 100"
);
?>

<div class="page-header">
  <div><h1>Audit Trail</h1><p>Complete log of all actions performed in the system.</p></div>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Date & Time</th><th>User</th><th>Action</th><th>Details</th><th>IP Address</th></tr></thead>
      <tbody>
      <?php if (mysqli_num_rows($logs) === 0): ?>
        <tr><td colspan="5"><div class="empty-state"><p>No audit records yet.</p></div></td></tr>
      <?php else: while($log = mysqli_fetch_assoc($logs)): ?>
      <tr>
        <td style="white-space:nowrap"><?= date('d M Y H:i', strtotime($log['created_at'])) ?></td>
        <td><?= htmlspecialchars($log['full_name'] ?? 'System') ?></td>
        <td><span class="badge badge-issued" style="font-size:10px"><?= htmlspecialchars($log['action_type']) ?></span></td>
        <td style="font-size:12px"><?= htmlspecialchars($log['details']) ?></td>
        <td style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars($log['ip_address']) ?></td>
      </tr>
      <?php endwhile; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once BASE_URL . '/includes/footer.php'; ?>
