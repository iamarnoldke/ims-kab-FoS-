<?php
define('BASE_URL', '../..');
require_once BASE_URL . '/config/db.php';
$page_title = 'Stock Register';
require_once BASE_URL . '/includes/header.php';

// Handle add item
$msg = $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'add') {
        $name  = sanitise($conn, $_POST['item_name']);
        $cat   = sanitise($conn, $_POST['category']);
        $uom   = sanitise($conn, $_POST['unit_of_measure']);
        $qty   = (int)$_POST['qty_on_hand'];
        $rop   = (int)$_POST['reorder_point'];
        $loc   = sanitise($conn, $_POST['location']);
        $uid   = (int)$current_user['user_id'];

        if ($name && $cat && $uom && $rop > 0) {
            mysqli_query($conn,
                "INSERT INTO stock_items (item_name,category,unit_of_measure,qty_on_hand,reorder_point,location,created_by)
                 VALUES ('$name','$cat','$uom',$qty,$rop,'$loc',$uid)"
            );
            audit_log($conn, $uid, 'STOCK_ADD', "Added item: $name");
            $msg = "Item '$name' added successfully.";
        } else {
            $err = 'Please fill in all required fields.';
        }
    }

    if ($_POST['action'] === 'deactivate') {
        $sid = (int)$_POST['stock_id'];
        mysqli_query($conn, "UPDATE stock_items SET is_active=0 WHERE stock_id=$sid");
        audit_log($conn, $current_user['user_id'], 'STOCK_DEACTIVATE', "Deactivated stock item ID $sid");
        $msg = 'Item removed from active register.';
    }
}

// Filter
$filter = $_GET['filter'] ?? 'all';
$where  = $filter === 'low' ? "WHERE qty_on_hand <= reorder_point AND is_active=1" : "WHERE is_active=1";
$search = sanitise($conn, $_GET['q'] ?? '');
if ($search) $where .= ($filter === 'all' ? " WHERE is_active=1 AND" : " AND") . " (item_name LIKE '%$search%' OR category LIKE '%$search%')";

$items = mysqli_query($conn, "SELECT * FROM stock_items $where ORDER BY category, item_name");
$can_edit = in_array($current_user['role'], ['admin','store_keeper']);
?>

<div class="page-header">
  <div><h1>Stock Register</h1><p>Current inventory of all items in the Faculty of Science store.</p></div>
  <?php if ($can_edit): ?>
  <button class="btn btn-primary" onclick="document.getElementById('add-modal').style.display='flex'">
    <i class="fa fa-plus"></i> Add Item
  </button>
  <?php endif; ?>
</div>

<?php if ($msg): ?><div class="alert alert-success"><i class="fa fa-check-circle"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger"><i class="fa fa-times-circle"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>

<!-- Filter bar -->
<div class="card">
  <div class="card-header">
    <div class="flex items-center gap-2">
      <a href="?filter=all" class="btn btn-sm <?= $filter==='all'?'btn-primary':'btn-outline' ?>">All Items</a>
      <a href="?filter=low" class="btn btn-sm <?= $filter==='low'?'btn-danger':'btn-outline' ?>">
        <i class="fa fa-exclamation-triangle"></i> Low Stock Only
      </a>
    </div>
    <form method="GET" style="display:flex;gap:8px">
      <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
      <input type="text" name="q" class="form-control" placeholder="Search items..." value="<?= htmlspecialchars($search) ?>" style="width:220px">
      <button class="btn btn-outline btn-sm"><i class="fa fa-search"></i></button>
    </form>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>#</th><th>Item Name</th><th>Category</th><th>UoM</th><th>Qty on Hand</th><th>Reorder Point</th><th>Location</th><th>Status</th><?php if($can_edit):?><th>Action</th><?php endif;?></tr>
      </thead>
      <tbody>
      <?php if (mysqli_num_rows($items) === 0): ?>
        <tr><td colspan="9"><div class="empty-state"><i class="fa fa-boxes"></i><p>No stock items found.</p></div></td></tr>
      <?php else: $i=1; while ($item = mysqli_fetch_assoc($items)): $low = $item['qty_on_hand'] <= $item['reorder_point']; ?>
        <tr>
          <td><?= $i++ ?></td>
          <td><strong><?= htmlspecialchars($item['item_name']) ?></strong></td>
          <td><?= htmlspecialchars($item['category']) ?></td>
          <td><?= htmlspecialchars($item['unit_of_measure']) ?></td>
          <td style="<?= $low?'color:var(--danger);font-weight:700':'' ?>"><?= $item['qty_on_hand'] ?></td>
          <td><?= $item['reorder_point'] ?></td>
          <td><?= htmlspecialchars($item['location']) ?></td>
          <td><span class="badge <?= $low?'badge-low':'badge-ok' ?>"><?= $low?'Low Stock':'In Stock' ?></span></td>
          <?php if($can_edit): ?>
          <td>
            <form method="POST" onsubmit="return confirm('Remove this item from the register?')">
              <input type="hidden" name="action" value="deactivate">
              <input type="hidden" name="stock_id" value="<?= $item['stock_id'] ?>">
              <button class="btn btn-sm btn-danger"><i class="fa fa-trash"></i></button>
            </form>
          </td>
          <?php endif; ?>
        </tr>
      <?php endwhile; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Item Modal -->
<?php if ($can_edit): ?>
<div id="add-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:200;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:12px;padding:28px;width:520px;max-width:95vw;max-height:90vh;overflow-y:auto">
    <h2 style="margin-bottom:20px;font-size:16px">Add New Stock Item</h2>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="form-grid">
        <div class="form-group" style="grid-column:1/-1">
          <label>Item Name *</label>
          <input type="text" name="item_name" class="form-control" required>
        </div>
        <div class="form-group">
          <label>Category *</label>
          <select name="category" class="form-control" required>
            <option value="">— Select —</option>
            <option>Chemicals</option><option>Glassware</option><option>Equipment</option>
            <option>Consumables</option><option>Stationery</option><option>Other</option>
          </select>
        </div>
        <div class="form-group">
          <label>Unit of Measure *</label>
          <select name="unit_of_measure" class="form-control" required>
            <option value="">— Select —</option>
            <option>Pieces</option><option>Litres</option><option>Kg</option>
            <option>Packs</option><option>Box</option><option>Reams</option><option>Metres</option>
          </select>
        </div>
        <div class="form-group">
          <label>Opening Qty on Hand</label>
          <input type="number" name="qty_on_hand" class="form-control" value="0" min="0">
        </div>
        <div class="form-group">
          <label>Reorder Point *</label>
          <input type="number" name="reorder_point" class="form-control" value="5" min="1" required>
        </div>
        <div class="form-group" style="grid-column:1/-1">
          <label>Storage Location</label>
          <input type="text" name="location" class="form-control" placeholder="e.g. Shelf A1">
        </div>
      </div>
      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:8px">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('add-modal').style.display='none'">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Item</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php require_once BASE_URL . '/includes/footer.php'; ?>
