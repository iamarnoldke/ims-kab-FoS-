<?php
$role = $_SESSION['role'] ?? '';
$uri  = $_SERVER['REQUEST_URI'] ?? '';
function nav_link($href, $icon, $label, $uri) {
    $active = (strpos($uri, $href) !== false) ? ' active' : '';
    return "<a href='" . BASE_URL . $href . "' class='$active'><i class='fa $icon'></i> $label</a>";
}
?>
<nav class="sidebar">
  <div class="sidebar-brand">
    <h1>IMS</h1>
    <p>Faculty of Science<br>Kabale University</p>
  </div>

  <div class="sidebar-nav">
    <?php echo nav_link('/modules/dashboard/index.php', 'fa-tachometer-alt', 'Dashboard', $uri); ?>
    <?php echo nav_link('/modules/profile/index.php', 'fa-user-circle', 'My Profile', $uri); ?>

    <?php if (in_array($role, ['store_keeper','admin','faculty_admin'])): ?>
    <div class="nav-section-label">Inventory</div>
    <?php echo nav_link('/modules/stock/register.php',   'fa-boxes',        'Stock Register', $uri); ?>
    <?php echo nav_link('/modules/goods_received/receive.php','fa-truck',   'Goods Received', $uri); ?>
    <?php echo nav_link('/modules/stock/adjust.php',     'fa-sliders-h',    'Stock Adjustments', $uri); ?>
    <?php endif; ?>

    <?php if (in_array($role, ['staff','hod','admin'])): ?>
    <div class="nav-section-label">Stock Requests</div>
    <?php endif; ?>

    <?php if (in_array($role, ['staff','admin'])): ?>
    <?php echo nav_link('/modules/requests/submit.php',  'fa-plus-circle',  'New Request', $uri); ?>
    <?php echo nav_link('/modules/requests/my_requests.php','fa-list',      'My Requests', $uri); ?>
    <?php endif; ?>

    <?php if (in_array($role, ['hod','admin'])): ?>
    <?php echo nav_link('/modules/requests/approve.php', 'fa-check-circle', 'Approve Requests', $uri); ?>
    <?php endif; ?>

    <?php if (in_array($role, ['store_keeper','admin'])): ?>
    <?php echo nav_link('/modules/issuance/issue.php',   'fa-hand-holding', 'Issue Stock', $uri); ?>
    <?php endif; ?>

    <?php if (in_array($role, ['store_keeper','admin','faculty_admin'])): ?>
    <div class="nav-section-label">Reports</div>
    <?php echo nav_link('/modules/reports/index.php',    'fa-chart-bar',    'Reports', $uri); ?>
    <?php echo nav_link('/modules/reports/audit.php',    'fa-history',      'Audit Trail', $uri); ?>
    <?php endif; ?>

    <?php if (in_array($role, ['admin','faculty_admin'])): ?>
    <div class="nav-section-label">Administration</div>
    <?php echo nav_link('/modules/admin/departments.php','fa-sitemap',      'Departments', $uri); ?>
    <?php endif; ?>

    <?php if ($role === 'admin'): ?>
    <?php echo nav_link('/modules/admin/users.php',      'fa-users',        'Manage Users', $uri); ?>
    <?php endif; ?>
  </div>

  <div class="sidebar-footer">
    &copy; <?= date('Y') ?> Kabale University
  </div>
</nav>
