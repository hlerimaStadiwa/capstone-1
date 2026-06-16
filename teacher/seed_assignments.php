<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// SEEDING DISABLED: Prevent automatic creation of sample assignments.
echo "Seeding disabled: sample assignments are currently turned off.\n";
exit;

try {
    $subjects_stmt = $db->query("SELECT id, name FROM subjects ORDER BY name ASC");
    $subjects = $subjects_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($subjects)) {
        echo "No subjects found in the database.\n";
        exit;
    }

    $created = 0;
    foreach ($subjects as $subject) {
        $subject_id = $subject['id'];
        $subject_name = $subject['name'];

        // Find a teacher assigned to this subject
        $teacher_stmt = $db->prepare(
            "SELECT t.id AS teacher_id, t.full_name, r.id AS classroom_id
             FROM teacher_subjects ts
             JOIN teachers t ON t.id = ts.teacher_id
             LEFT JOIN rooms r ON r.teacher_id = t.id
             WHERE ts.subject_id = ?
             ORDER BY r.id NULLS LAST, t.id
             LIMIT 1"
        );
        $teacher_stmt->execute([$subject_id]);
        $teacher = $teacher_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$teacher) {
            echo "Skipped subject '{$subject_name}': no teacher assigned.\n";
            continue;
        }

        $classroom_id = $teacher['classroom_id'] ?: null;
        $student_id = null;

        if (!$classroom_id) {
            $student_stmt = $db->prepare(
                "SELECT s.id FROM student_subjects ss JOIN students s ON s.id = ss.student_id WHERE ss.subject_id = ? LIMIT 1"
            );
            $student_stmt->execute([$subject_id]);
            $student = $student_stmt->fetch(PDO::FETCH_ASSOC);
            if ($student) {
                $student_id = $student['id'];
            }
        }

        if (!$classroom_id && !$student_id) {
            echo "Skipped subject '{$subject_name}': no class or student target found.\n";
            continue;
        }

        // Realistic assignment templates per subject (fallback to a generic set)
        $templates = [
            "Essay: Research and write a 1200-word paper on contemporary issues in %s",
            "Project: Prepare a group presentation on a real-world application of %s",
            "Problem Set: Complete the practice problems covering the latest %s topics",
            "Case Study: Analyze a recent case related to %s and submit recommendations",
            "Lab Report: Conduct the assigned experiment and submit a formal lab report on %s",
            "Portfolio: Compile a portfolio of exercises and reflections for %s",
            "Practical Assignment: Design a small practical task illustrating key %s concepts"
        ];

        $descriptions = [
            "Provide clear answers with references where applicable. Use examples and evidence to support your points.",
            "Work in groups of 3-4. Allocate roles, prepare slides, and rehearse before submission.",
            "Show full workings and reasoning. Partial credit may be awarded for clear steps.",
            "Include citations, a short summary of findings, and suggestions for future work.",
            "Follow the lab safety guidelines, document observations, and attach data tables.",
            "Choose 5 representative pieces and write a short reflection for each.",
            "Deliver a working prototype or proof-of-concept along with a short write-up."
        ];

        // Pick a template and description randomly
        $tpl = $templates[array_rand($templates)];
        $desc = $descriptions[array_rand($descriptions)];
        $title = sprintf($tpl, $subject_name);
        $description = "$desc\n\nTopic: $subject_name. Expected length/format: follow teacher guidance.";
        $due_date = date('Y-m-d', strtotime('+10 days'));

        $check_stmt = $db->prepare(
            "SELECT id FROM assignments WHERE teacher_id = ? AND title = ? AND due_date = ? LIMIT 1"
        );
        $check_stmt->execute([$teacher['teacher_id'], $title, $due_date]);
        if ($check_stmt->fetch()) {
            echo "Already exists: '{$title}' for teacher {$teacher['full_name']}.\n";
            continue;
        }

        $insert_stmt = $db->prepare(
            "INSERT INTO assignments (teacher_id, classroom_id, student_id, title, description, due_date) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $insert_stmt->execute([
            $teacher['teacher_id'],
            $classroom_id,
            $student_id,
            $title,
            $description,
            $due_date,
        ]);

        echo "Created sample assignment for '{$subject_name}' (Teacher: {$teacher['full_name']}).\n";
        $created++;
    }

    echo "\nSeed complete. {$created} sample assignment(s) created.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Cleanup existing assignments that have 'Sample ' prefix in the title (make them presentable)
try {
    $cleanup = $db->prepare("UPDATE assignments SET title = regexp_replace(title, '^Sample\\s+', '', 'i') WHERE title ILIKE 'Sample %'");
    $cleanup->execute();
    $updated = $cleanup->rowCount();
    if ($updated > 0) {
        echo "Cleaned up $updated existing assignment title(s) that started with 'Sample '.\n";
    }
} catch (Exception $e) {
    // Non-fatal - just report
    echo "Cleanup notice: " . $e->getMessage() . "\n";
}
