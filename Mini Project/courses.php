<?php
$allowGuest = true;
require_once __DIR__ . '/header.php';

$message = '';
$error = '';

if ($role === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_course'])) {
    $title = trim($_POST['title'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $schedule = trim($_POST['schedule'] ?? '');
    $tutor_id = (int) ($_POST['tutor_id'] ?? 0);
    $max_seats = (int) ($_POST['max_seats'] ?? 0);

    if ($title === '' || $subject === '' || $schedule === '' || $tutor_id < 1 || $max_seats < 1) {
        $error = 'Please fill in all course fields correctly.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'tutor' LIMIT 1");
        $stmt->execute([$tutor_id]);

        if (!$stmt->fetch()) {
            $error = 'Selected tutor is invalid.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO courses (title, subject, schedule, tutor_id, max_seats) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$title, $subject, $schedule, $tutor_id, $max_seats]);
            $message = 'Course added successfully.';
        }
    }
}

if ($role === 'admin' && isset($_GET['delete'])) {
    $course_id = (int) $_GET['delete'];
    if ($course_id > 0) {
        $stmt = $pdo->prepare('DELETE FROM courses WHERE id = ?');
        $stmt->execute([$course_id]);
        $message = $stmt->rowCount() ? 'Course deleted successfully.' : 'Course not found.';
    }
}

if (isset($_GET['msg'])) {
    $messages = [
        'already_enrolled' => 'You are already enrolled in this course.',
        'full' => 'This course is full.',
        'enrolled' => 'You have been enrolled successfully.',
        'withdrawn' => 'You have withdrawn from the course.',
        'updated' => 'Course updated successfully.',
    ];
    if (isset($messages[$_GET['msg']])) {
        $message = $messages[$_GET['msg']];
    }
}

$tutors = $pdo->query("SELECT id, name FROM users WHERE role = 'tutor' ORDER BY name")->fetchAll();

$courses = $pdo->query("\n    SELECT c.*, u.name AS tutor_name,\n           (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS seats_taken\n    FROM courses c\n    JOIN users u ON c.tutor_id = u.id\n    ORDER BY c.id DESC\n")->fetchAll();

$myEnrolledIds = [];
if ($role === 'student') {
    $stmt = $pdo->prepare('SELECT course_id FROM enrollments WHERE student_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $myEnrolledIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}
?>

<h1>Courses</h1>

<?php if ($message): ?>
    <p class="success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

<?php if ($error): ?>
    <p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

<?php if ($role === 'admin'): ?>
    <h2>Add New Course</h2>
    <form method="POST" action="courses.php" class="inline-form">
        <input type="hidden" name="add_course" value="1">
        <input type="text" name="title" placeholder="Course title" required>
        <input type="text" name="subject" placeholder="Subject" required>
        <input type="text" name="schedule" placeholder="Schedule e.g. Mon 4-6pm" required>
        <select name="tutor_id" required>
            <option value="">-- Assign Tutor --</option>
            <?php foreach ($tutors as $t): ?>
                <option value="<?= (int) $t['id'] ?>"><?= htmlspecialchars($t['name'], ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
        </select>
        <input type="number" name="max_seats" placeholder="Max seats" min="1" required>
        <button type="submit">Add Course</button>
    </form>
<?php endif; ?>

<?php if (empty($courses)): ?>
    <p>No courses available.</p>
<?php else: ?>
<table>
    <tr>
        <th>Title</th><th>Subject</th><th>Schedule</th><th>Tutor</th><th>Seats</th><th>Action</th>
    </tr>
    <?php foreach ($courses as $c): ?>
        <tr>
            <td><?= htmlspecialchars($c['title'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($c['subject'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($c['schedule'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($c['tutor_name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= (int) $c['seats_taken'] ?> / <?= (int) $c['max_seats'] ?></td>
            <td>
                <?php if ($role === 'admin'): ?>
                    <a href="course_students.php?course_id=<?= (int) $c['id'] ?>">View Students</a> |
                    <a href="edit_course.php?course_id=<?= (int) $c['id'] ?>">Edit</a> |
                    <a href="courses.php?delete=<?= (int) $c['id'] ?>" onclick="return confirm('Delete this course?')">Delete</a>
                <?php elseif ($role === 'student'): ?>
                    <?php if (in_array((int) $c['id'], $myEnrolledIds, true)): ?>
                        <a href="enroll.php?action=withdraw&amp;course_id=<?= (int) $c['id'] ?>">Withdraw</a>
                    <?php elseif ((int) $c['seats_taken'] >= (int) $c['max_seats']): ?>
                        <span class="full">Full</span>
                    <?php else: ?>
                        <a href="enroll.php?action=enroll&amp;course_id=<?= (int) $c['id'] ?>">Enroll</a>
                    <?php endif; ?>
                <?php elseif ($role === 'guest'): ?>
                    <a href="auth/login.php">Login to enroll</a>
                <?php else: ?>
                    —
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>
