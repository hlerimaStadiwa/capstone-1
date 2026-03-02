<?php
// login.php - Complete working version
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = '';
$success = '';

// Check if setup is needed
$config_exists = file_exists('config/database.php');
$setup_needed = !$config_exists;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    // Validate inputs
    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        try {
            // Try to connect to database
            if (!$config_exists) {
                throw new Exception("Database configuration not found. Please run setup first.");
            }
            
            require_once 'config/database.php';
            $database = new Database();
            $db = $database->getConnection();
            
            // Check if users table exists (PostgreSQL compatible)
            $tableCheck = $db->query("SELECT 1 FROM information_schema.tables WHERE table_name = 'users'");
            if (!$tableCheck || $tableCheck->rowCount() == 0) {
                throw new Exception("Database tables not set up. Please run database setup.");
            }
            
            // Get user from database
            $query = "SELECT id, username, password, role FROM users WHERE username = :username OR email = :username";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':username', $username);
            
            if ($stmt->execute()) {
                if ($stmt->rowCount() == 1) {
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Verify password
                    if (password_verify($password, $user['password'])) {
                        // Password is correct - set session
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['user_role'] = $user['role'];

                        // Log Activity
                        require_once 'includes/Logger.php';
                        Logger::log($user['id'], 'Login', 'User logged in successfully');
                        
                        // Redirect based on role
                        switch($user['role']) {
                            case 'admin':
                                header("Location: admin/dashboard.php");
                                break;
                            case 'teacher':
                                header("Location: teacher/dashboard.php");
                                break;
                            case 'student':
                                header("Location: student/dashboard.php");
                                break;
                            default:
                                header("Location: index.php"); // Default redirect
                                break;
                        }
                        exit();
                    } else {
                        $error = "Invalid username or password.";
                    }
                } else {
                    $error = "Invalid username or password.";
                }
            } else {
                $error = "Database query failed.";
            }
        } catch (Exception $e) {
            $error = "Login Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Silver High Academy</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .system-status {
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            font-weight: bold;
        }
        .status-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .status-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .setup-steps {
            background: #e7f3ff;
            padding: 20px;
            border-radius: 6px;
            margin: 20px 0;
            border-left: 4px solid #3498db;
        }
        .setup-steps ol {
            margin-left: 20px;
        }
        .setup-steps li {
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2 style="text-align: center; color: #2c3e50; margin-bottom: 10px;">Student Management System</h2>
        <h3 style="text-align: center; color: #7f8c8d; margin-bottom: 30px;">Login to Your Account</h3>
        
        <?php if ($setup_needed): ?>
            <div class="system-status status-warning">
                <strong>Setup Required</strong>
                <p>Database configuration not found. Please complete setup first.</p>
            </div>
            
            <div class="setup-steps">
                <h4>Setup Instructions:</h4>
                <ol>
                    <li><strong>Run Database Setup:</strong> 
                        <a href="setup_database.php" style="color: #3498db; font-weight: bold;">Click here to setup database</a>
                    </li>
                    <li><strong>If setup fails:</strong> Check that MySQL is running in XAMPP</li>
                </ol>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="message success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username or Email:</label>
                <input type="text" id="username" name="username" required 
                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                       placeholder="Enter username or email">
            </div>
            
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required 
                       placeholder="Enter password">
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%;">Login</button>
        </form>
                

    </div>
    
    <script>
    // Basic form validation
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form');
        const submitBtn = form.querySelector('button[type="submit"]');
        
        form.addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();
            
            if (!username || !password) {
                e.preventDefault();
                alert('Please enter both username and password.');
                return false;
            }
            
            // Show loading state
            if (submitBtn) {
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = 'Logging in...';
                submitBtn.disabled = true;
                
                // Restore button after 5 seconds (in case of error)
                setTimeout(function() {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }, 5000);
            }
        });
    });
    </script>
</body>
</html>