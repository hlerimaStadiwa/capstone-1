<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

// Get student data
$database = new Database();
$db = $database->getConnection();

$query = "SELECT s.* FROM students s 
          JOIN users u ON s.user_id = u.id 
          WHERE u.id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Calculate Total Paid Fees
$fee_stmt = $db->prepare("SELECT SUM(amount) FROM fees WHERE student_id = ?");
$fee_stmt->execute([$student['id']]);
$total_paid = $fee_stmt->fetchColumn() ?: 0;
$school_fee = 150.00;
$balance_due = max(0, $school_fee - $total_paid);
$min_fee_for_reports = $school_fee / 2; // 75.00
$has_access = ($total_paid >= $min_fee_for_reports);

$fee_transactions_stmt = $db->prepare("SELECT * FROM fees WHERE student_id = ? ORDER BY payment_date DESC, id DESC");
$fee_transactions_stmt->execute([$student['id']]);
$fee_transactions = $fee_transactions_stmt->fetchAll(PDO::FETCH_ASSOC);

$teachers_list = [];
if ($student['classroom_id']) {
    $c_t_stmt = $db->prepare("SELECT t.full_name, t.email, t.phone, t.gender, t.subject_specialization FROM rooms r JOIN teachers t ON r.teacher_id = t.id WHERE r.id = ?");
    $c_t_stmt->execute([$student['classroom_id']]);
    if ($ct = $c_t_stmt->fetch(PDO::FETCH_ASSOC)) {
        $teachers_list[] = [
            'name' => $ct['full_name'],
            'email' => $ct['email'],
            'phone' => $ct['phone'],
            'gender' => $ct['gender'],
            'subject_specialization' => $ct['subject_specialization'],
            'role' => 'Class Teacher'
        ];
    }
}
$sub_t_stmt = $db->prepare("SELECT DISTINCT t.full_name, t.email, t.phone, t.gender, t.subject_specialization, s.name AS subject_name FROM teachers t JOIN teacher_subjects ts ON ts.teacher_id = t.id JOIN student_subjects ss ON ss.subject_id = ts.subject_id JOIN subjects s ON ts.subject_id = s.id WHERE ss.student_id = ?");
$sub_t_stmt->execute([$student['id']]);
while ($st = $sub_t_stmt->fetch(PDO::FETCH_ASSOC)) {
    $is_duplicate = false;
    foreach ($teachers_list as $existing) {
        if ($existing['name'] === $st['full_name']) {
            $is_duplicate = true;
            break;
        }
    }
    if (!$is_duplicate) {
        $teachers_list[] = [
            'name' => $st['full_name'],
            'email' => $st['email'],
            'phone' => $st['phone'],
            'gender' => $st['gender'],
            'subject_specialization' => $st['subject_specialization'],
            'role' => $st['subject_name'] . ' Teacher'
        ];
    }
}

// Fetch hostel allocation (if any)
$hostel_room = null;
if (!empty($student['room_id'])) {
    $hr_stmt = $db->prepare("SELECT room_number FROM rooms WHERE id = ? AND room_type = 'Hostel'");
    $hr_stmt->execute([$student['room_id']]);
    $hostel_room = $hr_stmt->fetchColumn() ?: null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Danborough</title>
    <link rel="stylesheet" href="../assets/style.css?v=<?php echo time(); ?>">
    <style>
        .student-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-item { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); text-align: center; }
        .stat-item h4 { color: #7f8c8d; font-size: 0.8em; text-transform: uppercase; margin-bottom: 10px; }
        .stat-item .value { font-size: 1.5em; font-weight: bold; color: #2c3e50; }
        .panel-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
        .panel { background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 25px; }
        .panel-header { background: #f8f9fa; padding: 15px 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .panel-body { padding: 20px; }
        .news-item { padding-bottom: 15px; border-bottom: 1px solid #f0f0f0; margin-bottom: 15px; }
        .news-item:last-child { border-bottom: none; margin-bottom: 0; }
        .grade-badge { background: #e8f5e9; color: #2e7d32; padding: 2px 8px; border-radius: 12px; font-weight: bold; }
        .staff-avatar { width: 32px; height: 32px; background: #eee; border-radius: 50%; font-size: 10px; display: flex; align-items: center; justify-content: center; }
        .teacher-card:hover { background: #eef6ff; }
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.55); display: none; align-items: center; justify-content: center; z-index: 1000; padding: 15px; }
        .modal-overlay.active { display: flex; }
        .modal { width: 100%; max-width: 580px; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 18px 45px rgba(0,0,0,0.18); }
        .modal-header { padding: 18px 22px; background: #f6f8fa; display: flex; justify-content: space-between; align-items: center; }
        .modal-body { padding: 20px; max-height: 70vh; overflow-y: auto; }
        .modal-close { border: none; background: transparent; font-size: 1.4rem; cursor: pointer; }
        .modal-body .form-group { margin-bottom: 15px; }
        .modal-body .form-group label { display: block; font-weight: 600; margin-bottom: 6px; color: #34495e; }
        .modal-body .form-group input { width: 100%; padding: 10px 12px; border: 1px solid #dfe6e9; border-radius: 6px; background: #f9fbfd; }
        @media (max-width: 900px) { .panel-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="logo">Danborough</div>
            <div class="user-info" style="padding: 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1);">
                <div class="avatar-circle" style="width: 60px; height: 60px; background: #3498db; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: bold; margin: 0 auto 10px; color: white;">
                    <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                </div>
                <p>Welcome,<br><strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></p>
            </div>
            <ul class="nav-menu">
                <li><a href="dashboard.php" class="active">Dashboard</a></li>
                <li><a href="profile.php">My Profile</a></li>
                <li><a href="grades.php">My Grades</a></li>
                <li><a href="attendance.php">My Attendance</a></li>
                <li><a href="assignments.php">My Assignments</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="top-bar" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                <div>
                    <h1 style="font-size: 1.8em; color: #2c3e50;">Student Portal</h1>
                    <p style="color: #7f8c8d;">Home / Dashboard</p>
                </div>
                <div style="text-align: right; color: #7f8c8d;">
                    <div><?php echo date('l, F jS'); ?></div>
                    <div style="font-size: 0.8em;">Account: <span style="color: #27ae60; font-weight: bold;">Verified</span></div>
                </div>
            </div>

            <!-- Stats Bar -->
            <div class="student-stats">
                <div class="stat-item">
                    <h4>Enrollment ID</h4>
                    <div class="value"><?php echo htmlspecialchars($student['pin']); ?></div>
                </div>
                <div class="stat-item">
                    <h4>Room / Classroom</h4>
                    <div class="value" style="font-size: 1.2em;">
                        <?php
                        $locs = [];
                        if ($student['classroom_id']) {
                             $c_stmt = $db->prepare("SELECT room_number FROM rooms WHERE id = ?");
                             $c_stmt->execute([$student['classroom_id']]);
                             $locs[] = "Class " . $c_stmt->fetchColumn();
                        }
                        if ($student['room_id']) {
                             $r_stmt = $db->prepare("SELECT room_number FROM rooms WHERE id = ?");
                             $r_stmt->execute([$student['room_id']]);
                             $locs[] = "Hostel " . $r_stmt->fetchColumn();
                        }
                        echo !empty($locs) ? implode(' / ', $locs) : 'Not Allocated';
                        ?>
                    </div>
                </div>
                <div class="stat-item">
                    <h4>Hostel</h4>
                    <div class="value">
                        <?php echo $hostel_room ? 'Hostel ' . htmlspecialchars($hostel_room) : 'Not Allocated'; ?>
                    </div>
                </div>
                <div class="stat-item">
                    <h4>Fees Paid</h4>
                    <div class="value" style="color: <?php echo $has_access ? '#27ae60' : '#e74c3c'; ?>">
                        $<?php echo number_format($total_paid, 2); ?>
                    </div>
                    <small><?php echo $has_access ? 'Access Granted' : 'Access Locked'; ?></small>
                </div>                <div class="stat-item">
                    <h4>Tuition Balance</h4>
                    <div class="value" style="color: <?php echo $balance_due > 0 ? '#e74c3c' : '#27ae60'; ?>;">
                        $<?php echo number_format($balance_due, 2); ?>
                    </div>
                    <small><?php echo $balance_due > 0 ? 'Amount Due' : 'Paid in Full'; ?></small>
                </div>            </div>

            <div class="panel-grid">
                <!-- Left Column -->
                <div class="left-col">
                    <!-- Recent News -->
                    <div class="panel">
                        <div class="panel-header">
                            <h3 style="margin:0; font-size: 1.1em;">Announcements</h3>
                        </div>
                        <div class="panel-body">
                            <?php
                            $news_stmt = $db->query("SELECT * FROM news WHERE title NOT ILIKE '%silver%' ORDER BY created_at DESC LIMIT 3");
                            if ($news_stmt && $news_stmt->rowCount() > 0) {
                                while ($news = $news_stmt->fetch(PDO::FETCH_ASSOC)) {
                                    echo '<div class="news-item">';
                                    echo '<div style="display: flex; justify-content: space-between; margin-bottom: 5px;">';
                                    echo '<strong style="color: #2c3e50;">' . htmlspecialchars($news['title']) . '</strong>';
                                    echo '<small style="color: #95a5a6;">' . date('M d', strtotime($news['created_at'])) . '</small>';
                                    echo '</div>';
                                    echo '<p style="font-size: 0.9em; color: #7f8c8d;">' . htmlspecialchars(substr($news['content'], 0, 150)) . '...</p>';
                                    echo '</div>';
                                }
                            } else {
                                echo '<p style="text-align: center; color: #bdc3c7; padding: 20px;">No news available.</p>';
                            }
                            ?>
                        </div>
                    </div>

                    <!-- Recent Assignments -->
                    <div class="panel">
                        <div class="panel-header">
                            <h3 style="margin:0; font-size: 1.1em;">Recent Assignments</h3>
                            <a href="assignments.php" class="btn btn-small">View All</a>
                        </div>
                        <div class="panel-body">
                            <?php
                            $ass_stmt = $db->prepare("
                                SELECT a.*, t.full_name as teacher_name
                                FROM assignments a
                                JOIN teachers t ON a.teacher_id = t.id
                                WHERE (a.student_id = ? OR a.classroom_id = ?)
                                  AND a.title NOT ILIKE '%sample%'
                                  AND a.title NOT ILIKE '%shona%'
                                ORDER BY a.due_date ASC, a.created_at DESC
                                LIMIT 3
                            ");
                            $ass_stmt->execute([$student['id'], $student['classroom_id']]);
                            if ($ass_stmt && $ass_stmt->rowCount() > 0) {
                                while ($ass = $ass_stmt->fetch(PDO::FETCH_ASSOC)) {
                                    $is_overdue = (strtotime($ass['due_date']) < strtotime(date('Y-m-d')));
                                    echo '<div class="news-item" style="padding-bottom: 10px; border-bottom: 1px solid #f0f0f0; margin-bottom: 10px; ' . ($is_overdue ? 'border-left: 3px solid #e74c3c; padding-left: 10px;' : '') . '">';
                                    echo '<div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 5px;">';
                                    echo '<strong style="color: #2c3e50; font-size: 0.95em;">' . htmlspecialchars($ass['title']) . '</strong>';
                                    echo '<span style="font-size: 0.8em; font-weight: bold; padding: 2px 6px; border-radius: 4px; color: white; background: ' . ($is_overdue ? '#e74c3c' : '#2ecc71') . ';">' . ($is_overdue ? 'Overdue' : 'Active') . '</span>';
                                    echo '</div>';
                                    echo '<p style="font-size: 0.85em; color: #7f8c8d; margin-bottom: 5px;">';
                                    echo 'Assigned by ' . htmlspecialchars($ass['teacher_name']) . ' | Due: <strong>' . date('M d, Y', strtotime($ass['due_date'])) . '</strong>';
                                    echo '</p>';
                                    echo '</div>';
                                }
                            } else {
                                echo '<p style="text-align: center; color: #bdc3c7; padding: 20px; margin: 0;">No active assignments.</p>';
                            }
                            ?>
                        </div>
                    </div>

                    <!-- Recent Grades -->
                    <div class="panel">
                        <div class="panel-header">
                            <h3 style="margin:0; font-size: 1.1em;">Recent Grades</h3>
                            <a href="grades.php" class="btn btn-small">View All</a>
                        </div>
                        <div class="panel-body">
                            <?php if ($has_access): ?>
                                <?php
                                $grades_stmt = $db->prepare("SELECT * FROM grades WHERE student_id = ? AND is_published = TRUE ORDER BY id DESC LIMIT 5");
                                $grades_stmt->execute([$student['id']]);
                                if ($grades_stmt && $grades_stmt->rowCount() > 0) {
                                    echo '<table class="table">';
                                    echo '<thead><tr><th>Subject</th><th>Score</th><th>Grade</th></tr></thead>';
                                    echo '<tbody>';
                                    while ($grade = $grades_stmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo '<tr>';
                                        echo '<td>' . htmlspecialchars($grade['subject_name']) . '</td>';
                                        echo '<td>' . number_format($grade['score'], 1) . '%</td>';
                                        echo '<td><span class="grade-badge">' . htmlspecialchars($grade['grade']) . '</span></td>';
                                        echo '</tr>';
                                    }
                                    echo '</tbody></table>';
                                } else {
                                    echo '<p style="text-align: center; color: #bdc3c7; padding: 20px;">No published reports found.</p>';
                                }
                                ?>
                            <?php else: ?>
                                <div class="access-denied" style="background: #fff3f3; padding: 30px; border-radius: 4px; border: 1px solid #ffcdd2; text-align: center;">
                                    <h4 style="color: #d32f2f; margin-bottom: 10px;">Reports Locked</h4>
                                    <p style="font-size: 0.9em; color: #666;">Minimum 50% fee payment required to access results.</p>
                                    <p style="margin-top: 15px; font-weight: bold; font-size: 1.1em;">Total Paid: $<?php echo number_format($total_paid, 2); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="right-col">
                    <!-- Quick Links -->
                    <div class="panel">
                        <div class="panel-header"><h3 style="margin:0; font-size: 1.1em;">Quick Links</h3></div>
                        <div class="panel-body" style="display: grid; gap: 10px;">
                            <a href="profile.php" class="btn" style="background: #f8f9fa; color: #2c3e50; text-align: left; border: 1px solid #ddd; padding: 12px;">My Profile</a>
                            <a href="attendance.php" class="btn" style="background: #f8f9fa; color: #2c3e50; text-align: left; border: 1px solid #ddd; padding: 12px;">Attendance</a>
                            <a href="grades.php" class="btn" style="background: #f8f9fa; color: #2c3e50; text-align: left; border: 1px solid #ddd; padding: 12px;">Result Slip</a>
                        </div>
                    </div>

                    <!-- Fees Ledger -->
                    <div class="panel">
                        <div class="panel-header"><h3 style="margin:0; font-size: 1.1em;">School Fees Ledger</h3></div>
                        <div class="panel-body">
                            <?php if (count($fee_transactions) > 0): ?>
                                <div style="display: grid; gap: 12px;">
                                    <?php foreach ($fee_transactions as $payment): ?>
                                        <div style="padding: 14px; border-radius: 8px; background: #f8f9fa; border: 1px solid #ececec;">
                                            <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                                <strong style="color: #2c3e50;">$<?php echo number_format($payment['amount'], 2); ?></strong>
                                                <span style="font-size: 0.8em; color: #7f8c8d;"><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></span>
                                            </div>
                                            <div style="font-size: 0.85em; color: #555; margin-bottom: 8px;">Type: <?php echo htmlspecialchars($payment['fee_type'] ?: 'Tuition'); ?></div>
                                            <div style="font-size: 0.84em; color: #7f8c8d;">Method: <?php echo htmlspecialchars($payment['payment_method'] ?: 'N/A'); ?></div>
                                            <?php if (!empty($payment['remarks'])): ?>
                                                <div style="font-size: 0.82em; color: #7f8c8d; margin-top: 8px;"><?php echo htmlspecialchars($payment['remarks']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p style="text-align: center; color: #7f8c8d;">No fee payments have been recorded yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- My Subjects -->
                    <div class="panel">
                        <div class="panel-header">
                            <h3 style="margin:0; font-size: 1.1em;">My Subjects</h3>
                        </div>
                        <div class="panel-body">
                            <div style="display: grid; gap: 10px;">
                                <?php
                                $subj_stmt = $db->prepare("
                                    SELECT s.* 
                                    FROM subjects s 
                                    JOIN student_subjects ss ON ss.subject_id = s.id 
                                    WHERE ss.student_id = ? 
                                    ORDER BY s.name ASC
                                ");
                                $subj_stmt->execute([$student['id']]);
                                $subjects = $subj_stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                if (count($subjects) > 0) {
                                    foreach ($subjects as $subj) {
                                        echo '<a href="assignments.php?subject=' . urlencode($subj['name']) . '" style="display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; background: #f8f9fa; border-radius: 6px; border: 1px solid #eee; text-decoration: none; color: inherit; transition: background 0.2s;">';
                                        echo '  <div>';
                                        echo '      <div style="font-weight: bold; font-size: 0.9em; color: #2c3e50;">' . htmlspecialchars($subj['name']) . '</div>';
                                        echo '      <div style="font-size: 0.75em; color: #7f8c8d;">' . htmlspecialchars($subj['code']) . '</div>';
                                        echo '  </div>';
                                        echo '  <span style="font-size: 0.75em; font-weight: bold; background: #3498db; color: white; padding: 4px 10px; border-radius: 999px;">View Assignments</span>';
                                        echo '</a>';
                                    }
                                } else {
                                    echo '<p style="text-align: center; color: #bdc3c7; font-size: 0.9em;">No subjects registered.</p>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>

                    <!-- Staff Contact -->
                    <div class="panel">
                        <div class="panel-header"><h3 style="margin:0; font-size: 1.1em;">Faculty Info</h3></div>
                        <div class="panel-body">
                            <?php if (count($teachers_list) > 0): ?>
                                <div style="display: grid; gap: 12px;">
                                    <?php foreach ($teachers_list as $teacher_item): ?>
                                        <button type="button" class="teacher-card" style="display: flex; align-items: center; gap: 12px; padding: 14px; width: 100%; border: 1px solid #eaeaea; border-radius: 8px; background: #f8f9fa; cursor: pointer; text-align: left;"
                                            data-name="<?php echo htmlspecialchars($teacher_item['name']); ?>"
                                            data-role="<?php echo htmlspecialchars($teacher_item['role']); ?>"
                                            data-email="<?php echo htmlspecialchars($teacher_item['email']); ?>"
                                            data-phone="<?php echo htmlspecialchars($teacher_item['phone']); ?>"
                                            data-gender="<?php echo htmlspecialchars($teacher_item['gender']); ?>"
                                            data-subject="<?php echo htmlspecialchars($teacher_item['subject_specialization']); ?>">
                                            <div class="staff-avatar"><?php echo htmlspecialchars(strtoupper(substr($teacher_item['name'], 0, 1))); ?></div>
                                            <div style="flex: 1; text-align: left;">
                                                <div style="font-size: 0.95em; font-weight: bold; color: #2c3e50;"><?php echo htmlspecialchars($teacher_item['name']); ?></div>
                                                <div style="font-size: 0.77em; color: #7f8c8d; margin-top: 3px;"><?php echo htmlspecialchars($teacher_item['role']); ?></div>
                                            </div>
                                            <span style="font-size: 0.75em; color: #3498db;">View Profile</span>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p style="text-align: center; color: #bdc3c7; font-size: 0.9em;">No teachers assigned.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="modal-overlay" id="teacherProfileModal">
                        <div class="modal">
                            <div class="modal-header">
                                <h3>Teacher Profile</h3>
                                <button type="button" class="modal-close" id="closeTeacherModal">&times;</button>
                            </div>
                            <div class="modal-body">
                                <div class="form-group"><label>Name</label><input type="text" id="teacher_name" disabled></div>
                                <div class="form-group"><label>Role</label><input type="text" id="teacher_role" disabled></div>
                                <div class="form-group"><label>Subject</label><input type="text" id="teacher_subject" disabled></div>
                                <div class="form-group"><label>Gender</label><input type="text" id="teacher_gender" disabled></div>
                                <div class="form-group"><label>Phone</label><input type="text" id="teacher_phone" disabled></div>
                                <div class="form-group"><label>Email</label><input type="text" id="teacher_email" disabled></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const teacherButtons = document.querySelectorAll('.teacher-card');
            const modal = document.getElementById('teacherProfileModal');
            const closeModal = document.getElementById('closeTeacherModal');
            const fields = {
                name: document.getElementById('teacher_name'),
                role: document.getElementById('teacher_role'),
                subject: document.getElementById('teacher_subject'),
                gender: document.getElementById('teacher_gender'),
                phone: document.getElementById('teacher_phone'),
                email: document.getElementById('teacher_email')
            };

            teacherButtons.forEach(button => {
                button.addEventListener('click', function() {
                    fields.name.value = this.dataset.name || '';
                    fields.role.value = this.dataset.role || '';
                    fields.subject.value = this.dataset.subject || 'Not available';
                    fields.gender.value = this.dataset.gender || 'Not available';
                    fields.phone.value = this.dataset.phone || 'Not available';
                    fields.email.value = this.dataset.email || 'Not available';
                    modal.classList.add('active');
                });
            });

            closeModal.addEventListener('click', function() {
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