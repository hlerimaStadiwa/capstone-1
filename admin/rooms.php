<?php
// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once '../config/init.php';
// Handle Delete Request
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $stmt = $db->prepare("DELETE FROM rooms WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['success'] = "Room deleted successfully!";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error deleting room: " . $e->getMessage();
    }
    header("Location: rooms.php");
    exit();
}

// Fetch Classrooms with Teacher Info
$classrooms_query = "
    SELECT r.*, t.full_name as teacher_name 
    FROM rooms r 
    LEFT JOIN teachers t ON r.teacher_id = t.id 
    WHERE r.room_type = 'Classroom' 
    ORDER BY r.room_number ASC
";
$classrooms = $db->query($classrooms_query)->fetchAll(PDO::FETCH_ASSOC);

// Fetch Other Rooms
$other_rooms_query = "SELECT * FROM rooms WHERE room_type != 'Classroom' ORDER BY room_type, room_number ASC";
$other_rooms = $db->query($other_rooms_query)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Rooms - Danborough Student Management System</title>
    <link rel="stylesheet" href="../assets/style.css?v=<?php echo time(); ?>">
    <style>
        .room-stats { display: flex; gap: 10px; margin-top: 5px; font-size: 0.9em; color: #666; }
        .section-title { margin-top: 30px; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Manage Rooms</h1>
            <p>Add and manage classrooms, hostels, and labs</p>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="message success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="message error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div class="action-bar">
            <a href="room_form.php" class="btn btn-primary">Add New Room</a>
            <a href="dashboard.php" class="btn">Back to Dashboard</a>
        </div>

        <div class="table-container">
                
                <!-- Classrooms Section -->
                <h3 class="section-title">Classrooms</h3>
                <?php if (count($classrooms) > 0): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Room</th>
                                <th>Assigned Teacher</th>
                                <th>Status</th>
                                <th>Capacity</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($classrooms as $room): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($room['room_number']); ?></strong></td>
                                    <td>
                                        <?php echo $room['teacher_name'] ? htmlspecialchars($room['teacher_name']) : '<span style="color:#999; font-style:italic;">None</span>'; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo ($room['status'] === 'Available') ? 'status-present' : 'status-absent'; ?>">
                                            <?php echo htmlspecialchars($room['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $room['capacity']; ?></td>
                                    <td>
                                        <div class="action-buttons-small">
                                            <a href="room_form.php?id=<?php echo $room['id']; ?>" class="btn btn-small btn-primary">Edit</a>
                                            <a href="rooms.php?action=delete&id=<?php echo $room['id']; ?>" 
                                               class="btn btn-small btn-danger"
                                               onclick="return confirm('Delete this classroom?')">Delete</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="color: #666;">No classrooms found.</p>
                <?php endif; ?>

                <!-- Other Rooms Section -->
                <h3 class="section-title">Hostels, Labs & Others</h3>
                <?php if (count($other_rooms) > 0): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Room</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Capacity</th>
                                <th>Avail. Beds/Seats</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($other_rooms as $room): ?>
                                <tr class="<?php echo ($room['status'] === 'Occupied') ? 'late-row' : ''; ?>">
                                    <td><strong><?php echo htmlspecialchars($room['room_number']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($room['room_type']); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo ($room['status'] === 'Available') ? 'status-present' : 'status-absent'; ?>">
                                            <?php echo htmlspecialchars($room['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $room['capacity']; ?></td>
                                    <td><?php echo $room['available_beds']; ?></td>
                                    <td>
                                        <div class="action-buttons-small">
                                            <a href="room_form.php?id=<?php echo $room['id']; ?>" class="btn btn-small btn-primary">Edit</a>
                                            <a href="rooms.php?action=delete&id=<?php echo $room['id']; ?>" 
                                               class="btn btn-small btn-danger"
                                               onclick="return confirm('Delete this room?')">Delete</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="color: #666;">No other rooms found.</p>
                <?php endif; ?>

            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>
