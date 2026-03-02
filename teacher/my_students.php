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

// Mandatory Classroom Filter
$classroom_id = isset($_GET['classroom_id']) ? $_GET['classroom_id'] : '';
$students = [];

if ($classroom_id) {
    // Verify this classroom belongs to the teacher
    $check = $db->prepare("SELECT id FROM rooms WHERE id = ? AND teacher_id = ?");
    $check->execute([$classroom_id, $teacher['id']]);
    
    if ($check->rowCount() > 0) {
        $query = "
            SELECT s.*, r.room_number 
            FROM students s
            JOIN rooms r ON s.classroom_id = r.id 
            WHERE s.classroom_id = ? 
            ORDER BY s.full_name";
        $stmt_students = $db->prepare($query);
        $stmt_students->execute([$classroom_id]);
        $students = $stmt_students->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Roster - Teacher Portal</title>
    <link rel="stylesheet" href="../assets/style.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="dashboard">
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
                <li><a href="my_students.php" class="active">My Students</a></li>
                <li><a href="student_performance.php">Student Performance</a></li>
                <li><a href="attendance.php">Mark Attendance</a></li>
                <li><a href="attendance_history.php">Attendance History</a></li>
                <li><a href="grades.php">Grades</a></li>
                <li><a href="profile.php">My Profile</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="page-header">
                <h1>Academic Roster</h1>
                <p>Select a classroom to view enrolled students</p>
            </div>

            <!-- Mandatory Class Filter Form -->
            <div class="card" style="margin-bottom: 25px;">
                <form method="GET" action="my_students.php" style="display: flex; gap: 15px; align-items: flex-end;">
                    <div class="form-group" style="margin: 0; flex: 1;">
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
                        <a href="my_students.php" class="btn">Clear Selection</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="table-container">
                <?php if ($classroom_id && !empty($students)): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Institutional PIN</th>
                                <th>Full Name</th>
                                <th>Gender</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $row): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($row['pin']); ?></code></td>
                                <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['gender']); ?></td>
                                <td style="display: flex; gap: 8px;">
                                    <a href="student_performance.php?student_id=<?php echo $row['id']; ?>&classroom_id=<?php echo $classroom_id; ?>" class="btn btn-small btn-primary">Academic Performance</a>
                                    <a href="attendance_history.php?student_id=<?php echo $row['id']; ?>&classroom_id=<?php echo $classroom_id; ?>" class="btn btn-small">Attendance Log</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php elseif ($classroom_id): ?>
                    <div class="empty-state" style="padding: 60px; text-align: center; background: #f9f9f9; border-radius: 8px; border: 1px dashed #ccc;">
                        <p style="color: #666; font-size: 1.1em;">No students currently enrolled in the selected classroom.</p>
                    </div>
                <?php else: ?>
                    <div class="empty-state" style="padding: 60px; text-align: center; background: #f9f9f9; border-radius: 8px; border: 1px dashed #ccc;">
                        <h3 style="color: #2c3e50;">Awaiting Classroom Selection</h3>
                        <p style="color: #666;">Please select one of your assigned classrooms above to access the student registry.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
