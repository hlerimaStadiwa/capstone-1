<?php
// setup_database.php - Run this once to create database and tables
echo "<h2>Student Management System - Database Setup (PostgreSQL)</h2>";

$host = "localhost";
$port = "5432";
$user = "postgres";
$pass = "1234"; // Placeholder - User should update this
$dbname = "student_management_system";

try {
    // 1. Connect to default 'postgres' database to create the new database
    $pdo_base = new PDO("pgsql:host=$host;port=$port;dbname=postgres", $user, $pass);
    $pdo_base->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if database exists
    $stmt = $pdo_base->query("SELECT 1 FROM pg_database WHERE datname = '$dbname'");
    if (!$stmt->fetch()) {
        $pdo_base->exec("CREATE DATABASE $dbname");
        echo "<div class='message success'>Database '$dbname' created.</div>";
    } else {
        echo "<div class='message success'>Database '$dbname' already exists.</div>";
    }
    $pdo_base = null; // Close connection

    // 2. Connect to the new database
    $db = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<div class='message success'>Connected to PostgreSQL and selected database '$dbname'</div>";
    
    // 3. Create Custom Types (Replacing ENUM)
    $types_sql = [
        "DROP TYPE IF EXISTS user_role CASCADE; CREATE TYPE user_role AS ENUM ('admin', 'teacher', 'student')",
        "DROP TYPE IF EXISTS gender_type CASCADE; CREATE TYPE gender_type AS ENUM ('Male', 'Female', 'Other')",
        "DROP TYPE IF EXISTS room_type_enum CASCADE; CREATE TYPE room_type_enum AS ENUM ('Classroom', 'Lab', 'Hostel')",
        "DROP TYPE IF EXISTS attendance_status CASCADE; CREATE TYPE attendance_status AS ENUM ('Present', 'Absent', 'Late', 'Excused')"
    ];

    foreach ($types_sql as $sql) {
        try {
            $db->exec($sql);
        } catch (Exception $e) {
            // Types might already exist if not using CASCADE, or other issues
        }
    }

    // 4. Define Table Schemas
    $tables_sql = [
        "Users table" => "CREATE TABLE IF NOT EXISTS users (
            id SERIAL PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role user_role NOT NULL,
            email VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        
        "Teachers table" => "CREATE TABLE IF NOT EXISTS teachers (
            id SERIAL PRIMARY KEY,
            user_id INT,
            full_name VARCHAR(100) NOT NULL,
            gender gender_type,
            email VARCHAR(100),
            phone VARCHAR(15),
            subject_specialization VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        
        "News table" => "CREATE TABLE IF NOT EXISTS news (
            id SERIAL PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            category VARCHAR(50) DEFAULT 'General',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            author_id INT,
            FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
        )",

        "Rooms Table" => "CREATE TABLE IF NOT EXISTS rooms (
            id SERIAL PRIMARY KEY,
            room_number VARCHAR(20) NOT NULL UNIQUE,
            capacity INT NOT NULL,
            available_beds INT NOT NULL,
            room_type room_type_enum DEFAULT 'Classroom',
            status VARCHAR(20) DEFAULT 'Active',
            teacher_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL
        )",

        "Students table" => "CREATE TABLE IF NOT EXISTS students (
            id SERIAL PRIMARY KEY,
            user_id INT,
            room_id INT NULL,
            pin VARCHAR(20) UNIQUE NOT NULL,
            full_name VARCHAR(100) NOT NULL,
            email VARCHAR(100),
            phone VARCHAR(15),
            address TEXT,
            date_of_birth DATE,
            gender gender_type,
            enrollment_date DATE,
            classroom_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL,
            FOREIGN KEY (classroom_id) REFERENCES rooms(id) ON DELETE SET NULL
        )",
        
        "Attendance table" => "CREATE TABLE IF NOT EXISTS attendance (
            id SERIAL PRIMARY KEY,
            student_id INT NOT NULL,
            date DATE NOT NULL,
            status attendance_status DEFAULT 'Present',
            remarks VARCHAR(255),
            recorded_by INT NULL,
            recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(student_id, date),
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
        )",
        
        "Grades table" => "CREATE TABLE IF NOT EXISTS grades (
            id SERIAL PRIMARY KEY,
            student_id INT NOT NULL,
            subject_name VARCHAR(100) NOT NULL,
            score DECIMAL(5,2),
            grade CHAR(2),
            term VARCHAR(50),
            comments TEXT,
            is_published BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
        )",

        "Activity Logs table" => "CREATE TABLE IF NOT EXISTS activity_logs (
            id SERIAL PRIMARY KEY,
            user_id INT,
            action VARCHAR(255) NOT NULL,
            details TEXT,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )",

        "Subjects table" => "CREATE TABLE IF NOT EXISTS subjects (
            id SERIAL PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            code VARCHAR(20) UNIQUE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",

        "Teacher subjects table" => "CREATE TABLE IF NOT EXISTS teacher_subjects (
            teacher_id INT NOT NULL,
            subject_id INT NOT NULL,
            PRIMARY KEY (teacher_id, subject_id),
            FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
            FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
        )",

        "Fees table" => "CREATE TABLE IF NOT EXISTS fees (
            id SERIAL PRIMARY KEY,
            student_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            payment_date DATE NOT NULL,
            payment_method VARCHAR(50),
            fee_type VARCHAR(50),
            remarks TEXT,
            recorded_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
        )"
    ];
    
    // 5. Create Tables
    foreach ($tables_sql as $table_name => $sql) {
        $db->exec($sql);
        echo "<div class='message success'>Created/Checked $table_name</div>";
    }
    
    // 6. Insert Default Users & Data (PostgreSQL syntax)
    $data_sql = [
        "Default Admin" => "INSERT INTO users (username, password, role, email) VALUES 
            ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'admin@sms.com') 
            ON CONFLICT (username) DO NOTHING",
            
        "Default Teacher" => "INSERT INTO users (username, password, role, email) VALUES 
            ('teacher1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher', 'teacher1@sms.com') 
            ON CONFLICT (username) DO NOTHING",
            
        "Default Student" => "INSERT INTO users (username, password, role, email) VALUES 
            ('student1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'student1@sms.com') 
            ON CONFLICT (username) DO NOTHING",
            
        "Sample News" => "INSERT INTO news (title, content, category, author_id) 
            SELECT 'Welcome to Silver Academy', 'We are pleased to announce the new system.', 'General', id 
            FROM users WHERE username='admin' LIMIT 1 
            ON CONFLICT DO NOTHING",
        
        "Sample Room" => "INSERT INTO rooms (room_number, capacity, available_beds, room_type) 
            VALUES ('A-101', 30, 29, 'Classroom') ON CONFLICT (room_number) DO NOTHING",
        
        "Sample Teacher Profile" => "INSERT INTO teachers (user_id, full_name, email, phone, subject_specialization) 
            SELECT id, 'Mr. Thompson', 'teacher1@sms.com', '077-555-0101', 'Mathematics' 
            FROM users WHERE username='teacher1' 
            ON CONFLICT DO NOTHING",
             
        "Sample Student Profile" => "INSERT INTO students (user_id, pin, full_name, email, phone, date_of_birth, gender, enrollment_date) 
            SELECT id, 'STU001', 'John Smith', 'john@student.com', '0771234567', '2000-05-15', 'Male', '2024-01-01' 
            FROM users WHERE username='student1' 
            ON CONFLICT (pin) DO NOTHING",

        "Sample Subjects" => "INSERT INTO subjects (name, code) VALUES 
            ('Mathematics', 'MAT101'),
            ('English Literature', 'ENG101'),
            ('Computer Science', 'CS101'),
            ('Physics', 'PHY101') 
            ON CONFLICT (code) DO NOTHING",
        
        "Sample Fee Payment" => "INSERT INTO fees (student_id, amount, payment_date, payment_method, fee_type, remarks) 
            SELECT id, 80.00, CURRENT_DATE, 'Cash', 'Tuition', 'Initial deposit' 
            FROM students WHERE pin='STU001' LIMIT 1"
    ];

    foreach ($data_sql as $data_name => $sql) {
        try {
            $db->exec($sql);
            echo "<div class='message success'>Added $data_name</div>";
        } catch (Exception $e) {
            echo "<div class='message'>$data_name: Error or already exists (" . $e->getMessage() . ")</div>";
        }
    }
    
    // 7. Post-Insert Updates (Assignments)
    // Assign Room to Student
    $db->exec("UPDATE students SET room_id = (SELECT id FROM rooms WHERE room_number = 'A-101' LIMIT 1) WHERE pin = 'STU001'");
    
    // Add Sample Grades/Attendance for Student 1
    $stmt = $db->query("SELECT id FROM students WHERE pin = 'STU001'");
    if ($stu = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $sid = $stu['id'];
        $db->exec("INSERT INTO attendance (student_id, date, status, remarks) VALUES ($sid, CURRENT_DATE, 'Present', 'On time') ON CONFLICT DO NOTHING");
        $db->exec("INSERT INTO grades (student_id, subject_name, score, grade, term) VALUES ($sid, 'Mathematics', 85.5, 'A', 'Term 1')");
        
        // Assign Subject to Teacher
        $db->exec("INSERT INTO teacher_subjects (teacher_id, subject_id) 
            SELECT t.id, s.id FROM teachers t, subjects s WHERE t.full_name='Mr. Thompson' AND s.code='MAT101' 
            ON CONFLICT DO NOTHING");
        
        echo "<div class='message success'>Assigned sample room, subjects, grades, and attendance</div>";
    }

    echo "<div class='message success' style='margin-top: 20px;'><strong>Setup Complete!</strong> Database is ready.</div>";
    
    // 8. Verification (PostgreSQL version)
    echo "<h3>Database Schema Verification</h3>";
    $tables = ['users', 'teachers', 'students', 'attendance', 'rooms', 'grades', 'subjects', 'teacher_subjects', 'fees', 'activity_logs'];
    foreach ($tables as $table) {
        echo "<h4>Table: $table</h4>";
        try {
            $stmt = $db->prepare("SELECT column_name, data_type, is_nullable, column_default FROM information_schema.columns WHERE table_name = ? ORDER BY ordinal_position");
            $stmt->execute([$table]);
            echo "<table style='width: 100%; border-collapse: collapse; margin-bottom: 20px;'>";
            echo "<tr style='background: #f4f4f4;'><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
            while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "<tr>";
                echo "<td style='border: 1px solid #ddd; padding: 5px;'>".$row['column_name']."</td>";
                echo "<td style='border: 1px solid #ddd; padding: 5px;'>".$row['data_type']."</td>";
                echo "<td style='border: 1px solid #ddd; padding: 5px;'>".$row['is_nullable']."</td>";
                echo "<td style='border: 1px solid #ddd; padding: 5px;'>".$row['column_default']."</td>";
                echo "</tr>";
            }
            echo "</table>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>Error describing table $table: " . $e->getMessage() . "</p>";
        }
    }

    echo "<p><a href='login.php' class='btn btn-primary'>Go to Login Page</a></p>";
    
} catch (PDOException $e) {
    echo "<div class='message error'>Database Error: " . $e->getMessage() . "</div>";
    echo "<p>Make sure PostgreSQL is running on port $port and credentials are correct.</p>";
}
?>

<style>
.message { padding: 15px; margin: 10px 0; border-radius: 4px; }
.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; }
</style>
