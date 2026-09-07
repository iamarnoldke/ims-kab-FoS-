<?php
define('BASE_URL', '../..');
require_once BASE_URL . '/config/db.php';
$page_title = 'Manage Users';
require_once BASE_URL . '/includes/header.php';

// Admin only
if ($current_user['role'] !== 'admin') {
    echo '<div class="alert alert-danger">Access denied.</div>';
    require_once BASE_URL . '/includes/footer.php'; exit;
}

$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($_POST['action'] === 'add') {
        $name  = sanitise($conn, $_POST['full_name']);
        $email = sanitise($conn, $_POST['email']);
        $uname = sanitise($conn, $_POST['username']);
        $role  = sanitise($conn, $_POST['role']);
        $dept  = (int)$_POST['dept_id'];
        $pass  = password_hash($_POST['password'], PASSWORD_DEFAULT);

        if ($name && $email && $uname && $role && $_POST['password']) {
            $r = mysqli_query($conn,
                "INSERT INTO users (full_name,email,username,password,role,dept_id)
                 VALUES ('$name','$email','$uname','$pass','$role',$dept)"
            );
            if ($r) {
                audit_log($conn, $current_user['user_id'], 'USER_CREATE', "Created user: $uname");
                $msg = "User '$uname' created successfully.";
            } else {
                $err = 'Username or email already exists.';
            }
        } else { $err = 'Please fill in all required fields.'; }
    }

    if ($_POST['action'] === 'toggle') {
        $uid    = (int)$_POST['uid'];
        $status = (int)$_POST['status'];
        $new    = $status ? 0 : 1;
        mysqli_query($conn, "UPDATE users SET is_active=$new WHERE user_id=$uid");
        audit_log($conn, $current_user['user_id'], 'USER_TOGGLE', "Toggled user ID $uid to " . ($new?'active':'inactive'));
        $msg = 'User status updated.';
    }
}

$users = mysqli_query($conn,
    "SELECT u.*, d.dept_name FROM users u LEFT JOIN departments d ON u.dept_id=d.dept_id ORDER BY u.full_name"
);
$depts = mysqli_query($conn, "SELECT * FROM departments ORDER BY dept_name");
?>

<div class="page-header">
  <div><h1>Manage Users</h1><p>Create and manage system user accounts.</p></div>
  <button class="btn btn-primary" onclick="document.getElementById('add-user-modal').style.display='flex'">
    <i class="fa fa-user-plus"></i> Add User
  </button>
</div>

<?php if ($msg): ?><div class="alert alert-success"><i class="fa fa-check-circle"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger"><i class="fa fa-times-circle"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Department</th><th>Status</th><th>Action</th></tr></thead>
      <tbody>
      <?php while ($u = mysqli_fetch_assoc($users)): ?>
      <tr>
        <td><strong><?= htmlspecialchars($u['full_name']) ?></strong></td>
        <td><?= htmlspecialchars($u['username']) ?></td>
        <td><?= htmlspecialchars($u['email']) ?></td>
        <td><span class="badge badge-issued" style="text-transform:capitalize"><?= str_replace('_',' ',$u['role']) ?></span></td>
        <td><?= htmlspecialchars($u['dept_name'] ?? '—') ?></td>
        <td><span class="badge <?= $u['is_active']?'badge-approved':'badge-rejected' ?>"><?= $u['is_active']?'Active':'Inactive' ?></span></td>
        <td>
          <?php if ($u['user_id'] !== (int)$current_user['user_id']): ?>
          <form method="POST" style="display:inline">
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="uid" value="<?= $u['user_id'] ?>">
            <input type="hidden" name="status" value="<?= $u['is_active'] ?>">
            <button class="btn btn-sm <?= $u['is_active']?'btn-danger':'btn-success' ?>">
              <?= $u['is_active']?'Deactivate':'Activate' ?>
            </button>
          </form>
          <?php else: ?>
          <span style="color:var(--text-muted);font-size:12px">You</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add User Modal -->
<div id="add-user-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:200;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:12px;padding:28px;width:480px;max-width:95vw">
    <h2 style="margin-bottom:20px;font-size:16px">Add New User</h2>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="form-grid">
        <div class="form-group" style="grid-column:1/-1">
          <label>Full Name *</label>
          <input type="text" name="full_name" class="form-control" required>
        </div>
        <div class="form-group">
          <label>Email *</label>
          <input type="email" name="email" class="form-control" required>
        </div>
        <div class="form-group">
          <label>Username *</label>
          <input type="text" name="username" class="form-control" required>
        </div>
        <div class="form-group">
          <label>Role *</label>
          <select name="role" class="form-control" required>
            <option value="">— Select —</option>
            <option value="admin">Admin</option>
            <option value="store_keeper">Store Keeper</option>
            <option value="hod">HOD</option>
            <option value="staff">Staff / Lab Tech</option>
            <option value="faculty_admin">Faculty Admin</option>
          </select>
        </div>
        <div class="form-group">
          <label>Department</label>
          <select name="dept_id" class="form-control">
            <option value="0">— None —</option>
            <?php mysqli_data_seek($depts,0); while($d=mysqli_fetch_assoc($depts)): ?>
            <option value="<?= $d['dept_id'] ?>"><?= htmlspecialchars($d['dept_name']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="form-group" style="grid-column:1/-1">
          <label>Password *</label>
          <input type="password" name="password" class="form-control" required>
        </div>
      </div>
      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:8px">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('add-user-modal').style.display='none'">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Create User</button>
      </div>
    </form>
  </div>
</div>

<?php require_once BASE_URL . '/includes/footer.php'; ?>
