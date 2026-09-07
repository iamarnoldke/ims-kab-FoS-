<?php
session_start();
require_once '../../config/db.php';
if (isset($_SESSION['user_id'])) {
    audit_log($conn, $_SESSION['user_id'], 'LOGOUT', 'User logged out');
}
session_destroy();
header('Location: ../../modules/auth/login.php');
exit;
