<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: ../../modules/dashboard/index.php'); exit;
}
require_once '../../config/db.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitise($conn, $_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $r = mysqli_query($conn, "SELECT * FROM users WHERE username='$username' AND is_active=1");
        $user = mysqli_fetch_assoc($r);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']   = $user['user_id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['role']      = $user['role'];
            $_SESSION['dept_id']   = $user['dept_id'];
            $_SESSION['profile_picture'] = $user['profile_picture'] ?? null;
            audit_log($conn, $user['user_id'], 'LOGIN', 'User logged in');
            header('Location: ../../modules/dashboard/index.php'); exit;
        } else {
            $error = 'Invalid username or password. Please try again.';
        }
    } else {
        $error = 'Please enter both username and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login — IMS, Faculty of Science</title>
<link rel="stylesheet" href="../../assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="login-page">
  <div class="login-box">
    <div class="brand">
      <h1><i class="fa fa-boxes"></i> IMS</h1>
      <p>Inventory Management System<br>Faculty of Science — Kabale University</p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label>Username</label>
        <input type="text" name="username" class="form-control"
               placeholder="Enter your username"
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus>
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" class="form-control"
               placeholder="Enter your password" required>
      </div>
      <button type="submit" class="btn btn-primary">
        <i class="fa fa-sign-in-alt"></i> Sign In
      </button>
    </form>

    <p style="text-align:center;margin-top:20px;font-size:12px;color:var(--text-muted)">
      Trouble logging in? Contact the System Administrator.
    </p>
  </div>
</div>
</body>
</html>
