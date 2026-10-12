<?php
// Website entry point: start the session so the login state can be read.
session_start();
// Logged in -> go to dashboard.php; not logged in (guest) -> go to the course list courses.php.
header('Location: ' . (isset($_SESSION['user_id']) ? 'dashboard.php' : 'courses.php'));
exit;
