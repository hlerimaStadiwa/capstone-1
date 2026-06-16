<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get Teacher ID
$stmt = $db->prepare("SELECT id, full_name FROM teachers WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher) {
    echo "Teacher profile not found.";
    exit();
}

// Get Teacher's Assigned Classes
$stmt_classes = $db->prepare("
    SELECT DISTINCT r.id, r.room_number 
    FROM rooms r
    LEFT JOIN students s ON s.classroom_id = r.id
    LEFT JOIN student_subjects ss ON ss.student_id = s.id
    LEFT JOIN teacher_subjects ts ON ts.subject_id = ss.subject_id
    WHERE r.teacher_id = ? OR ts.teacher_id = ?
    ORDER BY r.room_number
");
$stmt_classes->execute([$teacher['id'], $teacher['id']]);
$classes = $stmt_classes->fetchAll(PDO::FETCH_ASSOC);

// Filtering Parameters
$classroom_id = isset($_GET['classroom_id']) ? $_GET['classroom_id'] : '';
$student_id = isset($_GET['student_id']) ? $_GET['student_id'] : '';
$term = isset($_GET['term']) ? $_GET['term'] : 'Term 1';

$grades = [];
$student_info = null;
$all_students = [];

if ($classroom_id) {
    // Verify classroom ownership / instruction
    $check = $db->prepare("
        SELECT DISTINCT r.id 
        FROM rooms r
        LEFT JOIN students s ON s.classroom_id = r.id
        LEFT JOIN student_subjects ss ON ss.student_id = s.id
        LEFT JOIN teacher_subjects ts ON ts.subject_id = ss.subject_id
        WHERE r.id = ? AND (r.teacher_id = ? OR ts.teacher_id = ?)
    ");
    $check->execute([$classroom_id, $teacher['id'], $teacher['id']]);
    
    if ($check->rowCount() > 0) {
        // Get list of students in this class
        $st_stmt = $db->prepare("
            SELECT DISTINCT s.id, s.pin, s.full_name 
            FROM students s
            JOIN rooms r ON s.classroom_id = r.id
            LEFT JOIN student_subjects ss ON ss.student_id = s.id
            LEFT JOIN teacher_subjects ts ON ts.subject_id = ss.subject_id
            WHERE s.classroom_id = ? AND (r.teacher_id = ? OR ts.teacher_id = ?)
            ORDER BY s.full_name");
        $st_stmt->execute([$classroom_id, $teacher['id'], $teacher['id']]);
        $all_students = $st_stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($student_id) {
            // Fetch student basic info
            $info_stmt = $db->prepare("SELECT * FROM students WHERE id = ? AND classroom_id = ?");
            $info_stmt->execute([$student_id, $classroom_id]);
            $student_info = $info_stmt->fetch(PDO::FETCH_ASSOC);

            if ($student_info) {
                // Fetch grades across ALL subjects for the selected term - include drafts for teacher
                $g_stmt = $db->prepare("SELECT * FROM grades WHERE student_id = ? AND term = ? ORDER BY subject_name ASC");
                $g_stmt->execute([$student_id, $term]);
                $grades = $g_stmt->fetchAll(PDO::FETCH_ASSOC);

                // Check for existence of any UNPUBLISHED grades for this term (to show a notice)
                $check_stmt = $db->prepare("SELECT COUNT(*) FROM grades WHERE student_id = ? AND term = ? AND is_published = FALSE");
                $check_stmt->execute([$student_id, $term]);
                $has_unpublished = $check_stmt->fetchColumn() > 0;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Performance - Teacher Portal</title>
    <link rel="stylesheet" href="../assets/style.css?v=<?php echo time(); ?>">
    <style>
        .performance-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 25px; }
        .score-card { background: #f8f9fa; padding: 20px; border-radius: 8px; border-left: 5px solid #2c3e50; }
        .grade-badge { padding: 4px 10px; border-radius: 4px; font-weight: bold; font-size: 0.9em; }
        .grade-a { background: #e8f5e9; color: #2e7d32; }
        .grade-b { background: #e3f2fd; color: #1565c0; }
        .grade-c { background: #fff3e0; color: #e65100; }
        .grade-f { background: #ffeeb2; color: #c62828; }
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="sidebar">
            <div class="logo">Danborough</div>
            <div class="user-info" style="padding: 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1);">
                <div class="avatar-circle" style="width: 60px; height: 60px; background: #27ae60; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: bold; margin: 0 auto 10px; color: white;">
                    <?php echo strtoupper(substr($teacher['full_name'], 0, 1)); ?>
                </div>
                <p>Welcome,<br><strong><?php echo htmlspecialchars($teacher['full_name']); ?></strong></p>
            </div>
            <ul class="nav-menu">
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="my_classes.php">My Classes</a></li>
                <li><a href="my_students.php">My Students</a></li>
                <li><a href="student_performance.php" class="active">Student Performance</a></li>
                <li><a href="attendance.php">Mark Attendance</a></li>
                <li><a href="attendance_history.php">Attendance History</a></li>
                <li><a href="grades.php">Grades</a></li>
                <li><a href="assignments.php">Assignments</a></li>
                <li><a href="profile.php">My Profile</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="page-header">
                <h1>Student Performance</h1>
                <p>Comprehensive academic overview across all subjects</p>
            </div>

            <div class="card" style="margin-bottom: 25px;">
                <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: flex-end;">
                    <div class="form-group" style="margin: 0;">
                        <label>Institutional Classroom</label>
                        <select name="classroom_id" required onchange="this.form.submit()">
                            <option value="">-- Select Class --</option>
                            <?php foreach($classes as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo ($classroom_id == $c['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['room_number']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if ($classroom_id): ?>
                    <div class="form-group" style="margin: 0;">
                        <label>Select Student</label>
                        <select name="student_id" required onchange="this.form.submit()">
                            <option value="">-- Select Student --</option>
                            <?php foreach($all_students as $s): ?>
                                <option value="<?php echo $s['id']; ?>" <?php echo ($student_id == $s['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s['full_name']); ?> (<?php echo $s['pin']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin: 0;">
                        <label>Academic Term</label>
                        <select name="term" onchange="this.form.submit()">
                            <option value="Term 1" <?php echo ($term == 'Term 1') ? 'selected' : ''; ?>>Term 1</option>
                            <option value="Term 2" <?php echo ($term == 'Term 2') ? 'selected' : ''; ?>>Term 2</option>
                            <option value="Term 3" <?php echo ($term == 'Term 3') ? 'selected' : ''; ?>>Term 3</option>
                        </select>
                    </div>
                    <?php endif; ?>
                </form>
            </div>

            <?php if ($student_info): ?>
                <div class="performance-header">
                    <div>
                        <h2 style="margin:0;"><?php echo htmlspecialchars($student_info['full_name']); ?></h2>
                        <p style="color: #666;">Institutional PIN: <code><?php echo htmlspecialchars($student_info['pin']); ?></code> | <?php echo htmlspecialchars($term); ?></p>
                    </div>
                </div>

                <?php if ($has_unpublished): ?>
                    <div class="alert" style="background: #fff3cd; color: #856404; padding: 15px; border-radius: 8px; border: 1px solid #ffeeba; margin-bottom: 25px; display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 1.2em;">Privacy Notice:</span>
                        <div>
                            <strong>Institutional Privacy Notice:</strong> Some results for this term are currently marked as "Draft" and are awaiting formal administrative release. Only published scores are visible here.
                        </div>
                    </div>
                <?php endif; ?>

                <div class="table-container">
                    <?php if (!empty($grades)): ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Subject Name</th>
                                    <th>Score (%)</th>
                                    <th>Grade</th>
                                    <th>Status</th>
                                    <th>Teacher Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($grades as $g): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($g['subject_name']); ?></strong></td>
                                    <td><?php echo number_format($g['score'], 1); ?>%</td>
                                    <td>
                                        <?php 
                                        $g_class = 'grade-' . strtolower(substr($g['grade'], 0, 1));
                                        ?>
                                        <span class="grade-badge <?php echo $g_class; ?>"><?php echo htmlspecialchars($g['grade']); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($g['is_published']): ?>
                                            <span class="badge" style="background: #2ecc71; color: white;">Published</span>
                                        <?php else: ?>
                                            <span class="badge" style="background: #f1c40f; color: #333;">Draft</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><small style="color: #666; font-style: italic;"><?php echo htmlspecialchars($g['comments'] ?: 'No comments provided'); ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state" style="padding: 60px; text-align: center; background: #fff; border-radius: 8px; border: 1px solid #eee;">
                            <h3 style="color: #7f8c8d; margin-top: 0;">No Records Available</h3>
                            <p style="color: #999;">There are currently no <strong>published</strong> academic records for this student in <?php echo htmlspecialchars($term); ?>. <br>Results are revealed once they have been officially posted by the Administration.</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="empty-state" style="padding: 80px; text-align: center; background: #f9f9f9; border-radius: 12px; border: 2px dashed #ddd;">
                    <h3 style="color: #7f8c8d;">Institutional Performance Discovery</h3>
                    <p style="color: #999;">Please select a classroom and student above to analyze academic progress across all subjects.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
