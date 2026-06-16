<?php
// includes/header.php
// This file assumes it is included inside the <body> of a page
// It provides the Navigation Bar

// Simple path logic to determine links
$current_dir = dirname($_SERVER['PHP_SELF']);
$is_admin = strpos($current_dir, '/admin') !== false;
$root_path = $is_admin ? '../' : './';
$pendingRequestsCount = 0;
if ($is_admin && isset($db)) {
    try {
        $pendingRequestsCount = (int)$db->query("SELECT COUNT(*) FROM grade_update_requests WHERE status = 'Pending'")->fetchColumn();
    } catch (Exception $e) {
        $pendingRequestsCount = 0;
    }
}
?>

<button class="sidebar-toggle" type="button" aria-label="Toggle navigation">☰</button>
<aside class="site-sidebar" aria-label="Site navigation">
    <div class="sidebar-logo">
        <h2>Danborough College</h2>
    </div>
    <nav class="site-nav">
        <?php if ($is_admin): ?>
            <a href="dashboard.php">Dashboard</a>
            <a href="students.php">Students</a>
            <a href="teachers.php">Teachers</a>
            <a href="subjects.php">Subjects</a>
            <a href="fees.php">Fees</a>
            <a href="grade_requests.php">Grade Requests<?php if ($pendingRequestsCount > 0) echo ' <span class="badge">' . $pendingRequestsCount . '</span>'; ?></a>
            <a href="activity_logs.php">Activity Logs</a>
        <?php endif; ?>
        <a href="<?php echo $root_path; ?>logout.php" onclick="return confirm('Logout?')">Logout</a>
    </nav>
</aside>
<div class="site-overlay"></div>
