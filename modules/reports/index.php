<?php
define('BASE_URL', '../..');
require_once BASE_URL . '/config/db.php';
$page_title = 'Reports';
$allowed_reports = ['stock_on_hand', 'low_stock', 'issuance', 'goods_received', 'consumption'];
$report = in_array($_GET['report'] ?? '', $allowed_reports, true) ? $_GET['report'] : 'stock_on_hand';
$from   = sanitise($conn, $_GET['from'] ?? '2000-01-01');
$to     = sanitise($conn, $_GET['to']   ?? date('Y-m-d'));
$is_print = ($_GET['print'] ?? '') === '1';

require_once BASE_URL . '/includes/header.php';
?>

<div class="page-header">
  <div><h1>Reports</h1><p>Inventory reports for the Faculty of Science store.</p></div>
</div>

<!-- Report selector -->
<div class="card report-controls" style="margin-bottom:16px">
  <div class="card-body" style="padding:14px 20px">
    <form method="GET" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end">
      <div class="form-group" style="margin:0;min-width:200px">
        <label>Report Type</label>
        <select name="report" class="form-control" onchange="this.form.submit()">
          <option value="stock_on_hand"  <?= $report==='stock_on_hand'?'selected':'' ?>>Stock on Hand</option>
          <option value="low_stock"      <?= $report==='low_stock'?'selected':'' ?>>Low Stock Report</option>
          <option value="issuance"       <?= $report==='issuance'?'selected':'' ?>>Stock Issuance Report</option>
          <option value="goods_received" <?= $report==='goods_received'?'selected':'' ?>>Goods Received Report</option>
          <option value="consumption"    <?= $report==='consumption'?'selected':'' ?>>Consumption by Department</option>
        </select>
      </div>
      <?php if (in_array($report,['issuance','goods_received','consumption'])): ?>
      <div class="form-group" style="margin:0">
        <label>From</label>
        <input type="date" name="from" class="form-control" value="<?= $from ?>">
      </div>
      <div class="form-group" style="margin:0">
        <label>To</label>
        <input type="date" name="to" class="form-control" value="<?= $to ?>">
      </div>
      <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-filter"></i> Filter</button>
      <?php endif; ?>
      <a href="?report=<?= $report ?>&from=<?= $from ?>&to=<?= $to ?>&print=1" target="_blank" class="btn btn-outline btn-sm" style="margin-left:auto">
        <i class="fa fa-print"></i> Print
      </a>
    </form>
  </div>
</div>

<!-- Report content -->
<div class="card report-output">
<?php

// 1. Stock on Hand
if ($report === 'stock_on_hand'):
    $rows = mysqli_query($conn, "SELECT * FROM stock_items WHERE is_active=1 ORDER BY category, item_name");
?>
  <div class="card-header"><h2>Stock on Hand Report — <?= date('d F Y') ?></h2></div>
  <div class="table-wrap"><table>
    <thead><tr><th>#</th><th>Item Name</th><th>Category</th><th>Qty on Hand</th><th>Reorder Point</th><th>UoM</th><th>Location</th><th>Status</th></tr></thead>
    <tbody>
    <?php $i=1; while($r=mysqli_fetch_assoc($rows)): $low=$r['qty_on_hand']<=$r['reorder_point']; ?>
    <tr>
      <td><?= $i++ ?></td>
      <td><?= htmlspecialchars($r['item_name']) ?></td>
      <td><?= htmlspecialchars($r['category']) ?></td>
      <td style="<?= $low?'color:var(--danger);font-weight:700':'' ?>"><?= $r['qty_on_hand'] ?></td>
      <td><?= $r['reorder_point'] ?></td>
      <td><?= htmlspecialchars($r['unit_of_measure']) ?></td>
      <td><?= htmlspecialchars($r['location']) ?></td>
      <td><span class="badge <?= $low?'badge-low':'badge-ok' ?>"><?= $low?'Low':'OK' ?></span></td>
    </tr>
    <?php endwhile; ?>
    </tbody>
  </table></div>

// 2. Low Stock
elseif ($report === 'low_stock'):
    $rows = mysqli_query($conn, "SELECT * FROM stock_items WHERE qty_on_hand<=reorder_point AND is_active=1 ORDER BY qty_on_hand ASC");
?>
  <div class="card-header"><h2>Low Stock Report — <?= date('d F Y') ?></h2></div>
  <div class="table-wrap"><table>
    <thead><tr><th>#</th><th>Stock ID</th><th>Item Name</th><th>Category</th><th>Qty on Hand</th><th>Reorder Point</th><th>Shortfall</th><th>UoM</th><th>Location</th><th>Created</th><th>Updated</th></tr></thead>
    <tbody>
    <?php $i=1; while($r=mysqli_fetch_assoc($rows)): ?>
    <tr>
      <td><?= $i++ ?></td>
      <td><?= (int)$r['stock_id'] ?></td>
      <td><?= htmlspecialchars($r['item_name']) ?></td>
      <td><?= htmlspecialchars($r['category']) ?></td>
      <td style="color:var(--danger);font-weight:700"><?= $r['qty_on_hand'] ?></td>
      <td><?= $r['reorder_point'] ?></td>
      <td style="color:var(--danger)">-<?= $r['reorder_point']-$r['qty_on_hand'] ?></td>
      <td><?= htmlspecialchars($r['unit_of_measure']) ?></td>
      <td><?= htmlspecialchars($r['location']) ?></td>
      <td><?= date('d M Y', strtotime($r['created_at'])) ?></td>
      <td><?= date('d M Y', strtotime($r['updated_at'])) ?></td>
    </tr>
    <?php endwhile; ?>
    </tbody>
  </table></div>

// 3. Issuance report
elseif ($report === 'issuance'):
    $rows = mysqli_query($conn,
         "SELECT iss.issuance_id, iss.request_id, iss.issued_date, iss.notes AS issuance_notes,
           r.ref_number, r.purpose, r.status AS request_status,
           u.full_name AS requester_name, d.dept_name,
           issuer.full_name AS issued_by_name,
           si.stock_id, si.item_name, si.category, ii.qty_issued, si.unit_of_measure, si.location
         FROM stock_issuance_items ii
         JOIN stock_issuances iss ON ii.issuance_id=iss.issuance_id
         JOIN stock_requests r ON iss.request_id=r.request_id
         JOIN users u ON r.requester_id=u.user_id
         JOIN users issuer ON iss.issued_by=issuer.user_id
         JOIN departments d ON r.dept_id=d.dept_id
         JOIN stock_items si ON ii.stock_id=si.stock_id
         WHERE iss.issued_date BETWEEN '$from' AND '$to'
         ORDER BY iss.issued_date DESC"
    );
?>
  <div class="card-header"><h2>Stock Issuance Report: <?= date('d M Y',strtotime($from)) ?> – <?= date('d M Y',strtotime($to)) ?></h2></div>
  <div class="table-wrap"><table>
    <thead><tr><th>Issuance ID</th><th>Request ID</th><th>Date</th><th>Ref</th><th>Requester</th><th>Department</th><th>Purpose</th><th>Status</th><th>Issued By</th><th>Stock ID</th><th>Item</th><th>Category</th><th>Qty Issued</th><th>UoM</th><th>Location</th><th>Notes</th></tr></thead>
    <tbody>
    <?php if(mysqli_num_rows($rows)===0): ?>
      <tr><td colspan="16"><div class="empty-state"><p>No issuances in this period.</p></div></td></tr>
    <?php else: while($r=mysqli_fetch_assoc($rows)): ?>
    <tr>
      <td><?= (int)$r['issuance_id'] ?></td>
      <td><?= (int)$r['request_id'] ?></td>
      <td><?= date('d M Y',strtotime($r['issued_date'])) ?></td>
      <td><?= htmlspecialchars($r['ref_number']) ?></td>
      <td><?= htmlspecialchars($r['requester_name']) ?></td>
      <td><?= htmlspecialchars($r['dept_name']) ?></td>
      <td><?= htmlspecialchars($r['purpose'] ?? '') ?></td>
      <td><?= htmlspecialchars($r['request_status']) ?></td>
      <td><?= htmlspecialchars($r['issued_by_name']) ?></td>
      <td><?= (int)$r['stock_id'] ?></td>
      <td><?= htmlspecialchars($r['item_name']) ?></td>
      <td><?= htmlspecialchars($r['category']) ?></td>
      <td><?= $r['qty_issued'] ?></td>
      <td><?= htmlspecialchars($r['unit_of_measure']) ?></td>
      <td><?= htmlspecialchars($r['location'] ?? '') ?></td>
      <td><?= htmlspecialchars($r['issuance_notes'] ?? '') ?></td>
    </tr>
    <?php endwhile; endif; ?>
    </tbody>
  </table></div>

// 4. Goods Received
elseif ($report === 'goods_received'):
    $rows = mysqli_query($conn,
         "SELECT g.gr_id, g.grn_number, g.received_date, g.created_at, g.notes,
           u.user_id AS received_by_id, u.full_name AS received_by_name,
           si.stock_id, si.item_name, si.category, gi.qty_received,
           si.unit_of_measure, si.location
         FROM goods_received_items gi
         JOIN goods_received g ON gi.gr_id=g.gr_id
         JOIN users u ON g.received_by=u.user_id
         JOIN stock_items si ON gi.stock_id=si.stock_id
         WHERE g.received_date BETWEEN '$from' AND '$to'
         ORDER BY g.received_date DESC"
    );
?>
  <div class="card-header"><h2>Goods Received Report: <?= date('d M Y',strtotime($from)) ?> – <?= date('d M Y',strtotime($to)) ?></h2></div>
  <div class="table-wrap"><table>
    <thead><tr><th>GR ID</th><th>GRN</th><th>Date</th><th>Created</th><th>Received By ID</th><th>Received By</th><th>Stock ID</th><th>Item</th><th>Category</th><th>Qty</th><th>UoM</th><th>Location</th><th>Notes</th></tr></thead>
    <tbody>
    <?php if(mysqli_num_rows($rows)===0): ?>
      <tr><td colspan="13"><div class="empty-state"><p>No goods received in this period.</p></div></td></tr>
    <?php else: while($r=mysqli_fetch_assoc($rows)): ?>
    <tr>
      <td><?= (int)$r['gr_id'] ?></td>
      <td><?= htmlspecialchars($r['grn_number']) ?></td>
      <td><?= date('d M Y',strtotime($r['received_date'])) ?></td>
      <td><?= date('d M Y H:i',strtotime($r['created_at'])) ?></td>
      <td><?= (int)$r['received_by_id'] ?></td>
      <td><?= htmlspecialchars($r['received_by_name']) ?></td>
      <td><?= (int)$r['stock_id'] ?></td>
      <td><?= htmlspecialchars($r['item_name']) ?></td>
      <td><?= htmlspecialchars($r['category']) ?></td>
      <td><?= $r['qty_received'] ?></td>
      <td><?= htmlspecialchars($r['unit_of_measure']) ?></td>
      <td><?= htmlspecialchars($r['location'] ?? '') ?></td>
      <td><?= htmlspecialchars($r['notes'] ?? '') ?></td>
    </tr>
    <?php endwhile; endif; ?>
    </tbody>
  </table></div>

// 5. Consumption by department
elseif ($report === 'consumption'):
    $rows = mysqli_query($conn,
        "SELECT d.dept_id, d.dept_name, si.stock_id, si.item_name, si.category,
          SUM(ii.qty_issued) AS total_issued, si.unit_of_measure, si.location,
          COUNT(DISTINCT r.request_id) AS request_count,
          COUNT(DISTINCT iss.issuance_id) AS issuance_count
         FROM stock_issuance_items ii
         JOIN stock_issuances iss ON ii.issuance_id=iss.issuance_id
         JOIN stock_requests r ON iss.request_id=r.request_id
         JOIN departments d ON r.dept_id=d.dept_id
         JOIN stock_items si ON ii.stock_id=si.stock_id
         WHERE iss.issued_date BETWEEN '$from' AND '$to'
         GROUP BY d.dept_name, si.item_name
         ORDER BY d.dept_name, total_issued DESC"
    );
?>
  <div class="card-header"><h2>Consumption by Department: <?= date('d M Y',strtotime($from)) ?> – <?= date('d M Y',strtotime($to)) ?></h2></div>
  <div class="table-wrap"><table>
    <thead><tr><th>Department ID</th><th>Department</th><th>Stock ID</th><th>Item</th><th>Category</th><th>Total Issued</th><th>UoM</th><th>Location</th><th>Requests</th><th>Issuances</th></tr></thead>
    <tbody>
    <?php if(mysqli_num_rows($rows)===0): ?>
      <tr><td colspan="10"><div class="empty-state"><p>No consumption data in this period.</p></div></td></tr>
    <?php else: while($r=mysqli_fetch_assoc($rows)): ?>
    <tr>
      <td><?= (int)$r['dept_id'] ?></td>
      <td><?= htmlspecialchars($r['dept_name']) ?></td>
      <td><?= (int)$r['stock_id'] ?></td>
      <td><?= htmlspecialchars($r['item_name']) ?></td>
      <td><?= htmlspecialchars($r['category']) ?></td>
      <td><strong><?= $r['total_issued'] ?></strong></td>
      <td><?= htmlspecialchars($r['unit_of_measure']) ?></td>
      <td><?= htmlspecialchars($r['location'] ?? '') ?></td>
      <td><?= (int)$r['request_count'] ?></td>
      <td><?= (int)$r['issuance_count'] ?></td>
    </tr>
    <?php endwhile; endif; ?>
    </tbody>
  </table></div>
<?php endif; ?>
</div>

<?php if ($is_print): ?>
<script>
  window.addEventListener('load', function () {
    window.print();
  });
</script>
<?php endif; ?>

<?php require_once BASE_URL . '/includes/footer.php'; ?>
