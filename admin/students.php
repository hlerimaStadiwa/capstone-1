<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// --- HANDLE DELETION ---
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $db->beginTransaction();

        // Get info to delete user account too
        $stmt = $db->prepare("SELECT user_id, room_id, full_name FROM students WHERE id = ?");
        $stmt->execute([$id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($student) {
            // Delete student (cascade should handle related records if set, but let's be safe)
            $db->prepare("DELETE FROM students WHERE id = ?")->execute([$id]);

            // Release Room
            if ($student['room_id']) {
                $db->prepare("UPDATE rooms SET available_beds = available_beds + 1 WHERE id = ?")->execute([$student['room_id']]);
            }

            // Delete User Account
            if ($student['user_id']) {
                $db->prepare("DELETE FROM users WHERE id = ?")->execute([$student['user_id']]);
            }
            
            $_SESSION['success'] = "Student '{$student['full_name']}' deleted successfully.";
        }
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = "Error deleting student: " . $e->getMessage();
    }
    // Redirect to clear URL parameters
    header("Location: students.php");
    exit();
}

// Display messages
$success_message = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error_message = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['success'], $_SESSION['error']);

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clause = '';
$params = [];

if (!empty($search)) {
    $where_clause = "WHERE full_name LIKE :search OR pin LIKE :search OR email LIKE :search";
    $params[':search'] = "%$search%";
}

// Get all students
$query = "SELECT * FROM students $where_clause ORDER BY created_at DESC";
$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    // Determine type for binding - vague bug prevention
    $stmt->bindValue($key, $value);
}
$stmt->execute();

$total_students = $db->query("SELECT COUNT(*) FROM students")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - Student Management System</title>
    <link rel="stylesheet" href="../assets/style.css?v=<?php echo time(); ?>">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Manage Students</h1>
            <p>Total Students: <strong><?php echo $total_students; ?></strong></p>
        </div>
        
        <?php if ($success_message): ?>
            <div class="message success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="message error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <!-- Action Bar -->
        <div class="action-bar">
            <div class="search-box">
                <form method="GET" action="" style="display: flex; gap: 10px;">
                    <input type="text" name="search" placeholder="Search by name, PIN, or email..." 
                           value="<?php echo htmlspecialchars($search); ?>" style="flex: 1;">
                    <button type="submit" class="btn">Search</button>
                    <?php if (!empty($search)): ?>
                        <a href="students.php" class="btn">Clear</a>
                    <?php endif; ?>
                </form>
            </div>
            <div class="action-buttons">
                <a href="student_form.php" class="btn btn-primary">Add New Student</a>
                <a href="manage_reports.php" class="btn btn-success">Bulk Post Reports</a>
                <a href="dashboard.php" class="btn">Back to Dashboard</a>
            </div>
        </div>
        
        <!-- Students Table -->
        <div class="table-container">
            <?php if ($stmt->rowCount() > 0): ?>
                <div class="table-responsive">
                    <table class="table" id="studentsTable">
                        <thead>
                            <tr>
                                <th>PIN</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Gender</th>
                                <th>Enrollment Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($student = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($student['pin']); ?></strong></td>
                                <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                <td><?php echo htmlspecialchars($student['phone']); ?></td>
                                <td><?php echo htmlspecialchars($student['gender']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($student['enrollment_date'])); ?></td>
                                <td>
                                    <div class="action-buttons-small">
                                        <a href="attendance.php?student_id=<?php echo $student['id']; ?>" class="btn btn-small btn-success">Attendance</a>
                                        <a href="fees.php?student_id=<?php echo $student['id']; ?>" class="btn btn-small btn-warning">Fees</a>
                                        <a href="view_student.php?id=<?php echo $student['id']; ?>" class="btn btn-small">View</a>
                                        <a href="student_form.php?id=<?php echo $student['id']; ?>" class="btn btn-small btn-primary">Edit</a>
                                        <a href="students.php?action=delete&id=<?php echo $student['id']; ?>" 
                                           class="btn btn-small btn-danger" 
                                           onclick="return confirm('Are you sure you want to delete <?php echo addslashes($student['full_name']); ?>? This action cannot be undone.')">Delete</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <p>No students found. 
                        <?php if (!empty($search)): ?>
                            Try a different search term.
                        <?php else: ?>
                            <a href="student_form.php">Add your first student</a>.
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Quick Stats -->
        <div class="quick-stats">
            <div class="stat-card">
                <h3>Total Students</h3>
                <p class="stat-number"><?php echo $total_students; ?></p>
            </div>
            <div class="stat-card">
                <h3>Male Students</h3>
                <p class="stat-number">
                    <?php echo $db->query("SELECT COUNT(*) FROM students WHERE gender = 'Male'")->fetchColumn(); ?>
                </p>
            </div>
            <div class="stat-card">
                <h3>Female Students</h3>
                <p class="stat-number">
                    <?php echo $db->query("SELECT COUNT(*) FROM students WHERE gender = 'Female'")->fetchColumn(); ?>
                </p>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>