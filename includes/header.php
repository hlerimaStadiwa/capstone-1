<?php
// includes/header.php
// This file assumes it is included inside the <body> of a page
// It provides the Navigation Bar

// Simple path logic to determine links
$current_dir = dirname($_SERVER['PHP_SELF']);
$is_admin = strpos($current_dir, '/admin') !== false;
$root_path = $is_admin ? '../' : './';
?>

<nav class="navbar">
    <div class="nav-container">
        <div class="nav-logo">
            <h2>Silver Academy Student System</h2>
        </div>
        <div class="nav-menu">
            <?php if ($is_admin): ?>
                <a href="dashboard.php">Dashboard</a>
                <a href="students.php">Students</a>
                <a href="teachers.php">Teachers</a>
                <a href="subjects.php">Subjects</a>
                <a href="fees.php">Fees</a>
                <a href="activity_logs.php">Activity Logs</a>
            <?php endif; ?>
            <a href="<?php echo $root_path; ?>logout.php" onclick="return confirm('Logout?')">Logout</a>
        </div>
    </div>
</nav>