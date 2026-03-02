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
$stmt = $db->prepare("SELECT id, full_name, subject_specialization FROM teachers WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher) {
    echo "Teacher profile not found.";
    exit();
}

// Get Teacher's Assigned Classes for the filter
$stmt_classes = $db->prepare("SELECT id, room_number FROM rooms WHERE teacher_id = ? ORDER BY room_number");
$stmt_classes->execute([$teacher['id']]);
$classes = $stmt_classes->fetchAll(PDO::FETCH_ASSOC);

// Get filter parameters
$classroom_id = isset($_GET['classroom_id']) ? $_GET['classroom_id'] : '';
$student_id = isset($_GET['student_id']) ? $_GET['student_id'] : '';
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$status = isset($_GET['status']) ? $_GET['status'] : '';

$records = [];
$all_students = [];

if ($classroom_id) {
    // Verify this classroom belongs to the teacher
    $check = $db->prepare("SELECT id FROM rooms WHERE id = ? AND teacher_id = ?");
    $check->execute([$classroom_id, $teacher['id']]);
    
    if ($check->rowCount() > 0) {
        // Build WHERE clause
        $where_conditions = ["s.classroom_id = :classroom_id"];
        $params = [':classroom_id' => $classroom_id];

        if (!empty($student_id)) {
            $where_conditions[] = "a.student_id = :student_id";
            $params[':student_id'] = $student_id;
        }

        if (!empty($status)) {
            $where_conditions[] = "a.status = :status";
            $params[':status'] = $status;
        }

        if (!empty($month)) {
            $where_conditions[] = "TO_CHAR(a.date, 'YYYY-MM') = :month";
            $params[':month'] = $month;
        }

        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

        // Get attendance records
        $query = "
            SELECT a.*, s.pin, s.full_name, u.username as recorded_by_name
            FROM attendance a
            JOIN students s ON a.student_id = s.id
            $where_clause
            ORDER BY a.date DESC, s.full_name";

        $stmt = $db->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get students in the selected class for the dropdown
        $students_query = "SELECT id, pin, full_name FROM students WHERE classroom_id = ? ORDER BY full_name";
        $students_stmt = $db->prepare($students_query);
        $students_stmt->execute([$classroom_id]);
        $all_students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Stats for the current filtered view
$stats = [
    'total' => count($records),
    'present' => 0,
    'absent' => 0,
    'late' => 0,
    'excused' => 0
];
foreach($records as $r) {
    $s = strtolower($r['status']);
    if (isset($stats[$s])) $stats[$s]++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Attendance History - Teacher Portal</title>
    <link rel="stylesheet" href="../assets/style.css?v=<?php echo time(); ?>">
    <style>
        .filter-card { background: white; padding: 25px; border-radius: 8px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .stat-box { background: white; padding: 20px; border-radius: 8px; text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .stat-box h4 { margin: 0 0 10px; color: #7f8c8d; font-size: 0.85em; text-transform: uppercase; }
        .stat-box .value { font-size: 1.8em; font-weight: bold; color: #2c3e50; }
        
        .status-present { color: #27ae60; }
        .status-absent { color: #e74c3c; }
        .status-late { color: #f39c12; }
        .status-excused { color: #3498db; }
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
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="my_classes.php">My Classes</a></li>
                <li><a href="my_students.php">My Students</a></li>
                <li><a href="student_performance.php">Student Performance</a></li>
                <li><a href="attendance.php">Mark Attendance</a></li>
                <li><a href="attendance_history.php" class="active">Attendance History</a></li>
                <li><a href="grades.php">Grades</a></li>
                <li><a href="profile.php">My Profile</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="page-header">
                <h1>Attendance History</h1>
                <p>Select a classroom below to access historical student records</p>
            </div>

            <!-- Mandatory Class Filter Form -->
            <div class="filter-card">
                <form method="GET">
                    <div class="filter-grid">
                        <div class="form-group">
                            <label>Institutional Classroom</label>
                            <select name="classroom_id" required onchange="this.form.submit()">
                                <option value="">-- Select Assigned Class --</option>
                                <?php foreach($classes as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" <?php echo ($classroom_id == $c['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c['room_number']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($classroom_id): ?>
                            <div class="form-group">
                                <label>Specific Student</label>
                                <select name="student_id">
                                    <option value="">All Students in Class</option>
                                    <?php foreach($all_students as $s): ?>
                                        <option value="<?php echo $s['id']; ?>" <?php echo ($student_id == $s['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($s['full_name']); ?> (<?php echo $s['pin']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Record Month</label>
                                <input type="month" name="month" value="<?php echo htmlspecialchars($month); ?>">
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($classroom_id): ?>
                        <div style="margin-top: 15px; display: flex; gap: 10px;">
                            <button type="submit" class="btn btn-primary">Refresh Records</button>
                            <a href="attendance_history.php" class="btn">Clear All Filters</a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-box">
                    <h4>Total Records</h4>
                    <div class="value"><?php echo $stats['total']; ?></div>
                </div>
                <div class="stat-box">
                    <h4>Present</h4>
                    <div class="value status-present"><?php echo $stats['present']; ?></div>
                </div>
                <div class="stat-box">
                    <h4>Absent</h4>
                    <div class="value status-absent"><?php echo $stats['absent']; ?></div>
                </div>
                <div class="stat-box">
                    <h4>Attendance Rate</h4>
                    <div class="value" style="color: #3498db;">
                        <?php echo $stats['total'] ? round(($stats['present'] + $stats['late'])/$stats['total'] * 100, 1) : 0; ?>%
                    </div>
                </div>
            </div>

            <!-- History Table -->
            <div class="card">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Student</th>
                                <th>PIN</th>
                                <th>Status</th>
                                <th>Notes/Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($records)): ?>
                                <tr><td colspan="5" style="text-align: center; color: #95a5a6; padding: 40px;">No historical records found for this criteria.</td></tr>
                            <?php else: ?>
                                <?php foreach($records as $r): ?>
                                <tr>
                                    <td><strong><?php echo date('M d, Y', strtotime($r['date'])); ?></strong><br><small><?php echo date('l', strtotime($r['date'])); ?></small></td>
                                    <td><?php echo htmlspecialchars($r['full_name']); ?></td>
                                    <td><code><?php echo htmlspecialchars($r['pin']); ?></code></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($r['status']); ?>" style="font-weight: bold; text-transform: uppercase; font-size: 0.8em;">
                                            <?php echo $r['status']; ?>
                                        </span>
                                    </td>
                                    <td style="font-style: italic; color: #7f8c8d;"><?php echo htmlspecialchars($r['remarks'] ?: 'No formal remarks'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
