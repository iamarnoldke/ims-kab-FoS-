<?php
define('BASE_URL', '../..');
require_once BASE_URL . '/config/db.php';
$page_title = 'New Stock Request';
require_once BASE_URL . '/includes/header.php';

$msg = $err = '';
$stock_items = mysqli_query($conn, "SELECT * FROM stock_items WHERE is_active=1 ORDER BY category, item_name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $purpose  = sanitise($conn, $_POST['purpose'] ?? '');
    $dept_id  = (int)$current_user['dept_id'];
    $req_id_u = (int)$current_user['user_id'];
    $items    = $_POST['items'] ?? [];

    // Validate at least one item
    $valid_items = array_filter($items, fn($i) => !empty($i['stock_id']) && (int)$i['qty'] > 0);

    if (empty($valid_items)) {
        $err = 'Please add at least one item with a valid quantity.';
    } else {
        $ref = generate_ref($conn, 'REQ');
        mysqli_query($conn,
            "INSERT INTO stock_requests (ref_number,requester_id,dept_id,purpose,status)
             VALUES ('$ref',$req_id_u,$dept_id,'$purpose','pending')"
        );
        $request_id = mysqli_insert_id($conn);

        foreach ($valid_items as $item) {
            $sid = (int)$item['stock_id'];
            $qty = (int)$item['qty'];
            mysqli_query($conn,
                "INSERT INTO stock_request_items (request_id,stock_id,qty_requested)
                 VALUES ($request_id,$sid,$qty)"
            );
        }

        audit_log($conn, $req_id_u, 'REQUEST_SUBMIT', "Submitted stock request $ref");
        $msg = "Stock request <strong>$ref</strong> submitted successfully and is awaiting HOD approval.";
    }
}
?>

<div class="page-header">
  <div><h1>New Stock Request</h1><p>Request items from the Faculty of Science store.</p></div>
  <a href="my_requests.php" class="btn btn-outline"><i class="fa fa-list"></i> My Requests</a>
</div>

<?php if ($msg): ?><div class="alert alert-success"><i class="fa fa-check-circle"></i> <?= $msg ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger"><i class="fa fa-times-circle"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="card">
  <div class="card-header"><h2>Request Details</h2></div>
  <div class="card-body">
    <form method="POST" id="req-form">

      <div class="form-group">
        <label>Purpose / Justification</label>
        <textarea name="purpose" class="form-control" rows="2" placeholder="e.g. Required for BIO2101 lab session on 28 Aug 2026"></textarea>
      </div>

      <h3 style="font-size:14px;font-weight:600;margin-bottom:12px">Items Requested</h3>

      <div id="items-container">
        <div class="item-row" style="display:grid;grid-template-columns:2fr 1fr auto;gap:10px;margin-bottom:10px">
          <select name="items[0][stock_id]" class="form-control stock-select">
            <option value="">— Select item —</option>
            <?php mysqli_data_seek($stock_items, 0); while ($si = mysqli_fetch_assoc($stock_items)): ?>
            <option value="<?= $si['stock_id'] ?>" data-qty="<?= $si['qty_on_hand'] ?>" data-uom="<?= htmlspecialchars($si['unit_of_measure']) ?>">
              <?= htmlspecialchars($si['item_name']) ?> (<?= htmlspecialchars($si['category']) ?>) — <?= $si['qty_on_hand'] ?> <?= htmlspecialchars($si['unit_of_measure']) ?> available
            </option>
            <?php endwhile; ?>
          </select>
          <input type="number" name="items[0][qty]" class="form-control" placeholder="Qty" min="1">
          <button type="button" class="btn btn-outline btn-sm remove-row" style="display:none"><i class="fa fa-times"></i></button>
        </div>
      </div>

      <button type="button" id="add-item-btn" class="btn btn-outline btn-sm mt-1">
        <i class="fa fa-plus"></i> Add Another Item
      </button>

      <div style="margin-top:20px;display:flex;gap:8px;justify-content:flex-end">
        <a href="my_requests.php" class="btn btn-outline">Cancel</a>
        <button type="submit" class="btn btn-primary"><i class="fa fa-paper-plane"></i> Submit Request</button>
      </div>
    </form>
  </div>
</div>

<script>
// Store all options HTML once
const stockOptions = document.querySelector('.stock-select').innerHTML;
let rowCount = 1;

document.getElementById('add-item-btn').addEventListener('click', () => {
    const container = document.getElementById('items-container');
    const div = document.createElement('div');
    div.className = 'item-row';
    div.style.cssText = 'display:grid;grid-template-columns:2fr 1fr auto;gap:10px;margin-bottom:10px';
    div.innerHTML = `
      <select name="items[${rowCount}][stock_id]" class="form-control stock-select">${stockOptions}</select>
      <input type="number" name="items[${rowCount}][qty]" class="form-control" placeholder="Qty" min="1">
      <button type="button" class="btn btn-outline btn-sm remove-row"><i class="fa fa-times"></i></button>
    `;
    container.appendChild(div);
    rowCount++;
    updateRemoveButtons();
});

document.addEventListener('click', e => {
    if (e.target.closest('.remove-row')) {
        e.target.closest('.item-row').remove();
        updateRemoveButtons();
    }
});

function updateRemoveButtons() {
    const rows = document.querySelectorAll('.item-row');
    rows.forEach(r => {
        r.querySelector('.remove-row').style.display = rows.length > 1 ? 'inline-flex' : 'none';
    });
}
</script>

<?php require_once BASE_URL . '/includes/footer.php'; ?>
