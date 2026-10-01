<?php
require_once __DIR__ . '/config/db.php';

$isLoggedIn = isset($_SESSION['user_id'], $_SESSION['role'], $_SESSION['name']);

// A page can set $allowGuest = true before including this file to be public
if (!$isLoggedIn && empty($allowGuest)) {
    header('Location: auth/login.php');
    exit;
}

$role = $isLoggedIn ? $_SESSION['role'] : 'guest';
$name = $isLoggedIn ? $_SESSION['name'] : 'Guest';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Enrollment System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <nav>
        <span class="brand">Course Enrollment System</span>
        <span class="nav-links">
            <?php if ($isLoggedIn): ?>
                <a href="dashboard.php">Dashboard</a>
            <?php endif; ?>
            <a href="courses.php">Courses</a>
            <?php if ($role === 'student' || $role === 'tutor'): ?>
                <a href="attendance.php">Attendance</a>
            <?php endif; ?>
        </span>
        <span class="user-info">
            <?php if ($isLoggedIn): ?>
                <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>
                (<?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>)
                | <a href="auth/logout.php">Logout</a>
            <?php else: ?>
                <a href="auth/login.php">Login</a> | <a href="auth/register.php">Register</a>
            <?php endif; ?>
        </span>
    </nav>
    <main>
