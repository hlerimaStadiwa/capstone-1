<?php
require_once '../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

// Get student data
$query = "SELECT s.* FROM students s 
          JOIN users u ON s.user_id = u.id 
          WHERE u.id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    echo "Student profile not found.";
    exit();
}

$upload_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assignment_id'])) {
    $assignment_id = (int)$_POST['assignment_id'];
    
    if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === 0) {
        $allowed = ['pdf' => 'application/pdf'];
        $filename = $_FILES['pdf_file']['name'];
        $filetype = $_FILES['pdf_file']['type'];
        $filesize = $_FILES['pdf_file']['size'];
        
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!array_key_exists($ext, $allowed)) {
            $upload_message = "<div class='message error'>Error: Please select a valid PDF file.</div>";
        } else {
            $maxsize = 5 * 1024 * 1024; // 5MB
            if ($filesize > $maxsize) {
                $upload_message = "<div class='message error'>Error: File size is larger than the allowed limit (5MB).</div>";
            } else {
                if ($filetype === "application/pdf") {
                    $new_filename = 'assignment_' . $assignment_id . '_student_' . $student['id'] . '_' . time() . '.pdf';
                    
                    try {
                        $storage = new SupabaseStorage();
                        $fileData = file_get_contents($_FILES['pdf_file']['tmp_name']);
                        
                        // We will store the public URL in the database directly
                        $publicUrl = $storage->uploadFile($new_filename, $fileData, 'application/pdf');
                        
                        $sub_stmt = $db->prepare("
                            INSERT INTO assignment_submissions (assignment_id, student_id, file_path, submitted_at, score, feedback, graded_at) 
                            VALUES (?, ?, ?, CURRENT_TIMESTAMP, NULL, NULL, NULL)
                            ON CONFLICT (assignment_id, student_id) 
                            DO UPDATE SET file_path = EXCLUDED.file_path, submitted_at = CURRENT_TIMESTAMP, score = NULL, feedback = NULL, graded_at = NULL
                        ");
                        $sub_stmt->execute([$assignment_id, $student['id'], $publicUrl]);
                        $upload_message = "<div class='message success'>Assignment submitted successfully!</div>";
                    } catch (Exception $e) {
                        $upload_message = "<div class='message error'>Upload/Database Error: " . htmlspecialchars($e->getMessage()) . "</div>";
                    }
                } else {
                    $upload_message = "<div class='message error'>Error: Only PDF files are allowed.</div>";
                }
            }
        }
    } else {
        $upload_message = "<div class='message error'>Error: No file uploaded or upload error occurred.</div>";
    }
}

// Fetch Assignments (directly assigned OR class-assigned) with student's submission status
$query_assignments = "
        SELECT a.*, t.full_name as teacher_name, t.subject_specialization,
                     sub.file_path as submission_file, sub.submitted_at as submission_date, 
                     sub.score as submission_score, sub.feedback as submission_feedback
        FROM assignments a
        JOIN teachers t ON a.teacher_id = t.id
        LEFT JOIN assignment_submissions sub ON a.id = sub.assignment_id AND sub.student_id = ?
        WHERE (a.student_id = ? OR a.classroom_id = ?)
            AND a.title NOT ILIKE '%sample%'
            AND a.title NOT ILIKE '%shona%'
        ORDER BY a.due_date ASC, a.created_at DESC
";
$stmt_assignments = $db->prepare($query_assignments);
$stmt_assignments->execute([$student['id'], $student['id'], $student['classroom_id']]);
$assignments = $stmt_assignments->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Assignments - Student Portal</title>
    <link rel="stylesheet" href="../assets/style.css?v=<?php echo time(); ?>">
    <style>
        .assignment-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            border-left: 5px solid #3498db;
        }
        .assignment-card.overdue {
            border-left-color: #e74c3c;
        }
        .assignment-card.class-target {
            border-left-color: #9b59b6;
        }
        .assignment-meta {
            display: flex;
            gap: 15px;
            font-size: 0.85em;
            color: #7f8c8d;
            margin-bottom: 10px;
        }
        .badge {
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.85em;
            color: white;
            font-weight: bold;
        }
        .badge-danger { background: #e74c3c; }
        .badge-success { background: #2ecc71; }
        .badge-info { background: #3498db; }
        .badge-purple { background: #9b59b6; }
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
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="profile.php">My Profile</a></li>
                <li><a href="grades.php">My Grades</a></li>
                <li><a href="attendance.php">My Attendance</a></li>
                <li><a href="assignments.php" class="active">My Assignments</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="page-header">
                <h1>My Assignments</h1>
                <p>Assignments and academic tasks assigned to you</p>
            </div>
            
            <?php if (!empty($upload_message)) echo $upload_message; ?>

            <!-- Subject Filter Dropdown -->
            <div class="filter-section" style="margin-top: 20px; background: white; padding: 15px 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 15px;">
                <label for="subject-filter" style="font-weight: bold; color: #2c3e50; font-size: 0.95em;">Filter by Subject:</label>
                <select id="subject-filter" onchange="filterAssignments(this.value)" style="padding: 10px 16px; border: 1px solid #dfe6e9; border-radius: 6px; font-size: 0.9em; min-width: 240px; color: #2c3e50; outline: none; transition: border-color 0.2s;">
                    <option value="all">All Subjects</option>
                    <?php 
                    $unique_subjects = [];
                    foreach ($assignments as $row) {
                        $subj = $row['subject_specialization'] ?: 'General';
                        if (!in_array($subj, $unique_subjects)) {
                            $unique_subjects[] = $subj;
                        }
                    }
                    sort($unique_subjects);
                    foreach ($unique_subjects as $s) {
                        echo '<option value="' . htmlspecialchars($s) . '">' . htmlspecialchars($s) . '</option>';
                    }
                    ?>
                </select>
            </div>

            <!-- List of Assignments -->
            <div style="margin-top: 20px;">
                <?php if (!empty($assignments)): ?>
                    <?php foreach ($assignments as $row): 
                        $is_overdue = (strtotime($row['due_date']) < strtotime(date('Y-m-d')));
                        $is_class = ($row['classroom_id'] !== null);
                        $card_class = $is_overdue ? 'overdue' : ($is_class ? 'class-target' : '');
                        $subject_name = $row['subject_specialization'] ?: 'General';
                        // Display-time cleanup: remove leading "Sample " if present
                        $display_title = preg_replace('/^Sample\s+/i', '', $row['title']);
                    ?>
                        <div class="assignment-card <?php echo $card_class; ?>" data-subject="<?php echo htmlspecialchars($subject_name); ?>">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                                <h3 style="color: #2c3e50; font-size: 1.25em;"><?php echo htmlspecialchars($display_title); ?></h3>
                                <div>
                                    <?php if ($is_overdue): ?>
                                        <span class="badge badge-danger">Overdue</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php endif; ?>

                                    <?php if ($is_class): ?>
                                        <span class="badge badge-purple">Class Task</span>
                                    <?php else: ?>
                                        <span class="badge badge-info">Personal Task</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="assignment-meta">
                                <span><strong>Subject:</strong> <?php echo htmlspecialchars($subject_name); ?></span>
                                <span><strong>Assigned By:</strong> <?php echo htmlspecialchars($row['teacher_name']); ?></span>
                                <span><strong>Due Date:</strong> 
                                    <span style="color: <?php echo $is_overdue ? '#e74c3c' : '#2c3e50'; ?>; font-weight: bold;">
                                        <?php echo date('M d, Y', strtotime($row['due_date'])); ?>
                                    </span>
                                </span>
                                <span><strong>Posted:</strong> <?php echo date('M d, Y', strtotime($row['created_at'])); ?></span>
                            </div>

                            <p style="color: #34495e; white-space: pre-line; line-height: 1.5; font-size: 0.95em;">
                                <?php echo htmlspecialchars($row['description']); ?>
                            </p>

                            <!-- Submission Section -->
                            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">
                                <?php if ($row['submission_file']): ?>
                                    <div style="background: #e8f5e9; border: 1px solid #c8e6c9; padding: 12px 15px; border-radius: 6px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                                        <div>
                                            <span style="color: #2e7d32; font-weight: bold; font-size: 0.95em;">✓ Submitted on <?php echo date('M d, Y h:i A', strtotime($row['submission_date'])); ?></span>
                                            <?php if ($row['submission_score'] !== null): ?>
                                                <div style="margin-top: 5px; font-size: 0.9em; color: #2c3e50;">
                                                    <strong>Mark/Grade:</strong> <span class="badge badge-success" style="background: #27ae60; color: white; display: inline-block; padding: 2px 8px; font-weight: bold;"><?php echo number_format($row['submission_score'], 1); ?>/100</span>
                                                    <?php if ($row['submission_feedback']): ?>
                                                        <p style="margin: 5px 0 0; font-style: italic; color: #555;">Feedback: "<?php echo htmlspecialchars($row['submission_feedback']); ?>"</p>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <div style="margin-top: 5px; font-size: 0.85em; color: #7f8c8d;">Awaiting marking / grades.</div>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <a href="<?php echo str_starts_with($row['submission_file'], 'http') ? htmlspecialchars($row['submission_file']) : '../uploads/assignments/' . htmlspecialchars($row['submission_file']); ?>" class="btn btn-small" target="_blank" style="background: #2e7d32; color: white; text-decoration: none; padding: 5px 10px; border-radius: 4px; font-size: 0.85em; font-weight: bold; display: inline-block;">Download Submission</a>
                                            <?php if (!$is_overdue): ?>
                                                <button onclick="toggleResubmit(<?php echo $row['id']; ?>)" class="btn btn-small" style="margin-left: 5px; padding: 5px 10px; font-size: 0.85em; border-radius: 4px;">Resubmit</button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Hidden resubmit form -->
                                    <div id="resubmit-form-<?php echo $row['id']; ?>" style="display: none; margin-top: 15px; padding: 15px; border: 1px dashed #ccc; border-radius: 6px; background: #fafafa;">
                                        <form action="assignments.php" method="POST" enctype="multipart/form-data">
                                            <input type="hidden" name="assignment_id" value="<?php echo $row['id']; ?>">
                                            <label style="display: block; font-weight: bold; margin-bottom: 8px; font-size: 0.9em; color: #2c3e50;">Upload New PDF Response (Max 5MB):</label>
                                            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                                <input type="file" name="pdf_file" accept=".pdf" required style="font-size: 0.9em;">
                                                <button type="submit" class="btn btn-primary btn-small">Upload & Submit</button>
                                                <button type="button" onclick="toggleResubmit(<?php echo $row['id']; ?>)" class="btn btn-small">Cancel</button>
                                            </div>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <?php if ($is_overdue): ?>
                                        <div style="color: #c0392b; font-weight: bold; font-size: 0.9em; padding: 5px 0;">Missing: This assignment is overdue and cannot be submitted.</div>
                                    <?php else: ?>
                                        <form action="assignments.php" method="POST" enctype="multipart/form-data" style="background: #fdfdfd; padding: 15px; border: 1px solid #eee; border-radius: 6px; margin: 0;">
                                            <input type="hidden" name="assignment_id" value="<?php echo $row['id']; ?>">
                                            <label style="display: block; font-weight: bold; margin-bottom: 8px; font-size: 0.9em; color: #2c3e50;">Submit PDF Response (Max 5MB):</label>
                                            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                                <input type="file" name="pdf_file" accept=".pdf" required style="font-size: 0.9em;">
                                                <button type="submit" class="btn btn-primary btn-small">Upload & Submit</button>
                                            </div>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state" style="padding: 60px; text-align: center; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                        <h3 style="color: #7f8c8d; margin-top: 0;">No Assignments Found</h3>
                        <p style="color: #999;">Hooray! You currently have no active or historical assignments assigned to you.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    function toggleResubmit(assignmentId) {
        var form = document.getElementById('resubmit-form-' + assignmentId);
        if (form) {
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const params = new URLSearchParams(window.location.search);
        const selectedSubject = params.get('subject');
        const select = document.getElementById('subject-filter');

        if (selectedSubject && select) {
            const option = Array.from(select.options).find(opt => opt.value === selectedSubject);
            if (option) {
                select.value = selectedSubject;
                filterAssignments(selectedSubject);
            }
        }
    });

    function filterAssignments(subject) {
        const cards = document.querySelectorAll('.assignment-card');
        let visibleCount = 0;
        
        cards.forEach(card => {
            if (subject === 'all' || card.getAttribute('data-subject') === subject) {
                card.style.display = 'block';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        // Toggle no results container
        let emptyState = document.getElementById('no-filter-results');
        if (visibleCount === 0 && cards.length > 0) {
            if (!emptyState) {
                const container = document.querySelector('.main-content');
                emptyState = document.createElement('div');
                emptyState.id = 'no-filter-results';
                emptyState.className = 'empty-state';
                emptyState.style.cssText = 'padding: 60px; text-align: center; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-top: 20px;';
                emptyState.innerHTML = '<h3 style="color: #7f8c8d; margin-top: 0;">No Assignments Found</h3><p style="color: #999;">There are no active or historical assignments matching this subject.</p>';
                container.appendChild(emptyState);
            } else {
                emptyState.style.display = 'block';
            }
        } else if (emptyState) {
            emptyState.style.display = 'none';
        }
    }
    </script>
</body>
</html>
