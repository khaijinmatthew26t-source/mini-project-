<?php
require_once __DIR__ . '/header.php';

if ($role === 'admin') {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$error = '';

if ($role === 'tutor' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Read the form data: which enrollment, which date, which status.
    $enrollment_id = (int) ($_POST['enrollment_id'] ?? 0);
    $session_date = $_POST['session_date'] ?? '';
    $status = $_POST['status'] ?? '';

    
    if ($enrollment_id < 1 || $session_date === '' || !in_array($status, ['Present', 'Absent', 'Late'], true)) {
        $error = 'Please enter valid attendance information.';
    } else {
        // Make sure this enrollment belongs to a course taught by this tutor, so a tutor cannot change attendance for other tutors' courses.
        // Verify that this enrollment belongs to one of the logged-in tutor's courses.
        $stmt = $pdo->prepare("\n            SELECT e.id\n            FROM enrollments e\n            JOIN courses c ON e.course_id = c.id\n            WHERE e.id = ? AND c.tutor_id = ?\n            LIMIT 1\n        ");
        $stmt->execute([$enrollment_id, $_SESSION['user_id']]);

        if (!$stmt->fetch()) {
            $error = 'You are not allowed to mark attendance for this enrollment.';
        } else {
            // One record per student per day: UPDATE it if it already exists, otherwise INSERT a new one.
            // One attendance record per student/course/date.
            $stmt = $pdo->prepare('SELECT id FROM attendance WHERE enrollment_id = ? AND session_date = ? LIMIT 1');
            $stmt->execute([$enrollment_id, $session_date]);
            $existing = $stmt->fetch();

            if ($existing) {
                $stmt = $pdo->prepare('UPDATE attendance SET status = ? WHERE id = ?');
                $stmt->execute([$status, $existing['id']]);
                $message = 'Attendance updated.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO attendance (enrollment_id, session_date, status) VALUES (?, ?, ?)');
                $stmt->execute([$enrollment_id, $session_date, $status]);
                $message = 'Attendance recorded.';
            }
        }
    }
}

// Tutor: load every enrolled student in this tutor's courses, used for the dropdown list.
if ($role === 'tutor') {
    $stmt = $pdo->prepare("\n        SELECT e.id AS enrollment_id, u.name AS student_name, c.title AS course_title\n        FROM enrollments e\n        JOIN users u ON e.student_id = u.id\n        JOIN courses c ON e.course_id = c.id\n        WHERE c.tutor_id = ?\n        ORDER BY c.title, u.name\n    ");
    $stmt->execute([$_SESSION['user_id']]);
    $roster = $stmt->fetchAll();
}

// Student: load this student's attendance records, newest date first.
if ($role === 'student') {
    $stmt = $pdo->prepare("\n        SELECT c.title AS course_title, a.session_date, a.status\n        FROM attendance a\n        JOIN enrollments e ON a.enrollment_id = e.id\n        JOIN courses c ON e.course_id = c.id\n        WHERE e.student_id = ?\n        ORDER BY a.session_date DESC, c.title\n    ");
    $stmt->execute([$_SESSION['user_id']]);
    $history = $stmt->fetchAll();
}
?>

<h1>Attendance</h1>
<?php if ($message): ?>
    <p class="success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<?php if ($error): ?>
    <p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

<?php if ($role === 'tutor'): ?>
    <?php if (empty($roster)): ?>
        <p>No students enrolled in your courses yet.</p>

    <?php else: ?>
        <h2>Mark Attendance</h2>
        <form method="POST" action="attendance.php" class="inline-form">
            <select name="enrollment_id" required>
                <option value="">-- Select Student --</option>
                <?php foreach ($roster as $r): ?>
                    <option value="<?= (int) $r['enrollment_id'] ?>">
                        <?= htmlspecialchars($r['student_name'], ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($r['course_title'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="session_date" value="<?= date('Y-m-d') ?>" required>
            <select name="status" required>
                <option value="Present">Present</option>
                <option value="Absent">Absent</option>
                <option value="Late">Late</option>
            </select>
            <button type="submit">Save</button>
        </form>
    <?php endif; ?>

<?php elseif ($role === 'student'): ?>
    <h2>My Attendance History</h2>
    <?php if (empty($history)): ?>
        <p>No attendance records yet.</p>
    <?php else: ?>
        <table>
            <tr><th>Course</th><th>Date</th><th>Status</th></tr>
            <?php foreach ($history as $h): ?>
                <tr>
                    <td><?= htmlspecialchars($h['course_title'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($h['session_date'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($h['status'], ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>
