<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$message = '';

// Get Teacher ID
$stmt = $db->prepare("SELECT id, full_name FROM teachers WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher) {
    echo "Teacher profile not found.";
    exit();
}

// Handle Assignment Deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $assignment_id = $_GET['id'];
    try {
        // Ensure the assignment belongs to this teacher
        $delete_stmt = $db->prepare("DELETE FROM assignments WHERE id = ? AND teacher_id = ?");
        $delete_stmt->execute([$assignment_id, $teacher['id']]);
        
        if ($delete_stmt->rowCount() > 0) {
            $_SESSION['success'] = "Assignment deleted successfully.";
        } else {
            $_SESSION['error'] = "Assignment not found or unauthorized.";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error deleting assignment: " . $e->getMessage();
    }
    header("Location: assignments.php");
    exit();
}

// Display messages from session
$success_message = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error_message = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['success'], $_SESSION['error']);

// Handle Assignment Posting (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $due_date = $_POST['due_date'] ?? '';
    $target_type = $_POST['target_type'] ?? 'class';
    $classroom_id = !empty($_POST['classroom_id']) ? $_POST['classroom_id'] : null;
    $student_id = !empty($_POST['student_id']) ? $_POST['student_id'] : null;

    if ($target_type === 'class') {
        $student_id = null; // Send to whole class
    } else {
        $classroom_id = null; // Send to individual student
    }

    if (!empty($title) && !empty($description) && !empty($due_date) && ($classroom_id !== null || $student_id !== null)) {
        $due_ts = strtotime($due_date);
        if ($due_ts === false) {
            $error_message = "Please enter a valid due date.";
        } elseif ($due_ts < strtotime(date('Y-m-d'))) {
            $error_message = "Due date cannot be in the past.";
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO assignments (teacher_id, classroom_id, student_id, title, description, due_date) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$teacher['id'], $classroom_id, $student_id, $title, $description, $due_date]);
                $success_message = "Assignment posted successfully!";
            } catch (PDOException $e) {
                $error_message = "Error: " . $e->getMessage();
            }
        }
    } else {
        $error_message = "Please fill in all required fields and select a valid target.";
    }
}

// Fetch Teacher's Assigned Classes for target dropdown
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

// Fetch Students in Teacher's Assigned Classes for target dropdown
$stmt_students = $db->prepare("
    SELECT DISTINCT s.id, s.full_name, s.pin, r.room_number 
    FROM students s
    JOIN rooms r ON s.classroom_id = r.id
    LEFT JOIN student_subjects ss ON ss.student_id = s.id
    LEFT JOIN teacher_subjects ts ON ts.subject_id = ss.subject_id
    WHERE r.teacher_id = ? OR ts.teacher_id = ?
    ORDER BY s.full_name ASC
");
$stmt_students->execute([$teacher['id'], $teacher['id']]);
$students = $stmt_students->fetchAll(PDO::FETCH_ASSOC);

// Fetch Sent Assignments with submission count
$query_assignments = "
    SELECT a.*, r.room_number, s.full_name as student_name, s.pin as student_pin,
           (SELECT COUNT(*) FROM assignment_submissions sub WHERE sub.assignment_id = a.id) as response_count
    FROM assignments a
    LEFT JOIN rooms r ON a.classroom_id = r.id
    LEFT JOIN students s ON a.student_id = s.id
    WHERE a.teacher_id = ?
    ORDER BY a.created_at DESC
";
$stmt_assignments = $db->prepare($query_assignments);
$stmt_assignments->execute([$teacher['id']]);
$assignments = $stmt_assignments->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Assignments - Teacher Portal</title>
    <link rel="stylesheet" href="../assets/style.css?v=<?php echo time(); ?>">
    <style>
        .assignment-form {
            display: none;
            background: #fff;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        .target-group {
            display: none;
        }
        .target-group.active {
            display: block;
        }
        .radio-options {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }
        .radio-options label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-weight: 500;
        }
        .message { padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .error { background: #fee; color: #c00; border: 1px solid #fcc; }
        .success { background: #efe; color: #0c0; border: 1px solid #cfc; }
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
                <li><a href="attendance.php">Mark Attendance</a></li>
                <li><a href="attendance_history.php">Attendance History</a></li>
                <li><a href="grades.php">Grades</a></li>
                <li><a href="assignments.php" class="active">Assignments</a></li>
                <li><a href="profile.php">My Profile</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="page-header">
                <h1>Manage Assignments</h1>
                <button onclick="toggleForm()" class="btn btn-primary">Create Assignment</button>
            </div>

            <?php if ($success_message): ?><div class="message success"><?php echo $success_message; ?></div><?php endif; ?>
            <?php if ($error_message): ?><div class="message error"><?php echo $error_message; ?></div><?php endif; ?>

            <!-- Create Assignment Form -->
            <div id="assignmentForm" class="assignment-form card">
                <h2 style="margin-bottom: 20px; color: #2c3e50;">New Assignment Details</h2>
                <form method="POST" action="assignments.php">
                    <div class="form-group">
                        <label>Assignment Title *</label>
                        <input type="text" name="title" required placeholder="Enter assignment title...">
                    </div>

                    <div class="form-group">
                        <label>Description / Instructions *</label>
                        <textarea name="description" rows="5" required placeholder="Write assignment description and instructions here..."></textarea>
                    </div>

                    <div class="form-group">
                        <label>Due Date *</label>
                        <input type="date" name="due_date" required min="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="form-group">
                        <label>Send To *</label>
                        <div class="radio-options">
                            <label>
                                <input type="radio" name="target_type" value="class" checked onclick="switchTarget('class')">
                                Entire Classroom
                            </label>
                            <label>
                                <input type="radio" name="target_type" value="student" onclick="switchTarget('student')">
                                Individual Student
                            </label>
                        </div>
                    </div>

                    <!-- Classroom Target Select -->
                    <div id="classroom_target" class="form-group target-group active">
                        <label>Choose Classroom *</label>
                        <select name="classroom_id" id="classroom_select">
                            <option value="">-- Select Classroom --</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['room_number']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Student Target Select -->
                    <div id="student_target" class="form-group target-group">
                        <label>Choose Student *</label>
                        <select name="student_id" id="student_select">
                            <option value="">-- Select Student --</option>
                            <?php foreach ($students as $s): ?>
                                <option value="<?php echo $s['id']; ?>">
                                    <?php echo htmlspecialchars($s['full_name']); ?> (<?php echo $s['student_pin'] ?? $s['pin']; ?>) - Room <?php echo htmlspecialchars($s['room_number']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 25px;">
                        <button type="submit" class="btn btn-primary">Publish Assignment</button>
                        <button type="button" onclick="toggleForm()" class="btn">Cancel</button>
                    </div>
                </form>
            </div>

            <!-- Sent Assignments List -->
            <div class="table-container">
                <h2 style="padding: 20px 20px 10px; color: #2c3e50; font-size: 1.3em;">Sent Assignments</h2>
                <?php if (!empty($assignments)): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Assignment Title</th>
                                <th>Assigned To</th>
                                <th>Due Date</th>
                                <th>Date Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assignments as $row): ?>
                            <tr>
                                <?php $display_title = preg_replace('/^Sample\\s+/i', '', $row['title']); ?>
                                <td>
                                    <strong><?php echo htmlspecialchars($display_title); ?></strong>
                                    <div style="font-size: 0.85em; color: #666; margin-top: 5px; max-width: 400px; white-space: pre-line;"><?php echo htmlspecialchars(substr($row['description'], 0, 200)) . (strlen($row['description']) > 200 ? '...' : ''); ?></div>
                                </td>
                                <td>
                                    <?php if ($row['room_number']): ?>
                                        <span class="badge" style="background: #3498db; color: white;">Class: Room <?php echo htmlspecialchars($row['room_number']); ?></span>
                                    <?php elseif ($row['student_name']): ?>
                                        <span class="badge" style="background: #2ecc71; color: white;">Student: <?php echo htmlspecialchars($row['student_name']); ?> (<?php echo htmlspecialchars($row['student_pin']); ?>)</span>
                                    <?php else: ?>
                                        <span class="badge" style="background: #7f8c8d; color: white;">Unknown Target</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="color: <?php echo (strtotime($row['due_date']) < time()) ? '#e74c3c' : '#2c3e50'; ?>; font-weight: 600;">
                                        <?php echo date('M d, Y', strtotime($row['due_date'])); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y h:i A', strtotime($row['created_at'])); ?></td>
                                <td>
                                    <a href="assignment_responses.php?assignment_id=<?php echo $row['id']; ?>" 
                                       class="btn btn-small" 
                                       style="background: #27ae60; color: white; text-decoration: none; padding: 4px 8px; border-radius: 4px; display: inline-block; margin-bottom: 5px; font-size: 0.85em; font-weight: bold;">View Responses (<?php echo $row['response_count']; ?>)</a>
                                    <a href="assignments.php?action=delete&id=<?php echo $row['id']; ?>" 
                                       class="btn btn-small btn-danger" 
                                       style="padding: 4px 8px; font-size: 0.85em;"
                                       onclick="return confirm('Are you sure you want to cancel and delete this assignment?')">Cancel / Delete</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state" style="padding: 60px; text-align: center; background: #f9f9f9; border-radius: 8px; margin: 20px;">
                        <p style="color: #666; font-size: 1.1em;">You have not sent any assignments yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function toggleForm() {
            var form = document.getElementById('assignmentForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
            if (form.style.display === 'block') {
                form.scrollIntoView({ behavior: 'smooth' });
            }
        }

        function switchTarget(type) {
            var classGroup = document.getElementById('classroom_target');
            var studentGroup = document.getElementById('student_target');
            var classSelect = document.getElementById('classroom_select');
            var studentSelect = document.getElementById('student_select');

            if (type === 'class') {
                classGroup.classList.add('active');
                studentGroup.classList.remove('active');
                classSelect.setAttribute('required', 'required');
                studentSelect.removeAttribute('required');
            } else {
                studentGroup.classList.add('active');
                classGroup.classList.remove('active');
                studentSelect.setAttribute('required', 'required');
                classSelect.removeAttribute('required');
            }
        }

        // Initialize target fields validation
        document.addEventListener('DOMContentLoaded', function() {
            switchTarget('class');
        });
    </script>
</body>
</html>
