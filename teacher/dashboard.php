<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Fetch Teacher Info
$stmt = $db->prepare("SELECT * FROM teachers WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher) {
    echo "Teacher profile not found.";
    exit();
}

// Fetch Assigned Classrooms
$stmt = $db->prepare("SELECT COUNT(*) FROM rooms WHERE teacher_id = ?");
$stmt->execute([$teacher['id']]);
$assigned_rooms = $stmt->fetchColumn();

// Fetch Total Students in assigned rooms
// We need to join students with rooms where room.teacher_id = this teacher
$stmt = $db->prepare("
    SELECT COUNT(*) 
    FROM students s
    JOIN rooms r ON s.classroom_id = r.id
    WHERE r.teacher_id = ?
");
$stmt->execute([$teacher['id']]);
$total_students = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - Silver Academy</title>
    <link rel="stylesheet" href="../assets/style.css?v=<?php echo time(); ?>">
    <style>
        .teacher-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-item { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); text-align: center; }
        .stat-item h4 { color: #7f8c8d; font-size: 0.8em; text-transform: uppercase; margin-bottom: 10px; }
        .stat-item .value { font-size: 1.5em; font-weight: bold; color: #2c3e50; }
        .panel-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
        .panel { background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 25px; }
        .panel-header { background: #f8f9fa; padding: 15px 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .panel-body { padding: 20px; }
        .news-item { padding-bottom: 15px; border-bottom: 1px solid #f0f0f0; margin-bottom: 15px; }
        .news-item:last-child { border-bottom: none; margin-bottom: 0; }
        @media (max-width: 900px) { .panel-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="logo">Silver Academy</div>
            <div class="user-info" style="padding: 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1);">
                <div class="avatar-circle" style="width: 60px; height: 60px; background: #27ae60; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: bold; margin: 0 auto 10px; color: white;">
                    <?php echo strtoupper(substr($teacher['full_name'], 0, 1)); ?>
                </div>
                <p>Welcome,<br><strong><?php echo htmlspecialchars($teacher['full_name']); ?></strong></p>
            </div>
            <ul class="nav-menu">
                <li><a href="dashboard.php" class="active">Dashboard</a></li>
                <li><a href="my_classes.php">My Classes</a></li>
                <li><a href="my_students.php">My Students</a></li>
                <li><a href="student_performance.php">Student Performance</a></li>
                <li><a href="attendance.php">Mark Attendance</a></li>
                <li><a href="attendance_history.php">Attendance History</a></li>
                <li><a href="grades.php">Grades</a></li>
                <li><a href="profile.php">My Profile</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="top-bar" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                <div>
                    <h1 style="font-size: 1.8em; color: #2c3e50;">Teacher Portal</h1>
                    <p style="color: #7f8c8d;">Home / Dashboard</p>
                </div>
                <div style="text-align: right; color: #7f8c8d;">
                    <div><?php echo date('l, F jS'); ?></div>
                    <div style="font-size: 0.8em;">Session: <span style="color: #27ae60; font-weight: bold;">Active</span></div>
                </div>
            </div>

            <!-- Stats Bar -->
            <div class="teacher-stats">
                <div class="stat-item">
                    <h4>Assigned Classes</h4>
                    <div class="value"><?php echo $assigned_rooms; ?></div>
                </div>
                <div class="stat-item">
                    <h4>Total Students</h4>
                    <div class="value"><?php echo $total_students; ?></div>
                </div>
                <div class="stat-item">
                    <h4>Specialization</h4>
                    <div class="value" style="font-size: 1.1em;"><?php echo htmlspecialchars($teacher['subject_specialization'] ?: 'General'); ?></div>
                </div>
            </div>

            <div class="panel-grid">
                <!-- Left Column -->
                <div class="left-col">
                    <!-- Announcements -->
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
                                echo '<p style="text-align: center; color: #bdc3c7; padding: 20px;">No announcements available.</p>';
                            }
                            ?>
                        </div>
                    </div>

                    <!-- Recent Grades Entered -->
                    <div class="panel">
                        <div class="panel-header">
                            <h3 style="margin:0; font-size: 1.1em;">Recent Grades Entered</h3>
                            <a href="grades.php" class="btn btn-small">Input New</a>
                        </div>
                        <div class="panel-body">
                            <?php
                            // Fetch last 5 grades entered by this teacher's subject specialization or just generic recent?
                            // Currently grades don't track which teacher entered them, but we can filter by subject.
                            $subjects = explode(',', $teacher['subject_specialization']);
                            $subj_placeholders = implode(',', array_fill(0, count($subjects), '?'));
                            
                            $grades_stmt = $db->prepare("
                                SELECT g.*, s.full_name 
                                FROM grades g
                                JOIN students s ON g.student_id = s.id
                                WHERE g.subject_name IN ($subj_placeholders)
                                ORDER BY g.id DESC LIMIT 5
                            ");
                            $grades_stmt->execute(array_map('trim', $subjects));
                            
                            if ($grades_stmt->rowCount() > 0) {
                                echo '<table class="table">';
                                echo '<thead><tr><th>Student</th><th>Subject</th><th>Score</th><th>Grade</th></tr></thead>';
                                echo '<tbody>';
                                while ($grade = $grades_stmt->fetch(PDO::FETCH_ASSOC)) {
                                    echo '<tr>';
                                    echo '<td>' . htmlspecialchars($grade['full_name']) . '</td>';
                                    echo '<td>' . htmlspecialchars($grade['subject_name']) . '</td>';
                                    echo '<td>' . number_format($grade['score'], 1) . '%</td>';
                                    echo '<td><strong>' . $grade['grade'] . '</strong></td>';
                                    echo '</tr>';
                                }
                                echo '</tbody></table>';
                            } else {
                                echo '<p style="text-align: center; color: #bdc3c7; padding: 20px;">No recent grades recorded for your subjects.</p>';
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="right-col">
                    <!-- Quick Actions -->
                    <div class="panel">
                        <div class="panel-header"><h3 style="margin:0; font-size: 1.1em;">Quick Actions</h3></div>
                        <div class="panel-body" style="display: grid; gap: 10px;">
                            <a href="my_students.php" class="btn" style="background: #f8f9fa; color: #2c3e50; text-align: left; border: 1px solid #ddd; padding: 12px;">Academic Roster</a>
                            <a href="student_performance.php" class="btn" style="background: #f8f9fa; color: #2c3e50; text-align: left; border: 1px solid #ddd; padding: 12px;">Student Performance</a>
                            <a href="attendance.php" class="btn" style="background: #f8f9fa; color: #2c3e50; text-align: left; border: 1px solid #ddd; padding: 12px;">Mark Attendance</a>
                            <a href="attendance_history.php" class="btn" style="background: #f8f9fa; color: #2c3e50; text-align: left; border: 1px solid #ddd; padding: 12px;">Attendance History</a>
                            <a href="grades.php" class="btn" style="background: #f8f9fa; color: #2c3e50; text-align: left; border: 1px solid #ddd; padding: 12px;">Record Grades</a>
                        </div>
                    </div>

                    <!-- Classes List -->
                    <div class="panel">
                        <div class="panel-header"><h3 style="margin:0; font-size: 1.1em;">My Classes</h3></div>
                        <div class="panel-body">
                            <?php
                            $rooms_stmt = $db->prepare("SELECT room_number FROM rooms WHERE teacher_id = ?");
                            $rooms_stmt->execute([$teacher['id']]);
                            while ($room = $rooms_stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo '<div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">';
                                echo '<div style="width: 35px; height: 35px; background: #e8f5e9; color: #2e7d32; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.8em;">C</div>';
                                echo '<div>';
                                echo '<div style="font-size: 0.9em; font-weight: bold;">Room ' . htmlspecialchars($room['room_number']) . '</div>';
                                echo '<div style="font-size: 0.7em; color: #7f8c8d;">Class Teacher</div>';
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