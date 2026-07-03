<?php
// 1. Authorization
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once '../config/init.php';
// 2. Initialize Variables
$error = '';
$success = '';
$action = isset($_GET['id']) ? 'edit' : 'add';
$teacher_id = isset($_GET['id']) ? $_GET['id'] : '';

$form_data = [
    'full_name' => '', 'gender' => '', 'email' => '', 'phone' => '', 'subject_specialization' => '', 'assigned_classes' => [], 'assigned_subjects' => []
];

// 3. Handle Form Submission (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Get Data
    $form_data = [
        'full_name' => trim($_POST['full_name']),
        'gender' => $_POST['gender'] ?? '',
        'email' => trim($_POST['email']),
        'phone' => trim($_POST['phone']),
        'subject_specialization' => trim($_POST['subject_specialization'] ?? ''),
        'assigned_classes' => isset($_POST['assigned_classes']) ? $_POST['assigned_classes'] : [],
        'assigned_subjects' => isset($_POST['assigned_subjects']) ? $_POST['assigned_subjects'] : []
    ];

    try {
        // Validation
        if (empty($form_data['full_name']) || empty($form_data['email'])) {
            throw new Exception("Name and Email are required.");
        }

        $db->beginTransaction();

        if ($action === 'add') {
            // --- ADD NEW TEACHER ---
            
            // Generate Username from email
            $username_parts = explode('@', $form_data['email']);
            $username = strtolower($username_parts[0]);
            
            // Password: Use provided or default
            $raw_password = !empty($_POST['password']) ? $_POST['password'] : 'teacher123';
            $password = password_hash($raw_password, PASSWORD_DEFAULT);

            // Create User
            $stmt = $db->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, 'teacher')");
            $stmt->execute([$username, $password, $form_data['email']]);
            $user_id = $db->lastInsertId();

            // Create Teacher
            $stmt = $db->prepare("INSERT INTO teachers (user_id, full_name, gender, email, phone, subject_specialization) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $user_id, $form_data['full_name'], $form_data['gender'], $form_data['email'], $form_data['phone'], $form_data['subject_specialization']
            ]);

            // Assign Classes
            if (!empty($form_data['assigned_classes'])) {
                // Determine which teacher_id to use (user_id is not teacher_id, we need teacher_id from lastInsertId implies teacher table id?)
                // Wait, db->lastInsertId() returned user_id earlier? 
                // Line 54: $user_id = $db->lastInsertId(); (users table)
                // We need to get the teacher ID.
                $teacher_id = $db->lastInsertId(); // This will be the ID from the INSERT INTO teachers statement just executed
                
                // But wait, we executed INSERT INTO teachers at Line 58.
                // We need to verify if $db->lastInsertId() returns correctly. It should return the last auto-inc id.
                // line 58: execute...
                $teacher_id = $db->lastInsertId();

                $placeholders = implode(',', array_fill(0, count($form_data['assigned_classes']), '?'));
                $stmt = $db->prepare("UPDATE rooms SET teacher_id = ? WHERE id IN ($placeholders)");
                $params = array_merge([$teacher_id], $form_data['assigned_classes']);
                $stmt->execute($params);
            }

            // Assign Subjects
            if (!empty($form_data['assigned_subjects'])) {
                $stmt_sub = $db->prepare("INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES (?, ?)");
                foreach ($form_data['assigned_subjects'] as $sub_id) {
                    $stmt_sub->execute([$teacher_id, $sub_id]);
                }
            }

            $_SESSION['success'] = "Teacher added successfully! Username: <strong>$username</strong>";
            $db->commit();
            header("Location: teachers.php");
            exit();

        } else {
            // --- EDIT EXISTING TEACHER ---
            
            // Get current info
            $stmt = $db->prepare("SELECT user_id FROM teachers WHERE id = ?");
            $stmt->execute([$teacher_id]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$current) throw new Exception("Teacher not found");

            // Update Teacher
            $stmt = $db->prepare("UPDATE teachers SET full_name=?, gender=?, email=?, phone=?, subject_specialization=? WHERE id=?");
            $stmt->execute([
                $form_data['full_name'], $form_data['gender'], $form_data['email'], $form_data['phone'], $form_data['subject_specialization'], $teacher_id
            ]);

            // Assign Classes (Update Rooms)
            // 1. Clear previous assignments for this teacher
            $db->prepare("UPDATE rooms SET teacher_id = NULL WHERE teacher_id = ?")->execute([$teacher_id]);
            
            // 2. Assign new classes
            if (!empty($form_data['assigned_classes'])) {
                $placeholders = implode(',', array_fill(0, count($form_data['assigned_classes']), '?'));
                $stmt = $db->prepare("UPDATE rooms SET teacher_id = ? WHERE id IN ($placeholders)");
                $params = array_merge([$teacher_id], $form_data['assigned_classes']);
                $stmt->execute($params);
            }

            // Update Assigned Subjects
            $db->prepare("DELETE FROM teacher_subjects WHERE teacher_id = ?")->execute([$teacher_id]);
            if (!empty($form_data['assigned_subjects'])) {
                $stmt_sub = $db->prepare("INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES (?, ?)");
                foreach ($form_data['assigned_subjects'] as $sub_id) {
                    $stmt_sub->execute([$teacher_id, $sub_id]);
                }
            }

            // Update User Email and Password
            if ($current['user_id']) {
                if (!empty($_POST['password'])) {
                    $db->prepare("UPDATE users SET email = ?, password = ? WHERE id = ?")->execute([$form_data['email'], password_hash($_POST['password'], PASSWORD_DEFAULT), $current['user_id']]);
                } else {
                    $db->prepare("UPDATE users SET email = ? WHERE id = ?")->execute([$form_data['email'], $current['user_id']]);
                }
            }

            $_SESSION['success'] = "Teacher updated successfully!";
            $db->commit();
            header("Location: teachers.php");
            exit();
        }

        $db->commit();

    } catch (Exception $e) {
        $db->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

// 4. Fetch Data for Edit Mode (GET)
if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    // We need email from specific table or user table?
    // Let's join to get email from users table as it is source of truth for auth
    $stmt = $db->prepare("
        SELECT t.*, u.email 
        FROM teachers t 
        LEFT JOIN users u ON t.user_id = u.id 
        WHERE t.id = ?
    ");
    $stmt->execute([$teacher_id]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$teacher) {
        header("Location: teachers.php");
        exit();
    }
    
    // Get assigned classes
    $stmt = $db->prepare("SELECT id FROM rooms WHERE teacher_id = ?");
    $stmt->execute([$teacher_id]);
    $assigned = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $form_data = $teacher;
    $form_data['assigned_classes'] = $assigned;

    // Get assigned subjects
    $stmt = $db->prepare("SELECT subject_id FROM teacher_subjects WHERE teacher_id = ?");
    $stmt->execute([$teacher_id]);
    $form_data['assigned_subjects'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Fetch Master List of Subjects
$all_subjects = $db->query("SELECT * FROM subjects ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ucfirst($action); ?> Teacher - Danborough Student Management System</title>
    <link rel="stylesheet" href="../assets/style.css?v=<?php echo time(); ?>">
    <style>
        .message { padding: 15px; margin: 15px 0; border-radius: 4px; }
        .error { background: #fee; color: #c00; border: 1px solid #fcc; }
        .success { background: #efe; color: #0c0; border: 1px solid #cfc; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><?php echo ucfirst($action); ?> Teacher</h1>
            <a href="teachers.php" class="btn">Back to List</a>
        </div>
        
        <div class="form-container">
            <?php if ($error): ?><div class="message error"><?php echo $error; ?></div><?php endif; ?>
            
            <form method="POST">
                
                <div class="form-row" style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($form_data['full_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Gender *</label>
                        <select name="gender" required>
                            <option value="">Select...</option>
                            <option value="Male" <?php echo ($form_data['gender'] ?? '') == 'Male' ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo ($form_data['gender'] ?? '') == 'Female' ? 'selected' : ''; ?>>Female</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($form_data['email']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" value="<?php echo htmlspecialchars($form_data['phone']); ?>">
                </div>

                <div class="form-group">
                    <label>Password <?php echo $action === 'add' ? '(Default: teacher123)' : '(Leave blank to keep current)'; ?></label>
                    <input type="password" name="password" placeholder="Enter new password">
                </div>

                <div class="form-group">
                    <label>Assigned Subjects</label>
                    <div style="max-height: 150px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 4px; background: #fff;">
                        <?php foreach ($all_subjects as $subj): ?>
                            <label style="display:block; margin-bottom:5px; font-weight: normal;">
                                <input type="checkbox" name="assigned_subjects[]" value="<?php echo $subj['id']; ?>" 
                                    <?php echo (in_array($subj['id'], $form_data['assigned_subjects'] ?? [])) ? 'checked' : ''; ?>>
                                <?php echo htmlspecialchars($subj['name'] . ' (' . $subj['code'] . ')'); ?>
                            </label>
                        <?php endforeach; ?>
                        <?php if (empty($all_subjects)) echo "<p style='color: #666;'>No subjects found. Add them in 'Manage Subjects' first.</p>"; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label>Assigned Classes (Classrooms)</label>
                    <div style="max-height: 150px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 4px;">
                        <?php
                        $rooms = $db->query("SELECT * FROM rooms WHERE room_type = 'Classroom' ORDER BY room_number");
                        while ($room = $rooms->fetch(PDO::FETCH_ASSOC)) {
                            // Check if this room is assigned to THIS teacher, OR if it's available (teacher_id is null)
                            // Ideally show all, but maybe mark if taken?
                            // If taken by SOMEONE ELSE, maybe show "(Assigned to X)"?
                            // For simplicity, just list all classrooms.
                            
                            $checked = (in_array($room['id'], $form_data['assigned_classes'])) ? 'checked' : '';
                            
                            // Check if assigned to another (optional enhancement, but let's keep it simple first)
                            // If $room['teacher_id'] is set and NOT this teacher, maybe warn?
                            
                            echo "<label style='display:block; margin-bottom:5px;'>
                                    <input type='checkbox' name='assigned_classes[]' value='{$room['id']}' $checked> 
                                    {$room['room_number']}
                                  </label>";
                        }
                        ?>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><?php echo ucfirst($action); ?> Teacher</button>
                    <?php if ($action === 'add'): ?><button type="reset" class="btn">Reset</button><?php endif; ?>
                </div>

            </form>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>
