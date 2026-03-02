<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Fetch Teacher & User Data
$query = "SELECT t.*, u.username, u.email as user_email, u.created_at as account_since 
          FROM teachers t 
          JOIN users u ON t.user_id = u.id 
          WHERE u.id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher) {
    echo "Profile not found.";
    exit();
}

$message = '';
$error = '';

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    if ($new !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if (password_verify($current, $user['password'])) {
            $hashed = password_hash($new, PASSWORD_DEFAULT);
            $update = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update->execute([$hashed, $_SESSION['user_id']]);
            $message = "Password updated successfully!";
        } else {
            $error = "Current password is incorrect.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Teacher Dashboard</title>
    <link rel="stylesheet" href="../assets/style.css?v=<?php echo time(); ?>">
    <style>
        .profile-layout { display: grid; grid-template-columns: 1fr 2fr; gap: 30px; }
        .profile-detail-card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .detail-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f0f0f0; }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { color: #7f8c8d; font-weight: 500; }
        .detail-value { color: #2c3e50; font-weight: bold; }
        @media (max-width: 900px) { .profile-layout { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
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
                <li><a href="grades.php">Grades</a></li>
                <li><a href="profile.php" class="active">My Profile</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="page-header">
                <h1>Teacher Profile</h1>
                <p>Manage your institutional records and security</p>
            </div>

            <?php if ($message): ?><div class="message success"><?php echo $message; ?></div><?php endif; ?>
            <?php if ($error): ?><div class="message error"><?php echo $error; ?></div><?php endif; ?>

            <div class="profile-layout">
                <!-- Data Card -->
                <div class="profile-detail-card">
                    <h3>Professional Details</h3>
                    <div class="detail-row">
                        <span class="detail-label">Full Name</span>
                        <span class="detail-value"><?php echo htmlspecialchars($teacher['full_name']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Specialization</span>
                        <span class="detail-value"><?php echo htmlspecialchars($teacher['subject_specialization'] ?: 'General'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Institutional Email</span>
                        <span class="detail-value"><?php echo htmlspecialchars($teacher['email']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Contact Phone</span>
                        <span class="detail-value"><?php echo htmlspecialchars($teacher['phone'] ?: 'N/A'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Faculty Status</span>
                        <span class="detail-value" style="color: #27ae60;">Active Permanent</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Account Created</span>
                        <span class="detail-value"><?php echo date('M d, Y', strtotime($teacher['account_since'])); ?></span>
                    </div>
                </div>

                <!-- Account / Password Card -->
                <div>
                    <div class="profile-detail-card">
                        <h3>Account Security</h3>
                        <p style="color: #7f8c8d; font-size: 0.9em; margin-bottom: 20px;">Use this form to update your portal access password.</p>
                        
                        <form method="POST" class="password-form">
                            <input type="hidden" name="change_password" value="1">
                            <div class="form-group">
                                <label>Current Authentication Password</label>
                                <input type="password" name="current_password" required>
                            </div>
                            <div class="form-group">
                                <label>New Password (min 6 characters)</label>
                                <input type="password" name="new_password" required minlength="6">
                            </div>
                            <div class="form-group">
                                <label>Confirm New Password</label>
                                <input type="password" name="confirm_password" required minlength="6">
                            </div>
                            <button type="submit" class="btn btn-primary">Update Secure Password</button>
                        </form>
                    </div>

                    <div class="profile-detail-card" style="margin-top: 20px; background: #f8f9fa;">
                        <h3>Employment Summary</h3>
                        <div class="detail-row">
                            <span class="detail-label">Service Period</span>
                            <span class="detail-value"><?php 
                                $start = new DateTime($teacher['account_since']);
                                $now = new DateTime();
                                $diff = $start->diff($now);
                                echo $diff->y . ' Years, ' . $diff->m . ' Months';
                            ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Access Level</span>
                            <span class="detail-value">Primary Educator</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
