<?php
define('BASE_URL', '../..');
require_once BASE_URL . '/config/db.php';
$page_title = 'Departments';
require_once BASE_URL . '/includes/header.php';

if (!in_array($current_user['role'], ['admin', 'faculty_admin'])) {
    echo '<div class="alert alert-danger">Access denied.</div>';
    require_once BASE_URL . '/includes/footer.php';
    exit;
}

$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $dept_name = trim($_POST['dept_name'] ?? '');

    if ($dept_name === '') {
        $err = 'Please enter a department name.';
    } elseif (strlen($dept_name) > 100) {
        $err = 'Department names must be 100 characters or fewer.';
    } else {
        $check = mysqli_prepare($conn, 'SELECT dept_id FROM departments WHERE LOWER(dept_name) = LOWER(?) LIMIT 1');
        mysqli_stmt_bind_param($check, 's', $dept_name);
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);

        if (mysqli_stmt_num_rows($check) > 0) {
            $err = 'That department already exists.';
        } else {
            $insert = mysqli_prepare($conn, 'INSERT INTO departments (dept_name) VALUES (?)');
            mysqli_stmt_bind_param($insert, 's', $dept_name);
            if (mysqli_stmt_execute($insert)) {
                audit_log($conn, $current_user['user_id'], 'DEPARTMENT_CREATE', "Created department: $dept_name");
                $msg = "Department '$dept_name' added successfully.";
            } else {
                $err = 'Unable to add the department.';
            }
            mysqli_stmt_close($insert);
        }
        mysqli_stmt_close($check);
    }
}

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    $dept_id = (int)($_POST['dept_id'] ?? 0);
    $dept_name = trim($_POST['dept_name'] ?? '');

    if ($dept_id < 1) {
      $err = 'Invalid department selected.';
    } elseif ($dept_name === '') {
      $err = 'Please enter a department name.';
    } elseif (strlen($dept_name) > 100) {
      $err = 'Department names must be 100 characters or fewer.';
    } else {
      $check = mysqli_prepare($conn,
        'SELECT dept_id FROM departments
         WHERE LOWER(dept_name) = LOWER(?) AND dept_id <> ? LIMIT 1'
      );
      mysqli_stmt_bind_param($check, 'si', $dept_name, $dept_id);
      mysqli_stmt_execute($check);
      mysqli_stmt_store_result($check);

      if (mysqli_stmt_num_rows($check) > 0) {
        $err = 'That department already exists.';
      } else {
        $update = mysqli_prepare($conn, 'UPDATE departments SET dept_name = ? WHERE dept_id = ?');
        mysqli_stmt_bind_param($update, 'si', $dept_name, $dept_id);
        if (mysqli_stmt_execute($update) && mysqli_stmt_affected_rows($update) > 0) {
          audit_log($conn, $current_user['user_id'], 'DEPARTMENT_UPDATE', "Updated department to: $dept_name");
          $msg = "Department updated to '$dept_name'.";
        } elseif (mysqli_stmt_affected_rows($update) === 0) {
          $exists = mysqli_prepare($conn, 'SELECT dept_id FROM departments WHERE dept_id = ?');
          mysqli_stmt_bind_param($exists, 'i', $dept_id);
          mysqli_stmt_execute($exists);
          mysqli_stmt_store_result($exists);
          $msg = mysqli_stmt_num_rows($exists) > 0 ? 'No department changes were made.' : 'Department not found.';
          mysqli_stmt_close($exists);
        } else {
          $err = 'Unable to update the department.';
        }
        mysqli_stmt_close($update);
      }
      mysqli_stmt_close($check);
    }
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $dept_id = (int)($_POST['dept_id'] ?? 0);

    if ($dept_id < 1) {
      $err = 'Invalid department selected.';
    } else {
      $usage = mysqli_prepare($conn,
        'SELECT
          (SELECT COUNT(*) FROM users WHERE dept_id = ?) AS user_count,
          (SELECT COUNT(*) FROM stock_requests WHERE dept_id = ?) AS request_count
         FROM departments WHERE dept_id = ?'
      );
      mysqli_stmt_bind_param($usage, 'iii', $dept_id, $dept_id, $dept_id);
      mysqli_stmt_execute($usage);
      $usage_result = mysqli_stmt_get_result($usage);
      $usage_data = mysqli_fetch_assoc($usage_result);
      mysqli_stmt_close($usage);

      if (!$usage_data) {
        $err = 'Department not found.';
      } elseif ((int)$usage_data['user_count'] > 0 || (int)$usage_data['request_count'] > 0) {
        $err = 'This department cannot be deleted because it is linked to '
          . (int)$usage_data['user_count'] . ' user(s) and '
          . (int)$usage_data['request_count'] . ' request(s).';
      } else {
        $department = mysqli_prepare($conn, 'SELECT dept_name FROM departments WHERE dept_id = ?');
        mysqli_stmt_bind_param($department, 'i', $dept_id);
        mysqli_stmt_execute($department);
        $department_result = mysqli_stmt_get_result($department);
        $department_data = mysqli_fetch_assoc($department_result);
        mysqli_stmt_close($department);

        $delete = mysqli_prepare($conn, 'DELETE FROM departments WHERE dept_id = ?');
        mysqli_stmt_bind_param($delete, 'i', $dept_id);
        if (mysqli_stmt_execute($delete)) {
          audit_log($conn, $current_user['user_id'], 'DEPARTMENT_DELETE',
            'Deleted department: ' . ($department_data['dept_name'] ?? $dept_id));
          $msg = 'Department deleted successfully.';
        } else {
          $err = 'Unable to delete the department.';
        }
        mysqli_stmt_close($delete);
      }
    }
  }

$departments = mysqli_query($conn,
    'SELECT d.dept_id, d.dept_name, d.created_at, COUNT(u.user_id) AS user_count
     FROM departments d
     LEFT JOIN users u ON u.dept_id = d.dept_id
     GROUP BY d.dept_id, d.dept_name, d.created_at
     ORDER BY d.dept_name'
);
?>

<div class="page-header">
  <div>
    <h1>Departments</h1>
    <p>View and manage Faculty of Science departments.</p>
  </div>
  <button class="btn btn-primary" onclick="document.getElementById('add-department-modal').style.display='flex'">
    <i class="fa fa-plus"></i> Add Department
  </button>
</div>

<?php if ($msg): ?><div class="alert alert-success"><i class="fa fa-check-circle"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger"><i class="fa fa-times-circle"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Department</th><th>Users</th><th>Created</th><th>Action</th></tr></thead>
      <tbody>
      <?php if (mysqli_num_rows($departments) === 0): ?>
        <tr><td colspan="4"><div class="empty-state"><p>No departments have been added yet.</p></div></td></tr>
      <?php else: while ($department = mysqli_fetch_assoc($departments)): ?>
        <tr>
          <td><strong><?= htmlspecialchars($department['dept_name']) ?></strong></td>
          <td><?= (int)$department['user_count'] ?></td>
          <td><?= date('d M Y', strtotime($department['created_at'])) ?></td>
          <td style="display:flex;gap:6px;align-items:center">
            <button type="button" class="btn btn-outline" onclick="document.getElementById('edit-department-<?= (int)$department['dept_id'] ?>').style.display='flex'">
              <i class="fa fa-edit"></i> Edit
            </button>
            <form method="POST" onsubmit="return confirm('Delete this department? Departments linked to users or requests cannot be deleted.');">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="dept_id" value="<?= (int)$department['dept_id'] ?>">
              <button type="submit" class="btn btn-outline" style="color:#b42318;border-color:#f0b5b0">
                <i class="fa fa-trash"></i> Delete
              </button>
            </form>
          </td>
        </tr>
        <div id="edit-department-<?= (int)$department['dept_id'] ?>" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:200;align-items:center;justify-content:center">
          <div style="background:#fff;border-radius:12px;padding:28px;width:420px;max-width:95vw">
            <h2 style="margin-bottom:20px;font-size:16px">Edit Department</h2>
            <form method="POST">
              <input type="hidden" name="action" value="edit">
              <input type="hidden" name="dept_id" value="<?= (int)$department['dept_id'] ?>">
              <div class="form-group">
                <label for="dept_name_<?= (int)$department['dept_id'] ?>">Department Name *</label>
                <input id="dept_name_<?= (int)$department['dept_id'] ?>" type="text" name="dept_name" class="form-control" maxlength="100" value="<?= htmlspecialchars($department['dept_name'], ENT_QUOTES, 'UTF-8') ?>" required>
              </div>
              <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:8px">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('edit-department-<?= (int)$department['dept_id'] ?>').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Changes</button>
              </div>
            </form>
          </div>
        </div>
      <?php endwhile; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div id="add-department-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:200;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:12px;padding:28px;width:420px;max-width:95vw">
    <h2 style="margin-bottom:20px;font-size:16px">Add Department</h2>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="form-group">
        <label for="dept_name">Department Name *</label>
        <input id="dept_name" type="text" name="dept_name" class="form-control" maxlength="100" required autofocus>
      </div>
      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:8px">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('add-department-modal').style.display='none'">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Add Department</button>
      </div>
    </form>
  </div>
</div>

<?php require_once BASE_URL . '/includes/footer.php'; ?>