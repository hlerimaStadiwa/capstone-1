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

// Fetch Assigned Classes
$query = "
    SELECT DISTINCT r.*, 
           (SELECT COUNT(*) FROM students s WHERE s.classroom_id = r.id) as student_count
    FROM rooms r 
    LEFT JOIN students s ON s.classroom_id = r.id
    LEFT JOIN student_subjects ss ON ss.student_id = s.id
    LEFT JOIN teacher_subjects ts ON ts.subject_id = ss.subject_id
    WHERE r.teacher_id = ? OR ts.teacher_id = ? 
    ORDER BY r.room_number";
    
$stmt = $db->prepare($query);
$stmt->execute([$teacher['id'], $teacher['id']]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Classes - Teacher Portal</title>
    <link rel="stylesheet" href="../assets/style.css">
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
                <li><a href="my_classes.php" class="active">My Classes</a></li>
                <li><a href="my_students.php">My Students</a></li>
                <li><a href="student_performance.php">Student Performance</a></li>
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
                <h1>Assigned Classrooms</h1>
                <p>Overview of classes under your supervision or instruction</p>
            </div>
            
            <div class="table-container">
                <?php if ($stmt->rowCount() > 0): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Class/Form</th>
                                <th>Type</th>
                                <th>Role</th>
                                <th>Students Enrolled</th>
                                <th>Capacity</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $stmt->fetch(PDO::FETCH_ASSOC)): 
                                $role = ($row['teacher_id'] == $teacher['id']) ? 'Class Teacher' : 'Subject Teacher';
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['room_number']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['room_type']); ?></td>
                                <td>
                                    <span class="badge" style="background: <?php echo ($role === 'Class Teacher') ? '#27ae60' : '#2980b9'; ?>; color: white;">
                                        <?php echo $role; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge" style="background: #7f8c8d; color: white;"><?php echo $row['student_count']; ?></span>
                                </td>
                                <td><?php echo $row['capacity']; ?></td>
                                <td>
                                    <a href="my_students.php?classroom_id=<?php echo $row['id']; ?>" class="btn btn-small btn-primary">View Students</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <p>You have not been assigned any classes yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
