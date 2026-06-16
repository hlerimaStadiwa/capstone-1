<?php
session_start();

// Authorization
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();
require_once '../includes/Logger.php';

$error = '';
$success = '';
$action = isset($_GET['id']) ? 'edit' : 'add';
$room_id = isset($_GET['id']) ? $_GET['id'] : '';

// Init Variables
$form_data = [
    'room_number' => '', 'capacity' => '', 'room_type' => 'Classroom', 
    'status' => 'Available', 'teacher_id' => ''
];

// Handle Post
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_data = [
        'room_number' => trim($_POST['room_number']),
        'capacity' => trim($_POST['capacity']),
        'room_type' => $_POST['room_type'],
        'status' => $_POST['status'],
        'teacher_id' => !empty($_POST['teacher_id']) ? $_POST['teacher_id'] : null
    ];

    try {
        if (empty($form_data['room_number']) || empty($form_data['capacity'])) {
            throw new Exception("Room Number and Capacity are required.");
        }

        if ($action === 'add') {
            $stmt = $db->prepare("INSERT INTO rooms (room_number, capacity, room_type, available_beds, status, teacher_id) VALUES (?, ?, ?, ?, ?, ?)");
            // For new rooms, available_beds = capacity initially
            $stmt->execute([
                $form_data['room_number'], $form_data['capacity'], $form_data['room_type'], 
                $form_data['capacity'], $form_data['status'], $form_data['teacher_id']
            ]);
            $id = $db->lastInsertId();
            
            Logger::log($_SESSION['user_id'], 'Create Room', "Created room {$form_data['room_number']}");
            $_SESSION['success'] = "Room added successfully!";
        } else {
            // Edit
            $stmt = $db->prepare("UPDATE rooms SET room_number=?, capacity=?, room_type=?, status=?, teacher_id=? WHERE id=?");
            $stmt->execute([
                $form_data['room_number'], $form_data['capacity'], $form_data['room_type'], 
                $form_data['status'], $form_data['teacher_id'], $room_id
            ]);
            
            Logger::log($_SESSION['user_id'], 'Update Room', "Updated room {$form_data['room_number']}");
            $_SESSION['success'] = "Room updated successfully!";
        }

        header("Location: rooms.php");
        exit();

    } catch (PDOException $e) {
        $error = "Database Error: " . $e->getMessage();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Fetch for Edit
if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare("SELECT * FROM rooms WHERE id = ?");
    $stmt->execute([$room_id]);
    $room = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($room) {
        $form_data = $room;
    } else {
        header("Location: rooms.php");
        exit();
    }
}

// Fetch Teachers for Dropdown
$teachers = $db->query("SELECT id, full_name FROM teachers ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ucfirst($action); ?> Room - Danborough Student Management System</title>
    <link rel="stylesheet" href="../assets/style.css?v=<?php echo time(); ?>">
    <style>
        .form-container { max-width: 600px; margin: 40px auto; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><?php echo ucfirst($action); ?> Room</h1>
            <a href="rooms.php" class="btn">Back to List</a>
        </div>

        <div class="form-container card">
            <?php if ($error): ?><div class="message error"><?php echo $error; ?></div><?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Room Number/Name *</label>
                    <input type="text" name="room_number" value="<?php echo htmlspecialchars($form_data['room_number']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Capacity *</label>
                    <input type="number" name="capacity" value="<?php echo htmlspecialchars($form_data['capacity']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Room Type</label>
                    <select name="room_type" id="room_type" onchange="toggleTeacher()">
                        <option value="Classroom" <?php echo $form_data['room_type'] == 'Classroom' ? 'selected' : ''; ?>>Classroom</option>
                        <option value="Hostel" <?php echo $form_data['room_type'] == 'Hostel' ? 'selected' : ''; ?>>Hostel</option>
                        <option value="Lab" <?php echo $form_data['room_type'] == 'Lab' ? 'selected' : ''; ?>>Lab</option>
                    </select>
                </div>

                <div class="form-group" id="teacher_group">
                    <label>Assign Teacher (Class Teacher)</label>
                    <select name="teacher_id">
                        <option value="">-- None --</option>
                        <?php foreach ($teachers as $t): ?>
                            <option value="<?php echo $t['id']; ?>" <?php echo $form_data['teacher_id'] == $t['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($t['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="Available" <?php echo $form_data['status'] == 'Available' ? 'selected' : ''; ?>>Available</option>
                        <option value="Occupied" <?php echo $form_data['status'] == 'Occupied' ? 'selected' : ''; ?>>Occupied</option>
                        <option value="Maintenance" <?php echo $form_data['status'] == 'Maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><?php echo ucfirst($action); ?> Room</button>
                </div>
            </form>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script>
        function toggleTeacher() {
            const type = document.getElementById('room_type').value;
            const group = document.getElementById('teacher_group');
            if (type === 'Classroom') {
                group.style.display = 'block';
            } else {
                group.style.display = 'none';
            }
        }
        toggleTeacher(); // Run on load
    </script>
</body>
</html>
