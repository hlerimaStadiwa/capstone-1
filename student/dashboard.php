<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

// Get student data
$database = new Database();
$db = $database->getConnection();

$query = "SELECT s.* FROM students s 
          JOIN users u ON s.user_id = u.id 
          WHERE u.id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Calculate Total Paid Fees
$fee_stmt = $db->prepare("SELECT SUM(amount) FROM fees WHERE student_id = ?");
$fee_stmt->execute([$student['id']]);
$total_paid = $fee_stmt->fetchColumn() ?: 0;
$school_fee = 150.00;
$min_fee_for_reports = $school_fee / 2; // 75.00
$has_access = ($total_paid >= $min_fee_for_reports);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Silver Academy</title>
    <link rel="stylesheet" href="../assets/style.css?v=<?php echo time(); ?>">
    <style>
        .student-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-item { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); text-align: center; }
        .stat-item h4 { color: #7f8c8d; font-size: 0.8em; text-transform: uppercase; margin-bottom: 10px; }
        .stat-item .value { font-size: 1.5em; font-weight: bold; color: #2c3e50; }
        .panel-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
        .panel { background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 25px; }
        .panel-header { background: #f8f9fa; padding: 15px 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .panel-body { padding: 20px; }
        .news-item { padding-bottom: 15px; border-bottom: 1px solid #f0f0f0; margin-bottom: 15px; }
        .news-item:last-child { border-bottom: none; margin-bottom: 0; }
        .grade-badge { background: #e8f5e9; color: #2e7d32; padding: 2px 8px; border-radius: 12px; font-weight: bold; }
        .staff-avatar { width: 32px; height: 32px; background: #eee; border-radius: 50%; font-size: 10px; display: flex; align-items: center; justify-content: center; }
        @media (max-width: 900px) { .panel-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="logo">Silver Academy</div>
            <div class="user-info" style="padding: 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1);">
                <div class="avatar-circle" style="width: 60px; height: 60px; background: #3498db; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: bold; margin: 0 auto 10px; color: white;">
                    <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                </div>
                <p>Welcome,<br><strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></p>
            </div>
            <ul class="nav-menu">
                <li><a href="dashboard.php" class="active">Dashboard</a></li>
                <li><a href="profile.php">My Profile</a></li>
                <li><a href="grades.php">My Grades</a></li>
                <li><a href="attendance.php">My Attendance</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="top-bar" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                <div>
                    <h1 style="font-size: 1.8em; color: #2c3e50;">Student Portal</h1>
                    <p style="color: #7f8c8d;">Home / Dashboard</p>
                </div>
                <div style="text-align: right; color: #7f8c8d;">
                    <div><?php echo date('l, F jS'); ?></div>
                    <div style="font-size: 0.8em;">Account: <span style="color: #27ae60; font-weight: bold;">Verified</span></div>
                </div>
            </div>

            <!-- Stats Bar -->
            <div class="student-stats">
                <div class="stat-item">
                    <h4>Enrollment ID</h4>
                    <div class="value"><?php echo htmlspecialchars($student['pin']); ?></div>
                </div>
                <div class="stat-item">
                    <h4>Room / Classroom</h4>
                    <div class="value" style="font-size: 1.2em;">
                        <?php
                        $locs = [];
                        if ($student['classroom_id']) {
                             $c_stmt = $db->prepare("SELECT room_number FROM rooms WHERE id = ?");
                             $c_stmt->execute([$student['classroom_id']]);
                             $locs[] = "Class " . $c_stmt->fetchColumn();
                        }
                        if ($student['room_id']) {
                             $r_stmt = $db->prepare("SELECT room_number FROM rooms WHERE id = ?");
                             $r_stmt->execute([$student['room_id']]);
                             $locs[] = "Hostel " . $r_stmt->fetchColumn();
                        }
                        echo !empty($locs) ? implode(' / ', $locs) : 'Not Allocated';
                        ?>
                    </div>
                </div>
                <div class="stat-item">
                    <h4>Fees Paid</h4>
                    <div class="value" style="color: <?php echo $has_access ? '#27ae60' : '#e74c3c'; ?>">
                        $<?php echo number_format($total_paid, 2); ?>
                    </div>
                    <small><?php echo $has_access ? 'Access Granted' : 'Access Locked'; ?></small>
                </div>
            </div>

            <div class="panel-grid">
                <!-- Left Column -->
                <div class="left-col">
                    <!-- Recent News -->
                    <div class="panel">
                        <div class="panel-header">
                            <h3 style="margin:0; font-size: 1.1em;">Announcements</h3>
                        </div>
                        <div class="panel-body">
                            <?php
                            $news_stmt = $db->query("SELECT * FROM news ORDER BY created_at DESC LIMIT 3");
                            if ($news_stmt && $news_stmt->rowCount() > 0) {
                                while ($news = $news_stmt->fetch(PDO::FETCH_ASSOC)) {
                                    echo '<div class="news-item">';
                                    echo '<div style="display: flex; justify-content: space-between; margin-bottom: 5px;">';
                                    echo '<strong style="color: #2c3e50;">' . htmlspecialchars($news['title']) . '</strong>';
                                    echo '<small style="color: #95a5a6;">' . date('M d', strtotime($news['created_at'])) . '</small>';
                                    echo '</div>';
                                    echo '<p style="font-size: 0.9em; color: #7f8c8d;">' . htmlspecialchars(substr($news['content'], 0, 150)) . '...</p>';
                                    echo '</div>';
                                }
                            } else {
                                echo '<p style="text-align: center; color: #bdc3c7; padding: 20px;">No news available.</p>';
                            }
                            ?>
                        </div>
                    </div>

                    <!-- Recent Grades -->
                    <div class="panel">
                        <div class="panel-header">
                            <h3 style="margin:0; font-size: 1.1em;">Recent Grades</h3>
                            <a href="grades.php" class="btn btn-small">View All</a>
                        </div>
                        <div class="panel-body">
                            <?php if ($has_access): ?>
                                <?php
                                $grades_stmt = $db->prepare("SELECT * FROM grades WHERE student_id = ? AND is_published = TRUE ORDER BY id DESC LIMIT 5");
                                $grades_stmt->execute([$student['id']]);
                                if ($grades_stmt && $grades_stmt->rowCount() > 0) {
                                    echo '<table class="table">';
                                    echo '<thead><tr><th>Subject</th><th>Score</th><th>Grade</th></tr></thead>';
                                    echo '<tbody>';
                                    while ($grade = $grades_stmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo '<tr>';
                                        echo '<td>' . htmlspecialchars($grade['subject_name']) . '</td>';
                                        echo '<td>' . number_format($grade['score'], 1) . '%</td>';
                                        echo '<td><span class="grade-badge">' . htmlspecialchars($grade['grade']) . '</span></td>';
                                        echo '</tr>';
                                    }
                                    echo '</tbody></table>';
                                } else {
                                    echo '<p style="text-align: center; color: #bdc3c7; padding: 20px;">No published reports found.</p>';
                                }
                                ?>
                            <?php else: ?>
                                <div class="access-denied" style="background: #fff3f3; padding: 30px; border-radius: 4px; border: 1px solid #ffcdd2; text-align: center;">
                                    <h4 style="color: #d32f2f; margin-bottom: 10px;">Reports Locked</h4>
                                    <p style="font-size: 0.9em; color: #666;">Minimum 50% fee payment required to access results.</p>
                                    <p style="margin-top: 15px; font-weight: bold; font-size: 1.1em;">Total Paid: $<?php echo number_format($total_paid, 2); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="right-col">
                    <!-- Quick Links -->
                    <div class="panel">
                        <div class="panel-header"><h3 style="margin:0; font-size: 1.1em;">Quick Links</h3></div>
                        <div class="panel-body" style="display: grid; gap: 10px;">
                            <a href="profile.php" class="btn" style="background: #f8f9fa; color: #2c3e50; text-align: left; border: 1px solid #ddd; padding: 12px;">My Profile</a>
                            <a href="attendance.php" class="btn" style="background: #f8f9fa; color: #2c3e50; text-align: left; border: 1px solid #ddd; padding: 12px;">Attendance</a>
                            <a href="grades.php" class="btn" style="background: #f8f9fa; color: #2c3e50; text-align: left; border: 1px solid #ddd; padding: 12px;">Result Slip</a>
                        </div>
                    </div>

                    <!-- Staff Contact -->
                    <div class="panel">
                        <div class="panel-header"><h3 style="margin:0; font-size: 1.1em;">Faculty Info</h3></div>
                        <div class="panel-body">
                            <?php
                            $staff_stmt = $db->query("
                                SELECT COALESCE(t.full_name, u.username) as display_name, u.role 
                                FROM users u 
                                LEFT JOIN teachers t ON u.id = t.user_id 
                                WHERE u.role IN ('admin', 'teacher') 
                                LIMIT 4
                            ");
                            while ($staff = $staff_stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo '<div style="display: flex; align-items: center; gap: 12px; margin-bottom: 15px;">';
                                echo '<div class="staff-avatar">S</div>';
                                echo '<div>';
                                echo '<div style="font-size: 0.9em; font-weight: bold;">' . htmlspecialchars($staff['display_name']) . '</div>';
                                echo '<div style="font-size: 0.75em; color: #7f8c8d; text-transform: uppercase;">' . $staff['role'] . '</div>';
                                echo '</div>';
                                echo '</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>