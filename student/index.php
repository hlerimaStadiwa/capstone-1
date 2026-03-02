<?php
// student/index.php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}
header("Location: dashboard.php");
exit();
?>
