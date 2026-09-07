<?php
define('BASE_URL', '../..');
require_once BASE_URL . '/config/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/modules/auth/login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$page_title = 'My Profile';
$message = '';
$error = '';

if (empty($_SESSION['profile_csrf'])) {
    $_SESSION['profile_csrf'] = bin2hex(random_bytes(32));
}

$user_result = mysqli_query($conn,
    "SELECT user_id, full_name, email, username, password, role, profile_picture
     FROM users WHERE user_id = $user_id AND is_active = 1 LIMIT 1"
);
$user = mysqli_fetch_assoc($user_result);
if (!$user) {
    session_destroy();
    header('Location: ' . BASE_URL . '/modules/auth/login.php');
    exit;
}

$remove_profile_picture = function ($picture) {
    if ($picture) {
        @unlink(__DIR__ . '/../../uploads/profile/' . basename($picture));
    }
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['profile_csrf'], $_POST['csrf_token'] ?? '')) {
        $error = 'Your session expired. Please try again.';
    } elseif (($_POST['action'] ?? '') === 'remove_picture') {
        if (!empty($user['profile_picture'])) {
            $stmt = mysqli_prepare($conn, 'UPDATE users SET profile_picture = NULL WHERE user_id = ?');
            mysqli_stmt_bind_param($stmt, 'i', $user_id);
            $saved = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            if ($saved) {
                $remove_profile_picture($user['profile_picture']);
                $user['profile_picture'] = null;
                $_SESSION['profile_picture'] = null;
                $message = 'Profile picture removed successfully.';
                audit_log($conn, $user_id, 'PROFILE_PICTURE_REMOVE', 'Removed profile picture');
            } else {
                $error = 'Profile picture could not be removed. Please try again.';
            }
        } else {
            $message = 'No profile picture to remove.';
        }
    } elseif (($_POST['action'] ?? '') === 'details') {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');

        if ($full_name === '' || $email === '' || $username === '') {
            $error = 'Full name, email and username are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
            $error = 'Username may contain letters, numbers, dots, underscores and hyphens.';
        } elseif (!empty($_FILES['profile_picture']['name'])) {
            $file = $_FILES['profile_picture'];
            $allowed_types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
            $file_info = new finfo(FILEINFO_MIME_TYPE);
            $mime = $file['error'] === UPLOAD_ERR_OK ? $file_info->file($file['tmp_name']) : '';

            if ($file['error'] !== UPLOAD_ERR_OK || !isset($allowed_types[$mime])) {
                $error = 'Please upload a valid JPG, PNG, GIF or WEBP image.';
            } elseif ($file['size'] > 2 * 1024 * 1024) {
                $error = 'Profile pictures must be 2 MB or smaller.';
            } else {
                $upload_path = __DIR__ . '/../../uploads/profile';
                if (!is_dir($upload_path) && !mkdir($upload_path, 0755, true)) {
                    $error = 'The profile picture folder could not be created.';
                } else {
                    $filename = 'user_' . $user_id . '_' . bin2hex(random_bytes(8)) . '.' . $allowed_types[$mime];
                    $relative_path = 'uploads/profile/' . $filename;
                    if (!move_uploaded_file($file['tmp_name'], $upload_path . '/' . $filename)) {
                        $error = 'The profile picture could not be saved.';
                    } else {
                        $stmt = mysqli_prepare($conn, 'UPDATE users SET full_name = ?, email = ?, username = ?, profile_picture = ? WHERE user_id = ?');
                        mysqli_stmt_bind_param($stmt, 'ssssi', $full_name, $email, $username, $relative_path, $user_id);
                        $saved = mysqli_stmt_execute($stmt);
                        mysqli_stmt_close($stmt);
                        if ($saved) {
                            $remove_profile_picture($user['profile_picture']);
                            $user['profile_picture'] = $relative_path;
                        } else {
                            @unlink($upload_path . '/' . $filename);
                            $error = 'Email or username is already in use.';
                        }
                    }
                }
            }
        } else {
            $stmt = mysqli_prepare($conn, 'UPDATE users SET full_name = ?, email = ?, username = ? WHERE user_id = ?');
            mysqli_stmt_bind_param($stmt, 'sssi', $full_name, $email, $username, $user_id);
            $saved = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            if (!$saved) $error = 'Email or username is already in use.';
        }

        if ($error === '') {
            $_SESSION['full_name'] = $full_name;
            $_SESSION['username'] = $username;
            $_SESSION['profile_picture'] = $user['profile_picture'] ?? null;
            $user['full_name'] = $full_name;
            $user['email'] = $email;
            $user['username'] = $username;
            $message = 'Profile details updated successfully.';
            audit_log($conn, $user_id, 'PROFILE_UPDATE', 'Updated personal profile details');
        }
    } elseif (($_POST['action'] ?? '') === 'password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (!password_verify($current_password, $user['password'])) {
            $error = 'Your current password is incorrect.';
        } elseif (strlen($new_password) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New password and confirmation do not match.';
        } else {
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn, 'UPDATE users SET password = ? WHERE user_id = ?');
            mysqli_stmt_bind_param($stmt, 'si', $password_hash, $user_id);
            $saved = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            if ($saved) {
                $user['password'] = $password_hash;
                $message = 'Password reset successfully.';
                audit_log($conn, $user_id, 'PASSWORD_RESET', 'User reset their password');
            } else {
                $error = 'Password could not be updated. Please try again.';
            }
        }
    }
}

require_once BASE_URL . '/includes/header.php';
?>

<div class="page-header">
  <div><h1>My Profile</h1><p>Manage your account details, profile picture and password.</p></div>
</div>

<?php if ($message): ?><div class="alert alert-success"><i class="fa fa-check-circle"></i> <?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><i class="fa fa-times-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="profile-layout">
  <div class="card card-body profile-summary">
    <?php if (!empty($user['profile_picture'])): ?>
      <img class="profile-picture" src="<?= BASE_URL . '/' . htmlspecialchars($user['profile_picture']) ?>" alt="Profile picture">
    <?php else: ?>
      <div class="profile-picture"><?= strtoupper(substr($user['full_name'], 0, 1)) ?></div>
    <?php endif; ?>
    <h2><?= htmlspecialchars($user['full_name']) ?></h2>
    <p>@<?= htmlspecialchars($user['username']) ?></p>
    <span class="badge badge-issued"><?= htmlspecialchars(str_replace('_', ' ', $user['role'])) ?></span>
  </div>

  <div>
    <div class="card">
      <div class="card-header"><h2>Profile Details</h2></div>
      <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['profile_csrf']) ?>">
          <input type="hidden" name="action" value="details">
          <div class="form-grid">
            <div class="form-group"><label>Full Name</label><input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>" required></div>
            <div class="form-group"><label>Username</label><input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required></div>
                        <div class="form-group"><label>Replace Profile Picture</label><input type="file" name="profile_picture" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp"></div>
          </div>
          <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Details</button>
        </form>
      </div>
    </div>

        <?php if (!empty($user['profile_picture'])): ?>
        <form method="POST" style="margin-top:-12px;margin-bottom:20px">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['profile_csrf']) ?>">
            <input type="hidden" name="action" value="remove_picture">
            <button type="submit" class="btn btn-danger"><i class="fa fa-trash"></i> Remove Profile Picture</button>
        </form>
        <?php endif; ?>

    <div class="card">
      <div class="card-header"><h2>Reset Password</h2></div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['profile_csrf']) ?>">
          <input type="hidden" name="action" value="password">
          <div class="form-grid">
            <div class="form-group"><label>Current Password</label><input type="password" name="current_password" class="form-control" required></div>
            <div class="form-group"><label>New Password</label><input type="password" name="new_password" class="form-control" minlength="8" required></div>
            <div class="form-group"><label>Confirm New Password</label><input type="password" name="confirm_password" class="form-control" minlength="8" required></div>
          </div>
          <button type="submit" class="btn btn-warning"><i class="fa fa-key"></i> Reset Password</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once BASE_URL . '/includes/footer.php'; ?>