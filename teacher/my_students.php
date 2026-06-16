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

// Classroom Filter (Optional now)
$classroom_id = isset($_GET['classroom_id']) ? $_GET['classroom_id'] : '';
$students = [];

if ($classroom_id) {
    // Verify this classroom belongs to or is taught by the teacher
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
        $query = "
            SELECT DISTINCT s.*, r.room_number AS classroom_number, hr.room_number AS hostel_number
            FROM students s
            JOIN rooms r ON s.classroom_id = r.id 
            LEFT JOIN rooms hr ON hr.id = s.room_id
            LEFT JOIN student_subjects ss ON ss.student_id = s.id
            LEFT JOIN teacher_subjects ts ON ts.subject_id = ss.subject_id
            WHERE s.classroom_id = ? AND (r.teacher_id = ? OR ts.teacher_id = ?)
            ORDER BY s.full_name";
        $stmt_students = $db->prepare($query);
        $stmt_students->execute([$classroom_id, $teacher['id'], $teacher['id']]);
        $students = $stmt_students->fetchAll(PDO::FETCH_ASSOC);
    }
} else {
    // Fetch all students in all classrooms assigned/taught by this teacher
    $query = "
        SELECT DISTINCT s.*, r.room_number AS classroom_number, hr.room_number AS hostel_number
        FROM students s
        JOIN rooms r ON s.classroom_id = r.id 
        LEFT JOIN rooms hr ON hr.id = s.room_id
        LEFT JOIN student_subjects ss ON ss.student_id = s.id
        LEFT JOIN teacher_subjects ts ON ts.subject_id = ss.subject_id
        WHERE r.teacher_id = ? OR ts.teacher_id = ? 
        ORDER BY s.full_name";
    $stmt_students = $db->prepare($query);
    $stmt_students->execute([$teacher['id'], $teacher['id']]);
    $students = $stmt_students->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Roster - Teacher Portal</title>
    <link rel="stylesheet" href="../assets/style.css?v=<?php echo time(); ?>">
    <style>
        .student-profile-modal .form-group { margin-bottom: 14px; }
        .student-profile-modal .form-group label { display: block; font-weight: 600; margin-bottom: 6px; color: #34495e; }
        .student-profile-modal .form-group input, .student-profile-modal .form-group textarea { width: 100%; padding: 10px 12px; border: 1px solid #dfe6e9; border-radius: 6px; background: #f9fbfd; }
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.55); display: none; align-items: center; justify-content: center; z-index: 1000; padding: 15px; }
        .modal-overlay.active { display: flex; }
        .modal { width: 100%; max-width: 620px; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 18px 45px rgba(0,0,0,0.18); }
        .modal-header { padding: 18px 22px; background: #f6f8fa; display: flex; justify-content: space-between; align-items: center; }
        .modal-body { padding: 20px; max-height: 72vh; overflow-y: auto; }
        .modal-close { border: none; background: transparent; font-size: 1.4rem; cursor: pointer; }
        .btn-link { text-decoration: underline; }
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
                <li><a href="my_students.php" class="active">My Students</a></li>
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
                <h1>Academic Roster</h1>
                <p>View students enrolled in your classrooms</p>
            </div>

            <!-- Optional Class Filter Form -->
            <div class="card" style="margin-bottom: 25px;">
                <form method="GET" action="my_students.php" style="display: flex; gap: 15px; align-items: flex-end;">
                    <div class="form-group" style="margin: 0; flex: 1;">
                        <label>Filter by Classroom</label>
                        <select name="classroom_id" onchange="this.form.submit()">
                            <option value="">-- All Assigned Classes --</option>
                            <?php foreach($classes as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo ($classroom_id == $c['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['room_number']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($classroom_id): ?>
                        <a href="my_students.php" class="btn">Clear Filter</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="table-container">
                <?php if (!empty($students)): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Institutional PIN</th>
                                <th>Full Name</th>
                                <th>Classroom</th>
                                <th>Gender</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $row): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($row['pin']); ?></code></td>
                                <td>
                                    <button type="button" class="btn btn-link student-profile-btn" style="padding:0; border:none; background:none; color:#3498db; cursor:pointer;" 
                                        data-name="<?php echo htmlspecialchars($row['full_name']); ?>"
                                        data-pin="<?php echo htmlspecialchars($row['pin']); ?>"
                                        data-dob="<?php echo htmlspecialchars($row['date_of_birth']); ?>"
                                        data-enrollment="<?php echo htmlspecialchars($row['enrollment_date']); ?>"
                                        data-email="<?php echo htmlspecialchars($row['email']); ?>"
                                        data-phone="<?php echo htmlspecialchars($row['phone']); ?>"
                                        data-address="<?php echo htmlspecialchars($row['address']); ?>"
                                        data-classroom="<?php echo htmlspecialchars($row['classroom_number']); ?>"
                                        data-hostel="<?php echo htmlspecialchars($row['hostel_number']); ?>"
                                        data-gender="<?php echo htmlspecialchars($row['gender']); ?>">
                                        <?php echo htmlspecialchars($row['full_name']); ?>
                                    </button>
                                </td>
                                <td>Room <?php echo htmlspecialchars($row['classroom_number']); ?></td>
                                <td><?php echo htmlspecialchars($row['gender']); ?></td>
                                <td style="display: flex; gap: 8px; flex-wrap: wrap;">
                                    <a href="student_performance.php?student_id=<?php echo $row['id']; ?>&classroom_id=<?php echo $row['classroom_id']; ?>" class="btn btn-small btn-primary">Academic Performance</a>
                                    <a href="attendance_history.php?student_id=<?php echo $row['id']; ?>&classroom_id=<?php echo $row['classroom_id']; ?>" class="btn btn-small">Attendance Log</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state" style="padding: 60px; text-align: center; background: #f9f9f9; border-radius: 8px; border: 1px dashed #ccc;">
                        <p style="color: #666; font-size: 1.1em;">No students found in your classrooms.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="studentProfileModal">
        <div class="modal">
            <div class="modal-header">
                <h3>Student Profile</h3>
                <button type="button" class="modal-close" id="closeStudentModal">&times;</button>
            </div>
            <div class="modal-body student-profile-modal">
                <div class="form-group"><label>Full Name</label><input type="text" id="modal_student_name" disabled></div>
                <div class="form-group"><label>PIN</label><input type="text" id="modal_student_pin" disabled></div>
                <div class="form-group"><label>Date of Birth</label><input type="text" id="modal_student_dob" disabled></div>
                <div class="form-group"><label>Enrollment Date</label><input type="text" id="modal_student_enrollment" disabled></div>
                <div class="form-group"><label>Email</label><input type="text" id="modal_student_email" disabled></div>
                <div class="form-group"><label>Phone</label><input type="text" id="modal_student_phone" disabled></div>
                <div class="form-group"><label>Address</label><textarea id="modal_student_address" rows="3" disabled></textarea></div>
                <div class="form-group"><label>Classroom</label><input type="text" id="modal_student_classroom" disabled></div>
                <div class="form-group"><label>Hostel Room</label><input type="text" id="modal_student_hostel" disabled></div>
                <div class="form-group"><label>Gender</label><input type="text" id="modal_student_gender" disabled></div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const buttons = document.querySelectorAll('.student-profile-btn');
            const modal = document.getElementById('studentProfileModal');
            const closeBtn = document.getElementById('closeStudentModal');
            const fields = {
                name: document.getElementById('modal_student_name'),
                pin: document.getElementById('modal_student_pin'),
                dob: document.getElementById('modal_student_dob'),
                enrollment: document.getElementById('modal_student_enrollment'),
                email: document.getElementById('modal_student_email'),
                phone: document.getElementById('modal_student_phone'),
                address: document.getElementById('modal_student_address'),
                classroom: document.getElementById('modal_student_classroom'),
                hostel: document.getElementById('modal_student_hostel'),
                gender: document.getElementById('modal_student_gender')
            };

            buttons.forEach(button => {
                button.addEventListener('click', function() {
                    fields.name.value = this.dataset.name || 'N/A';
                    fields.pin.value = this.dataset.pin || 'N/A';
                    fields.dob.value = this.dataset.dob ? new Date(this.dataset.dob).toLocaleDateString() : 'N/A';
                    fields.enrollment.value = this.dataset.enrollment ? new Date(this.dataset.enrollment).toLocaleDateString() : 'N/A';
                    fields.email.value = this.dataset.email || 'N/A';
                    fields.phone.value = this.dataset.phone || 'N/A';
                    fields.address.value = this.dataset.address || 'N/A';
                    fields.classroom.value = this.dataset.classroom ? 'Room ' + this.dataset.classroom : 'N/A';
                    fields.hostel.value = this.dataset.hostel ? 'Room ' + this.dataset.hostel : 'None assigned';
                    fields.gender.value = this.dataset.gender || 'N/A';
                    modal.classList.add('active');
                });
            });

            closeBtn.addEventListener('click', function() {
                modal.classList.remove('active');
            });

            modal.addEventListener('click', function(event) {
                if (event.target === modal) {
                    modal.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>
