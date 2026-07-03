<?php
require_once '../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

$message = '';

// Get Teacher ID
$stmt = $db->prepare("SELECT id, full_name, subject_specialization FROM teachers WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher) {
    echo "Teacher profile not found.";
    exit();
}

// Handle Attendance Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['date'];
    $attendance_data = $_POST['status']; // Array of student_id => status
    $recorded_by = $_SESSION['user_id'];

    if (!empty($attendance_data)) {
        try {
            $date_ts = strtotime($date);
            if ($date_ts === false) {
                throw new Exception('Invalid attendance date.');
            }
            if ($date_ts > strtotime(date('Y-m-d'))) {
                throw new Exception('Attendance date cannot be in the future.');
            }
            $db->beginTransaction();
            
            $stmt = $db->prepare("INSERT INTO attendance (student_id, date, status, recorded_by, recorded_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP) 
                                  ON CONFLICT (student_id, date) DO UPDATE SET status = EXCLUDED.status, recorded_by = EXCLUDED.recorded_by, recorded_at = CURRENT_TIMESTAMP");
            
            foreach ($attendance_data as $student_id => $status) {
                $stmt->execute([$student_id, $date, $status, $recorded_by]);
            }
            
            $db->commit();
            $message = "<div class='message success'>Attendance recorded for " . count($attendance_data) . " students.</div>";
        } catch (PDOException $e) {
            $db->rollBack();
            $message = "<div class='message error'>Error: " . $e->getMessage() . "</div>";
        }
    }
}

// Fetch Teacher's Assigned Classes
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

// Get Selected Class
$selected_class_id = isset($_GET['class_id']) ? $_GET['class_id'] : '';
$students = [];

if ($selected_class_id) {
    // Verify class belongs to teacher
    $stmt_check = $db->prepare("
        SELECT DISTINCT r.id 
        FROM rooms r
        LEFT JOIN students s ON s.classroom_id = r.id
        LEFT JOIN student_subjects ss ON ss.student_id = s.id
        LEFT JOIN teacher_subjects ts ON ts.subject_id = ss.subject_id
        WHERE r.id = ? AND (r.teacher_id = ? OR ts.teacher_id = ?)
    ");
    $stmt_check->execute([$selected_class_id, $teacher['id'], $teacher['id']]);
    
    if ($stmt_check->rowCount() > 0) {
        $stmt_students = $db->prepare("
            SELECT DISTINCT s.id, s.full_name, s.pin 
            FROM students s
            JOIN rooms r ON s.classroom_id = r.id
            LEFT JOIN student_subjects ss ON ss.student_id = s.id
            LEFT JOIN teacher_subjects ts ON ts.subject_id = ss.subject_id
            WHERE s.classroom_id = ? AND (r.teacher_id = ? OR ts.teacher_id = ?)
            ORDER BY s.full_name ASC
        ");
        $stmt_students->execute([$selected_class_id, $teacher['id'], $teacher['id']]);
        $students = $stmt_students->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $message = "<div class='message error'>Invalid class selected.</div>";
    }
} else {
    // Optional: Default to first class if available
    if (!empty($classes)) {
        // $selected_class_id = $classes[0]['id']; // Uncomment to auto-select first class
        // Re-run fetch logic if auto-selecting
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Mark Attendance - Teacher Dashboard</title>
    <link rel="stylesheet" href="../assets/style.css?v=<?php echo time(); ?>">
    <style>
        .attendance-grid { width: 100%; border-collapse: collapse; }
        .student-row { display: grid; grid-template-columns: 1fr 2fr; padding: 15px; border-bottom: 1px solid #f0f0f0; align-items: center; }
        .student-row:hover { background: #fcfcfc; }
        .radio-group { display: flex; gap: 20px; }
        .radio-group label { display: flex; align-items: center; gap: 5px; cursor: pointer; font-size: 0.9em; }
        .status-present { color: #2e7d32; font-weight: bold; }
        .status-absent { color: #c62828; font-weight: bold; }
        .status-late { color: #f9a825; font-weight: bold; }
        .empty-state { text-align: center; padding: 40px; color: #7f8c8d; background: #f9f9f9; border-radius: 8px; border: 1px dashed #ccc; }
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
                <li><a href="student_performance.php">Student Performance</a></li>
                <li><a href="attendance.php" class="active">Mark Attendance</a></li>
                <li><a href="attendance_history.php">Attendance History</a></li>
                <li><a href="grades.php">Grades</a></li>
                <li><a href="assignments.php">Assignments</a></li>
                <li><a href="profile.php">My Profile</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="page-header">
                <h1>Mark Attendance</h1>
            </div>
            <?php echo $message; ?>
            
            <div class="card form-container" style="max-width: 100%; margin-bottom: 20px;">
                <form method="GET" action="attendance.php" style="display: flex; gap: 15px; align-items: end;">
                    <div class="form-group" style="margin-bottom: 0; flex: 1;">
                        <label>Select Class to Mark</label>
                        <select name="class_id" onchange="this.form.submit()" required>
                            <option value="">-- Select Class --</option>
                            <?php foreach($classes as $class): ?>
                                <option value="<?php echo $class['id']; ?>" <?php echo ($selected_class_id == $class['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class['room_number']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>

            <?php if ($selected_class_id && !empty($students)): ?>
            <form method="POST" action="attendance.php?class_id=<?php echo $selected_class_id; ?>">
                <div class="form-group">
                    <label>Date: <input type="date" name="date" max="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>" required></label>
                </div>

                <div class="card">
                    <div class="attendance-grid">
                        <div class="student-row" style="font-weight: bold; background: #f8f9fa;">
                            <div>Student Name</div>
                            <div>Status</div>
                        </div>
                        <?php foreach ($students as $student): ?>
                        <div class="student-row">
                            <div>
                                <?php echo htmlspecialchars($student['full_name']); ?> 
                                <small>(<?php echo htmlspecialchars($student['pin']); ?>)</small>
                            </div>
                            <div class="radio-group">
                                <label><input type="radio" name="status[<?php echo $student['id']; ?>]" value="Present" checked> <span class="status-present">Present</span></label>
                                <label><input type="radio" name="status[<?php echo $student['id']; ?>]" value="Absent"> <span class="status-absent">Absent</span></label>
                                <label><input type="radio" name="status[<?php echo $student['id']; ?>]" value="Late"> <span class="status-late">Late</span></label>
                                <label><input type="radio" name="status[<?php echo $student['id']; ?>]" value="Excused"> <span style="color: #1565c0; font-weight: bold;">Excused</span></label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-actions" style="margin-top: 20px; display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">Save Attendance Records</button>
                    <a href="dashboard.php" class="btn">Cancel</a>
                </div>
            </form>
            <?php elseif ($selected_class_id): ?>
                <div class="empty-state">No students found in this class.</div>
            <?php else: ?>
                <div class="empty-state">Please select a class above to start marking attendance.</div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
