<?php
session_start();

// 1. Authorization
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();
require_once '../includes/Logger.php'; // Include Logger

// 2. Initialize Variables
$error = '';
$success = '';
$action = isset($_GET['id']) ? 'edit' : 'add';
$student_id = isset($_GET['id']) ? $_GET['id'] : '';

$form_data = [
    'pin' => '', 'full_name' => '', 'email' => '', 'phone' => '', 
    'address' => '', 'date_of_birth' => '', 'gender' => '', 
    'is_boarder' => false, 'room_id' => '', 'classroom_id' => '', 'enrollment_date' => date('Y-m-d')
];

// 3. Handle Form Submission (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Get Data
    $form_data = [
        'pin' => trim($_POST['pin']),
        'full_name' => trim($_POST['full_name']),
        'email' => trim($_POST['email']),
        'phone' => trim($_POST['phone']),
        'address' => trim($_POST['address']),
        'date_of_birth' => $_POST['date_of_birth'],
        'gender' => $_POST['gender'],
        'is_boarder' => isset($_POST['is_boarder']),
        'room_id' => (isset($_POST['is_boarder']) && !empty($_POST['room_id'])) ? $_POST['room_id'] : null,
        'classroom_id' => !empty($_POST['classroom_id']) ? $_POST['classroom_id'] : null,
        'enrollment_date' => $_POST['enrollment_date']
    ];

    // Re-calculate total_paid for enforcement if editing
    $total_paid = 0;
    if ($action === 'edit') {
        $fee_stmt = $db->prepare("SELECT SUM(amount) FROM fees WHERE student_id = ?");
        $fee_stmt->execute([$student_id]);
        $total_paid = $fee_stmt->fetchColumn() ?: 0;
    }

    try {
        // Enforce Room Allocation Rule
        if ($form_data['room_id'] && $total_paid < 75.00) {
            throw new Exception("Student has only paid $" . number_format($total_paid, 2) . ". Minimum 50% fee ($75.00) is required for room allocation.");
        }
        // Validation
        if (empty($form_data['pin']) || empty($form_data['full_name']) || empty($form_data['email'])) {
            throw new Exception("PIN, Name, and Email are required.");
        }

        $db->beginTransaction();

        if ($action === 'add') {
            // --- ADD NEW STUDENT ---
            
            // Generate Username
            $username_parts = explode('@', $form_data['email']);
            $username = strtolower($username_parts[0]);
            
            // Password: Use provided or default
            $raw_password = !empty($_POST['password']) ? $_POST['password'] : 'student123';
            $password = password_hash($raw_password, PASSWORD_DEFAULT);

            // Create User
            $stmt = $db->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, 'student')");
            $stmt->execute([$username, $password, $form_data['email']]);
            $user_id = $db->lastInsertId();

            // Create Student
            $stmt = $db->prepare("INSERT INTO students (user_id, pin, full_name, email, phone, address, date_of_birth, gender, enrollment_date, room_id, classroom_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $user_id, $form_data['pin'], $form_data['full_name'], $form_data['email'], $form_data['phone'], 
                $form_data['address'], $form_data['date_of_birth'], $form_data['gender'], $form_data['enrollment_date'], $form_data['room_id'], $form_data['classroom_id']
            ]);

            // Update Room Capacity
            if ($form_data['room_id']) {
                $db->prepare("UPDATE rooms SET available_beds = available_beds - 1 WHERE id = ?")->execute([$form_data['room_id']]);
            }

            Logger::log($_SESSION['user_id'], 'Create Student', "Created student account for {$form_data['full_name']} ({$username})");

            $success = "Student added successfully! Username: <strong>$username</strong>";
            
            // Reset for "Add Another" if needed, otherwise could redirect
            if (!isset($_POST['add_another'])) {
                $db->commit(); // Commit before redirect
                $_SESSION['success'] = $success;
                header("Location: students.php");
                exit();
            } else {
                // Clear form for next entry
                $form_data = array_fill_keys(array_keys($form_data), '');
                $form_data['enrollment_date'] = date('Y-m-d'); // Reset date
            }

        } else {
            // --- EDIT EXISTING STUDENT ---
            
            // Get current room to handle capacity changes
            $stmt = $db->prepare("SELECT room_id, user_id FROM students WHERE id = ?");
            $stmt->execute([$student_id]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$current) throw new Exception("Student not found");

            // Update Student
            $stmt = $db->prepare("
                UPDATE students SET pin=?, full_name=?, email=?, phone=?, address=?, date_of_birth=?, gender=?, enrollment_date=?, room_id=?, classroom_id=? 
                WHERE id=?
            ");
            $stmt->execute([
                $form_data['pin'], $form_data['full_name'], $form_data['email'], $form_data['phone'], 
                $form_data['address'], $form_data['date_of_birth'], $form_data['gender'], $form_data['enrollment_date'], $form_data['room_id'], $form_data['classroom_id'],
                $student_id
            ]);

            // Update User Email and Password
            if ($current['user_id']) {
                if (!empty($_POST['password'])) {
                    $res = $db->prepare("UPDATE users SET email = ?, password = ? WHERE id = ?");
                    $res->execute([$form_data['email'], password_hash($_POST['password'], PASSWORD_DEFAULT), $current['user_id']]);
                } else {
                    $db->prepare("UPDATE users SET email = ? WHERE id = ?")->execute([$form_data['email'], $current['user_id']]);
                }
            }

            // Handle Room Swapping
            $old_room = $current['room_id'];
            $new_room = $form_data['room_id'];

            if ($old_room != $new_room) {
                if ($old_room) $db->prepare("UPDATE rooms SET available_beds = available_beds + 1 WHERE id = ?")->execute([$old_room]);
                if ($new_room) $db->prepare("UPDATE rooms SET available_beds = available_beds - 1 WHERE id = ?")->execute([$new_room]);
            }

            // Save Subjects (For Edit)
            // Strategy: Delete all and re-insert (Simpler) or Diff (More complex). We'll do delete & re-insert for now.
            $db->prepare("DELETE FROM student_subjects WHERE student_id = ?")->execute([$student_id]);
            if (isset($_POST['subjects']) && is_array($_POST['subjects'])) {
                $stmt_sub = $db->prepare("INSERT INTO student_subjects (student_id, subject_id) VALUES (?, ?)");
                foreach ($_POST['subjects'] as $sub_id) {
                    $stmt_sub->execute([$student_id, $sub_id]);
                }
            }

            Logger::log($_SESSION['user_id'], 'Update Student', "Updated student details for {$form_data['full_name']} (ID: $student_id)");

            $_SESSION['success'] = "Student updated successfully!";
            $db->commit(); // Commit before redirect
            header("Location: students.php");
            exit();
        }

        // Handle Subjects for ADD (New Student)
        // Note: For 'add', $user_id is created but we need student ID from line 67
        // Wait, line 67 gets user_id, but we need student_id which is not fetched until Insert. 
        // Ah, INSERT INTO students doesn't return ID directly. We need $db->lastInsertId() AGAIN after the students insert.
        if ($action === 'add') {
             $new_student_id = $db->lastInsertId(); // This works because 'students' was the last insert
             
             if (isset($_POST['subjects']) && is_array($_POST['subjects'])) {
                $stmt_sub = $db->prepare("INSERT INTO student_subjects (student_id, subject_id) VALUES (?, ?)");
                foreach ($_POST['subjects'] as $sub_id) {
                    $stmt_sub->execute([$new_student_id, $sub_id]);
                }
            }
        }
        
        $db->commit();

    } catch (Exception $e) {
        $db->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

// 4. Fetch Data for Edit Mode (GET)
$assigned_subjects = []; // Array of subject IDs
$total_paid = 0;

if ($action === 'edit') {
    // Calculate Fees even for POST if it fails and re-renders
    $fee_stmt = $db->prepare("SELECT SUM(amount) FROM fees WHERE student_id = ?");
    $fee_stmt->execute([$student_id]);
    $total_paid = $fee_stmt->fetchColumn() ?: 0;
}

$can_allocate_room = ($total_paid >= 75.00);

if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        header("Location: students.php");
        exit();
    }
    
    // Populate form
    $form_data = $student;
    $form_data['is_boarder'] = !empty($student['room_id']);

    // Fetch Assigned Subjects
    $stmt_subs = $db->prepare("SELECT subject_id FROM student_subjects WHERE student_id = ?");
    $stmt_subs->execute([$student_id]);
    $assigned_subjects = $stmt_subs->fetchAll(PDO::FETCH_COLUMN); // Returns array like [1, 2, 5]
}

// Fetch Master List of Subjects
$all_subjects = $db->query("SELECT * FROM subjects ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ucfirst($action); ?> Student - Student Management System</title>
    <link rel="stylesheet" href="../assets/style.css?v=<?php echo time(); ?>">
    <style>
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 768px) { .form-row { grid-template-columns: 1fr; } }
        .message { padding: 15px; margin: 15px 0; border-radius: 4px; }
        .error { background: #fee; color: #c00; border: 1px solid #fcc; }
        .success { background: #efe; color: #0c0; border: 1px solid #cfc; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><?php echo ucfirst($action); ?> Student</h1>
            <a href="students.php" class="btn">Back to List</a>
        </div>
        
        <div class="form-container">
            <?php if ($error): ?><div class="message error"><?php echo $error; ?></div><?php endif; ?>
            <?php if ($success): ?><div class="message success"><?php echo $success; ?></div><?php endif; ?>
            
            <form method="POST">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Student PIN *</label>
                        <input type="text" name="pin" value="<?php echo htmlspecialchars($form_data['pin']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($form_data['full_name']); ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($form_data['email']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" value="<?php echo htmlspecialchars($form_data['phone']); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Assign Subjects</label>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 10px; max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 4px; background: #fff;">
                        <?php foreach ($all_subjects as $subj): ?>
                            <label style="display: flex; align-items: center; gap: 8px; font-weight: normal; cursor: pointer; margin-bottom: 0;">
                                <input type="checkbox" name="subjects[]" value="<?php echo $subj['id']; ?>" 
                                    <?php echo (in_array($subj['id'], $assigned_subjects ?? [])) ? 'checked' : ''; ?>>
                                <?php echo htmlspecialchars($subj['name'] . ' (' . $subj['code'] . ')'); ?>
                            </label>
                        <?php endforeach; ?>
                        <?php if (empty($all_subjects)) echo "<p style='color: #666;'>No subjects found. Add them in 'Manage Subjects' first.</p>"; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label>Password <?php echo $action === 'add' ? '(Default: student123)' : '(Leave blank to keep current)'; ?></label>
                    <input type="password" name="password" placeholder="Enter new password">
                </div>

                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address"><?php echo htmlspecialchars($form_data['address']); ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Date of Birth</label>
                        <input type="date" name="date_of_birth" value="<?php echo $form_data['date_of_birth']; ?>">
                    </div>
                    <div class="form-group">
                        <label>Gender</label>
                        <select name="gender">
                            <option value="">Select...</option>
                            <option value="Male" <?php echo $form_data['gender'] == 'Male' ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo $form_data['gender'] == 'Female' ? 'selected' : ''; ?>>Female</option>
                        </select>
                    </div>
                </div>



                <div class="form-group">
                    <label>Assign Classroom (Academic)</label>
                    <select name="classroom_id">
                        <option value="">Select Classroom...</option>
                        <?php
                        // Fetch only Classrooms
                        $classrooms = $db->query("SELECT * FROM rooms WHERE room_type = 'Classroom' ORDER BY room_number");
                        while ($cls = $classrooms->fetch(PDO::FETCH_ASSOC)) {
                            $selected = ($form_data['classroom_id'] == $cls['id']) ? 'selected' : '';
                            echo "<option value='{$cls['id']}' $selected>{$cls['room_number']}</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group" style="margin: 20px 0; padding: 15px; background: #f9f9f9; border-radius: 4px;">
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-weight: bold;">
                        <input type="checkbox" id="is_boarder" name="is_boarder" <?php echo $form_data['is_boarder'] ? 'checked' : ''; ?> onchange="toggleRoom()">
                        Is Boarding Student?
                    </label>
                    
                    <div id="room_select" style="margin-top: 15px; display: none;">
                        <label>Allocate Room</label>
                        <?php if ($can_allocate_room): ?>
                            <select name="room_id">
                                <option value="">Select Room...</option>
                                <?php
                                $rooms = $db->query("SELECT * FROM rooms WHERE room_type = 'Hostel' ORDER BY room_number");
                                while ($room = $rooms->fetch(PDO::FETCH_ASSOC)) {
                                    $is_selected = $form_data['room_id'] == $room['id'];
                                    $label = $room['room_number'] . " (" . $room['available_beds'] . " beds free)";
                                    echo "<option value='{$room['id']}' " . ($is_selected ? 'selected' : '') . ">$label</option>";
                                }
                                ?>
                            </select>
                            <p style="font-size: 0.85em; color: green; margin-top: 5px;">Eligible: Paid $<?php echo number_format($total_paid, 2); ?></p>
                        <?php else: ?>
                            <div style="padding: 10px; background: #fff3f3; border: 1px solid #ffcdd2; border-radius: 4px; color: #d32f2f; font-size: 0.9em;">
                                <strong>Allocation Restricted</strong><br>
                                Student must pay at least 50% ($75.00) of fees.<br>
                                Current Payment: <strong>$<?php echo number_format($total_paid, 2); ?></strong>
                                <input type="hidden" name="room_id" value="">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label>Enrollment Date</label>
                    <input type="date" name="enrollment_date" value="<?php echo $form_data['enrollment_date']; ?>" required>
                </div>

                <?php if ($action === 'add'): ?>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 10px;">
                        <input type="checkbox" name="add_another"> Add another student after saving
                    </label>
                </div>
                <?php endif; ?>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><?php echo ucfirst($action); ?> Student</button>
                    <?php if ($action === 'add'): ?><button type="reset" class="btn">Reset</button><?php endif; ?>
                </div>

            </form>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script>
        function toggleRoom() {
            var isBoarder = document.getElementById('is_boarder').checked;
            document.getElementById('room_select').style.display = isBoarder ? 'block' : 'none';
        }
        // Run on load
        toggleRoom();
    </script>
</body>
</html>
