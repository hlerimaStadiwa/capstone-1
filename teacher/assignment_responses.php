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

// Get Assignment ID
$assignment_id = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : 0;

if (!$assignment_id) {
    header("Location: assignments.php");
    exit();
}

// Fetch Assignment and verify ownership
$stmt_assign = $db->prepare("
    SELECT a.*, r.room_number, s.full_name as student_name, s.pin as student_pin
    FROM assignments a
    LEFT JOIN rooms r ON a.classroom_id = r.id
    LEFT JOIN students s ON a.student_id = s.id
    WHERE a.id = ? AND a.teacher_id = ?
");
$stmt_assign->execute([$assignment_id, $teacher['id']]);
$assignment = $stmt_assign->fetch(PDO::FETCH_ASSOC);

if (!$assignment) {
    echo "Assignment not found or access denied.";
    exit();
}

// Handle Grading Form Submission (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'grade') {
    $student_id = (int)$_POST['student_id'];
    $score = isset($_POST['score']) ? (float)$_POST['score'] : null;
    $feedback = trim($_POST['feedback'] ?? '');

    if ($score >= 0 && $score <= 100) {
        try {
            $stmt_grade = $db->prepare("
                UPDATE assignment_submissions 
                SET score = ?, feedback = ?, graded_at = CURRENT_TIMESTAMP 
                WHERE assignment_id = ? AND student_id = ?
            ");
            $stmt_grade->execute([$score, $feedback, $assignment_id, $student_id]);
            
            if ($stmt_grade->rowCount() > 0) {
                $_SESSION['success'] = "Grade recorded successfully!";
            } else {
                $_SESSION['error'] = "Submission not found or could not be graded.";
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error updating grade: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Score must be a valid number between 0 and 100.";
    }
    
    header("Location: assignment_responses.php?assignment_id=" . $assignment_id);
    exit();
}

// Retrieve Session Messages
$success_message = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error_message = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['success'], $_SESSION['error']);

// Fetch list of students targeted by this assignment
$students = [];
if ($assignment['classroom_id']) {
    // Classroom-targeted: Fetch all students in that class
    $stmt_students = $db->prepare("
        SELECT s.id, s.full_name, s.pin,
               sub.file_path as submission_file, sub.submitted_at as submission_date, 
               sub.score as submission_score, sub.feedback as submission_feedback, sub.graded_at
        FROM students s
        LEFT JOIN assignment_submissions sub ON sub.assignment_id = ? AND sub.student_id = s.id
        WHERE s.classroom_id = ?
        ORDER BY s.full_name ASC
    ");
    $stmt_students->execute([$assignment_id, $assignment['classroom_id']]);
    $students = $stmt_students->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Individual student targeted
    $stmt_students = $db->prepare("
        SELECT s.id, s.full_name, s.pin,
               sub.file_path as submission_file, sub.submitted_at as submission_date, 
               sub.score as submission_score, sub.feedback as submission_feedback, sub.graded_at
        FROM students s
        LEFT JOIN assignment_submissions sub ON sub.assignment_id = ? AND sub.student_id = s.id
        WHERE s.id = ?
    ");
    $stmt_students->execute([$assignment_id, $assignment['student_id']]);
    $students = $stmt_students->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignment Responses - Teacher Portal</title>
    <link rel="stylesheet" href="../assets/style.css?v=<?php echo time(); ?>">
    <style>
        .assignment-summary {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            border-left: 5px solid #27ae60;
        }
        .grading-form {
            background: #fdfdfd;
            border: 1px solid #e0e0e0;
            padding: 15px;
            border-radius: 6px;
            margin-top: 10px;
        }
        .message { padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .error { background: #fee; color: #c00; border: 1px solid #fcc; }
        .success { background: #efe; color: #0c0; border: 1px solid #cfc; }
        .btn-green { background: #27ae60; color: white; border-radius: 4px; padding: 6px 12px; font-weight: bold; text-decoration: none; display: inline-block; font-size: 0.85em; }
        .btn-green:hover { background: #219653; }
        .btn-outline { border: 1px solid #bdc3c7; color: #34495e; padding: 6px 12px; border-radius: 4px; text-decoration: none; display: inline-block; font-size: 0.85em; background: transparent; }
        .btn-outline:hover { background: #f2f2f2; }
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
                <h1>Assignment Responses</h1>
                <a href="assignments.php" class="btn btn-outline">Back to Assignments</a>
            </div>

            <?php if ($success_message): ?><div class="message success"><?php echo $success_message; ?></div><?php endif; ?>
            <?php if ($error_message): ?><div class="message error"><?php echo $error_message; ?></div><?php endif; ?>

            <!-- Assignment Summary Card -->
            <div class="assignment-summary">
                <h2 style="color: #2c3e50; margin-bottom: 10px; font-size: 1.5em;"><?php echo htmlspecialchars($assignment['title']); ?></h2>
                <p style="color: #7f8c8d; font-size: 0.9em; margin-bottom: 15px;">
                    <strong>Target:</strong> 
                    <?php if ($assignment['room_number']): ?>
                        Class Room <?php echo htmlspecialchars($assignment['room_number']); ?>
                    <?php else: ?>
                        Student: <?php echo htmlspecialchars($assignment['student_name']); ?> (<?php echo htmlspecialchars($assignment['student_pin']); ?>)
                    <?php endif; ?>
                    &nbsp;|&nbsp; 
                    <strong>Due Date:</strong> <span style="color: <?php echo (strtotime($assignment['due_date']) < time()) ? '#e74c3c' : '#2c3e50'; ?>; font-weight: bold;"><?php echo date('M d, Y', strtotime($assignment['due_date'])); ?></span>
                </p>
                <div style="background: #f9f9f9; padding: 15px; border-radius: 6px; border: 1px solid #eee; white-space: pre-line; color: #555; line-height: 1.5;">
                    <?php echo htmlspecialchars($assignment['description']); ?>
                </div>
            </div>

            <!-- Student Submissions Table -->
            <div class="table-container">
                <h2 style="padding: 20px 20px 10px; color: #2c3e50; font-size: 1.3em;">Student Submissions</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>PIN</th>
                            <th>Status</th>
                            <th>Submitted At</th>
                            <th>Score / Grade</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr><td colspan="6" style="text-align: center; color: #999; padding: 30px;">No students assigned to this task.</td></tr>
                        <?php else: ?>
                            <?php foreach ($students as $row): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($row['full_name']); ?></strong></td>
                                    <td><code><?php echo htmlspecialchars($row['pin']); ?></code></td>
                                    <td>
                                        <?php if ($row['submission_file']): ?>
                                            <span class="badge" style="background: #2ecc71; color: white;">Submitted</span>
                                        <?php else: ?>
                                            <span class="badge" style="background: #e74c3c; color: white;">Not Submitted</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo $row['submission_date'] ? date('M d, Y h:i A', strtotime($row['submission_date'])) : '<span style="color: #bbb; font-style: italic;">N/A</span>'; ?>
                                    </td>
                                    <td>
                                        <?php if ($row['submission_score'] !== null): ?>
                                            <strong style="color: #27ae60; font-size: 1.1em;"><?php echo number_format($row['submission_score'], 1); ?>%</strong>
                                            <?php if ($row['submission_feedback']): ?>
                                                <div style="font-size: 0.8em; color: #666; font-style: italic; margin-top: 4px; max-width: 250px;">Feedback: "<?php echo htmlspecialchars($row['submission_feedback']); ?>"</div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color: #bbb; font-style: italic;">Ungraded</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($row['submission_file']): ?>
                                            <a href="../uploads/assignments/<?php echo htmlspecialchars($row['submission_file']); ?>" 
                                               class="btn-green" 
                                               target="_blank" 
                                               style="margin-bottom: 5px;">Download PDF</a>
                                            <button onclick="toggleGradeForm(<?php echo $row['id']; ?>)" 
                                                    class="btn-outline" 
                                                    style="margin-bottom: 5px; cursor: pointer;">
                                                <?php echo ($row['submission_score'] !== null) ? 'Edit Mark' : 'Mark / Grade'; ?>
                                            </button>
                                            
                                            <!-- Collapsible Grading Form -->
                                            <div id="grade-form-<?php echo $row['id']; ?>" class="grading-form" style="display: none;">
                                                <h4 style="margin-top: 0; color: #2c3e50; border-bottom: 1px solid #eee; padding-bottom: 5px; margin-bottom: 10px;">Marking Roster: <?php echo htmlspecialchars($row['full_name']); ?></h4>
                                                <form method="POST">
                                                    <input type="hidden" name="action" value="grade">
                                                    <input type="hidden" name="student_id" value="<?php echo $row['id']; ?>">
                                                    
                                                    <div class="form-group" style="margin-bottom: 10px;">
                                                        <label style="font-weight: bold; font-size: 0.95em;">Score (0 - 100%) *</label>
                                                        <input type="number" name="score" min="0" max="100" step="0.1" value="<?php echo $row['submission_score'] !== null ? htmlspecialchars($row['submission_score']) : ''; ?>" required style="padding: 6px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box;">
                                                    </div>
                                                    
                                                    <div class="form-group" style="margin-bottom: 12px;">
                                                        <label style="font-weight: bold; font-size: 0.95em;">Teacher Feedback</label>
                                                        <textarea name="feedback" rows="3" style="padding: 6px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box; resize: vertical;" placeholder="Write constructive feedback for the student..."><?php echo htmlspecialchars($row['submission_feedback'] ?? ''); ?></textarea>
                                                    </div>
                                                    
                                                    <div style="display: flex; gap: 8px;">
                                                        <button type="submit" class="btn btn-primary btn-small" style="font-weight: bold;">Save Grade</button>
                                                        <button type="button" onclick="toggleGradeForm(<?php echo $row['id']; ?>)" class="btn btn-small">Cancel</button>
                                                    </div>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: #7f8c8d; font-size: 0.9em; font-style: italic;">No submission to grade</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function toggleGradeForm(studentId) {
            var form = document.getElementById('grade-form-' + studentId);
            if (form) {
                form.style.display = form.style.display === 'none' ? 'block' : 'none';
            }
        }
    </script>
</body>
</html>
