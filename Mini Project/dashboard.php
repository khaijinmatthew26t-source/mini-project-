<?php
require 'header.php';

if ($role === 'admin') {
    $totalCourses = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
    $totalStudents = $pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
    $totalTutors = $pdo->query("SELECT COUNT(*) FROM users WHERE role='tutor'")->fetchColumn();
    $totalEnrollments = $pdo->query("SELECT COUNT(*) FROM enrollments")->fetchColumn();
}

if ($role === 'tutor') {
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE tutor_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $myCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($role === 'student') {
    $stmt = $pdo->prepare("
        SELECT c.title, c.subject, c.schedule
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE e.student_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $myEnrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<h1>Welcome, <?= htmlspecialchars($name) ?></h1>

<?php if ($role === 'admin'): ?>
    <div class="cards">
        <div class="card"><strong><?= $totalCourses ?></strong><br>Courses</div>
        <div class="card"><strong><?= $totalStudents ?></strong><br>Students</div>
        <div class="card"><strong><?= $totalTutors ?></strong><br>Tutors</div>
        <div class="card"><strong><?= $totalEnrollments ?></strong><br>Enrollments</div>
    </div>
    <p><a href="courses.php">Manage Courses →</a></p>

<?php elseif ($role === 'tutor'): ?>
    <h2>My Courses</h2>
    <?php if (empty($myCourses)): ?>
        <p>You have not been assigned any courses yet.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($myCourses as $c): ?>
                <li><?= htmlspecialchars($c['title']) ?> — <?= htmlspecialchars($c['schedule']) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <p><a href="attendance.php">Mark Attendance →</a></p>

<?php elseif ($role === 'student'): ?>
    <h2>My Enrolled Courses</h2>
    <?php if (empty($myEnrollments)): ?>
        <p>You are not enrolled in any courses yet. <a href="courses.php">Browse courses</a>.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($myEnrollments as $e): ?>
                <li><?= htmlspecialchars($e['title']) ?> (<?= htmlspecialchars($e['subject']) ?>) — <?= htmlspecialchars($e['schedule']) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <p><a href="attendance.php">View My Attendance →</a></p>

<?php endif; ?>

<?php require 'footer.php'; ?>