<?php
// teacher/index.php
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}
header("Location: dashboard.php");
exit();
?>
