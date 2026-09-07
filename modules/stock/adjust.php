<?php
define('BASE_URL', '../..');
require_once BASE_URL . '/config/db.php';
$page_title = 'Stock Adjustments';
require_once BASE_URL . '/includes/header.php';

$msg = $err = '';
$stock_items = mysqli_query($conn, "SELECT * FROM stock_items WHERE is_active=1 ORDER BY item_name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stock_id  = (int)$_POST['stock_id'];
    $type      = $_POST['adj_type'] === 'increase' ? 1 : -1;
    $qty       = (int)$_POST['qty'];
    $reason    = sanitise($conn, $_POST['reason']);
    $adj_by    = (int)$current_user['user_id'];
    $qty_change = $type * $qty;

    if (!$stock_id || $qty <= 0 || !$reason) {
        $err = 'Please fill in all fields.';
    } else {
        // Check stock won't go negative
        $cur = mysqli_fetch_assoc(mysqli_query($conn, "SELECT qty_on_hand FROM stock_items WHERE stock_id=$stock_id"));
        if ($cur['qty_on_hand'] + $qty_change < 0) {
            $err = 'Adjustment would result in negative stock. Current quantity: ' . $cur['qty_on_hand'];
        } else {
            mysqli_query($conn,
                "INSERT INTO stock_adjustments (stock_id,adjusted_by,qty_change,reason) VALUES ($stock_id,$adj_by,$qty_change,'$reason')"
            );
            mysqli_query($conn, "UPDATE stock_items SET qty_on_hand=qty_on_hand+($qty_change) WHERE stock_id=$stock_id");
            audit_log($conn, $adj_by, 'STOCK_ADJUST', "Adjusted stock ID $stock_id by $qty_change. Reason: $reason");
            $msg = 'Stock adjustment recorded successfully.';
        }
    }
}

// Recent adjustments
$adjustments = mysqli_query($conn,
    "SELECT a.*, si.item_name, u.full_name FROM stock_adjustments a
     JOIN stock_items si ON a.stock_id=si.stock_id
     JOIN users u ON a.adjusted_by=u.user_id
     ORDER BY a.adj_date DESC LIMIT 20"
);
?>

<div class="page-header">
  <div><h1>Stock Adjustments</h1><p>Manually adjust stock quantities with a recorded reason.</p></div>
</div>

<?php if ($msg): ?><div class="alert alert-success"><i class="fa fa-check-circle"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger"><i class="fa fa-times-circle"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1.6fr;gap:16px;align-items:start">

<!-- Adjustment form -->
<div class="card">
  <div class="card-header"><h2>New Adjustment</h2></div>
  <div class="card-body">
    <form method="POST">
      <div class="form-group">
        <label>Stock Item *</label>
        <select name="stock_id" class="form-control" required>
          <option value="">— Select item —</option>
          <?php mysqli_data_seek($stock_items,0); while($si=mysqli_fetch_assoc($stock_items)): ?>
          <option value="<?= $si['stock_id'] ?>"><?= htmlspecialchars($si['item_name']) ?> (<?= $si['qty_on_hand'] ?> <?= htmlspecialchars($si['unit_of_measure']) ?>)</option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Adjustment Type *</label>
        <select name="adj_type" class="form-control" required>
          <option value="increase">Increase (add stock)</option>
          <option value="decrease">Decrease (remove stock)</option>
        </select>
      </div>
      <div class="form-group">
        <label>Quantity *</label>
        <input type="number" name="qty" class="form-control" min="1" required placeholder="Enter quantity">
      </div>
      <div class="form-group">
        <label>Reason *</label>
        <textarea name="reason" class="form-control" rows="3" required
                  placeholder="e.g. Breakage, expired items, physical count correction..."></textarea>
      </div>
      <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Adjustment</button>
    </form>
  </div>
</div>

<!-- Recent adjustments -->
<div class="card">
  <div class="card-header"><h2>Recent Adjustments</h2></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Item</th><th>Change</th><th>Reason</th><th>By</th><th>Date</th></tr></thead>
      <tbody>
      <?php if (mysqli_num_rows($adjustments) === 0): ?>
        <tr><td colspan="5"><div class="empty-state"><p>No adjustments recorded yet.</p></div></td></tr>
      <?php else: while ($adj = mysqli_fetch_assoc($adjustments)): $pos = $adj['qty_change'] > 0; ?>
        <tr>
          <td><?= htmlspecialchars($adj['item_name']) ?></td>
          <td style="font-weight:700;color:<?= $pos?'var(--accent-dark)':'var(--danger)' ?>">
            <?= $pos ? '+' : '' ?><?= $adj['qty_change'] ?>
          </td>
          <td style="font-size:12px"><?= htmlspecialchars(substr($adj['reason'],0,50)) ?></td>
          <td><?= htmlspecialchars($adj['full_name']) ?></td>
          <td><?= date('d M Y', strtotime($adj['adj_date'])) ?></td>
        </tr>
      <?php endwhile; endif; ?>
      </tbody>
    </table>
  </div>
</div>
</div>

<?php require_once BASE_URL . '/includes/footer.php'; ?>
