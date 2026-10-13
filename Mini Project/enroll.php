<?php
require_once __DIR__ . '/config/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header('Location: auth/login.php');
    exit;
}

$student_id = (int) $_SESSION['user_id'];
$course_id = (int) ($_GET['course_id'] ?? 0);
$action = $_GET['action'] ?? '';

if ($course_id < 1 || !in_array($action, ['enroll', 'withdraw'], true)) {
    header('Location: courses.php');
    exit;
}

// Enroll flow: check 1 = not already enrolled, check 2 = course is not full; only then insert.
if ($action === 'enroll') {
    // Check 1: has this student already enrolled in this course?
    $stmt = $pdo->prepare('SELECT id FROM enrollments WHERE student_id = ? AND course_id = ? LIMIT 1');
    $stmt->execute([$student_id, $course_id]);

    if ($stmt->fetch()) {
        header('Location: courses.php?msg=already_enrolled');
        exit;
    }

    // Check 2: get the seat limit and count current enrollments with a subquery COUNT(*) as seats_taken.
    $stmt = $pdo->prepare('SELECT id, max_seats, (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS seats_taken FROM courses c WHERE c.id = ? LIMIT 1');
    $stmt->execute([$course_id]);
    $course = $stmt->fetch();

    if (!$course) {
        header('Location: courses.php');
        exit;
    }

    // Enrolled count >= seat limit means the course is full, so no more enrollments.
    if ((int) $course['seats_taken'] >= (int) $course['max_seats']) {
        header('Location: courses.php?msg=full');
        exit;
    }

    // Checks passed: insert the enrollment record.
    // The database UNIQUE(student_id, course_id) is a second layer of protection against duplicates (e.g. double click).
    try {
        $stmt = $pdo->prepare('INSERT INTO enrollments (student_id, course_id) VALUES (?, ?)');
        $stmt->execute([$student_id, $course_id]);
        header('Location: courses.php?msg=enrolled');
    } catch (PDOException $e) {
        header('Location: courses.php?msg=already_enrolled');
    }
    exit;
}

// Reaching this line means the action is withdraw: delete this student's enrollment record.
$stmt = $pdo->prepare('DELETE FROM enrollments WHERE student_id = ? AND course_id = ?');
$stmt->execute([$student_id, $course_id]);
header('Location: courses.php?msg=withdrawn');
exit;
