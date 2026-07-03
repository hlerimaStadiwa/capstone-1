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

function calculateGradeLabel($score) {
    if ($score >= 80) return 'A';
    if ($score >= 66) return 'B';
    if ($score >= 50) return 'C';
    if ($score >= 45) return 'D';
    return 'U';
}

// Parse Subjects
$teacher_subjects = [];
if (!empty($teacher['subject_specialization'])) {
    $parts = explode(',', $teacher['subject_specialization']);
    foreach ($parts as $part) {
        $teacher_subjects[] = trim($part);
    }
}
if (empty($teacher_subjects)) {
    $teacher_subjects = ['General'];
}

// Handle Grade or Request Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['request_update'])) {
        $student_id = $_POST['student_id'];
        $subject_name = $_POST['subject_name'];
        $term = $_POST['term'];
        $new_score = $_POST['new_score'];
        $new_comments = trim($_POST['new_comments']);
        $reason = trim($_POST['reason']);

        if (empty($student_id) || empty($subject_name) || trim($reason) === '' || trim($new_score) === '') {
            $message = "<div class='message error'>Please provide student, subject, new score, and reason.</div>";
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO grade_update_requests (teacher_id, student_id, subject_name, term, new_score, new_comments, reason) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$teacher['id'], $student_id, $subject_name, $term, $new_score, $new_comments, $reason]);
                $message = "<div class='message success'>Grade update request submitted successfully.</div>";
            } catch (PDOException $e) {
                $message = "<div class='message error'>Error: " . $e->getMessage() . "</div>";
            }
        }
    } else {
        $student_id = $_POST['student_id'];
        $subject_name = $_POST['subject_name'];
        $score = $_POST['score'];
        $term = $_POST['term'];
        $comments = trim($_POST['comments']);
        $grade_label = calculateGradeLabel((float)$score);

        try {
            $stmt = $db->prepare("INSERT INTO grades (student_id, subject_name, score, grade, term, comments) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$student_id, $subject_name, $score, $grade_label, $term, $comments]);
            $message = "<div class='message success'>Grade added successfully!</div>";
        } catch (PDOException $e) {
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

// Fetch Students in Teacher's Assigned Classes
$stmt_students = $db->prepare("
    SELECT DISTINCT s.id, s.full_name, s.pin, s.classroom_id 
    FROM students s
    JOIN rooms r ON s.classroom_id = r.id
    LEFT JOIN student_subjects ss ON ss.student_id = s.id
    LEFT JOIN teacher_subjects ts ON ts.subject_id = ss.subject_id
    WHERE r.teacher_id = ? OR ts.teacher_id = ?
    ORDER BY s.full_name ASC
");
$stmt_students->execute([$teacher['id'], $teacher['id']]);
$students = $stmt_students->fetchAll(PDO::FETCH_ASSOC);

// Parse Subjects
$teacher_subjects = [];
if (!empty($teacher['subject_specialization'])) {
    // Split by comma if multiple subjects are stored like "Math, Science"
    $parts = explode(',', $teacher['subject_specialization']);
    foreach ($parts as $part) {
        $teacher_subjects[] = trim($part);
    }
}
if (empty($teacher_subjects)) {
    $teacher_subjects = ['General'];
}

// Fetch recorded grades for teacher subjects
$recordedGrades = [];
if (!empty($teacher_subjects)) {
    $placeholders = implode(',', array_fill(0, count($teacher_subjects), '?'));
    $stmt_recorded = $db->prepare("SELECT g.*, s.full_name AS student_name FROM grades g JOIN students s ON g.student_id = s.id WHERE g.subject_name IN ($placeholders) ORDER BY g.id DESC");
    $stmt_recorded->execute($teacher_subjects);
    $recordedGrades = $stmt_recorded->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch teacher's grade update requests
$stmt_requests = $db->prepare("SELECT r.*, s.full_name AS student_name FROM grade_update_requests r JOIN students s ON s.id = r.student_id WHERE r.teacher_id = ? ORDER BY r.created_at DESC");
$stmt_requests->execute([$teacher['id']]);
$gradeRequests = $stmt_requests->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Input Grades - Teacher Dashboard</title>
    <link rel="stylesheet" href="../assets/style.css?v=<?php echo time(); ?>">
    <style>
        .section-grid { display: grid; gap: 20px; margin-top: 25px; }
        @media (min-width: 900px) { .section-grid { grid-template-columns: 1fr 1fr; } }
        .request-panel, .recorded-panel { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .grade-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .grade-table th, .grade-table td { text-align: left; padding: 12px 10px; border-bottom: 1px solid #f0f0f0; }
        .grade-table th { background: #fafafa; font-weight: 700; }
        .status-pill { display: inline-flex; align-items: center; justify-content: center; padding: 6px 12px; border-radius: 999px; font-size: 0.8em; color: white; }
        .status-Pending { background: #f39c12; }
        .status-Approved { background: #27ae60; }
        .status-Rejected { background: #c0392b; }
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.55); display: none; align-items: center; justify-content: center; z-index: 1000; padding: 15px; }
        .modal-overlay.active { display: flex; }
        .modal { width: 100%; max-width: 640px; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 18px 45px rgba(0,0,0,0.18); }
        .modal-header { padding: 18px 22px; background: #f6f8fa; display: flex; justify-content: space-between; align-items: center; }
        .modal-body { padding: 20px; max-height: 70vh; overflow-y: auto; }
        .modal-close { border: none; background: transparent; font-size: 1.5rem; cursor: pointer; }
        .request-button { margin: 0; }
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
                <li><a href="grades.php" class="active">Grades</a></li>
                <li><a href="assignments.php">Assignments</a></li>
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

            <div class="section-grid">
                <div class="request-panel">
                    <h2>Recorded Grades</h2>
                    <?php if (!empty($recordedGrades)): ?>
                        <table class="grade-table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Subject</th>
                                    <th>Term</th>
                                    <th>Score</th>
                                    <th>Grade</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recordedGrades as $grade): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($grade['student_name']); ?></td>
                                        <td><?php echo htmlspecialchars($grade['subject_name']); ?></td>
                                        <td><?php echo htmlspecialchars($grade['term']); ?></td>
                                        <td><?php echo number_format($grade['score'], 1); ?>%</td>
                                        <td><?php echo htmlspecialchars($grade['grade']); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-small request-button" data-student-id="<?php echo $grade['student_id']; ?>" data-student-name="<?php echo htmlspecialchars($grade['student_name']); ?>" data-subject="<?php echo htmlspecialchars($grade['subject_name']); ?>" data-term="<?php echo htmlspecialchars($grade['term']); ?>">Request Update</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state" style="padding: 20px; text-align: center; color: #7f8c8d;">No recorded grades found for your subjects yet.</div>
                    <?php endif; ?>
                </div>

                <div class="recorded-panel">
                    <h2>Grade Request Ledger</h2>
                    <?php if (!empty($gradeRequests)): ?>
                        <table class="grade-table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Subject</th>
                                    <th>Term</th>
                                    <th>Proposed Score</th>
                                    <th>Status</th>
                                    <th>Submitted</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($gradeRequests as $request): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($request['student_name']); ?></td>
                                        <td><?php echo htmlspecialchars($request['subject_name']); ?></td>
                                        <td><?php echo htmlspecialchars($request['term']); ?></td>
                                        <td><?php echo number_format($request['new_score'], 1); ?>%</td>
                                        <td><span class="status-pill status-<?php echo htmlspecialchars($request['status']); ?>"><?php echo htmlspecialchars($request['status']); ?></span></td>
                                        <td><?php echo date('M d, Y H:i', strtotime($request['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state" style="padding: 20px; text-align: center; color: #7f8c8d;">You have not submitted any grade update requests yet.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="gradeRequestModal">
        <div class="modal">
            <div class="modal-header">
                <h3>Request Grade Update</h3>
                <button type="button" class="modal-close" id="closeGradeModal">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" class="request-form">
                    <input type="hidden" name="request_update" value="1">
                    <input type="hidden" name="student_id" id="modal_student_id">

                    <div class="form-group">
                        <label>Student</label>
                        <input type="text" id="modal_student_name" disabled>
                    </div>
                    <div class="form-group">
                        <label>Subject</label>
                        <input type="text" id="modal_subject" disabled>
                        <input type="hidden" name="subject_name" id="modal_subject_input">
                    </div>
                    <div class="form-group">
                        <label>Academic Term</label>
                        <input type="text" id="modal_term" name="term" readonly>
                    </div>
                    <div class="form-group">
                        <label>Proposed Score</label>
                        <input type="number" name="new_score" id="modal_new_score" min="0" max="100" step="0.1" required>
                    </div>
                    <div class="form-group">
                        <label>Teacher Comments</label>
                        <textarea name="new_comments" id="modal_new_comments" rows="3" placeholder="Optional note to admin..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Reason for Change</label>
                        <textarea name="reason" id="modal_reason" rows="3" required placeholder="Why should this grade be updated?"></textarea>
                    </div>
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" class="btn btn-primary">Submit Request</button>
                        <button type="button" class="btn" id="cancelGradeModal">Cancel</button>
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
            const gradeRequestModal = document.getElementById('gradeRequestModal');
            const modalCloseButtons = document.querySelectorAll('#closeGradeModal, #cancelGradeModal');
            const requestButtons = document.querySelectorAll('.request-button');
            const modalStudentId = document.getElementById('modal_student_id');
            const modalStudentName = document.getElementById('modal_student_name');
            const modalSubject = document.getElementById('modal_subject');
            const modalSubjectInput = document.getElementById('modal_subject_input');
            const modalTerm = document.getElementById('modal_term');

            classSelector.addEventListener('change', function() {
                const selectedClassId = this.value;
                studentSelector.value = "";

                if (selectedClassId) {
                    studentGroup.style.display = 'block';
                    allStudentOptions.forEach(option => {
                        if (option.getAttribute('data-class-id') === selectedClassId) {
                            option.style.display = 'block';
                            option.disabled = false;
                        } else {
                            option.style.display = 'none';
                            option.disabled = true;
                        }
                    });
                } else {
                    studentGroup.style.display = 'none';
                }
            });

            requestButtons.forEach(button => {
                button.addEventListener('click', function() {
                    modalStudentId.value = this.dataset.studentId;
                    modalStudentName.value = this.dataset.studentName;
                    modalSubject.value = this.dataset.subject;
                    modalSubjectInput.value = this.dataset.subject;
                    modalTerm.value = this.dataset.term;
                    gradeRequestModal.classList.add('active');
                });
            });

            modalCloseButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    gradeRequestModal.classList.remove('active');
                });
            });

            gradeRequestModal.addEventListener('click', function(event) {
                if (event.target === gradeRequestModal) {
                    gradeRequestModal.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>
