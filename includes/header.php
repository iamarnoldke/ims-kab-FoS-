<?php
// ── Session & auth guard ───────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/modules/auth/login.php');
    exit;
}
$current_user = $_SESSION;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($page_title ?? 'IMS') ?> — Faculty of Science, Kabale University</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="<?= !empty($is_print) ? 'print-mode' : '' ?>">
<div class="ims-wrapper">
<?php require_once __DIR__ . '/sidebar.php'; ?>
<div class="main">

<!-- Topbar -->
<div class="topbar">
  <div class="topbar-title"><?= htmlspecialchars($page_title ?? '') ?></div>
  <div class="topbar-right">
    <?php
    // Low stock alert count
    global $conn;
    $ls = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM stock_items WHERE qty_on_hand <= reorder_point AND is_active = 1");
    $ls_count = mysqli_fetch_assoc($ls)['cnt'];
    if ($ls_count > 0): ?>
    <a href="<?= BASE_URL ?>/modules/stock/register.php?filter=low" class="btn btn-sm btn-danger" title="Low stock items">
      <i class="fa fa-exclamation-triangle"></i> <?= $ls_count ?> Low Stock
    </a>
    <?php endif; ?>
    <div class="topbar-user">
      <a href="<?= BASE_URL ?>/modules/profile/index.php" class="avatar" title="My profile">
        <?php if (!empty($current_user['profile_picture'])): ?>
          <img src="<?= BASE_URL . '/' . htmlspecialchars($current_user['profile_picture']) ?>" alt="Profile picture">
        <?php else: ?>
          <?= strtoupper(substr($current_user['full_name'], 0, 1)) ?>
        <?php endif; ?>
      </a>
      <div>
        <div style="font-weight:600;line-height:1.2"><?= htmlspecialchars($current_user['full_name']) ?></div>
        <div style="font-size:11px;color:var(--text-muted);text-transform:capitalize">
          <?= str_replace('_', ' ', $current_user['role']) ?>
        </div>
      </div>
    </div>
    <a href="<?= BASE_URL ?>/modules/profile/index.php" class="btn btn-outline btn-sm">
      <i class="fa fa-user"></i> Profile
    </a>
    <a href="<?= BASE_URL ?>/modules/auth/logout.php" class="btn btn-outline btn-sm">
      <i class="fa fa-sign-out-alt"></i> Logout
    </a>
  </div>
</div>

<div class="content">
