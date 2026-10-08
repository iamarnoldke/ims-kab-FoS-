<?php
define('BASE_URL', '../..');
require_once BASE_URL . '/config/db.php';
$page_title = 'Dashboard';
require_once BASE_URL . '/includes/header.php';

// Stats
$total_items   = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM stock_items WHERE is_active=1"))['c'];
$low_stock     = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM stock_items WHERE qty_on_hand<=reorder_point AND is_active=1"))['c'];
$pending_reqs  = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM stock_requests WHERE status='pending'"))['c'];
$issued_today  = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM stock_issuances WHERE issued_date=CURDATE()"))['c'];

// Recent requests
$recent = mysqli_query($conn,
    "SELECT r.ref_number, r.status, r.created_at, u.full_name, d.dept_name
    FROM stock_requests r
    JOIN users u ON r.requester_id=u.user_id
    JOIN departments d ON r.dept_id=d.dept_id
    ORDER BY r.created_at DESC LIMIT 8"
);

// Low stock items
$low_items = mysqli_query($conn,
    "SELECT item_name, category, qty_on_hand, reorder_point, unit_of_measure
    FROM stock_items WHERE qty_on_hand<=reorder_point AND is_active=1 ORDER BY qty_on_hand ASC LIMIT 6"
);
?>

<div class="page-header">
  <div>
    <h1>Dashboard</h1>
    <p>Welcome back, <?= htmlspecialchars($current_user['full_name']) ?>. Here is today's overview.</p>
  </div>
  <div><?= date('l, d F Y') ?></div>
</div>

<!-- Stat cards -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fa fa-boxes"></i></div>
    <div class="stat-info"><p>Total Stock Items</p><h3><?= $total_items ?></h3></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon red"><i class="fa fa-exclamation-triangle"></i></div>
    <div class="stat-info"><p>Low Stock Alerts</p><h3><?= $low_stock ?></h3></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon orange"><i class="fa fa-clock"></i></div>
    <div class="stat-info"><p>Pending Requests</p><h3><?= $pending_reqs ?></h3></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><i class="fa fa-hand-holding"></i></div>
    <div class="stat-info"><p>Issued Today</p><h3><?= $issued_today ?></h3></div>
  </div>
</div>

<?php if ($low_stock > 0): ?>
<div class="low-stock-banner">
  <i class="fa fa-exclamation-triangle"></i>
  <span><strong><?= $low_stock ?> item(s)</strong> are at or below their reorder point and need restocking.</span>
  <a href="<?= BASE_URL ?>/modules/stock/register.php?filter=low" class="btn btn-sm btn-danger" style="margin-left:auto">View Items</a>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">

  <!-- Recent requests -->
  <div class="card">
    <div class="card-header">
      <h2>Recent Stock Requests</h2>
      <a href="<?= BASE_URL ?>/modules/requests/approve.php" class="btn btn-sm btn-outline">View All</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Ref</th><th>Requester</th><th>Dept</th><th>Status</th><th>Date</th></tr>
        </thead>
        <tbody>
        <?php if (mysqli_num_rows($recent) === 0): ?>
          <tr><td colspan="5"><div class="empty-state"><p>No requests yet.</p></div></td></tr>
        <?php else: while ($req = mysqli_fetch_assoc($recent)): ?>
          <tr>
            <td><strong><?= htmlspecialchars($req['ref_number']) ?></strong></td>
            <td><?= htmlspecialchars($req['full_name']) ?></td>
            <td><?= htmlspecialchars($req['dept_name']) ?></td>
            <td><span class="badge badge-<?= $req['status'] ?>"><?= $req['status'] ?></span></td>
            <td><?= date('d M Y', strtotime($req['created_at'])) ?></td>
          </tr>
        <?php endwhile; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Low stock items -->
  <div class="card">
    <div class="card-header">
      <h2>Low Stock Items</h2>
      <a href="<?= BASE_URL ?>/modules/stock/register.php" class="btn btn-sm btn-outline">Stock Register</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Item</th><th>Category</th><th>On Hand</th><th>Reorder</th><th>UoM</th></tr>
        </thead>
        <tbody>
        <?php if (mysqli_num_rows($low_items) === 0): ?>
          <tr><td colspan="5"><div class="empty-state"><p>All stock levels are healthy.</p></div></td></tr>
        <?php else: while ($item = mysqli_fetch_assoc($low_items)): ?>
          <tr>
            <td><strong><?= htmlspecialchars($item['item_name']) ?></strong></td>
            <td><?= htmlspecialchars($item['category']) ?></td>
            <td style="color:var(--danger);font-weight:600"><?= $item['qty_on_hand'] ?></td>
            <td><?= $item['reorder_point'] ?></td>
            <td><?= htmlspecialchars($item['unit_of_measure']) ?></td>
          </tr>
        <?php endwhile; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<?php require_once BASE_URL . '/includes/footer.php'; ?>
