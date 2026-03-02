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
$stmt = $db->prepare("SELECT id, full_name, subject_specialization FROM teachers WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher) {
    echo "Teacher profile not found.";
    exit();
}

// Handle Grade Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = $_POST['student_id'];
    $subject_name = $_POST['subject_name'];
    $score = $_POST['score'];
    $term = $_POST['term'];
    $comments = $_POST['comments'];
    
    // Calculate Grade Label
    $grade_label = 'F';
    if ($score >= 90) $grade_label = 'A';
    elseif ($score >= 80) $grade_label = 'B';
    elseif ($score >= 70) $grade_label = 'C';
    elseif ($score >= 60) $grade_label = 'D';

    try {
        $stmt = $db->prepare("INSERT INTO grades (student_id, subject_name, score, grade, term, comments) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$student_id, $subject_name, $score, $grade_label, $term, $comments]);
        $message = "<div class='message success'>Grade added successfully!</div>";
    } catch (PDOException $e) {
        $message = "<div class='message error'>Error: " . $e->getMessage() . "</div>";
    }
}

// Fetch Teacher's Assigned Classes
$stmt_classes = $db->prepare("SELECT id, room_number FROM rooms WHERE teacher_id = ? ORDER BY room_number");
$stmt_classes->execute([$teacher['id']]);
$classes = $stmt_classes->fetchAll(PDO::FETCH_ASSOC);

// Fetch Students in Teacher's Assigned Classes
$stmt_students = $db->prepare("
    SELECT s.id, s.full_name, s.pin, s.classroom_id 
    FROM students s
    JOIN rooms r ON s.classroom_id = r.id
    WHERE r.teacher_id = ?
    ORDER BY s.full_name ASC
");
$stmt_students->execute([$teacher['id']]);
$students = $stmt_students->fetchAll(PDO::FETCH_ASSOC);

// Parse Subjects
$teacher_subjects = [];
if (!empty($teacher['subject_specialization'])) {
    // Split by comma if multiple subjects are stored like "Math, Science"
    $parts = explode(',', $teacher['subject_specialization']);
    foreach ($parts as $part) {
        $teacher_subjects[] = trim($part);
    }
} else {
    // Fallback if no specialization is set
    $teacher_subjects = ['General'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Input Grades - Teacher Dashboard</title>
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
                <li><a href="my_students.php">My Students</a></li>
                <li><a href="student_performance.php">Student Performance</a></li>
                <li><a href="attendance.php">Mark Attendance</a></li>
                <li><a href="attendance_history.php">Attendance History</a></li>
                <li><a href="grades.php" class="active">Grades</a></li>
                <li><a href="profile.php">My Profile</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="page-header">
                <h1>Record Student Grades</h1>
                <p>Formal academic result entry</p>
            </div>
            <?php echo $message; ?>
            
            <div class="card form-container">
                <form method="POST" action="grades.php">
                    <div class="form-group">
                        <label>Academic Term</label>
                        <select name="term" required>
                            <option value="Term 1">Term 1</option>
                            <option value="Term 2">Term 2</option>
                            <option value="Term 3">Term 3</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Select Class</label>
                        <select id="class_selector" required>
                            <option value="">-- Choose Assigned Class --</option>
                            <?php foreach($classes as $class): ?>
                                <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['room_number']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" id="student_group" style="display:none;">
                        <label>Student</label>
                        <select name="student_id" id="student_selector" required>
                            <option value="">Select Student</option>
                            <?php foreach($students as $s): ?>
                                <option value="<?php echo $s['id']; ?>" data-class-id="<?php echo $s['classroom_id']; ?>">
                                    <?php echo htmlspecialchars($s['full_name']); ?> (<?php echo $s['pin']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Subject (Assigned Specialty Only)</label>
                        <select name="subject_name" required>
                            <?php foreach($teacher_subjects as $subject): ?>
                                <option value="<?php echo htmlspecialchars($subject); ?>"><?php echo htmlspecialchars($subject); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Score (0-100%)</label>
                        <input type="number" name="score" min="0" max="100" step="0.1" required>
                    </div>

                    <div class="form-group">
                        <label>Teacher Comments</label>
                        <textarea name="comments" rows="3" placeholder="Enter formal feedback for the student report card..."></textarea>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" class="btn btn-primary">Save Grade Entry</button>
                        <a href="dashboard.php" class="btn">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const classSelector = document.getElementById('class_selector');
            const studentGroup = document.getElementById('student_group');
            const studentSelector = document.getElementById('student_selector');
            const allStudentOptions = Array.from(studentSelector.querySelectorAll('option:not([value=""])'));

            classSelector.addEventListener('change', function() {
                const selectedClassId = this.value;
                
                // Reset student selector
                studentSelector.value = "";
                
                if (selectedClassId) {
                    studentGroup.style.display = 'block';
                    
                    // Filter options
                    allStudentOptions.forEach(option => {
                        if (option.getAttribute('data-class-id') === selectedClassId) {
                            option.style.display = 'block'; // Or keep it in DOM
                            option.disabled = false;
                        } else {
                            option.style.display = 'none'; // Simple hide
                            option.disabled = true; // Ensure it can't be selected
                        }
                    });

                    // For better browser compatibility (some Safari/Mobile don't support display:none on options),
                    // we might need to rebuild the select, but simple hide works on most modern desktops.
                    // A more robust way is removing them. Let's try the simple way first.
                } else {
                    studentGroup.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
