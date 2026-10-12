<?php
$allowGuest = true; 
require_once __DIR__ . '/header.php';

$message = '';
$error = '';

$view = $_GET['view'] ?? 'list';
$course_id = (int) ($_GET['course_id'] ?? $_POST['course_id'] ?? 0);

// Access control: anyone who is not an admin is forced back to the list and cannot open edit / students.
// Non-admins can only ever see the plain list
if ($role !== 'admin') {
    $view = 'list';
}

// ============================================================
// VIEW: edit  (admin only) - edit an existing course
// ============================================================
// SCREEN 1: edit a course (admin only).
if ($view === 'edit') {
    $stmt = $pdo->prepare('SELECT * FROM courses WHERE id = ?');
    $stmt->execute([$course_id]);
    $course = $stmt->fetch();

    if (!$course) {
        echo '<p class="error">Course not found.</p>';
        echo '<p><a href="courses.php">&larr; Back to Courses</a></p>';
        require __DIR__ . '/footer.php';
        exit;
    }

    // Load all tutors for the 'assign tutor' dropdown.
    $tutors = $pdo->query("SELECT id, name FROM users WHERE role = 'tutor' ORDER BY name")->fetchAll();

    // Only process the update when Save Changes is pressed (POST).
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $title = trim($_POST['title'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $schedule = trim($_POST['schedule'] ?? '');
        $tutor_id = (int) ($_POST['tutor_id'] ?? 0);
        $max_seats = (int) ($_POST['max_seats'] ?? 0);

        // Count current enrollments first, so max seats cannot be set lower than the number of enrolled students.
        // Don't let max_seats drop below how many students are already enrolled
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM enrollments WHERE course_id = ?');
        $stmt->execute([$course_id]);
        $seatsTaken = (int) $stmt->fetchColumn();

        // Validation: fields cannot be empty -> seats cannot be less than enrolled students -> tutor must be a valid tutor.
        if ($title === '' || $subject === '' || $schedule === '' || $tutor_id < 1 || $max_seats < 1) {
            $error = 'Please fill in all course fields correctly.';
        } elseif ($max_seats < $seatsTaken) {
            $error = "Max seats can't be less than the $seatsTaken student(s) already enrolled.";
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'tutor' LIMIT 1");
            $stmt->execute([$tutor_id]);

            if (!$stmt->fetch()) {
                $error = 'Selected tutor is invalid.';
            } else {
                // All checks passed: UPDATE the course, then go back to the list and show the updated message.
                $stmt = $pdo->prepare('UPDATE courses SET title = ?, subject = ?, schedule = ?, tutor_id = ?, max_seats = ? WHERE id = ?');
                $stmt->execute([$title, $subject, $schedule, $tutor_id, $max_seats, $course_id]);

                header('Location: courses.php?msg=updated');
                exit;
            }
        }

        // If there was an error, keep showing what the admin just typed.
        // Keep the form showing what the admin just typed if there was an error
        $course['title'] = $title;
        $course['subject'] = $subject;
        $course['schedule'] = $schedule;
        $course['tutor_id'] = $tutor_id;
        $course['max_seats'] = $max_seats;
    }
    ?>

    <p><a href="courses.php">&larr; Back to Courses</a></p>

    <h1>Edit Course</h1>

    <?php if ($error): ?>
        <p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <!-- Edit form: fields are pre-filled with the course's current data (value=...). -->
    <form method="POST" action="courses.php?view=edit&amp;course_id=<?= (int) $course_id ?>" class="inline-form">
        <input type="hidden" name="course_id" value="<?= (int) $course_id ?>">

        <input type="text" name="title" placeholder="Course title" required
               value="<?= htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8') ?>">

        <input type="text" name="subject" placeholder="Subject" required
               value="<?= htmlspecialchars($course['subject'], ENT_QUOTES, 'UTF-8') ?>">

        <input type="text" name="schedule" placeholder="Schedule e.g. Mon 4-6pm" required
               value="<?= htmlspecialchars($course['schedule'], ENT_QUOTES, 'UTF-8') ?>">

        <select name="tutor_id" required>
            <option value="">-- Assign Tutor --</option>
            <?php foreach ($tutors as $t): ?>
                <!-- The course's current tutor gets 'selected', so the dropdown stays on that tutor. -->
                <option value="<?= (int) $t['id'] ?>" <?= (int) $t['id'] === (int) $course['tutor_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($t['name'], ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>

        <input type="number" name="max_seats" placeholder="Max seats" min="1" required
               value="<?= (int) $course['max_seats'] ?>">

        <button type="submit">Save Changes</button>
    </form>

    <?php
    require __DIR__ . '/footer.php';
    exit;
}

// ============================================================
// VIEW: students  (admin only) - see/remove students of one course
// ============================================================
// SCREEN 2: list of students enrolled in this course (admin only).
if ($view === 'students') {
    // Admin can remove a student from this course
    // Admin pressed Remove: delete using both enrollment id and course id, then return to the same page.
    if (isset($_GET['remove'])) {
        $enrollment_id = (int) $_GET['remove'];
        $stmt = $pdo->prepare('DELETE FROM enrollments WHERE id = ? AND course_id = ?');
        $stmt->execute([$enrollment_id, $course_id]);
        header('Location: courses.php?view=students&course_id=' . $course_id);
        exit;
    }

    // Load this course's details, JOIN users to get the tutor's name.
    $stmt = $pdo->prepare('
        SELECT c.*, u.name AS tutor_name
        FROM courses c
        JOIN users u ON c.tutor_id = u.id
        WHERE c.id = ?
    ');
    $stmt->execute([$course_id]);
    $course = $stmt->fetch();

    if (!$course) {
        echo '<p class="error">Course not found.</p>';
        echo '<p><a href="courses.php">&larr; Back to Courses</a></p>';
        require __DIR__ . '/footer.php';
        exit;
    }

    // Load the students enrolled in this course: enrollments JOIN users for name, email and enrolled time.
    $stmt = $pdo->prepare('
        SELECT e.id AS enrollment_id, u.name, u.email, e.enrolled_at
        FROM enrollments e
        JOIN users u ON e.student_id = u.id
        WHERE e.course_id = ?
        ORDER BY u.name
    ');
    $stmt->execute([$course_id]);
    $students = $stmt->fetchAll();
    ?>

    <p><a href="courses.php">&larr; Back to Courses</a></p>

    <h1><?= htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8') ?></h1>
    <p>
        Subject: <?= htmlspecialchars($course['subject'], ENT_QUOTES, 'UTF-8') ?> |
        Schedule: <?= htmlspecialchars($course['schedule'], ENT_QUOTES, 'UTF-8') ?> |
        Tutor: <?= htmlspecialchars($course['tutor_name'], ENT_QUOTES, 'UTF-8') ?> |
        Seats: <?= count($students) ?> / <?= (int) $course['max_seats'] ?>
    </p>

    <h2>Enrolled Students</h2>

    <!-- Show a message when there are no students; otherwise show them in a table. -->
    <?php if (empty($students)): ?>
        <p>No students have enrolled in this course yet.</p>
    <?php else: ?>
        <table>
            <tr><th>#</th><th>Name</th><th>Email</th><th>Enrolled At</th><th>Action</th></tr>
            <?php foreach ($students as $i => $s): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($s['email'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($s['enrolled_at'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <a href="courses.php?view=students&amp;course_id=<?= $course_id ?>&amp;remove=<?= (int) $s['enrollment_id'] ?>"
                           onclick="return confirm('Remove this student from the course?')">Remove</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <?php
    require __DIR__ . '/footer.php';
    exit;
}

// ============================================================
// VIEW: list  (default) - course catalog, add/delete for admin
// ============================================================

// SCREEN 3: course list (default).
// Admin adds a course: validate the fields -> confirm the tutor is valid -> INSERT into courses.
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

// Admin deletes a course: rowCount() tells whether a row was really deleted.
// The course's enrollments and attendance are removed too because of ON DELETE CASCADE.
if ($role === 'admin' && isset($_GET['delete'])) {
    $delete_id = (int) $_GET['delete'];
    if ($delete_id > 0) {
        $stmt = $pdo->prepare('DELETE FROM courses WHERE id = ?');
        $stmt->execute([$delete_id]);
        $message = $stmt->rowCount() ? 'Course deleted successfully.' : 'Course not found.';
    }
}

// Turn the msg code in the URL (e.g. enrolled, full) into a message to display.
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

// Load all tutors (for the dropdown in the Add Course form).
$tutors = $pdo->query("SELECT id, name FROM users WHERE role = 'tutor' ORDER BY name")->fetchAll();

// Load all courses; JOIN users to get the tutor's name.
// The subquery (SELECT COUNT(*)) counts how many students are enrolled in each course as seats_taken.
$courses = $pdo->query("
    SELECT c.*, u.name AS tutor_name,
           (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS seats_taken
    FROM courses c
    JOIN users u ON c.tutor_id = u.id
    ORDER BY c.id DESC
")->fetchAll();

// Small picture for each subject; any other subject falls back to the default icon.
$subjectIcons = [
    'Mathematics' => 'images/subject-math.svg',
    'English' => 'images/subject-english.svg',
    'Bahasa Melayu' => 'images/subject-bm.svg',
];

// Student: collect the ids of courses already enrolled in, to decide between showing Enroll or Withdraw.
$myEnrolledIds = [];
if ($role === 'student') {
    $stmt = $pdo->prepare('SELECT course_id FROM enrollments WHERE student_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $myEnrolledIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}
?>

<!-- Page output below: messages -> admin's Add Course form -> course table. -->
<!-- Banner picture at the top of the course list. -->
<img src="images/courses-banner.svg" alt="Find your next class: Mathematics, English and Bahasa Melayu" class="page-banner">

<h1>Courses</h1>

<?php if ($message): ?>
    <p class="success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

<?php if ($error): ?>
    <p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

<!-- Only admins see the Add Course form. -->
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

<!-- Show a message when there are no courses; otherwise show the table. -->
<?php if (empty($courses)): ?>
    <p>No courses available.</p>
<?php else: ?>
<table>
    <tr>
        <th>Title</th><th>Subject</th><th>Schedule</th><th>Tutor</th><th>Seats</th><th>Action</th>
    </tr>
    <!-- Each course is shown as one table row (foreach loop). -->
    <?php foreach ($courses as $c): ?>
        <tr>
            <td><?= htmlspecialchars($c['title'], ENT_QUOTES, 'UTF-8') ?></td>
            <!-- Subject icon (the src comes from the $subjectIcons map above, not from user input) + subject name. -->
            <td>
                <img src="<?= $subjectIcons[$c['subject']] ?? 'images/subject-default.svg' ?>" alt="" class="subject-icon">
                <?= htmlspecialchars($c['subject'], ENT_QUOTES, 'UTF-8') ?>
            </td>
            <td><?= htmlspecialchars($c['schedule'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($c['tutor_name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= (int) $c['seats_taken'] ?> / <?= (int) $c['max_seats'] ?></td>
            <td>
                <!-- The Action column depends on the role: -->
                <!-- admin -> View Students / Edit / Delete; student -> Enroll / Withdraw / Full; guest -> Login to enroll. -->
                <?php if ($role === 'admin'): ?>
                    <a href="courses.php?view=students&amp;course_id=<?= (int) $c['id'] ?>">View Students</a> |
                    <a href="courses.php?view=edit&amp;course_id=<?= (int) $c['id'] ?>">Edit</a> |
                    <a href="courses.php?delete=<?= (int) $c['id'] ?>" onclick="return confirm('Delete this course?')">Delete</a>
                <?php elseif ($role === 'student'): ?>
                    <!-- Already enrolled -> Withdraw; course full -> Full; otherwise -> Enroll. -->
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
