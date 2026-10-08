<?php
define('BASE_URL', '../..');
require_once BASE_URL . '/config/db.php';
$page_title = 'Goods Received';
require_once BASE_URL . '/includes/header.php';

$msg = $err = '';
$stock_items = mysqli_query($conn, "SELECT * FROM stock_items WHERE is_active=1 ORDER BY category, item_name");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'receive') {
    $notes      = sanitise($conn, $_POST['notes'] ?? '');
    $recv_by    = (int)$current_user['user_id'];
    $items      = $_POST['items'] ?? [];
    $valid_items = array_filter($items, fn($i) => !empty($i['stock_id']) && (int)$i['qty'] > 0);

    if (empty($valid_items)) {
        $err = 'Please add at least one item with a valid quantity.';
    } else {
        $grn = generate_grn($conn);
        mysqli_query($conn,
            "INSERT INTO goods_received (grn_number,received_by,received_date,notes)
             VALUES ('$grn',$recv_by,CURDATE(),'$notes')"
        );
        $gr_id = mysqli_insert_id($conn);

        foreach ($valid_items as $item) {
            $sid = (int)$item['stock_id'];
            $qty = (int)$item['qty'];
            mysqli_query($conn,
                "INSERT INTO goods_received_items (gr_id,stock_id,qty_received) VALUES ($gr_id,$sid,$qty)"
            );
            mysqli_query($conn, "UPDATE stock_items SET qty_on_hand=qty_on_hand+$qty WHERE stock_id=$sid");
        }

        audit_log($conn, $recv_by, 'GOODS_RECEIVED', "GRN $grn — $gr_id recorded");
        $msg = "Goods received recorded successfully. GRN: <strong>$grn</strong>.";
    }
}

// Recent GRNs
$recent_grns = mysqli_query($conn,
    "SELECT g.*, u.full_name FROM goods_received g
     JOIN users u ON g.received_by=u.user_id
     ORDER BY g.created_at DESC LIMIT 10"
);
?>

<div class="page-header">
  <div><h1>Stock Received</h1><p>Record stock received into the store and update inventory levels.</p></div>
</div>

<?php if ($msg): ?><div class="alert alert-success"><i class="fa fa-check-circle"></i> <?= $msg ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger"><i class="fa fa-times-circle"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1.4fr 1fr;gap:16px;align-items:start">

<!-- Record GRN -->
<div class="card">
  <div class="card-header"><h2>Record Stock Received</h2></div>
  <div class="card-body">
    <form method="POST">
      <input type="hidden" name="action" value="receive">
      <div class="form-group">
        <label>Notes / Remarks</label>
        <input type="text" name="notes" class="form-control" placeholder="e.g. Delivered by ABC Suppliers, LPO #1234">
      </div>

      <h3 style="font-size:14px;font-weight:600;margin-bottom:10px">Items Received</h3>
      <div id="grn-items">
        <div class="grn-row" style="display:grid;grid-template-columns:2fr 1fr auto;gap:8px;margin-bottom:8px">
          <select name="items[0][stock_id]" class="form-control grn-select">
            <option value="">— Select item —</option>
            <?php mysqli_data_seek($stock_items,0); while($si=mysqli_fetch_assoc($stock_items)): ?>
            <option value="<?= $si['stock_id'] ?>"><?= htmlspecialchars($si['item_name']) ?> (<?= htmlspecialchars($si['unit_of_measure']) ?>)</option>
            <?php endwhile; ?>
          </select>
          <input type="number" name="items[0][qty]" class="form-control" placeholder="Qty" min="1">
          <button type="button" class="btn btn-outline btn-sm grn-remove" style="display:none"><i class="fa fa-times"></i></button>
        </div>
      </div>
      <button type="button" id="grn-add" class="btn btn-outline btn-sm mt-1"><i class="fa fa-plus"></i> Add Item</button>

      <div style="margin-top:18px">
        <button type="submit" class="btn btn-primary"><i class="fa fa-truck"></i> Record Receipt</button>
      </div>
    </form>
  </div>
</div>

<!-- Recent GRNs -->
<div class="card">
  <div class="card-header"><h2>Recent GRNs</h2></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>GRN</th><th>Received By</th><th>Date</th><th>Notes</th></tr></thead>
      <tbody>
      <?php if (mysqli_num_rows($recent_grns) === 0): ?>
        <tr><td colspan="4"><div class="empty-state"><p>No goods received yet.</p></div></td></tr>
      <?php else: while ($g = mysqli_fetch_assoc($recent_grns)): ?>
        <tr>
          <td><strong><?= htmlspecialchars($g['grn_number']) ?></strong></td>
          <td><?= htmlspecialchars($g['full_name']) ?></td>
          <td><?= date('d M Y', strtotime($g['received_date'])) ?></td>
          <td style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars(substr($g['notes']??'',0,40)) ?></td>
        </tr>
      <?php endwhile; endif; ?>
      </tbody>
    </table>
  </div>
</div>

</div>

<script>
const grnOptions = document.querySelector('.grn-select').innerHTML;
let grnCount = 1;
document.getElementById('grn-add').addEventListener('click', () => {
    const container = document.getElementById('grn-items');
    const div = document.createElement('div');
    div.className = 'grn-row';
    div.style.cssText = 'display:grid;grid-template-columns:2fr 1fr auto;gap:8px;margin-bottom:8px';
    div.innerHTML = `<select name="items[${grnCount}][stock_id]" class="form-control grn-select">${grnOptions}</select>
      <input type="number" name="items[${grnCount}][qty]" class="form-control" placeholder="Qty" min="1">
      <button type="button" class="btn btn-outline btn-sm grn-remove"><i class="fa fa-times"></i></button>`;
    container.appendChild(div);
    grnCount++;
    updateGrnRemove();
});
document.addEventListener('click', e => {
    if (e.target.closest('.grn-remove')) { e.target.closest('.grn-row').remove(); updateGrnRemove(); }
});
function updateGrnRemove() {
    const rows = document.querySelectorAll('.grn-row');
    rows.forEach(r => r.querySelector('.grn-remove').style.display = rows.length > 1 ? 'inline-flex' : 'none');
}
</script>

<?php require_once BASE_URL . '/includes/footer.php'; ?>
